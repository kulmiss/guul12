import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/repository.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

/// A text inside the "First steps" badge tile.
Finder firstSteps(String text) =>
    find.descendant(of: find.widgetWithText(ListTile, 'First steps'), matching: find.text(text));

void main() {
  testWidgets('Progress shows weak topics and chapter strength after a quiz', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    await pumpGuul(tester, server: server);

    await tapOn(tester, find.text('Progress').last);
    expect(
      find.text('Study a chapter and answer some questions to see your progress here.'),
      findsOneWidget,
    );
    await tapOn(tester, find.text('Home'));
    expect(find.text('Smart practice'), findsNothing, reason: 'no chapter saved yet');

    await tapOn(tester, find.text('Study'));
    await tapOn(tester, find.text('Physics'));
    await tapOn(tester, find.text('Unit 2: Two-dimensional motion'));
    await tapOn(tester, find.text('1. Chapter 21'));
    expect(find.text('Learning'), findsNothing, reason: 'no answers yet, no chip');
    await tapOn(tester, find.textContaining('Chapter quiz'));
    await tapOn(tester, find.text('3'));
    await tapOn(tester, find.text('Check'));
    await tapOn(tester, find.text('Finish'));
    await tapOn(tester, find.text('Done'));

    // Back on the chapter: the strength chip is there now.
    expect(find.text('Learning'), findsOneWidget);
    expect(find.text('0/100 · 1 answered'), findsOneWidget);

    await tapOn(tester, find.text('Home'));
    expect(find.text('Smart practice'), findsOneWidget);

    await tapOn(tester, find.text('Progress').last);
    expect(find.text('1 question answered'), findsOneWidget);
    expect(find.text('0% right (last answer to each question)'), findsOneWidget);
    expect(find.text('Weak topics'), findsOneWidget);
    expect(find.text('1. Chapter 21'), findsOneWidget);
    expect(find.text('Physics'), findsOneWidget);

    await tapOn(tester, find.text('Practise'));
    expect(find.text('Question 1 of 1'), findsOneWidget);
    expect(find.text('What is 2 + 2?'), findsOneWidget);
  });

  testWidgets('Smart practice starts from the Home card', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    await pumpGuul(tester, server: server);

    await tapOn(tester, find.text('Study'));
    await tapOn(tester, find.text('Physics'));
    await tapOn(tester, find.text('Unit 2: Two-dimensional motion'));
    await tapOn(tester, find.text('1. Chapter 21'));
    await tapOn(tester, find.text('Home'));
    await tapOn(tester, find.text('Smart practice'));
    expect(find.text('Question 1 of 1'), findsOneWidget);
    expect(find.text('What is 2 + 2?'), findsOneWidget);
  });

  testWidgets('streak, XP and badges after one wrong answer', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    await pumpGuul(tester, server: server);
    expect(find.textContaining('streak'), findsNothing, reason: 'nothing answered yet');

    await tapOn(tester, find.text('Progress').last);
    expect(find.text('Badges · 0 of 10 earned'), findsOneWidget);
    expect(firstSteps('0 / 10'), findsOneWidget);

    await tapOn(tester, find.text('Study'));
    await tapOn(tester, find.text('Physics'));
    await tapOn(tester, find.text('Unit 2: Two-dimensional motion'));
    await tapOn(tester, find.text('1. Chapter 21'));
    await tapOn(tester, find.textContaining('Chapter quiz'));
    await tapOn(tester, find.text('3'));
    await tapOn(tester, find.text('Check'));
    await tapOn(tester, find.text('Finish'));
    await tapOn(tester, find.text('Done'));

    await tapOn(tester, find.text('Home'));
    expect(find.text('No streak yet · 1 / 10 questions today'), findsOneWidget);
    await tapOn(tester, find.text('Progress').last);
    expect(find.text('2 XP · 2 XP this week'), findsOneWidget);
    expect(firstSteps('1 / 10'), findsOneWidget);
  });

  testWidgets('10 answers today: a 1-day streak and the first badge', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    await pumpGuul(
      tester,
      server: server,
      before: (repo) => repo.saveAttempt(
        clientUuid: '00000000-0000-4000-8000-0000000000aa',
        mode: 'quick10',
        chapterId: 21,
        startedAt: testNow,
        finishedAt: testNow,
        answers: [
          for (var i = 0; i < 10; i++)
            AttemptAnswer(
              questionId: 2101 + i,
              chapterId: 21,
              version: 1,
              response: 'B',
              timeMs: 5000,
              correctLocally: i < 7,
            ),
        ],
      ),
    );
    expect(find.text('1-day streak · Daily goal reached'), findsOneWidget);
    await tapOn(tester, find.text('Progress').last);
    expect(find.text('76 XP · 76 XP this week'), findsOneWidget);
    expect(find.text('Best: 1 day'), findsOneWidget);
    expect(find.text('Badges · 1 of 10 earned'), findsOneWidget);
    expect(firstSteps('Earned'), findsOneWidget);
  });
}
