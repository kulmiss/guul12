import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

FakeServer withNickname() {
  final s = FakeServer();
  onboardedProfile(s);
  s.profile.addAll({'nickname': 'Hodan', 'avatar_style': 'thumbs', 'avatar_seed': 'a02'});
  return s;
}

Future<void> openBattles(WidgetTester tester) => tapOn(tester, find.text('Battles'));

void main() {
  testWidgets('no nickname: battles ask for one first', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    await pumpGuul(tester, server: server);
    await openBattles(tester);
    expect(find.text('Quick battle'), findsNothing);
    await tapOn(tester, find.textContaining('Choose a nickname to start battles'));
    expect(find.text('Nickname and avatar'), findsOneWidget);
  });

  testWidgets('quick battle: timed questions in the server order, uploaded with the battle id', (
    tester,
  ) async {
    final server = withNickname();
    await pumpGuul(tester, server: server);
    await openBattles(tester);
    expect(find.text('No battles yet.'), findsOneWidget);

    await tapOn(tester, find.text('Quick battle'));
    expect(find.text('Question 1 of 3'), findsOneWidget);
    expect(find.text('Exam question 961'), findsOneWidget);
    expect(find.byIcon(Icons.timer_outlined), findsOneWidget, reason: '60 seconds a question');
    for (final pick in ['Option C', 'Option A']) {
      await tapOn(tester, find.text(pick));
      await tapOn(tester, find.text('Check'));
      await tapOn(tester, find.text('Next'));
    }
    await tapOn(tester, find.text('Option D'));
    await tapOn(tester, find.text('Check'));
    await tapOn(tester, find.text('Finish'));
    await tapOn(tester, find.text('Done'));

    final sent = server.receivedAttempts.single;
    expect([sent['mode'], sent['battle_id']], ['battle', 1]);
    expect(find.text('Waiting for an opponent'), findsWidgets);
  });

  testWidgets('battle a friend: the code is shown, and stays in the list', (tester) async {
    final server = withNickname();
    await pumpGuul(tester, server: server);
    await openBattles(tester);
    await tapOn(tester, find.text('Battle a friend'));
    expect(find.text('Your battle code'), findsOneWidget);
    expect(find.text('K7Q2MZ'), findsOneWidget);
    await tapOn(tester, find.text('Close'));
    expect(find.text('Your turn · Battle code: K7Q2MZ'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'Play now'), findsOneWidget);
  });

  testWidgets('join with a code: wrong code explained, right code plays and shows the result', (
    tester,
  ) async {
    final server = withNickname();
    await pumpGuul(tester, server: server);
    await openBattles(tester);

    await tapOn(tester, find.text('Join with a code'));
    await tester.enterText(find.byType(TextField), 'nope12');
    await tapOn(tester, find.widgetWithText(FilledButton, 'Play now'));
    expect(find.text('No battle has this code.'), findsOneWidget);

    await tapOn(tester, find.text('Join with a code'));
    await tester.enterText(find.byType(TextField), 'join22');
    await tapOn(tester, find.widgetWithText(FilledButton, 'Play now'));
    expect(find.text('Question 1 of 3'), findsOneWidget);
    for (var i = 0; i < 2; i++) {
      await tapOn(tester, find.text('Option A'));
      await tapOn(tester, find.text('Check'));
      await tapOn(tester, find.text('Next'));
    }
    await tapOn(tester, find.text('Option A'));
    await tapOn(tester, find.text('Check'));
    await tapOn(tester, find.text('Finish'));
    await tapOn(tester, find.text('Done'));
    expect(find.text('Ayan'), findsOneWidget);
    expect(find.text('You won · You 2/3 · Ayan 1/3'), findsOneWidget);
  });

  testWidgets('no battles card when switched off', (tester) async {
    final server = withNickname();
    server.features['battles'] = false;
    await pumpGuul(tester, server: server);
    expect(find.text('Battles'), findsNothing);
  });
}
