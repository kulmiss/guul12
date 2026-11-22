import 'package:flutter_test/flutter_test.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

void main() {
  testWidgets('Progress shows weak topics and chapter strength after a quiz', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    await pumpGuul(tester, server: server);

    await tapOn(tester, find.text('Progress').last);
    expect(
      find.text('Study a chapter and answer some questions to see your progress here.'),
      findsOneWidget,
    );
    await tapOn(tester, find.text('Home'));
    expect(find.text('Smart practice'), findsNothing, reason: 'no chapter saved yet');

    await tapOn(tester, find.text('Study'));
    await tapOn(tester, find.text('Physics'));
    await tapOn(tester, find.text('Unit 2: Two-dimensional motion'));
    await tapOn(tester, find.text('1. Chapter 21'));
    expect(find.text('Learning'), findsNothing, reason: 'no answers yet, no chip');
    await tapOn(tester, find.textContaining('Chapter quiz'));
    await tapOn(tester, find.text('3'));
    await tapOn(tester, find.text('Check'));
    await tapOn(tester, find.text('Finish'));
    await tapOn(tester, find.text('Done'));

    // Back on the chapter: the strength chip is there now.
    expect(find.text('Learning'), findsOneWidget);
    expect(find.text('0/100 · 1 answered'), findsOneWidget);

    await tapOn(tester, find.text('Home'));
    expect(find.text('Smart practice'), findsOneWidget);

    await tapOn(tester, find.text('Progress').last);
    expect(find.text('1 question answered'), findsOneWidget);
    expect(find.text('0% right (last answer to each question)'), findsOneWidget);
    expect(find.text('Weak topics'), findsOneWidget);
    expect(find.text('1. Chapter 21'), findsOneWidget);
    expect(find.text('Physics'), findsOneWidget);

    await tapOn(tester, find.text('Practise'));
    expect(find.text('Question 1 of 1'), findsOneWidget);
    expect(find.text('What is 2 + 2?'), findsOneWidget);
  });

  testWidgets('Smart practice starts from the Home card', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    await pumpGuul(tester, server: server);

    await tapOn(tester, find.text('Study'));
    await tapOn(tester, find.text('Physics'));
    await tapOn(tester, find.text('Unit 2: Two-dimensional motion'));
    await tapOn(tester, find.text('1. Chapter 21'));
    await tapOn(tester, find.text('Home'));
    await tapOn(tester, find.text('Smart practice'));
    expect(find.text('Question 1 of 1'), findsOneWidget);
    expect(find.text('What is 2 + 2?'), findsOneWidget);
  });
}
