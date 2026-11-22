import 'dart:math';

import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/models.dart';
import 'package:guul12/domain/mastery.dart';
import 'package:guul12/domain/practice.dart';

Question q(int id, int chapterId) => Question(
  id: id,
  chapterId: chapterId,
  passageId: null,
  type: QuestionType.singleChoice,
  stem: 'Q$id',
  mediaUrl: null,
  difficulty: 2,
  isBeta: false,
  answerKey: const {'correct': 'A'},
  explanation: 'E$id',
  version: 1,
  options: const [
    QuestionOption(key: 'A', side: null, body: '1', mediaUrl: null),
    QuestionOption(key: 'B', side: null, body: '2', mediaUrl: null),
  ],
);

ChapterMastery m(int answered, int correct, {int total = 20, int id = 1}) =>
    ChapterMastery(chapterId: id, totalQuestions: total, answered: answered, correct: correct);

void main() {
  group('ChapterMastery', () {
    test('nothing answered is "not started" with score 0', () {
      final x = m(0, 0);
      expect(x.level, MasteryLevel.notStarted);
      expect(x.score, 0);
      expect(x.accuracy, 0);
      expect(m(0, 0, total: 0).coverage, 0);
    });

    test('few lucky answers are not strong and score is weighted down', () {
      final x = m(2, 2);
      expect(x.level, MasteryLevel.learning);
      expect(x.score, 20); // 100% right, but only 2 of 10 answers for full weight
    });

    test('good needs 5 answers and 60%', () {
      expect(m(5, 3).level, MasteryLevel.good);
      expect(m(5, 2).level, MasteryLevel.learning);
      expect(m(4, 4).level, MasteryLevel.learning);
    });

    test('strong needs 85%, 10 answers and half the chapter', () {
      expect(m(10, 9).level, MasteryLevel.strong); // 10 of 20 = 50% coverage
      expect(m(10, 9, total: 21).level, MasteryLevel.good); // coverage < 50%
      expect(m(10, 8).level, MasteryLevel.good); // 80%
      expect(m(10, 9).score, 90);
    });

    test('coverage never goes above 1', () {
      expect(m(30, 30, total: 20).coverage, 1);
    });
  });

  group('chapterMastery', () {
    test('joins saved chapters and answered chapters', () {
      final list = chapterMastery(
        stats: const [
          ChapterStat(chapterId: 1, answered: 4, correct: 1),
          ChapterStat(chapterId: 3, answered: 2, correct: 2),
        ],
        questionsPerChapter: const {1: 20, 2: 15},
      );
      final byId = {for (final x in list) x.chapterId: x};
      expect(byId.keys.toSet(), {1, 2, 3});
      expect(byId[1]!.totalQuestions, 20);
      expect(byId[1]!.answered, 4);
      expect(byId[2]!.level, MasteryLevel.notStarted);
      expect(byId[3]!.totalQuestions, 0); // answered earlier, chapter not saved now
    });
  });

  group('weakTopics', () {
    test('weakest first, skips strong and not started, respects max', () {
      final list = [
        m(10, 9, id: 1), // strong
        m(0, 0, id: 2), // not started
        m(10, 5, id: 3), // 50%
        m(4, 1, id: 4), // 25%
        m(8, 2, id: 5), // 25%, more answers
        m(6, 5, id: 6), // 83% good
      ];
      expect(weakTopics(list).map((x) => x.chapterId), [5, 4, 3]);
      expect(weakTopics(list, max: 10).map((x) => x.chapterId), [5, 4, 3, 6]);
      expect(weakTopics(const []), isEmpty);
    });
  });

  group('smart practice', () {
    // 4 chapters x 10 questions: chapter c has ids c*100+1 .. c*100+10.
    final pool = [
      for (var c = 1; c <= 4; c++)
        for (var i = 1; i <= 10; i++) q(c * 100 + i, c),
    ];

    test('due first (max 3), then weak chapters (max 4 each), wrong before new', () {
      final set = PracticeBuilder(random: Random(7)).build(
        mode: PracticeMode.smart,
        pool: pool,
        due: {401, 402, 403, 404, 405},
        wrong: {101, 102},
        answered: {101, 102, 103, 104, 201, 401, 402, 403, 404, 405},
        weakChapters: [1, 2],
      );
      final ids = set.map((x) => x.id).toList();
      expect(ids, hasLength(PracticeBuilder.smartCount));
      expect(ids.toSet(), hasLength(ids.length));
      expect(ids.take(3).every((id) => id ~/ 100 == 4), isTrue);
      final chapter1 = ids.skip(3).take(4).toList();
      expect(chapter1.every((id) => id ~/ 100 == 1), isTrue);
      expect(chapter1.take(2).toSet(), {101, 102}); // wrong answers come first
      expect(chapter1.skip(2).every((id) => id > 104), isTrue); // then new, not right ones
      // The last 3 come from chapter 2, new questions only (201 was answered right).
      final chapter2 = ids.skip(7).toList();
      expect(chapter2, hasLength(3));
      expect(chapter2.every((id) => id ~/ 100 == 2 && id != 201), isTrue);
    });

    test('without history it fills from chapters not started', () {
      final set = PracticeBuilder(random: Random(1)).build(mode: PracticeMode.smart, pool: pool);
      expect(set, hasLength(10));
      expect(set.map((x) => x.id).toSet(), hasLength(10));
    });

    test('everything answered right still fills the set', () {
      final all = {for (final x in pool) x.id};
      final set = PracticeBuilder(random: Random(2))
          .build(mode: PracticeMode.smart, pool: pool, answered: all);
      expect(set, hasLength(10));
      expect(set.map((x) => x.id).toSet(), hasLength(10));
    });

    test('small pool returns all questions once; empty pool returns nothing', () {
      final small = pool.take(4).toList();
      final set = PracticeBuilder(random: Random(3))
          .build(mode: PracticeMode.smart, pool: small, due: {101}, weakChapters: [1]);
      expect(set.map((x) => x.id).toSet(), {101, 102, 103, 104});
      expect(set, hasLength(4));
      expect(PracticeBuilder().build(mode: PracticeMode.smart, pool: const []), isEmpty);
    });
  });
}
