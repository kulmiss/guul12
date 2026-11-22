import 'package:flutter_test/flutter_test.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

void main() {
  testWidgets('daily challenge: play from Home, uploaded with its date, result on the card', (
    tester,
  ) async {
    final server = FakeServer();
    onboardedProfile(server);
    await pumpGuul(tester, server: server);

    expect(find.text('Daily challenge'), findsOneWidget);
    expect(find.text('3 questions, the same for everyone in your grade today'), findsOneWidget);
    await tapOn(tester, find.text('Daily challenge'));

    expect(find.text('Question 1 of 3'), findsOneWidget);
    expect(find.text('Exam question 951'), findsOneWidget, reason: 'server order is kept');
    // Q1 key B: right. Q2 key D: wrong. Q3 key A: right.
    await tapOn(tester, find.text('Option B'));
    await tapOn(tester, find.text('Check'));
    await tapOn(tester, find.text('Next'));
    await tapOn(tester, find.text('Option A'));
    await tapOn(tester, find.text('Check'));
    await tapOn(tester, find.text('Next'));
    expect(find.text('Question 3 of 3'), findsOneWidget);
    await tapOn(tester, find.text('Option A'));
    await tapOn(tester, find.text('Check'));
    await tapOn(tester, find.text('Finish'));
    await tapOn(tester, find.text('Done'));

    final sent = server.receivedAttempts.single;
    expect(sent['mode'], 'daily_challenge');
    expect(sent['challenge_date'], '2026-11-22');
    expect(
      find.text('Today: 2 / 3 right · Come back later to compare with other players'),
      findsOneWidget,
    );
  });

  testWidgets('comparison shows once enough students played; hidden when switched off', (
    tester,
  ) async {
    final server = FakeServer();
    onboardedProfile(server);
    server
      ..challengeBetterThan = 64
      ..receivedAttempts.add({
        'mode': 'daily_challenge',
        'answers': [
          {'question_id': 951, 'response': 'B'},
        ],
      });
    await pumpGuul(tester, server: server);
    expect(find.text("Today: 1 / 3 right · Better than 64% of today's players"), findsOneWidget);
  });

  testWidgets('no challenge card when the switch is off', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    server.features['daily_challenge'] = false;
    await pumpGuul(tester, server: server);
    expect(find.text('Daily challenge'), findsNothing);
  });

  testWidgets('no challenge card offline (it needs the internet)', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    await pumpGuul(
      tester,
      server: server,
      before: (repo) async {
        await repo.syncAll();
        server.online = false;
      },
    );
    expect(find.text('Daily challenge'), findsNothing);
    expect(find.text('Quick 10'), findsOneWidget, reason: 'the rest of Home works');
  });
}
