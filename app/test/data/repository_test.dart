import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/api_client.dart';
import 'package:guul12/data/repository.dart';

import '../support/fake_server.dart';

void main() {
  test('first sync creates an anonymous account and downloads config and catalog', () async {
    final (repo, server, tokens) = await makeRepository();

    expect(await repo.syncAll(), isTrue);

    expect(server.registrations, 1);
    expect(tokens.token, 't1');
    expect((await repo.profile()).studentId, 'ST-7K3Q9');
    expect((await repo.config()).examDate, DateTime(2027, 6, 29));
    expect((await repo.catalog()).subjects, hasLength(3));
  });

  test('offline first launch: onboarding answers are kept and uploaded later', () async {
    final (repo, server, _) = await makeRepository();
    server.online = false;

    await repo.updateProfile({'grade': 12, 'stream': 'natural', 'onboarded': true});
    var profile = await repo.profile();
    expect(profile.grade, 12);
    expect(profile.onboarded, isTrue);
    expect(profile.studentId, isNull);
    expect(await repo.syncAll(), isFalse);

    server.online = true;
    expect(await repo.syncAll(), isTrue);
    expect(server.profile['grade'], 12);
    expect(server.profile['stream'], 'natural');
    profile = await repo.profile();
    expect(profile.studentId, 'ST-7K3Q9');
    expect(profile.grade, 12);
  });

  test('onboarded false is never sent (server only accepts true)', () async {
    final (repo, server, _) = await makeRepository();
    await repo.ensureSession();

    await repo.updateProfile({'grade': 10, 'onboarded': false});
    await repo.uploadProfile();

    expect(server.profile.containsKey('onboarded'), isTrue);
    expect(server.profile['onboarded'], isFalse);
    expect(server.profile['grade'], 10);
  });

  test('chapter is downloaded once and then read from the phone, also offline', () async {
    final (repo, server, _) = await makeRepository();
    await repo.syncAll();

    final first = await repo.chapter(21);
    server.online = false;
    final second = await repo.chapter(21);

    expect(first.questions.single.id, 2101);
    expect(second.summary?.formulas.single.latex, r't = \sqrt{\frac{2h}{g}}');
    expect(server.chapterDownloads[21], 1);
  });

  test('opening a chapter that was never downloaded while offline fails clearly', () async {
    final (repo, server, _) = await makeRepository();
    await repo.syncAll();
    server.online = false;

    expect(
      () => repo.chapter(22),
      throwsA(isA<ApiException>().having((e) => e.isOffline, 'offline', isTrue)),
    );
  });

  test('sync refreshes only saved chapters that changed', () async {
    final (repo, server, _) = await makeRepository();
    await repo.syncAll();
    await repo.chapter(21);

    server.changes.addAll([
      {'id': 11, 'entity': 'question', 'chapter_id': 21},
      {'id': 12, 'entity': 'question', 'chapter_id': 22},
    ]);

    expect(await repo.syncContent(), 1);
    expect(server.chapterDownloads[21], 2);
    expect(server.chapterDownloads[22], isNull);

    // Nothing new: nothing downloaded.
    expect(await repo.syncContent(), 0);
  });

  test('attempts saved offline are uploaded once when back online', () async {
    final (repo, server, _) = await makeRepository();
    await repo.syncAll();
    server.online = false;

    await repo.saveAttempt(
      clientUuid: '0b9a6c8e-1d2f-4c3b-8a7e-5f4d3c2b1a00',
      mode: 'quick10',
      chapterId: 21,
      startedAt: DateTime.utc(2026, 11, 22, 9),
      finishedAt: DateTime.utc(2026, 11, 22, 9, 5),
      answers: const [
        AttemptAnswer(
          questionId: 2101,
          chapterId: 21,
          version: 1,
          response: 'A',
          timeMs: 4000,
          correctLocally: false,
        ),
      ],
    );
    expect(await repo.db.lastAnswerWrong(), {2101});

    server.online = true;
    expect(await repo.uploadAttempts(), 1);
    expect(await repo.uploadAttempts(), 0);
    expect(server.receivedAttempts.single['client_uuid'], '0b9a6c8e-1d2f-4c3b-8a7e-5f4d3c2b1a00');
    expect((server.receivedAttempts.single['answers']! as List).single, {
      'question_id': 2101,
      'version': 1,
      'response': 'A',
      'time_ms': 4000,
    });
  });

  test(
    'a rejected attempt is dropped so it cannot block the queue; a server error keeps it',
    () async {
      final (repo, server, _) = await makeRepository();
      await repo.syncAll();
      Future<void> save(String uuid) => repo.saveAttempt(
        clientUuid: uuid,
        mode: 'quick10',
        chapterId: null,
        startedAt: DateTime.utc(2026),
        finishedAt: DateTime.utc(2026),
        answers: const [
          AttemptAnswer(
            questionId: 1,
            chapterId: 21,
            version: 1,
            response: null,
            timeMs: 1,
            correctLocally: false,
          ),
        ],
      );

      server.attemptStatus = (_) => 503;
      await save('00000000-0000-4000-8000-000000000001');
      await expectLater(repo.uploadAttempts(), throwsA(isA<ApiException>()));
      expect(await repo.db.pendingAttempts(), hasLength(1));

      server.attemptStatus = (_) => 422;
      expect(await repo.uploadAttempts(), 1);
      expect(await repo.db.pendingAttempts(), isEmpty);
    },
  );

  test('tryUploadAttempts reports whether anything is still waiting, and never throws', () async {
    final (repo, server, _) = await makeRepository();
    server.online = false;
    await repo.saveAttempt(
      clientUuid: '1c9a6c8e-1d2f-4c3b-8a7e-5f4d3c2b1a01',
      mode: 'chapter',
      chapterId: 21,
      startedAt: DateTime.utc(2026, 11, 22, 9),
      finishedAt: DateTime.utc(2026, 11, 22, 9, 1),
      answers: const [
        AttemptAnswer(
          questionId: 2101,
          chapterId: 21,
          version: 1,
          response: 'B',
          timeMs: 2000,
          correctLocally: true,
        ),
      ],
    );
    expect(await repo.tryUploadAttempts(), isFalse);
    expect(server.receivedAttempts, isEmpty);

    server.online = true;
    expect(await repo.tryUploadAttempts(), isTrue);
    expect(server.receivedAttempts, hasLength(1));
    expect(server.registrations, 1);
  });

  test('a rejected token leads to a new anonymous session automatically', () async {
    final (repo, server, tokens) = await makeRepository();
    await repo.syncAll();
    tokens.token = 'expired';

    await repo.refreshConfig();

    expect(server.registrations, 2);
    expect(tokens.token, 't2');
  });

  test('bookmarks work offline and sync later', () async {
    final (repo, server, _) = await makeRepository();
    await repo.syncAll();
    server.online = false;

    await repo.toggleBookmark(2101, on: true);
    expect(await repo.bookmarks(), {2101});

    server.online = true;
    await repo.syncBookmarks();
    expect(server.bookmarks, {2101});

    await repo.toggleBookmark(2101, on: false);
    await repo.syncBookmarks();
    expect(server.bookmarks, isEmpty);
    expect(await repo.bookmarks(), isEmpty);
  });

  test('recent chapters: newest first, no repeats, at most 3', () async {
    final (repo, _, _) = await makeRepository();
    expect(await repo.recentChapters(), isEmpty);
    for (final id in [21, 22, 21, 23, 24]) {
      await repo.rememberChapter(id);
    }
    expect(await repo.recentChapters(), [24, 23, 21]);
  });

  test('reports wait offline, replace each other per question, and are sent later', () async {
    final (repo, server, _) = await makeRepository();
    await repo.syncAll();
    server.online = false;

    await repo.reportQuestion(2101, reason: 'unclear');
    await repo.reportQuestion(2101, reason: 'wrong_answer', note: '  C is right  ');
    await repo.reportQuestion(2201, reason: 'typo', note: '   ');
    expect(server.receivedReports, isEmpty);

    server.online = true;
    expect(await repo.uploadReports(), 2);
    expect(server.receivedReports, [
      {'question_id': 2101, 'reason': 'wrong_answer', 'note': 'C is right'},
      {'question_id': 2201, 'reason': 'typo'},
    ]);
    expect(await repo.uploadReports(), 0, reason: 'nothing left to send');
  });

  test('refused reports are dropped; busy server keeps them', () async {
    final (repo, server, _) = await makeRepository();
    await repo.syncAll();
    server.online = false;
    await repo.reportQuestion(2101, reason: 'typo');
    server
      ..online = true
      ..reportStatus = 503;
    expect(await repo.syncAll(), isFalse);

    server
      ..reportStatus = 429
      ..reportCode = 'report_limit';
    expect(await repo.uploadReports(), 0);
    server
      ..reportStatus = 201
      ..reportCode = null;
    expect(await repo.uploadReports(), 0, reason: 'dropped after the daily limit answer');
    expect(server.receivedReports, isEmpty);
  });

  test('unknown profile fields are refused', () async {
    final (repo, _, _) = await makeRepository();

    expect(() => repo.updateProfile({'role': 'super_admin'}), throwsArgumentError);
  });
}
