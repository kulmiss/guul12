import 'dart:math';

import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/api_client.dart';
import 'package:guul12/data/repository.dart';
import 'package:guul12/domain/exam.dart';

import '../support/fake_server.dart';

void main() {
  final start = DateTime.utc(2026, 11, 22, 10);

  test('mocks list works offline; a mock downloads once and again only when it changed', () async {
    final (repo, server, _) = await makeRepository();
    await repo.ensureSession();

    final list = await repo.refreshMocks();
    expect(list.map((m) => [m.id, m.isPremium, m.locked]), [
      [1, false, false],
      [2, true, true],
    ]);

    final paper = await repo.mockPaper(list.first);
    expect(
      [paper.kind, paper.mockId, paper.minutes, paper.questions.length],
      [ExamKind.fixed, 1, 6, 4],
    );
    await repo.mockPaper(list.first);
    expect(server.mockDownloads, 1, reason: 'same content hash: read from the phone');

    server.online = false;
    expect((await repo.mocks()).map((m) => m.id), [1, 2]);
    expect((await repo.mockPaper(list.first)).questions.first.id, 901);

    server
      ..online = true
      ..mockHash = 'h1b'
      ..mockQuestions = [FakeServer.examQuestion(999, 'D')];
    final changed = (await repo.refreshMocks()).first;
    expect((await repo.mockPaper(changed)).questions.map((q) => q.id), [999]);
    expect(server.mockDownloads, 2);

    await expectLater(
      repo.mockPaper(list.last),
      throwsA(isA<ApiException>().having((e) => e.code, 'code', 'premium_required')),
    );
  });

  test('a finished exam is kept, uploaded with its mock, and gets "better than" later', () async {
    final (repo, server, _) = await makeRepository();
    await repo.ensureSession();
    final header = (await repo.refreshMocks()).first;
    final session = ExamSession(
      paper: await repo.mockPaper(header),
      clientUuid: '8d3f6a2e-1b4c-4f7a-9e2d-5c6b7a8f9e01',
      startedAt: start.subtract(const Duration(minutes: 4)),
    );
    session
      ..choose('A', start)
      ..goTo(1, start)
      ..choose('B', start);
    await repo.saveExamProgress(session);
    expect((await repo.examInProgress())?.answers, {0: 'A', 1: 'B'});

    final record = await repo.submitExam(session);

    expect([record.total, record.correct, record.uploaded], [4, 1, true]);
    expect(record.betterThanPercent, isNull);
    expect(record.students, 0);
    expect(await repo.examInProgress(), isNull);
    final sent = server.receivedAttempts.single;
    expect([sent['mode'], sent['mock_id'], sent['chapter_id']], ['exam_fixed', 1, null]);
    expect((sent['answers']! as List).map((a) => (a as Map)['response']), ['A', 'B', null, null]);
    // Wrong answers feed "My mistakes" and the chapter numbers.
    expect(await repo.db.lastAnswerWrong(), {902, 903, 904});
    expect((await repo.examResults()).single.clientUuid, record.clientUuid);

    server
      ..betterThanPercent = 64
      ..students = 31;
    final refreshed = await repo.refreshExamResult(record.clientUuid);
    expect([refreshed?.betterThanPercent, refreshed?.students], [64, 31]);
  });

  test('an exam finished offline is kept and uploaded later with its details', () async {
    final (repo, server, _) = await makeRepository();
    await repo.ensureSession();
    final header = (await repo.refreshMocks()).first;
    final paper = await repo.mockPaper(header);
    server
      ..online = false
      ..betterThanPercent = 40
      ..students = 30;

    final record = await repo.submitExam(
      ExamSession(
        paper: paper,
        clientUuid: 'c1d2e3f4-0000-4000-8000-000000000001',
        startedAt: start,
      ),
    );
    expect(record.uploaded, isFalse);
    expect(server.receivedAttempts, isEmpty);

    server.online = true;
    expect(await repo.syncAll(), isTrue);
    final later = await repo.examResult(record.clientUuid);
    expect([later?.uploaded, later?.betterThanPercent, later?.students], [true, 40, 30]);
  });

  test('random mock comes from the server; the daily limit is a clear error', () async {
    final (repo, server, _) = await makeRepository();
    await repo.ensureSession();

    final paper = await repo.randomPaper(subjectId: 1, title: 'Random mock');
    expect(
      [paper.kind, paper.mockId, paper.minutes, paper.questions.length],
      [ExamKind.random, null, 3, 2],
    );

    server.randomStatus = 429;
    await expectLater(
      repo.randomPaper(subjectId: 1, title: 'Random mock'),
      throwsA(isA<ApiException>().having((e) => e.code, 'code', 'daily_limit')),
    );
  });

  test('my mistakes and my weak topics are built on the phone from saved chapters', () async {
    final (repo, server, _) = await makeRepository();
    await repo.syncAll();
    // Nothing studied yet: no exam to build.
    expect(
      await repo.personalPaper(kind: ExamKind.mistakes, subjectId: 1, title: 'Mistakes'),
      isNull,
    );

    await repo.chapter(21);
    await repo.saveAttempt(
      clientUuid: '0f0e0d0c-0000-4000-8000-000000000002',
      mode: 'quick10',
      chapterId: 21,
      startedAt: start,
      finishedAt: start,
      answers: const [
        AttemptAnswer(
          questionId: 2101,
          chapterId: 21,
          version: 1,
          response: 'A',
          timeMs: 9000,
          correctLocally: false,
        ),
      ],
    );

    final mistakes = await repo.personalPaper(
      kind: ExamKind.mistakes,
      subjectId: 1,
      title: 'Mistakes',
      random: Random(1),
    );
    expect(mistakes?.questions.map((q) => q.id), [2101]);
    expect([mistakes?.kind, mistakes?.minutes], [ExamKind.mistakes, 2]);

    final weak = await repo.personalPaper(
      kind: ExamKind.weak,
      subjectId: 1,
      title: 'Weak',
      random: Random(1),
    );
    expect(weak?.questions.map((q) => q.id), [2101]);
    // Another subject has nothing saved.
    expect(await repo.personalPaper(kind: ExamKind.weak, subjectId: 2, title: 'Weak'), isNull);
    expect(server.calls.where((c) => c.contains('exams')), isEmpty, reason: 'built offline');
  });

  test('answer history comes back once per account, without doubles, page by page', () async {
    final (repo, server, _) = await makeRepository();
    server
      ..historyPage = 2
      ..history.addAll([
        {
          'id': 1,
          'question_id': 2101,
          'chapter_id': 21,
          'correct': false,
          'answered_at': '2026-11-01T08:00:00Z',
        },
        {
          'id': 2,
          'question_id': 2201,
          'chapter_id': 22,
          'correct': true,
          'answered_at': '2026-11-01T08:00:00Z',
        },
        {
          'id': 3,
          'question_id': 2101,
          'chapter_id': 21,
          'correct': true,
          'answered_at': '2026-11-02T08:00:00Z',
        },
      ]);
    // This phone already has the 2 November answer (same second, milliseconds differ).
    await repo.db.restoreAnswers([
      (questionId: 2101, chapterId: 21, correct: true, at: DateTime.utc(2026, 11, 2, 8, 0, 0, 400)),
    ]);

    await repo.ensureSession();
    expect(await repo.restoreHistory(), 2);
    expect(server.historyRequests, 2);
    expect(await repo.db.lastAnswerWrong(), isEmpty, reason: 'last answer to 2101 was right');
    expect((await repo.db.chapterStats()).map((s) => [s.chapterId, s.answered, s.correct]), [
      [21, 1, 1],
      [22, 1, 1],
    ]);

    expect(await repo.restoreHistory(), 0);
    expect(server.historyRequests, 2, reason: 'only once');

    // Google sign-in may join an older account: fetch its history on the next sync.
    await repo.signInWithGoogle('id-token');
    server.history.add({
      'id': 4,
      'question_id': 2201,
      'chapter_id': 22,
      'correct': false,
      'answered_at': '2026-11-03T08:00:00Z',
    });
    expect(await repo.restoreHistory(), 1);
    expect(await repo.db.lastAnswerWrong(), {2201});
  });
}
