import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/hook_questions.dart';
import 'package:guul12/data/models.dart';
import 'package:guul12/domain/scoring.dart';

/// Answers solved a second time, independently of the file, written as the
/// option TEXT (or the number), never as a key letter. If someone edits the
/// file and the key no longer points at the right text, this test fails.
const solved = <int, Object>{
  // Grade 9
  -901: '5', // 3x - 5 = 10 → 3x = 15 → x = 5
  -902: '60 km/h', // 120 km ÷ 2 h
  -903: 'Atom',
  -904: 'True',
  -905: 'goes', // third person singular, present simple
  // Grade 10
  -1001: '32', // 2·2·2·2·2
  -1002: 12, // F = m·a = 4 × 3
  -1003: 'True', // neutral at 25 °C
  -1004: 'Chloroplast',
  -1005: '24 hours',
  // Grade 11
  -1101: 7, // 2·3 + 1
  -1102: 'Joule',
  -1103: '6', // protons = atomic number
  -1104: 'True', // law of demand
  -1105: 'quick',
  // Grade 12
  -1201: 12, // f'(x) = 3x², f'(2) = 12
  -1202: '2 s', // h = ½gt² → t = √(2·20/10) = 2
  -1203: 'False', // oxidation is LOSS of electrons
  -1204: '30', // 2+4+6+8+10
  -1205: '48', // doubles each time
};

void main() {
  final all = parseHookQuestions(File(hookQuestionsAsset).readAsStringSync());
  const scorer = AnswerScorer();

  test('five questions for each grade 9–12, unique negative ids', () {
    expect(all.keys.toSet(), {9, 10, 11, 12});
    for (final list in all.values) {
      expect(list, hasLength(5));
    }
    final ids = all.values.expand((l) => l).map((q) => q.id).toList();
    expect(ids.toSet(), hasLength(ids.length));
    expect(ids.every((id) => id < 0), isTrue);
    expect(ids.toSet(), solved.keys.toSet());
  });

  test('every key matches the independently solved answer', () {
    for (final q in all.values.expand((l) => l)) {
      final answer = solved[q.id]!;
      expect(q.explanation, isNotEmpty, reason: 'q ${q.id} needs an explanation');
      if (q.type == QuestionType.numeric) {
        expect(scorer.isCorrect(q.type, q.answerKey, answer), isTrue, reason: 'q ${q.id}');
        expect(scorer.isCorrect(q.type, q.answerKey, (answer as int) + 1), isFalse);
        continue;
      }
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

  test('notes are skipped and a bad grade key is refused', () {
    expect(parseHookQuestions('{"_note": "x", "9": []}'), {9: <Question>[]});
    expect(() => parseHookQuestions('{"nine": []}'), throwsFormatException);
  });
}
