import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

void main() {
  testWidgets('today\'s plan: tasks, ticks, a stable list, and new minutes', (tester) async {
    final server = FakeServer();
    onboardedProfile(server); // target 450 / 600 → 75
    await pumpGuul(tester, server: server);

    // Exam on Jun 29, 2027 is 219 days away: 189 learning days. 2 chapters, 30 minutes.
    expect(find.text("Today's plan"), findsOneWidget);
    expect(find.text('2 tasks · 30 min · 0/2'), findsOneWidget);
    await tapOn(tester, find.text("Today's plan"));

    expect(find.text('Learning time. Revision starts in 189 days.'), findsOneWidget);
    expect(
      find.text('Study about 1 new chapter a week to finish before revision.'),
      findsOneWidget,
    );
    expect(find.text('0 of 2 chapters at your target'), findsOneWidget);
    expect(find.text("Today's plan · 2 tasks · 30 min"), findsOneWidget);
    expect(find.text('Study 1. Chapter 21'), findsOneWidget);
    expect(find.text('Smart practice'), findsOneWidget);

    // The task opens the chapter; a wrong answer there makes chapter 21 weak.
    await tapOn(tester, find.text('Study 1. Chapter 21'));
    await tapOn(tester, find.textContaining('Chapter quiz'));
    await tapOn(tester, find.text('3'));
    await tapOn(tester, find.text('Check'));
    await tapOn(tester, find.text('Finish'));
    await tapOn(tester, find.text('Done'));
    await tester.pageBack();
    await settle(tester);

    // The list does not move during the day.
    expect(find.text('Study 1. Chapter 21'), findsOneWidget);
    await tapOn(tester, find.bySemanticsLabel('Done').first);
    expect(tester.widget<Checkbox>(find.byType(Checkbox).first).value, isTrue);

    // New minutes build a new plan from what the student knows now.
    await tapOn(tester, find.widgetWithText(ChoiceChip, '60 min'));
    expect(find.text("Today's plan · 3 tasks · 40 min"), findsOneWidget);
    expect(find.text('Study 2. Chapter 22'), findsOneWidget);
    expect(find.text('Practise 1. Chapter 21'), findsOneWidget);
    expect(find.text('Smart practice'), findsOneWidget);
    expect(tester.widgetList<Checkbox>(find.byType(Checkbox)).map((c) => c.value), [
      false,
      false,
      false,
    ]);

    for (var i = 0; i < 3; i++) {
      await tapOn(tester, find.byType(Checkbox).at(i));
    }
    expect(find.text('All done for today. Great work!'), findsOneWidget);
    await tester.pageBack();
    await settle(tester);
    expect(find.text('All done for today. Great work!'), findsOneWidget, reason: 'Home card');
  });

  testWidgets('the last 30 days: revision text, mock exam days', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    // 21 days before the exam on Jun 29, 2027.
    await pumpGuul(tester, server: server, clock: () => DateTime(2027, 6, 8, 9));
    await tapOn(tester, find.text("Today's plan"));
    expect(
      find.text('Revision time: weak chapters first and a mock exam every week.'),
      findsOneWidget,
    );
    expect(find.text('Full mock exam'), findsOneWidget);
    await tapOn(tester, find.text('Full mock exam'));
    expect(find.text('Exams'), findsWidgets);
    expect(find.text('Physics mock exam 1'), findsNothing, reason: 'pick a subject first');
  });
}
