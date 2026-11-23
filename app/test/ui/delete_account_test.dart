import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/repository.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

Future<void> openDelete(WidgetTester tester) async {
  await tapOn(tester, find.text('Me'));
  await tapOn(tester, find.text('Delete my account'));
  expect(find.text('Delete your account?'), findsOneWidget);
}

void main() {
  testWidgets('cancel changes nothing', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    await pumpGuul(tester, server: server);
    await openDelete(tester);
    await tapOn(tester, find.text('Cancel'));
    expect(server.accountsDeleted, 0);
    expect(find.text('ST-7K3Q9'), findsOneWidget);
  });

  testWidgets('delete: gone on the server, phone data wiped, back to Welcome', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    final (repo, _) = await pumpGuul(
      tester,
      server: server,
      before: (repo) async {
        await repo.syncAll();
        await repo.chapter(21);
        await repo.saveAttempt(
          clientUuid: '00000000-0000-4000-8000-0000000000dd',
          mode: 'quick10',
          chapterId: 21,
          startedAt: testNow,
          finishedAt: testNow,
          answers: const [
            AttemptAnswer(
              questionId: 2101,
              chapterId: 21,
              version: 1,
              response: 'A',
              timeMs: 5000,
              correctLocally: false,
            ),
          ],
        );
      },
    );
    await openDelete(tester);
    await tapOn(tester, find.widgetWithText(FilledButton, 'Delete'));

    expect(server.accountsDeleted, 1);
    expect(await repo.db.answerEvents(), isEmpty);
    expect(await repo.db.chapters(), isEmpty);
    expect(await repo.db.readJson(Keys.serverProfile), isNull);
    expect(await repo.tokens.readToken(), isNull);
    expect(
      find.byKey(const ValueKey('walkthrough-next')),
      findsOneWidget,
      reason: 'Welcome slides',
    );
  });

  testWidgets('offline: a clear message and nothing is wiped', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    final (repo, _) = await pumpGuul(tester, server: server);
    server.online = false;
    await openDelete(tester);
    await tapOn(tester, find.widgetWithText(FilledButton, 'Delete'));
    expect(find.text('Deleting your account needs the internet.'), findsOneWidget);
    expect(server.accountsDeleted, 0);
    expect(await repo.db.readJson(Keys.serverProfile), isNotNull);
    expect(find.text('ST-7K3Q9'), findsOneWidget);
  });
}
