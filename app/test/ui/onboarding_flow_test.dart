import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

Future<void> tapText(WidgetTester tester, String text) => tapOn(tester, find.text(text));

Future<void> tapContinue(WidgetTester tester) async {
  await tester.tap(find.byKey(const ValueKey('onboarding-continue')));
  await settle(tester);
}

/// The three welcome slides, swiped through with the Next button.
Future<void> passWalkthrough(WidgetTester tester) async {
  for (var i = 0; i < 3; i++) {
    await tapOn(tester, find.byKey(const ValueKey('walkthrough-next')));
  }
}

/// Taps an answer in the warm-up, then Next.
Future<void> warmUp(WidgetTester tester, String answer) async {
  await tapText(tester, answer);
  await tapOn(tester, find.byKey(const ValueKey('warmup-next')));
}

/// The right answers of the bundled warm-up, in order.
const warmUpAnswers = ['12', 'Ice', 'Small', 'Addis Ababa', '10'];

void main() {
  testWidgets('walkthrough, easy warm-up, then Grade 12 profile saved to the server', (
    tester,
  ) async {
    final (repo, server) = await pumpGuul(tester);

    // Three welcome slides.
    expect(find.text('Learn in short, clear lessons'), findsOneWidget);
    expect(find.bySemanticsLabel('Page 1 of 3'), findsOneWidget);
    await tapOn(tester, find.byKey(const ValueKey('walkthrough-next')));
    expect(find.text('Practise like the real exam'), findsOneWidget);
    // The phone's Back button goes to the previous slide, not out of the app.
    await tester.binding.handlePopRoute();
    await settle(tester);
    expect(find.text('Learn in short, clear lessons'), findsOneWidget);
    await tapOn(tester, find.byKey(const ValueKey('walkthrough-next')));
    await tapOn(tester, find.byKey(const ValueKey('walkthrough-next')));
    expect(find.text('A little every day adds up'), findsOneWidget);
    expect(find.text("Let's start"), findsOneWidget);
    expect(find.text('Skip'), findsNothing, reason: 'nothing to skip on the last slide');
    await tapOn(tester, find.byKey(const ValueKey('walkthrough-next')));

    // Warm-up: tap only, instant friendly feedback, 4 right and 1 wrong.
    expect(find.text('Warm-up: 5 easy questions'), findsOneWidget);
    expect(find.text('Question 1 of 5'), findsOneWidget);
    expect(find.byType(TextField), findsNothing, reason: 'no typing in the warm-up');
    expect(find.byKey(const ValueKey('warmup-next')), findsNothing, reason: 'answer first');
    await tapText(tester, '12');
    expect(find.text('Great job!'), findsOneWidget);
    await tapOn(tester, find.byKey(const ValueKey('warmup-next')));
    await warmUp(tester, 'Ice');
    expect(find.text('Question 3 of 5'), findsOneWidget);
    await tapText(tester, 'Tall'); // wrong on purpose
    expect(find.text('Almost! The answer is Small.'), findsOneWidget);
    await tapText(tester, 'Small'); // a second tap changes nothing
    expect(find.text('Almost! The answer is Small.'), findsOneWidget);
    expect(find.text('Report a problem'), findsNothing, reason: 'warm-up questions are local');
    await tapOn(tester, find.byKey(const ValueKey('warmup-next')));
    await warmUp(tester, 'Addis Ababa');
    await tapText(tester, '10');
    expect(find.text('See my result'), findsOneWidget);
    await tapOn(tester, find.byKey(const ValueKey('warmup-next')));

    expect(find.text('You got 4 of 5!'), findsOneWidget);
    expect(find.text("Great start! Now let's set up your plan."), findsOneWidget);
    expect(find.byIcon(Icons.star_rounded), findsNWidgets(4), reason: 'one star per right answer');
    expect(find.byIcon(Icons.star_outline_rounded), findsOneWidget);
    expect(find.textContaining('Step '), findsNothing, reason: 'the warm-up is not a setup step');
    await tapContinue(tester);

    expect(find.text('Which grade are you in?'), findsOneWidget);
    expect(
      find.text('Step 1 of 5'),
      findsOneWidget,
      reason: 'grade, place, gender, target, reminder',
    );
    final continueButton = find.byKey(const ValueKey('onboarding-continue'));
    expect(tester.widget<FilledButton>(continueButton).onPressed, isNull);
    await tapText(tester, 'Grade 12');
    await tapContinue(tester);

    expect(find.text('Which stream are you in?'), findsOneWidget);
    expect(find.text('Step 2 of 7'), findsOneWidget, reason: 'Grade 12 adds stream and retaker');
    await tapText(tester, 'Natural Science');
    await tapContinue(tester);

    expect(find.text('Are you taking the national exam again?'), findsOneWidget);
    await tapText(tester, 'No, first time');
    await tapContinue(tester);

    expect(find.text('Where do you study?'), findsOneWidget);
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await settle(tester);
    await tester.tap(find.text('Somali').last);
    await settle(tester);
    await tester.enterText(find.byType(TextField), '  Jijiga ');
    await tapContinue(tester);

    expect(find.text('Gender (optional)'), findsOneWidget);
    await tapText(tester, 'Skip');

    expect(find.text('What is your target score?'), findsOneWidget);
    expect(find.text('400 / 600'), findsOneWidget);
    await tapContinue(tester);

    expect(find.text('When should we remind you to study?'), findsOneWidget);
    await tapContinue(tester); // "Start studying" without a reminder
    expect(find.text('Good morning!'), findsOneWidget);

    expect(server.profile['grade'], 12);
    expect(server.profile['stream'], 'natural');
    expect(server.profile['is_retaker'], false);
    expect(server.profile['region'], 'somali');
    expect(server.profile['city'], 'Jijiga');
    expect(server.profile['gender'], isNull);
    expect(server.profile['target_score'], 400);
    expect(server.profile['reminder_time'], isNull);
    expect(server.profile['onboarded'], true);
    // The warm-up is local only: no attempt with made-up question ids reaches the server.
    expect(server.receivedAttempts, isEmpty);
    expect((await repo.profile()).onboarded, isTrue);
  });

  testWidgets('Skip on the first slide goes straight to the warm-up; a low score is still kind', (
    tester,
  ) async {
    await pumpGuul(tester);
    await tapText(tester, 'Skip');
    expect(find.text('Warm-up: 5 easy questions'), findsOneWidget);
    for (final wrong in ['11', 'Steam', 'Tall', 'Jijiga', '9']) {
      await warmUp(tester, wrong);
    }
    expect(find.text('You got 0 of 5!'), findsOneWidget);
    expect(find.text('Good start! Guul 12 will help you get even better.'), findsOneWidget);
  });

  testWidgets('Grade 9 skips stream and retaker questions; Back never replays the warm-up', (
    tester,
  ) async {
    final (_, server) = await pumpGuul(tester);
    await passWalkthrough(tester);
    for (final answer in warmUpAnswers) {
      await warmUp(tester, answer);
    }
    expect(find.text('You got 5 of 5!'), findsOneWidget);
    await tapContinue(tester);
    await tapText(tester, 'Grade 9');

    // Back from the grade question shows the result again, never the warm-up.
    await tester.tap(find.byType(BackButton));
    await settle(tester);
    expect(find.text('You got 5 of 5!'), findsOneWidget);
    await tester.tap(find.byType(BackButton));
    await settle(tester);
    expect(find.text('Learn in short, clear lessons'), findsOneWidget);
    await passWalkthrough(tester);
    expect(find.text('You got 5 of 5!'), findsOneWidget, reason: 'the warm-up is played once');
    await tapContinue(tester);
    expect(find.text('Which grade are you in?'), findsOneWidget);
    await tapContinue(tester); // Grade 9 is still chosen

    expect(find.text('Which stream are you in?'), findsNothing);
    expect(find.text('Where do you study?'), findsOneWidget);
    // Region is required.
    expect(
      tester.widget<FilledButton>(find.byKey(const ValueKey('onboarding-continue'))).onPressed,
      isNull,
    );
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await settle(tester);
    await tester.tap(find.text('Addis Ababa').last);
    await settle(tester);
    await tapContinue(tester);
    await tapText(tester, 'Female');
    await tapContinue(tester);
    await tapContinue(tester);
    await tapContinue(tester);

    expect(find.text('Good morning!'), findsOneWidget);
    expect(server.profile['grade'], 9);
    expect(server.profile['stream'], isNull);
    expect(server.profile['gender'], 'female');
    expect(server.profile['city'], isNull);
  });

  testWidgets('offline first launch still finishes onboarding; it syncs later', (tester) async {
    final (repo, server) = await pumpGuul(tester, server: FakeServer()..online = false);
    await passWalkthrough(tester);
    for (final answer in warmUpAnswers) {
      await warmUp(tester, answer);
    }
    await tapContinue(tester);
    await tapText(tester, 'Grade 10');
    await tapContinue(tester);
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await settle(tester);
    await tester.tap(find.text('Oromia').last);
    await settle(tester);
    for (var i = 0; i < 4; i++) {
      await tapContinue(tester);
    }
    expect(find.text('Good morning!'), findsOneWidget);
    expect(
      find.text('You are offline. Your progress is saved and will sync later.'),
      findsOneWidget,
    );
    expect(server.profile['grade'], isNull);

    server.online = true;
    expect(await repo.syncAll(), isTrue);
    expect(server.profile['grade'], 10);
    expect(server.profile['region'], 'oromia');
  });

  testWidgets('finishing onboarding starts a full sync even though the screen closes', (
    tester,
  ) async {
    final server = FakeServer()..online = false;
    await pumpGuul(tester, server: server);
    await passWalkthrough(tester);
    for (final answer in warmUpAnswers) {
      await warmUp(tester, answer);
    }
    await tapContinue(tester);
    await tapText(tester, 'Grade 11');
    await tapContinue(tester);
    await tapText(tester, 'Social Science');
    await tapContinue(tester);
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await settle(tester);
    await tester.tap(find.text('Somali').last);
    await settle(tester);
    await tapContinue(tester);
    await tapContinue(tester);
    await tapContinue(tester);

    server.online = true;
    server.calls.clear();
    await tapContinue(tester);
    expect(find.text('Good morning!'), findsOneWidget);
    expect(server.calls, contains('GET /api/v1/catalog'));
    expect(server.profile['stream'], 'social');
    expect(find.text('You are offline. Your progress is saved and will sync later.'), findsNothing);
  });
}
