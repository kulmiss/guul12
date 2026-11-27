import 'dart:convert';
import 'dart:math';

import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/models.dart';
import 'package:guul12/domain/exam.dart';
import 'package:guul12/domain/exam_builder.dart';

Question eq(
  int id, {
  String key = 'B',
  int chapterId = 1,
  int? passageId,
  QuestionType type = QuestionType.singleChoice,
}) => Question(
  id: id,
  chapterId: chapterId,
  passageId: passageId,
  type: type,
  stem: 'Q$id',
  mediaUrl: null,
  difficulty: 2,
  isBeta: false,
  answerKey: type == QuestionType.numeric ? const {'value': 3, 'tolerance': 0} : {'correct': key},
  explanation: 'E$id',
  version: 1,
  options: const [
    QuestionOption(key: 'A', side: null, body: '3', mediaUrl: null),
    QuestionOption(key: 'B', side: null, body: '4', mediaUrl: null),
    QuestionOption(key: 'C', side: null, body: '5', mediaUrl: null),
    QuestionOption(key: 'D', side: null, body: '6', mediaUrl: null),
  ],
);

ExamPaper paper(List<Question> questions, {int minutes = 10}) => ExamPaper(
  kind: ExamKind.fixed,
  mockId: 7,
  subjectId: 3,
  title: 'Physics mock exam 1',
  minutes: minutes,
  questions: questions,
  passages: const {9: Passage(id: 9, title: 'Read', body: 'Text')},
);

