import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

Future<void> tapText(WidgetTester tester, String text) => tapOn(tester, find.text(text));

Future<void> tapContinue(WidgetTester tester) async {
  await tester.tap(find.byKey(const ValueKey('onboarding-continue')));
  await settle(tester);
}

Future<void> checkAndNext(WidgetTester tester) async {
  await tester.tap(find.byKey(const ValueKey('check')));
  await settle(tester);
  await tester.tap(find.byKey(const ValueKey('next')));
  await settle(tester);
}

void main() {
  testWidgets('Grade 12 onboarding: warm-up, stream, region, saved to the server', (tester) async {
    final (repo, server) = await pumpGuul(tester);

    expect(find.text('Pass the national exam with Guul 12'), findsOneWidget);
    await tapContinue(tester);

    expect(find.text('Which grade are you in?'), findsOneWidget);
    final continueButton = find.byKey(const ValueKey('onboarding-continue'));
    expect(tester.widget<FilledButton>(continueButton).onPressed, isNull);
    await tapText(tester, 'Grade 12');
    await tapContinue(tester);

    // Warm-up: answer 4 right and 1 wrong.
    expect(find.text('Quick warm-up: 5 questions'), findsOneWidget);
    await tester.enterText(find.byType(TextField), '12'); // f'(2) for x^3
    await checkAndNext(tester);
    await tapText(tester, '2 s');
    await checkAndNext(tester);
    await tapText(tester, 'True'); // wrong on purpose: oxidation is LOSS of electrons
    await tester.tap(find.byKey(const ValueKey('check')));
    await settle(tester);
    expect(find.text('Not quite'), findsOneWidget);
    expect(find.text('Correct answer: False'), findsOneWidget);
    expect(find.text('Report a problem'), findsNothing, reason: 'warm-up questions are local only');
    await tester.tap(find.byKey(const ValueKey('next')));
    await settle(tester);
    await tapText(tester, '30');
    await checkAndNext(tester);
    await tapText(tester, '48');
    await tester.tap(find.byKey(const ValueKey('check')));
    await settle(tester);
    expect(find.text('Correct!'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('next')));
    await settle(tester);

    expect(find.text('You got 4 of 5!'), findsOneWidget);
    await tapContinue(tester);

    expect(find.text('Which stream are you in?'), findsOneWidget);
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
    expect(find.text('Welcome back'), findsOneWidget);

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

  testWidgets('Grade 9 skips stream and retaker questions; Back never replays the warm-up', (
    tester,
  ) async {
    final (_, server) = await pumpGuul(tester);
    await tapContinue(tester);
    await tapText(tester, 'Grade 9');
    await tapContinue(tester);

    for (final answer in ['5', '60 km/h', 'Atom', 'True', 'goes']) {
      await tapText(tester, answer);
      await checkAndNext(tester);
    }
    expect(find.text('You got 5 of 5!'), findsOneWidget);

    await tester.tap(find.byType(BackButton));
    await settle(tester);
    expect(find.text('Which grade are you in?'), findsOneWidget);
    await tapContinue(tester);
    expect(find.text('You got 5 of 5!'), findsOneWidget);
    await tapContinue(tester);

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

    expect(find.text('Welcome back'), findsOneWidget);
    expect(server.profile['grade'], 9);
    expect(server.profile['stream'], isNull);
    expect(server.profile['gender'], 'female');
    expect(server.profile['city'], isNull);
  });

  testWidgets('offline first launch still finishes onboarding; it syncs later', (tester) async {
    final (repo, server) = await pumpGuul(tester, server: FakeServer()..online = false);
    await tapContinue(tester);
    await tapText(tester, 'Grade 10');
    await tapContinue(tester);
    for (final step in ['32', '#12', 'True', 'Chloroplast', '24 hours']) {
      if (step.startsWith('#')) {
        await tester.enterText(find.byType(TextField), step.substring(1));
      } else {
        await tapText(tester, step);
      }
      await checkAndNext(tester);
    }
    await tapContinue(tester);
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await settle(tester);
    await tester.tap(find.text('Oromia').last);
    await settle(tester);
    for (var i = 0; i < 4; i++) {
      await tapContinue(tester);
    }
    expect(find.text('Welcome back'), findsOneWidget);
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
    await tapContinue(tester);
    await tapText(tester, 'Grade 11');
    await tapContinue(tester);
    for (final step in ['#7', 'Joule', '6', 'True', 'quick']) {
      if (step.startsWith('#')) {
        await tester.enterText(find.byType(TextField), step.substring(1));
      } else {
        await tapText(tester, step);
      }
      await checkAndNext(tester);
    }
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
    expect(find.text('Welcome back'), findsOneWidget);
    expect(server.calls, contains('GET /api/v1/catalog'));
    expect(server.profile['stream'], 'social');
    expect(find.text('You are offline. Your progress is saved and will sync later.'), findsNothing);
  });
}
