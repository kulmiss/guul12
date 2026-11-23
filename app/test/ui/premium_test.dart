import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/repository.dart';
import 'package:guul12/services/ads.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

class _Ads implements Ads {
  var starts = 0;

  @override
  Future<void> start() async => starts++;

  @override
  Widget banner() => const SizedBox(key: ValueKey('ad'), height: 50, width: 320);

  @override
  Future<bool> showRewarded() async => false;
}

void expectNoPaymentWords(WidgetTester tester) {
  final texts = tester.widgetList<Text>(find.byType(Text)).map((t) => (t.data ?? '').toLowerCase());
  for (final t in texts) {
    for (final word in ['birr', 'price', 'pay', 'buy', 'whatsapp', 'telegram']) {
      expect(t.contains(word), isFalse, reason: '"$t" contains "$word"');
    }
  }
}

void main() {
  testWidgets('Premium ending within 7 days: a neutral note on Home', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    server.profile['premium'] = {'active': true, 'until': '2026-11-27T09:00:00Z'};
    await pumpGuul(tester, server: server);
    expect(find.text('Your Premium ends on Nov 27, 2026.'), findsOneWidget);
    expectNoPaymentWords(tester);
  });

  testWidgets('Premium ended: the phone remembers the date for a week', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    await pumpGuul(
      tester,
      server: server,
      before: (repo) => repo.db.writeString(Keys.lastPremiumUntil, '2026-11-20T09:00:00Z'),
    );
    expect(find.text('Your Premium ended on Nov 20, 2026.'), findsOneWidget);
    expectNoPaymentWords(tester);
  });

  testWidgets('far from the end: no note', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    server.profile['premium'] = {'active': true, 'until': '2027-07-01T00:00:00Z'};
    await pumpGuul(tester, server: server);
    expect(find.textContaining('Your Premium'), findsNothing);
  });

  testWidgets('staff give Premium: after a refresh the ads go and Me shows the end date', (
    tester,
  ) async {
    final server = FakeServer();
    onboardedProfile(server);
    final ads = _Ads();
    final (repo, _) = await pumpGuul(tester, server: server, ads: ads);
    expect(find.byKey(const ValueKey('ad')), findsOneWidget);

    // Payment happened outside the app; a payments helper gave Premium in Nova.
    server
      ..profile['premium'] = {'active': true, 'until': '2027-03-22T00:00:00Z'}
      ..showAds = false;
    fireAndForgetRefresh(tester);
    await tester.pump();
    await tester.pump(const Duration(seconds: 1));
    await settle(tester);

    expect(find.byKey(const ValueKey('ad')), findsNothing);
    expect(await repo.lastPremiumUntil(), DateTime.utc(2027, 3, 22));
    await tapOn(tester, find.text('Me'));
    expect(find.textContaining('Mar 22, 2027'), findsOneWidget);
    expectNoPaymentWords(tester);
  });
}

void fireAndForgetRefresh(WidgetTester tester) {
  // Pull to refresh on Home (as when the app is opened again).
  tester.state<RefreshIndicatorState>(find.byType(RefreshIndicator)).show();
}
