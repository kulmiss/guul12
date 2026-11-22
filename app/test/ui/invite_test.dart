import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/services/sharing.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

class FakeSharing implements Sharing {
  final shared = <String>[];

  @override
  Future<void> shareText(String text) async => shared.add(text);
}

void main() {
  testWidgets('invite: code, rule, share text with the price-free link, enter a code', (
    tester,
  ) async {
    final server = FakeServer();
    onboardedProfile(server);
    final sharing = FakeSharing();
    await pumpGuul(tester, server: server, sharing: sharing);

    await tapOn(tester, find.text('Me'));
    await tapOn(tester, find.text('Invite friends'));
    expect(find.text('Q4WX7P'), findsOneWidget);
    expect(
      find.text(
        'When a friend you invite answers 50 questions, you get 7 days of Premium (up to 4 friends a month).',
      ),
      findsOneWidget,
    );
    expect(find.text('Friends joined: 3 · Counted: 2 · Premium days earned: 14'), findsOneWidget);

    await tapOn(tester, find.text('Share'));
    expect(sharing.shared, [
      'Study for the Grade 12 national exam with Guul 12. My invite code: Q4WX7P. https://guul12.kulmiss.com/app',
    ]);
    // No price, no payment words in what students share.
    expect(
      sharing.shared.single.toLowerCase(),
      isNot(anyOf(contains('birr'), contains('pay'), contains('price'))),
    );

    await tester.enterText(find.byType(TextField), 'nope99');
    await tapOn(tester, find.text('Use code'));
    expect(find.text('No student has this code.'), findsOneWidget);

    await tester.enterText(find.byType(TextField), 'friend');
    await tapOn(tester, find.text('Use code'));
    expect(find.text("Thanks! Your friend's code is saved."), findsOneWidget);
    expect(find.text('Did a friend invite you?'), findsNothing, reason: 'only once');
  });

  testWidgets('reward set to 0 days: no Premium promise; sharing and referral switches', (
    tester,
  ) async {
    final server = FakeServer();
    onboardedProfile(server);
    server.referral = {...server.referral, 'reward_days': 0, 'can_enter_code': false};
    server.features['sharing'] = false;
    await pumpGuul(tester, server: server);
    await tapOn(tester, find.text('Me'));
    await tapOn(tester, find.text('Invite friends'));
    expect(find.text('Invite friends to study for the exam with you.'), findsOneWidget);
    expect(find.textContaining('Premium (up to'), findsNothing);
    expect(find.text('Share'), findsNothing);
    expect(find.text('Copy'), findsOneWidget);
  });

  testWidgets('no invite entry when referral is switched off', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    server.features['referral'] = false;
    await pumpGuul(tester, server: server);
    await tapOn(tester, find.text('Me'));
    expect(find.text('Invite friends'), findsNothing);
  });
}
