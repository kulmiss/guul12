import 'dart:math';

import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/models.dart';
import 'package:guul12/domain/practice.dart';
import 'package:guul12/domain/session.dart';

Question q(
  int id, {
  QuestionType type = QuestionType.singleChoice,
  Map<String, Object?>? key,
  List<QuestionOption>? options,
}) => Question(
  id: id,
  chapterId: 1,
  passageId: null,
  type: type,
  stem: 'Q$id',
  mediaUrl: null,
  difficulty: 2,
  isBeta: false,
  answerKey: key ?? const {'correct': 'B'},
  explanation: 'E$id',
  version: 1,
  options:
      options ??
      const [
        QuestionOption(key: 'A', side: null, body: '3', mediaUrl: null),
        QuestionOption(key: 'B', side: null, body: '4', mediaUrl: null),
      ],
);

void main() {
  final pool = [for (var i = 1; i <= 40; i++) q(i)];

  group('PracticeBuilder', () {
    test('quick 10 gives 10 different questions', () {
      final set = PracticeBuilder(random: Random(1)).build(mode: PracticeMode.quick10, pool: pool);
      expect(set, hasLength(10));
      expect(set.map((e) => e.id).toSet(), hasLength(10));
    });

    test('quick 10 brings back wrong answers but at most half', () {
      final wrong = {1, 2, 3, 4, 5, 6, 7, 8};
      final set = PracticeBuilder(random: Random(2)).build(
        mode: PracticeMode.quick10,
        pool: pool,
        wrong: wrong,
        answered: {for (var i = 1; i <= 20; i++) i},
      );
      final wrongCount = set.where((e) => wrong.contains(e.id)).length;
      expect(wrongCount, 5);
      // The other five are questions never answered.
      expect(set.where((e) => e.id > 20), hasLength(5));
    });

    test('small pool returns everything without repeats', () {
      final set = PracticeBuilder(random: Random(3))
          .build(mode: PracticeMode.quick10, pool: pool.take(4).toList());
      expect(set.map((e) => e.id).toSet(), {1, 2, 3, 4});
    });

    test('duplicated questions in the pool appear once', () {
      final set = PracticeBuilder(random: Random(4))
          .build(mode: PracticeMode.mixed, pool: [q(1), q(1), q(2)]);
      expect(set.map((e) => e.id).toList()..sort(), [1, 2]);
    });

    test('only my wrong answers', () {
      final set = PracticeBuilder(random: Random(5))
          .build(mode: PracticeMode.wrongOnly, pool: pool, wrong: {7, 9});
      expect(set.map((e) => e.id).toSet(), {7, 9});
    });

    test('quick revise has 5 questions, chapter at most 30, empty pool gives empty set', () {
      final b = PracticeBuilder(random: Random(6));
      expect(b.build(mode: PracticeMode.quickRevise, pool: pool), hasLength(5));
      expect(b.build(mode: PracticeMode.chapter, pool: pool), hasLength(30));
      expect(b.build(mode: PracticeMode.quick10, pool: const []), isEmpty);
    });

    test('onboarding keeps the given order', () {
      final set = PracticeBuilder(random: Random(7))
          .build(mode: PracticeMode.onboarding, pool: pool.take(5).toList());
      expect(set.map((e) => e.id).toList(), [1, 2, 3, 4, 5]);
    });

    test('timed mode has a timer, others do not', () {
      expect(PracticeMode.timed.secondsPerQuestion, 60);
      expect(PracticeMode.quick10.secondsPerQuestion, isNull);
      expect(PracticeMode.fromWire('wrong_only'), PracticeMode.wrongOnly);
    });
  });

  group('PracticeSession', () {
    test('scores, measures time and moves on', () {
      final t0 = DateTime(2026, 1, 1, 10);
      final s = PracticeSession(questions: [q(1), q(2)], startedAt: t0);

      final a1 = s.check('B', t0.add(const Duration(seconds: 7)));
      expect(a1.correct, isTrue);
      expect(a1.timeMs, 7000);
      expect(s.next(t0.add(const Duration(seconds: 8))), isTrue);

      final a2 = s.check('A', t0.add(const Duration(seconds: 11)));
      expect(a2.correct, isFalse);
      expect(a2.timeMs, 3000);
      expect(s.next(t0), isFalse);
      expect(s.finished, isTrue);
      expect(s.correctCount, 1);
    });

    test('checking twice keeps the first answer', () {
      final t0 = DateTime(2026);
      final s = PracticeSession(questions: [q(1)], startedAt: t0);
      s.check('A', t0);
      expect(s.check('B', t0).correct, isFalse);
    });

    test('skipped (time up) counts as wrong', () {
      final t0 = DateTime(2026);
      final s = PracticeSession(questions: [q(1)], startedAt: t0);
      expect(s.check(null, t0.add(const Duration(seconds: 60))).correct, isFalse);
    });
  });

  group('describeCorrectAnswer', () {
    test('choice', () => expect(describeCorrectAnswer(q(1)), 'B. 4'));

    test('numeric with unit', () {
      expect(
        describeCorrectAnswer(
          q(1, type: QuestionType.numeric, key: {'value': 45, 'unit': 'm'}, options: const []),
        ),
        '45 m',
      );
      expect(
        describeCorrectAnswer(
          q(1, type: QuestionType.numeric, key: {'value': 2.5}, options: const []),
        ),
        '2.5',
      );
    });

    test('multiple, ordering, matching, fill in', () {
      const opts = [
        QuestionOption(key: 'A', side: null, body: 'x', mediaUrl: null),
        QuestionOption(key: 'C', side: null, body: 'z', mediaUrl: null),
      ];
      expect(
        describeCorrectAnswer(
          q(
            1,
            type: QuestionType.multipleAnswers,
            key: {
              'correct': ['A', 'C'],
            },
            options: opts,
          ),
        ),
        'A. x · C. z',
      );
      expect(
        describeCorrectAnswer(
          q(
            1,
            type: QuestionType.ordering,
            key: {
              'order': ['C', 'A'],
            },
            options: opts,
          ),
        ),
        'z → x',
      );
      const match = [
        QuestionOption(key: 'L1', side: 'left', body: 'Velocity', mediaUrl: null),
        QuestionOption(key: 'R2', side: 'right', body: 'm/s', mediaUrl: null),
      ];
      expect(
        describeCorrectAnswer(
          q(
            1,
            type: QuestionType.matching,
            key: {
              'pairs': {'L1': 'R2'},
            },
            options: match,
          ),
        ),
        'Velocity → m/s',
      );
      expect(
        describeCorrectAnswer(
          q(
            1,
            type: QuestionType.fillIn,
            key: {
              'accepted': ['parabola'],
            },
            options: const [],
          ),
        ),
        'parabola',
      );
    });
  });
}
