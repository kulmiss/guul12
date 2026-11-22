import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/app/practice_set.dart';
import 'package:guul12/data/repository.dart';
import 'package:guul12/domain/practice.dart';

import '../support/fake_server.dart';

AttemptAnswer answer(int id, {required bool correct}) => AttemptAnswer(
  questionId: id,
  chapterId: id ~/ 100,
  version: 1,
  response: correct ? 'B' : 'A',
  timeMs: 9000,
  correctLocally: correct,
);

void main() {
  final day0 = DateTime.utc(2026, 11, 22, 10);
  var n = 0;
  String uuid() => '00000000-0000-4000-8000-${(++n).toString().padLeft(12, '0')}';

  Future<void> practise(Repository repo, List<AttemptAnswer> answers, DateTime at) =>
      repo.saveAttempt(
        clientUuid: uuid(),
        mode: 'quick10',
        chapterId: null,
        startedAt: at,
        finishedAt: at,
        answers: answers,
      );

  test('a wrong answer comes back for review after 1, 3 and 7 days', () async {
    final (repo, _, _) = await makeRepository();
    await repo.chapter(21);
    await practise(repo, [answer(2101, correct: false)], day0);

    Future<List<int>> reviewSet(DateTime now) async => (await buildPracticeSet(
      repo,
      const PracticeRequest(mode: PracticeMode.review),
      now: now,
    )).questions.map((q) => q.id).toList();

    expect(await reviewSet(day0.add(const Duration(hours: 23))), isEmpty);
    final day1 = day0.add(const Duration(days: 1));
    expect(await reviewSet(day1), [2101]);

    await practise(repo, [answer(2101, correct: true)], day1);
    expect(await reviewSet(day1.add(const Duration(days: 2))), isEmpty);
    final day4 = day1.add(const Duration(days: 3));
    expect(await reviewSet(day4), [2101]);
    await practise(repo, [answer(2101, correct: true)], day4);
    final day11 = day4.add(const Duration(days: 7));
    expect(await reviewSet(day11), [2101]);
    await practise(repo, [answer(2101, correct: true)], day11);

    expect(await repo.db.srsItems(), isEmpty, reason: 'learned');
    expect(await reviewSet(day11.add(const Duration(days: 30))), isEmpty);
  });

  test('exam answers (also skipped ones) go on the review list', () async {
    final (repo, _, _) = await makeRepository();
    await practise(repo, [
      answer(901, correct: true),
      const AttemptAnswer(
        questionId: 902,
        chapterId: 21,
        version: 1,
        response: null,
        timeMs: 0,
        correctLocally: false,
      ),
    ], day0);
    expect((await repo.db.srsItems()).map((s) => [s.questionId, s.step]), [
      [902, 0],
    ]);
  });

  test('mistakes restored from the server are due for review at once', () async {
    final (repo, server, _) = await makeRepository();
    server.history.add({
      'id': 1,
      'question_id': 2101,
      'chapter_id': 21,
      'correct': false,
      'answered_at': '2026-11-01T08:00:00Z',
    });
    await repo.ensureSession();
    await repo.restoreHistory();

    expect(await repo.db.dueQuestionIds(day0), {2101});
  });
}
