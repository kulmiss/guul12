import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/hook_questions.dart';
import 'package:guul12/data/models.dart';
import 'package:guul12/domain/scoring.dart';

/// Answers solved a second time, independently of the file, written as the option TEXT, never
/// as a key letter. If someone edits the file and the key no longer points at the right text,
/// this test fails.
const solved = <int, String>{
  -1: '12', // 7 + 5
  -2: 'Ice', // water freezes at 0 °C
  -3: 'Small', // opposite of big
  -4: 'Addis Ababa', // capital of Ethiopia
  -5: '10', // +2 each time: 2, 4, 6, 8, 10
};

void main() {
  final all = parseHookQuestions(File(hookQuestionsAsset).readAsStringSync());
  const scorer = AnswerScorer();

  test('the same five very easy questions for everyone, unique negative ids', () {
    expect(all, hasLength(5));
    expect(all.map((q) => q.id).toList(), solved.keys.toList());
  });

  test('tap only: one choice, three short options, a short question', () {
    for (final q in all) {
      expect(q.type, QuestionType.singleChoice, reason: 'q ${q.id}: no typing in the warm-up');
      expect(q.options, hasLength(3), reason: 'q ${q.id}');
      expect(q.stem.length, lessThanOrEqualTo(60), reason: 'q ${q.id}: keep it short');
      expect(q.stem, isNot(contains(r'$')), reason: 'q ${q.id}: no formulas');
      for (final o in q.options) {
        expect(o.body.length, lessThanOrEqualTo(20), reason: 'q ${q.id}: short answers');
      }
    }
  });

  test('every key matches the independently solved answer', () {
    for (final q in all) {
      final answer = solved[q.id]!;
      expect(q.explanation, isNotEmpty, reason: 'q ${q.id} needs an explanation');
      final matching = q.options.where((o) => o.body == answer).toList();
      expect(matching, hasLength(1), reason: 'q ${q.id}: answer text must appear once');
      for (final o in q.options) {
        expect(
          scorer.isCorrect(q.type, q.answerKey, o.key),
          o.body == answer,
          reason: 'q ${q.id} option ${o.key}',
        );
      }
      final bodies = q.options.map((o) => o.body).toList();
      expect(bodies.toSet(), hasLength(bodies.length), reason: 'q ${q.id}: duplicate options');
    }
  });

  test('the right answer is not always in the same place', () {
    final keys = all.map((q) => q.answerKey['correct']).toSet();
    expect(keys.length, greaterThan(1));
  });

  test('notes are skipped; a file without questions is refused', () {
    expect(parseHookQuestions('{"_note": "x", "questions": []}'), isEmpty);
    expect(() => parseHookQuestions('{"9": []}'), throwsFormatException);
  });
}
