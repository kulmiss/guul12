import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_launcher.dart';
import '../support/fake_server.dart';

/// Owner decision #40: "Become Premium" on Home opens a benefits page with WhatsApp and Telegram
/// contact buttons. Never a price.
FakeServer serverWithContacts() {
  final server = FakeServer();
  onboardedProfile(server);
  server.contact = {'whatsapp': '251912345678', 'telegram': 'Guul12_Help'};
  return server;
}

void expectNoPrices(WidgetTester tester) {
  final texts = tester.widgetList<Text>(find.byType(Text)).map((t) => (t.data ?? '').toLowerCase());
  for (final t in texts) {
    for (final word in ['birr', 'price', ' pay', 'buy']) {
      expect(t.contains(word), isFalse, reason: '"$t" contains "$word"');
    }
  }
}

void main() {
  testWidgets('free student: Become Premium → benefits → WhatsApp with the student ID', (
    tester,
  ) async {
    final launcher = fakeLauncher();
    await pumpGuul(tester, server: serverWithContacts());

    expect(find.text('Become Premium'), findsOneWidget);
    expectNoPrices(tester);
    await tapOn(tester, find.text('Become Premium'));

    expect(find.text('Guul 12 Premium'), findsOneWidget);
    for (final benefit in [
      'All mock exams',
      'Whole subjects offline',
      'Explain more (AI)',
      'No ads',
    ]) {
      expect(find.text(benefit), findsOneWidget, reason: benefit);
    }
    expect(find.text('How to get Premium'), findsOneWidget);
    expect(find.text('ST-7K3Q9'), findsOneWidget, reason: 'the student ID to send');
    expectNoPrices(tester);

    await tapOn(tester, find.byKey(const ValueKey('premium-whatsapp')));
    expect(launcher.opened, [
      'https://wa.me/251912345678?text=${Uri.encodeComponent('Hello! I want Guul 12 Premium. My student ID: ST-7K3Q9')}',
    ]);
    await tapOn(tester, find.byKey(const ValueKey('premium-telegram')));
    expect(launcher.opened.last, 'https://t.me/Guul12_Help');
  });

  testWidgets('only the contacts that are set get a button', (tester) async {
    final server = serverWithContacts()..contact = {'whatsapp': null, 'telegram': 'Guul12_Help'};
    await pumpGuul(tester, server: server);
    await tapOn(tester, find.text('Become Premium'));
    expect(find.byKey(const ValueKey('premium-whatsapp')), findsNothing);
    expect(find.byKey(const ValueKey('premium-telegram')), findsOneWidget);
  });

  testWidgets('contacts switched off in Nova: benefits only, no buttons', (tester) async {
    final server = serverWithContacts()..contact = {'whatsapp': null, 'telegram': null};
    await pumpGuul(tester, server: server);
    await tapOn(tester, find.text('Become Premium'));
    expect(find.text('All mock exams'), findsOneWidget);
    expect(find.text('How to get Premium'), findsNothing);
    expect(find.byKey(const ValueKey('premium-whatsapp')), findsNothing);
    expect(find.byKey(const ValueKey('premium-telegram')), findsNothing);
  });

  testWidgets('Premium student: Home says it is on, with the end date; no Become Premium', (
    tester,
  ) async {
    final server = serverWithContacts();
    server.profile['premium'] = {'active': true, 'until': '2027-03-22T00:00:00Z'};
    await pumpGuul(tester, server: server);

    expect(find.text('Become Premium'), findsNothing);
    expect(find.text('Premium is on'), findsOneWidget);
    expect(find.text('Until Mar 22, 2027'), findsOneWidget);
    await tapOn(tester, find.text('Premium is on'));
    expect(find.text('All mock exams'), findsOneWidget, reason: 'what they have');
    expect(find.text('How to get Premium'), findsNothing);
    expect(find.byKey(const ValueKey('premium-whatsapp')), findsNothing);
  });
}
