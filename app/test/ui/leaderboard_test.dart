import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

Map<String, Object?> row(int rank, String nick, int xp, {bool me = false}) => {
  'rank': rank,
  'nickname': nick,
  'avatar_style': 'thumbs',
  'avatar_seed': 'a0${rank % 9 + 1}',
  'xp': xp,
  'is_me': me,
};

void main() {
  testWidgets('weekly leaderboard: rows, my place, nickname prompt', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    server.leaderboard = {
      'week_start': '2026-11-16',
      'top': [row(1, 'Hodan', 920), row(2, 'Abdi', 700), row(2, 'Sara', 700)],
      'me': {'has_nickname': false, 'xp': 40, 'rank': null},
    };
    await pumpGuul(tester, server: server);

    await tapOn(tester, find.text('Progress').last);
    await tapOn(tester, find.text('See the weekly leaderboard'));
    expect(find.text('Week of Nov 16 · resets every Monday'), findsOneWidget);
    expect(find.text('Hodan'), findsOneWidget);
    expect(find.text('920 XP'), findsOneWidget);
    expect(find.text('#2'), findsNWidgets(2), reason: 'a tie shares the rank');
    expect(find.text('40 XP this week'), findsOneWidget);

    await tapOn(tester, find.textContaining('Choose a nickname to appear'));
    expect(find.text('Nickname and avatar'), findsOneWidget);
  });

  testWidgets('my row is marked; offline shows a clear message', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    server.leaderboard = {
      'week_start': '2026-11-16',
      'top': [row(1, 'Hodan', 920), row(2, 'Me Myself', 300, me: true)],
      'me': {'has_nickname': true, 'xp': 300, 'rank': 2},
    };
    await pumpGuul(tester, server: server);
    await tapOn(tester, find.text('Progress').last);
    await tapOn(tester, find.text('See the weekly leaderboard'));
    expect(find.text('You are number 2 this week'), findsOneWidget);
    expect(find.text('Me Myself · You'), findsOneWidget);
    expect(
      tester.widget<ListTile>(find.widgetWithText(ListTile, 'Me Myself · You')).selected,
      isTrue,
    );

    await tester.pageBack();
    await settle(tester);
    server.online = false;
    await tapOn(tester, find.text('See the weekly leaderboard'));
    expect(find.text('The leaderboard needs the internet.'), findsOneWidget);
  });

  testWidgets('no leaderboard button when the switch is off', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    server.features['leaderboard'] = false;
    await pumpGuul(tester, server: server);
    await tapOn(tester, find.text('Progress').last);
    expect(find.text('See the weekly leaderboard'), findsNothing);
  });
}
