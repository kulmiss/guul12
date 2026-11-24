import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

Map<String, Object?> row(int rank, String nick, {bool me = false}) => {
  'rank': rank,
  'nickname': nick,
  'avatar_style': 'thumbs',
  'avatar_seed': 'a0$rank',
  'xp': 1000 - rank * 100,
  'is_me': me,
  'ref': me ? null : 'ref-$nick',
};

FakeServer withBoard() {
  final s = FakeServer();
  onboardedProfile(s);
  s.profile.addAll({'nickname': 'Me Myself', 'avatar_style': 'thumbs', 'avatar_seed': 'a02'});
  s.leaderboard = {
    'week_start': '2026-11-16',
    'top': [row(1, 'Badname'), row(2, 'Me Myself', me: true)],
    'me': {'has_nickname': true, 'xp': 800, 'rank': 2},
  };
  return s;
}

Future<void> openBoard(WidgetTester tester) async {
  await tapOn(tester, find.text('Progress').last);
  await tapOn(tester, find.text('See the weekly leaderboard'));
}

Finder menuOf(String nickname) => find.descendant(
  of: find.widgetWithText(ListTile, nickname),
  matching: find.byTooltip('Report nickname'),
);

void main() {
  testWidgets('report another nickname from the leaderboard; never my own', (tester) async {
    final server = withBoard();
    await pumpGuul(tester, server: server);
    await openBoard(tester);

    expect(menuOf('Me Myself · You'), findsNothing, reason: 'no report button on my own row');
    await tapOn(tester, menuOf('Badname'));
    expect(find.text('Report "Badname"'), findsOneWidget);
    expect(
      find.text('Staff will check this nickname. The student is not told who reported it.'),
      findsOneWidget,
    );

    await tapOn(tester, find.text('Rude or hurtful'));
    await tester.enterText(find.byType(TextField), '  bad word  ');
    await tapOn(tester, find.text('Send report'));

    expect(server.receivedNicknameReports, [
      {'ref': 'ref-Badname', 'reason': 'rude', 'note': 'bad word'},
    ]);
    expect(find.text('Thanks. Staff will check this nickname.'), findsOneWidget);
  });

  testWidgets('a reason is needed; cancel sends nothing', (tester) async {
    final server = withBoard();
    await pumpGuul(tester, server: server);
    await openBoard(tester);
    await tapOn(tester, menuOf('Badname'));

    final send = find.widgetWithText(FilledButton, 'Send report');
    expect(tester.widget<FilledButton>(send).onPressed, isNull, reason: 'pick a reason first');
    await tapOn(tester, find.text('Cancel'));
    expect(server.receivedNicknameReports, isEmpty);
  });

  testWidgets('offline and daily limit show clear messages', (tester) async {
    final server = withBoard();
    await pumpGuul(tester, server: server);
    await openBoard(tester);

    server.online = false;
    await tapOn(tester, menuOf('Badname'));
    await tapOn(tester, find.text('Other'));
    await tapOn(tester, find.text('Send report'));
    expect(find.text('You need the internet to send this report.'), findsOneWidget);
    await tester.pump(const Duration(seconds: 5));
    await settle(tester);

    server
      ..online = true
      ..nicknameReportStatus = 429
      ..nicknameReportCode = 'report_limit';
    await tapOn(tester, menuOf('Badname'));
    await tapOn(tester, find.text('Pretends to be someone else'));
    await tapOn(tester, find.text('Send report'));
    expect(
      find.text('You have sent many reports today. Please try again tomorrow.'),
      findsOneWidget,
    );
    expect(server.receivedNicknameReports, isEmpty);
  });

  testWidgets('report a battle opponent', (tester) async {
    final server = withBoard();
    server.battles.add({'id': 1, 'friend': false, 'opponent': 'Rival'});
    server.receivedAttempts.add({'mode': 'battle', 'battle_id': 1, 'answers': <Object?>[]});
    await pumpGuul(tester, server: server);
    await tapOn(tester, find.text('Battles'));

    await tapOn(tester, menuOf('Rival'));
    await tapOn(tester, find.text('Rude or hurtful'));
    await tapOn(tester, find.text('Send report'));
    expect(server.receivedNicknameReports.single['ref'], 'ref-Rival');
  });
}
