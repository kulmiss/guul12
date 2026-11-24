import 'package:flutter_test/flutter_test.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

FakeServer server({bool premium = false}) {
  final s = FakeServer();
  onboardedProfile(s);
  if (premium) s.profile['premium'] = {'active': true, 'until': '2027-07-01T00:00:00Z'};
  return s;
}

Future<void> answerWrong(WidgetTester tester) async {
  await tapOn(tester, find.text('Study'));
  await tapOn(tester, find.text('Physics'));
  await tapOn(tester, find.text('Unit 2: Two-dimensional motion'));
  await tapOn(tester, find.text('1. Chapter 21'));
  await tapOn(tester, find.textContaining('Chapter quiz'));
  await tapOn(tester, find.text('3'));
  await tapOn(tester, find.text('Check'));
}

void main() {
  testWidgets('free student: only a "🔒 Premium" label, nothing to tap, no price', (tester) async {
    final s = server();
    await pumpGuul(tester, server: s);
    await answerWrong(tester);
    expect(find.text('Explain more · 🔒 Premium'), findsOneWidget);
    expect(find.text('Explain more'), findsNothing);
    await tester.tap(find.text('Explain more · 🔒 Premium'));
    await settle(tester);
    expect(s.explainRequests, isEmpty);
  });

  testWidgets('Premium: AI explanation in a sheet, with the warning and a vote', (tester) async {
    final s = server(premium: true);
    await pumpGuul(tester, server: s);
    await answerWrong(tester);
    await tapOn(tester, find.text('Explain more'));
    expect(s.explainRequests, [2101]);
    expect(find.text('More explanation'), findsOneWidget);
    expect(find.textContaining('two pairs make four'), findsOneWidget);
    expect(find.textContaining('Written by AI. It may contain mistakes.'), findsOneWidget);
    await tapOn(tester, find.text('Not helpful'));
    expect(s.explainVotes, [(71, false)]);
    expect(find.text('Thanks for telling us!'), findsOneWidget);
  });

  testWidgets('Premium: daily limit, AI unavailable and offline are explained', (tester) async {
    final s = server(premium: true)..explainError = 'explain_limit';
    await pumpGuul(tester, server: s);
    await answerWrong(tester);
    await tapOn(tester, find.text('Explain more'));
    expect(
      find.text("That's all the extra explanations for today. Come back tomorrow!"),
      findsOneWidget,
    );
    await tester.tapAt(const Offset(10, 10));
    await settle(tester);

    s.explainError = 'ai_budget';
    await tapOn(tester, find.text('Explain more'));
    expect(
      find.text('Explain more is not available right now. Please try again later.'),
      findsOneWidget,
    );
    await tester.tapAt(const Offset(10, 10));
    await settle(tester);

    s.online = false;
    await tapOn(tester, find.text('Explain more'));
    expect(find.text('Explain more needs the internet.'), findsOneWidget);
  });

  testWidgets('hidden when the server has it off (e.g. no AI key)', (tester) async {
    final s = server(premium: true);
    s.features['explain_more'] = false;
    await pumpGuul(tester, server: s);
    await answerWrong(tester);
    expect(find.textContaining('Explain more'), findsNothing);
  });
}