void main() {
  final start = DateTime.utc(2026, 11, 22, 10);

  group('ExamSession', () {
    test('the clock runs from the start and stops at the deadline', () {
      final s = ExamSession(paper: paper([eq(1)]), clientUuid: 'u', startedAt: start);
      expect(s.remaining(start), const Duration(minutes: 10));
      expect(
        s.remaining(start.add(const Duration(minutes: 9, seconds: 30))),
        const Duration(seconds: 30),
      );
      expect(s.isTimeUp(start.add(const Duration(minutes: 9, seconds: 59))), isFalse);
      expect(s.isTimeUp(start.add(const Duration(minutes: 10))), isTrue);
      expect(s.remaining(start.add(const Duration(hours: 2))), Duration.zero);
    });

    test('answers can change and be cleared until time is up; flags toggle', () {
      final s = ExamSession(paper: paper([eq(1), eq(2)]), clientUuid: 'u', startedAt: start);
      s.choose('A', start);
      s.choose('B', start);
      expect(s.currentAnswer, 'B');
      s.toggleFlag();
      expect(s.currentFlagged, isTrue);
      s.goTo(1, start);
      expect([s.index, s.answeredCount, s.unansweredCount], [1, 1, 1]);
      s.goTo(0, start);
      s.clear(start);
      expect(s.answeredCount, 0);
      s.toggleFlag();
      expect(s.flagged, isEmpty);
      final late = start.add(const Duration(minutes: 11));
      s.choose('C', late);
      expect(s.currentAnswer, isNull, reason: 'no answers after the deadline');
      s.goTo(5, start);
      s.goTo(-1, start);
      expect(s.index, 0, reason: 'out of range is ignored');
    });

    test('finish marks every answer; skipped is wrong; time is capped at the deadline', () {
      final s = ExamSession(
        paper: paper([eq(1, chapterId: 4), eq(2, key: 'C', chapterId: 5), eq(3, chapterId: 4)]),
        clientUuid: 'u',
        startedAt: start,
      );
      s.choose('B', start.add(const Duration(seconds: 30)));
      s.goTo(1, start.add(const Duration(seconds: 40)));
      s.choose('A', start.add(const Duration(seconds: 50)));
      s.goTo(2, start.add(const Duration(minutes: 1)));
      final outcome = s.finish(start.add(const Duration(hours: 1)));

      expect([outcome.total, outcome.correct], [3, 1]);
      expect(outcome.items.map((i) => i.response), ['B', 'A', null]);
      expect(outcome.items.map((i) => i.correct), [true, false, false]);
      expect(outcome.items.map((i) => i.timeMs), [40000, 20000, 9 * 60000]);
      expect(outcome.finishedAt, start.add(const Duration(minutes: 10)));
      expect(outcome.chapters.map((c) => [c.chapterId, c.total, c.correct]), [
        [4, 2, 1],
        [5, 1, 0],
      ]);
    });

    group('the exam clock cannot be turned back (bug hunt)', () {
      const sec = 1000;
      test('moving the phone clock back does not give more time', () {
        final s = ExamSession(paper: paper([eq(1)]), clientUuid: 'u', startedAt: start);
        // Phone time and the time since the phone started (cannot be changed by the student).
        expect(s.observe(start, 1000 * sec), start);
        final at5 = s.observe(start.add(const Duration(minutes: 5)), 1300 * sec);
        expect(s.remaining(at5), const Duration(minutes: 5));
        // The student sets the clock back 1 hour; 1 more real minute passes.
        final back = s.observe(start.subtract(const Duration(minutes: 55)), 1360 * sec);
        expect(s.remaining(back), const Duration(minutes: 4));
        expect(
          s.isTimeUp(s.observe(start.subtract(const Duration(minutes: 51)), 1600 * sec)),
          isTrue,
        );
      });

      test('moving the clock forward (or a time zone change) does not steal time', () {
        final s = ExamSession(paper: paper([eq(1)]), clientUuid: 'u', startedAt: start);
        s.observe(start, 50 * sec);
        final now = s.observe(start.add(const Duration(hours: 3)), 110 * sec);
        expect(s.remaining(now), const Duration(minutes: 9));
      });

      test('closing the app keeps the clock (saved and loaded)', () {
        final s = ExamSession(paper: paper([eq(1)]), clientUuid: 'u', startedAt: start);
        s.observe(start, 10 * sec);
        s.observe(start.add(const Duration(minutes: 2)), 130 * sec);
        final back = ExamSession.fromJson(
          jsonDecode(jsonEncode(s.toJson())) as Map<String, Object?>,
        );
        // Closed for 3 real minutes, and the clock was set back meanwhile.
        final now = back.observe(start.subtract(const Duration(hours: 1)), 310 * sec);
        expect(back.remaining(now), const Duration(minutes: 5));
      });

      test('after a restart of the phone the clock only moves forward', () {
        final s = ExamSession(paper: paper([eq(1)]), clientUuid: 'u', startedAt: start);
        s.observe(start, 900 * sec);
        s.observe(start.add(const Duration(minutes: 4)), 1140 * sec);
        // Restarted: the counter began again; the phone clock is 2 minutes later.
        final now = s.observe(start.add(const Duration(minutes: 6)), 5 * sec);
        expect(s.remaining(now), const Duration(minutes: 4));
        // Restarted and the clock set back: no time is given back.
        final again = s.observe(start.add(const Duration(minutes: 1)), 2 * sec);
        expect(s.remaining(again), const Duration(minutes: 4));
      });

      test(
        'without the counter (tests, other platforms) the phone clock is used, never backwards',
        () {
          final s = ExamSession(paper: paper([eq(1)]), clientUuid: 'u', startedAt: start);
          s.observe(start.add(const Duration(minutes: 3)), null);
          final now = s.observe(start.add(const Duration(minutes: 1)), null);
          expect(s.remaining(now), const Duration(minutes: 7));
        },
      );
    });

    test('saves and loads to the same state (resume after the app was closed)', () {
      final s = ExamSession(
        paper: paper([eq(1, passageId: 9), eq(2, key: 'D')]),
        clientUuid: 'abc',
        startedAt: start,
      );
      s.choose('A', start);
      s.toggleFlag();
      s.goTo(1, start.add(const Duration(seconds: 12)));

      final back = ExamSession.fromJson(jsonDecode(jsonEncode(s.toJson())) as Map<String, Object?>);

      expect(back.clientUuid, 'abc');
      expect(back.startedAt, start);
      expect(back.index, 1);
      expect(back.answers, {0: 'A'});
      expect(back.flagged, {0});
      expect(back.timeMs, {0: 12000});
      expect(back.paper.mockId, 7);
      expect(back.paper.kind, ExamKind.fixed);
      expect(back.paper.questions.map((q) => q.answerKey), [
        {'correct': 'B'},
        {'correct': 'D'},
      ]);
      expect(back.paper.questions.first.passageId, 9);
      expect(back.paper.passages[9]?.body, 'Text');
      final finished = back.finish(start.add(const Duration(seconds: 20)));
      expect(finished.items.last.timeMs, 8000);
    });
  });

  group('estimatedScoreRange', () {
    test('is centred on the score and narrows with more questions', () {
      final small = estimatedScoreRange(correct: 6, total: 10, paperMax: 100);
      final big = estimatedScoreRange(correct: 30, total: 50, paperMax: 100);
      expect(small.$1, lessThan(60));
      expect(small.$2, greaterThan(60));
      expect(big.$2 - big.$1, lessThan(small.$2 - small.$1));
      expect(big, (46, 72));
    });

    test('never leaves 0..max, even for all right or all wrong', () {
      final all = estimatedScoreRange(correct: 50, total: 50, paperMax: 100);
      final none = estimatedScoreRange(correct: 0, total: 50, paperMax: 100);
      expect(all.$2, 100);
      expect(all.$1, inInclusiveRange(90, 99));
      expect(none.$1, 0);
      expect(none.$2, inInclusiveRange(1, 10));
      expect(estimatedScoreRange(correct: 0, total: 0, paperMax: 600), (0, 600));
    });
  });

  group('exam builders', () {
    test('the key never shows a pattern and passages stay together', () {
      final letters = ['A', 'B', 'C', 'D'];
      final qs = [
        for (var i = 0; i < 24; i++) eq(i + 1, key: letters[i % 4]),
        eq(30, key: 'A', passageId: 9),
        eq(31, key: 'B', passageId: 9),
        eq(32, key: 'C', passageId: 9),
      ];
      expect(keyHasPattern(qs), isTrue, reason: 'the input is A, B, C, D…');
      for (var seed = 0; seed < 20; seed++) {
        final out = examOrder(qs, Random(seed));
        expect(out.toSet(), qs.toSet());
        expect(keyHasPattern(out), isFalse);
        final i = out.indexWhere((q) => q.passageId == 9);
        expect(out.sublist(i, i + 3).map((q) => q.id), [30, 31, 32]);
      }
    });

    test('keyHasPattern finds four of a kind and five in A–D order', () {
      List<Question> keys(String s) => [for (final (i, c) in s.split('').indexed) eq(i, key: c)];
      expect(keyHasPattern(keys('ACCCCB')), isTrue);
      expect(keyHasPattern(keys('ACCCB')), isFalse);
      expect(keyHasPattern(keys('CDABC')), isTrue);
      expect(keyHasPattern(keys('CDABD')), isFalse);
    });

    test('my mistakes takes only wrong choice questions', () {
      final pool = [
        eq(1),
        eq(2),
        eq(3, type: QuestionType.numeric),
        eq(4, type: QuestionType.trueFalse, key: 'TRUE'),
      ];
      final out = mistakesExamQuestions(pool: pool, lastWrong: {2, 3, 4}, random: Random(1));
      expect(out.map((q) => q.id).toSet(), {2, 4});
      expect(
        mistakesExamQuestions(
          pool: [for (var i = 1; i <= 50; i++) eq(i, key: 'ABCD'[i % 4])],
          lastWrong: {for (var i = 1; i <= 50; i++) i},
          random: Random(1),
        ),
        hasLength(personalExamSize),
      );
    });

    test('my weak topics fills from the weakest chapters, wrong and new first', () {
      final pool = [
        for (var i = 1; i <= 20; i++) eq(i, chapterId: 1, key: 'ABCD'[i % 4]),
        for (var i = 21; i <= 40; i++) eq(i, chapterId: 2, key: 'ABCD'[i % 4]),
        for (var i = 41; i <= 60; i++) eq(i, chapterId: 3, key: 'ABCD'[i % 4]),
      ];
      final stats = [
        const ChapterStat(chapterId: 1, answered: 10, correct: 9),
        const ChapterStat(chapterId: 2, answered: 10, correct: 2),
        const ChapterStat(chapterId: 3, answered: 0, correct: 0),
      ];
      final out = weakExamQuestions(
        pool: pool,
        stats: stats,
        lastWrong: {21, 22},
        answered: {for (var i = 21; i <= 30; i++) i},
        random: Random(3),
        size: 12,
      );
      expect(out, hasLength(12));
      // Weakest chapter (2) gets its full share first and the most questions overall.
      final per = <int, int>{};
      for (final q in out) {
        per[q.chapterId] = (per[q.chapterId] ?? 0) + 1;
      }
      expect(per.keys.toSet(), {1, 2}, reason: 'chapter 3 was never practised');
      expect(per[2], 8);
      expect(per[1], 4);
      // Its two wrong questions are in; then new ones before already-right ones.
      expect(out.map((q) => q.id), containsAll([21, 22]));
      final fromTwo = out.where((q) => q.chapterId == 2).map((q) => q.id).toSet();
      expect(fromTwo.difference({21, 22}).every((id) => id > 30), isTrue);
    });
  });
}
