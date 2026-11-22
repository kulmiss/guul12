import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/ui/screens/identity_screen.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

void main() {
  group('nickname rule (same as the server)', () {
    test('valid and invalid', () {
      for (final ok in ['Abdi', 'sara_12', 'Hodan A.', 'ፍቅር ቤት', 'Cali-Xasan', '  Ali   Nur  ']) {
        expect(nicknameLooksValid(ok), isTrue, reason: ok);
      }
      for (final bad in ['', 'ab', '  ab  ', 'a' * 25, 'bad!name', 'x@y.com', '<b>hi</b>']) {
        expect(nicknameLooksValid(bad), isFalse, reason: bad);
      }
      expect(cleanNickname('  Ali   Nur  '), 'Ali Nur');
    });
  });

  testWidgets('choose a nickname and an avatar; refused and offline cases', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    server.refusedNicknames.add('Badword');
    await pumpGuul(tester, server: server);

    await tapOn(tester, find.text('Me'));
    expect(find.text('Choose a nickname'), findsOneWidget);
    await tapOn(tester, find.text('Choose a nickname'));
    expect(find.text('Nickname and avatar'), findsOneWidget);

    // Too short: stopped on the phone, nothing sent.
    final patches = server.calls.where((c) => c.startsWith('PATCH ') && c.endsWith('/me')).length;
    await tester.enterText(find.byType(TextField), 'ab');
    await tapOn(tester, find.text('Save'));
    expect(
      find.text('Use 3 to 24 letters, numbers, spaces, dots, dashes or underscores.'),
      findsOneWidget,
    );
    expect(server.calls.where((c) => c.startsWith('PATCH ') && c.endsWith('/me')).length, patches);

    // Refused by the server.
    await tester.enterText(find.byType(TextField), 'Badword');
    await tapOn(tester, find.text('Save'));
    expect(find.text('Please choose another nickname.'), findsOneWidget);
    expect(server.profile['nickname'], isNull);

    // Offline.
    server.online = false;
    await tester.enterText(find.byType(TextField), 'Hodan');
    await tapOn(tester, find.text('Save'));
    expect(find.textContaining('needs the internet'), findsOneWidget);

    // Saved: avatar 15 = the 3rd avatar of the 2nd style.
    server.online = true;
    await tapOn(tester, find.bySemanticsLabel('Avatar 15'));
    await tapOn(tester, find.text('Save'));
    expect(server.profile['nickname'], 'Hodan');
    expect(server.profile['avatar_style'], 'lorelei-neutral');
    expect(server.profile['avatar_seed'], 'a03');
    expect(find.text('Hodan'), findsOneWidget, reason: 'back on Me');
  });
}
