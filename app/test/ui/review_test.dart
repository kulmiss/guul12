import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/app/providers.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

void main() {
  testWidgets('a wrong answer lands in My mistakes and comes back for review the next day', (
    tester,
  ) async {
    var now = testNow;
    final server = FakeServer();
    onboardedProfile(server);
    await pumpGuul(tester, server: server, clock: () => now);

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

    expect(find.text('My mistakes'), findsOneWidget);
    expect(find.text('1 question to learn'), findsOneWidget);
    expect(find.textContaining('to review today'), findsNothing, reason: 'due tomorrow');

    await tapOn(tester, find.text('My mistakes'));
    expect(find.text('1. Chapter 21'), findsOneWidget);
    expect(find.text('Next review: Nov 23'), findsOneWidget);
    await tapOn(tester, find.text('What is 2 + 2?'));
    expect(find.text('Correct answer: B. 4'), findsOneWidget);
    expect(find.text('2 + 2 = 4.'), findsOneWidget);
    await tester.pageBack();
    await settle(tester);

    // Next day: the review card appears and starts a review of that question.
    now = testNow.add(const Duration(days: 1));
    // Pull to refresh (as when the app is opened again the next day).
    final calls = server.calls.length;
    expect(find.byType(RefreshIndicator), findsOneWidget);
    fireAndForget(tester.state<RefreshIndicatorState>(find.byType(RefreshIndicator)).show());
    await tester.pump();
    await tester.pump(const Duration(seconds: 1));
    await settle(tester);
    expect(server.calls.length, greaterThan(calls), reason: 'pull to refresh ran a sync');
    expect(find.text('1 question to review today'), findsOneWidget);
    await tapOn(tester, find.text('1 question to review today'));
    expect(find.text('Review due questions'), findsOneWidget);
    expect(find.text('Question 1 of 1'), findsOneWidget);
    expect(find.text('What is 2 + 2?'), findsOneWidget);
  });

  testWidgets('Home and Exams can be pulled to refresh even when their list is short', (
    tester,
  ) async {
    final server = FakeServer();
    onboardedProfile(server);
    await pumpGuul(tester, server: server);

    for (final tab in ['Home', 'Exams']) {
      await tapOn(tester, find.text(tab).last);
      final list = tester.widget<ListView>(
        find.descendant(of: find.byType(RefreshIndicator), matching: find.byType(ListView)).first,
      );
      expect(list.physics, isA<AlwaysScrollableScrollPhysics>(), reason: tab);
    }
  });
}
