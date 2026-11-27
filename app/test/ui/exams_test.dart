import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/services/uptime_clock.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

FakeServer examServer({bool premium = false}) {
  final s = FakeServer()..premium = premium;
  onboardedProfile(s);
  if (premium) s.profile['premium'] = {'active': true, 'until': '2027-07-01T00:00:00Z'};
  return s;
}

/// Opens the Exams tab on Physics (English comes first in the subject list).
Future<void> openExams(WidgetTester tester) async {
  await tapOn(tester, find.text('Exams').last);
  await tapOn(tester, find.widgetWithText(ChoiceChip, 'Physics'));
}

Future<void> startMock1(WidgetTester tester) async {
  await tapOn(tester, find.text('Physics mock exam 1'));
  expect(find.text('Real Exam Mode'), findsOneWidget);
  expect(find.text('4 questions · 6 min'), findsWidgets);
  await tapOn(tester, find.text('Start exam'));
}

void main() {
  testWidgets('the Exams tab: free mock open, Premium mock and personal exams locked', (
    tester,
  ) async {
    await pumpGuul(tester, server: examServer());
    await openExams(tester);

    expect(find.text('Mock exams'), findsOneWidget);
    expect(find.text('Physics mock exam 1'), findsOneWidget);
    expect(find.text('4 questions · 6 min'), findsOneWidget);
    expect(find.text('Physics mock exam 2'), findsOneWidget);
    // Premium mock, weak topics and mistakes show only the lock (Google Play: no buy path).
    expect(find.text('🔒 Premium'), findsNWidgets(3));
    await tapOn(tester, find.text('Physics mock exam 2'));
    expect(find.text('Real Exam Mode'), findsNothing);
    await tapOn(tester, find.text('My mistakes'));
    expect(find.text('Real Exam Mode'), findsNothing);
    expect(find.textContaining('price', findRichText: true), findsNothing);
  });

  testWidgets('sit a mock: answer, change, flag, map, submit, see the result and review', (
    tester,
  ) async {
    final (repo, server) = await pumpGuul(tester, server: examServer());
    await openExams(tester);
    await startMock1(tester);

    expect(find.text('Question 1 of 4'), findsOneWidget);
    expect(find.text('06:00'), findsOneWidget);
    // No right/wrong during the exam.
    await tapOn(tester, find.text('Option B'));
    await tapOn(tester, find.text('Option A'));
    expect(find.text('Correct!'), findsNothing);
    await tapOn(tester, find.text('Next'));

    expect(find.text('Question 2 of 4'), findsOneWidget);
    await tapOn(tester, find.text('Option B'));
    await tapOn(tester, find.byTooltip('Flag for review'));
    expect(find.byTooltip('Remove flag'), findsOneWidget);

    // The map jumps straight to question 4; 3 stays unanswered.
    await tapOn(tester, find.byTooltip('All questions'));
    expect(find.bySemanticsLabel('2, Answered, Flagged'), findsOneWidget);
    expect(find.bySemanticsLabel('3, Not answered'), findsOneWidget);
    await tapOn(tester, find.bySemanticsLabel('4, Not answered'));
    expect(find.text('Question 4 of 4'), findsOneWidget);
    await tapOn(tester, find.text('Option D'));

    await tapOn(tester, find.text('Submit exam'));
    expect(find.text('Answered: 3 of 4\nNot answered: 1\nFlagged: 1'), findsOneWidget);
    await tapOn(tester, find.text('Keep going'));
    expect(find.text('Question 4 of 4'), findsOneWidget);
    await tapOn(tester, find.text('Submit exam'));
    await tapOn(
      tester,
      find.descendant(of: find.byType(AlertDialog), matching: find.text('Submit exam')),
    );

    // Result: 2 of 4 (A right, B wrong, skipped, D right).
    expect(find.text('Exam result'), findsOneWidget);
    expect(find.text('2 / 4 correct'), findsOneWidget);
    expect(find.text('50% right'), findsOneWidget);
    expect(find.textContaining('Estimated exam score:'), findsOneWidget);
    expect(
      find.text('Not enough students have taken this mock yet. Check again later.'),
      findsOneWidget,
    );
    expect(find.text('By chapter'), findsOneWidget);
    expect(find.text('1. Chapter 21'), findsOneWidget);
    expect(find.text('2. Chapter 22'), findsOneWidget);

    await tapOn(tester, find.text('Review answers'));
    expect(find.textContaining('Your answer: B. Option B'), findsOneWidget);
    expect(find.textContaining('Correct answer: C. Option C'), findsOneWidget);
    expect(find.textContaining('Because C.'), findsOneWidget);

    final sent = server.receivedAttempts.single;
    expect([sent['mode'], sent['mock_id']], ['exam_fixed', 1]);
    expect(await repo.examInProgress(), isNull);

    // "Check again" picks up the comparison once enough students took the mock.
    server
      ..betterThanPercent = 64
      ..students = 31;
    await tapOn(tester, find.text('Check again'));
    expect(find.text('Better than 64% of Guul 12 students'), findsOneWidget);

    await tapOn(tester, find.text('Done'));
    expect(find.text('Your results'), findsOneWidget);
    expect(find.textContaining('2 / 4 ·'), findsOneWidget);
  });

  testWidgets('a finished exam updates the Progress tab at once', (tester) async {
    await pumpGuul(tester, server: examServer());
    await tapOn(tester, find.text('Progress').last);
    expect(find.textContaining('to see your progress here'), findsOneWidget);

    await openExams(tester);
    await startMock1(tester);
    await tapOn(tester, find.text('Option A'));
    await tapOn(tester, find.byTooltip('All questions'));
    await tapOn(tester, find.bySemanticsLabel('4, Not answered'));
    await tapOn(tester, find.text('Submit exam'));
    await tapOn(
      tester,
      find.descendant(of: find.byType(AlertDialog), matching: find.text('Submit exam')),
    );
    expect(find.text('1 / 4 correct'), findsOneWidget);
    await tapOn(tester, find.text('Done'));

    // Every exam question counts, a skipped one as wrong: 4 answered, 1 right.
    await tapOn(tester, find.text('Progress').last);
    expect(find.text('4 questions answered'), findsOneWidget);
    expect(find.text('25% right (last answer to each question)'), findsOneWidget);
  });

  testWidgets('leaving keeps the exam (the clock does not stop); it can be continued', (
    tester,
  ) async {
    final (repo, _) = await pumpGuul(tester, server: examServer());
    await openExams(tester);
    await startMock1(tester);
    await tapOn(tester, find.text('Option C'));

    await tester.pageBack();
    await settle(tester);
    expect(find.text('Leave the exam?'), findsOneWidget);
    expect(
      find.text('The timer keeps running. You can come back from the Exams tab.'),
      findsOneWidget,
    );
    await tapOn(tester, find.text('Leave'));

    expect(find.text('Continue your exam'), findsOneWidget);
    expect((await repo.examInProgress())?.answers, {0: 'C'});
    // A second exam cannot start while one is open.
    await tapOn(tester, find.text('Random mock'));
    expect(find.text('Finish or submit your current exam first.'), findsOneWidget);

    await tapOn(tester, find.text('Continue your exam'));
    expect(find.text('Question 1 of 4'), findsOneWidget);
    // The answer is still chosen (the clear button only shows for a chosen answer).
    expect(find.text('Clear answer'), findsOneWidget);
  });

  testWidgets('when time is up the exam is submitted on its own', (tester) async {
    var now = testNow;
    final (_, server) = await pumpGuul(tester, server: examServer(), clock: () => now);
    await openExams(tester);
    await startMock1(tester);
    await tapOn(tester, find.text('Option A'));

    now = testNow.add(const Duration(minutes: 5, seconds: 30));
    await tester.pump(const Duration(seconds: 1));
    expect(find.text('00:30'), findsOneWidget);

    now = testNow.add(const Duration(minutes: 6, seconds: 1));
    await tester.pump(const Duration(seconds: 1));
    await settle(tester);

    expect(find.text('Time is up. Your exam was submitted.'), findsOneWidget);
    expect(find.text('Exam result'), findsOneWidget);
    expect(find.text('1 / 4 correct'), findsOneWidget);
    final sent = server.receivedAttempts.single;
    expect(sent['finished_at'], testNow.add(const Duration(minutes: 6)).toUtc().toIso8601String());
  });

  testWidgets('setting the phone clock back does not give more exam time (bug hunt)', (
    tester,
  ) async {
    var now = testNow;
    final uptime = FakeUptimeClock(1000);
    final (_, server) = await pumpGuul(
      tester,
      server: examServer(),
      clock: () => now,
      uptime: uptime,
    );
    await openExams(tester);
    await startMock1(tester);
    await tapOn(tester, find.text('Option A'));

    // 5½ real minutes pass, but the student turned the phone clock back by 1 hour.
    uptime.ms = 1000 + 330000;
    now = testNow.subtract(const Duration(hours: 1));
    await tester.pump(const Duration(seconds: 1));
    expect(find.text('00:30'), findsOneWidget);

    uptime.ms = 1000 + 361000;
    await tester.pump(const Duration(seconds: 1));
    await settle(tester);
    expect(find.text('Time is up. Your exam was submitted.'), findsOneWidget);
    final sent = server.receivedAttempts.single;
    expect(sent['finished_at'], testNow.add(const Duration(minutes: 6)).toUtc().toIso8601String());
  });

  testWidgets('random mock: one free a day, then a clear message (no rewarded videos)', (
    tester,
  ) async {
    // The rewarded-video offer is tested in ads_test.dart.
    final server = examServer()..randomStatus = 429;
    server.features['rewarded_ads'] = false;
    await pumpGuul(tester, server: server);
    await openExams(tester);

    await tapOn(tester, find.text('Random mock'));

    expect(
      find.text("Today's free random mock is used. A new one is ready tomorrow."),
      findsOneWidget,
    );
    expect(find.text('Real Exam Mode'), findsNothing);
  });

  testWidgets('Premium: my mistakes is built from the student\'s own wrong answers', (
    tester,
  ) async {
    await pumpGuul(tester, server: examServer(premium: true));
    await openExams(tester);
    expect(find.text('🔒 Premium'), findsNothing);

    // Nothing practised yet.
    await tapOn(tester, find.text('My mistakes'));
    expect(find.text('Practise some questions in this subject first.'), findsOneWidget);

    // Practise chapter 21 and get its question wrong.
    await tapOn(tester, find.text('Study'));
    await tapOn(tester, find.text('Physics'));
    await tapOn(tester, find.text('Unit 2: Two-dimensional motion'));
    await tapOn(tester, find.text('1. Chapter 21'));
    await tapOn(tester, find.textContaining('Chapter quiz'));
    await tapOn(tester, find.text('3'));
    await tapOn(tester, find.text('Check'));
    await tapOn(tester, find.text('Finish'));
    await tapOn(tester, find.text('Done'));

    await openExams(tester);
    await tapOn(tester, find.text('My mistakes'));
    expect(find.text('Physics · My mistakes'), findsOneWidget);
    expect(find.text('1 question · 2 min'), findsOneWidget);
  });
}
