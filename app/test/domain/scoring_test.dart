import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/models.dart';
import 'package:guul12/domain/scoring.dart';

/// Same cases as backend/tests/Unit/AnswerScorerTest.php so app and server always agree.
void main() {
  const scorer = AnswerScorer();

  final cases = <String, (QuestionType, Map<String, Object?>, Object?, bool)>{
    'single correct': (QuestionType.singleChoice, {'correct': 'B'}, 'B', true),
    'single lowercase': (QuestionType.singleChoice, {'correct': 'B'}, ' b ', true),
    'single wrong': (QuestionType.singleChoice, {'correct': 'B'}, 'C', false),
    'single skipped': (QuestionType.singleChoice, {'correct': 'B'}, null, false),
    'single array given': (QuestionType.singleChoice, {'correct': 'B'}, ['B'], false),
    'single number given': (QuestionType.singleChoice, {'correct': 'B'}, 2, false),
    'single broken key': (
      QuestionType.singleChoice,
      {
        'correct': ['B'],
      },
      'B',
      false,
    ),
    'true false': (QuestionType.trueFalse, {'correct': 'TRUE'}, 'true', true),
    'true false wrong': (QuestionType.trueFalse, {'correct': 'TRUE'}, 'FALSE', false),
    'image choice': (QuestionType.imageChoice, {'correct': 'D'}, 'D', true),
    'passage choice': (QuestionType.passageChoice, {'correct': 'A'}, 'A', true),
    'multi exact': (
      QuestionType.multipleAnswers,
      {
        'correct': ['A', 'C'],
      },
      ['A', 'C'],
      true,
    ),
    'multi other order': (
      QuestionType.multipleAnswers,
      {
        'correct': ['A', 'C'],
      },
      ['c', 'a'],
      true,
    ),
    'multi repeated key': (
      QuestionType.multipleAnswers,
      {
        'correct': ['A', 'C'],
      },
      ['A', 'A', 'C'],
      true,
    ),
    'multi missing one': (
      QuestionType.multipleAnswers,
      {
        'correct': ['A', 'C'],
      },
      ['A'],
      false,
    ),
    'multi extra one': (
      QuestionType.multipleAnswers,
      {
        'correct': ['A', 'C'],
      },
      ['A', 'B', 'C'],
      false,
    ),
    'multi empty': (
      QuestionType.multipleAnswers,
      {
        'correct': ['A', 'C'],
      },
      <String>[],
      false,
    ),
    'multi string given': (
      QuestionType.multipleAnswers,
      {
        'correct': ['A', 'C'],
      },
      'A,C',
      false,
    ),
    'multi object given': (
      QuestionType.multipleAnswers,
      {
        'correct': ['A', 'C'],
      },
      {'x': 'A'},
      false,
    ),
    'multi number in list': (
      QuestionType.multipleAnswers,
      {
        'correct': ['A', 'C'],
      },
      ['A', 3],
      false,
    ),
    'multi empty key': (QuestionType.multipleAnswers, {'correct': <String>[]}, <String>[], false),
    'numeric exact int': (QuestionType.numeric, {'value': 2}, 2, true),
    'numeric exact float': (QuestionType.numeric, {'value': 2.5}, 2.5, true),
    'numeric string': (QuestionType.numeric, {'value': 2.5}, '2.5', true),
    'numeric decimal comma': (QuestionType.numeric, {'value': 2.5}, '2,5', true),
    'numeric fraction': (QuestionType.numeric, {'value': 2.5}, '5/2', true),
    'numeric negative fraction': (QuestionType.numeric, {'value': -0.5}, '-1/2', true),
    'numeric unicode minus': (QuestionType.numeric, {'value': -3}, '−3', true),
    'numeric spaces': (QuestionType.numeric, {'value': 1000}, ' 1 000 ', true),
    'numeric within tolerance': (
      QuestionType.numeric,
      {'value': 9.8, 'tolerance': 0.05},
      '9.84',
      true,
    ),
    'numeric outside tolerance': (
      QuestionType.numeric,
      {'value': 9.8, 'tolerance': 0.05},
      '9.86',
      false,
    ),
    'numeric float noise': (QuestionType.numeric, {'value': 0.3}, 0.1 + 0.2, true),
    'numeric wrong': (QuestionType.numeric, {'value': 2}, '3', false),
    'numeric divide by zero': (QuestionType.numeric, {'value': 2}, '4/0', false),
    'numeric text': (QuestionType.numeric, {'value': 2}, 'two', false),
    'numeric with unit text': (QuestionType.numeric, {'value': 2}, '2 s', false),
    'numeric thousands comma with dot': (QuestionType.numeric, {'value': 1000.5}, '1,000.5', false),
    'numeric empty string': (QuestionType.numeric, {'value': 0}, '', false),
    'numeric scientific': (QuestionType.numeric, {'value': 0.00012}, '1.2e-4', true),
    'numeric broken key': (QuestionType.numeric, {'value': '2'}, '2', false),
    'numeric bool given': (QuestionType.numeric, {'value': 1}, true, false),
    'fill exact': (
      QuestionType.fillIn,
      {
        'accepted': ['photosynthesis'],
      },
      'photosynthesis',
      true,
    ),
    'fill case and spaces': (
      QuestionType.fillIn,
      {
        'accepted': ['Photosynthesis'],
      },
      '  PHOTOSYNTHESIS. ',
      true,
    ),
    'fill inner spaces': (
      QuestionType.fillIn,
      {
        'accepted': ['carbon dioxide'],
      },
      'carbon   dioxide',
      true,
    ),
    'fill second spelling': (
      QuestionType.fillIn,
      {
        'accepted': ['ion', 'ions'],
      },
      'ions',
      true,
    ),
    'fill wrong': (
      QuestionType.fillIn,
      {
        'accepted': ['ion'],
      },
      'atom',
      false,
    ),
    'fill empty': (
      QuestionType.fillIn,
      {
        'accepted': ['ion'],
      },
      '   ',
      false,
    ),
    'fill not a string': (
      QuestionType.fillIn,
      {
        'accepted': ['2'],
      },
      2,
      false,
    ),
    'match all right': (
      QuestionType.matching,
      {
        'pairs': {'L1': 'R2', 'L2': 'R1'},
      },
      {'L1': 'R2', 'L2': 'R1'},
      true,
    ),
    'match lowercase values': (
      QuestionType.matching,
      {
        'pairs': {'L1': 'R2', 'L2': 'R1'},
      },
      {'L1': 'r2', 'L2': 'r1'},
      true,
    ),
    'match one wrong': (
      QuestionType.matching,
      {
        'pairs': {'L1': 'R2', 'L2': 'R1'},
      },
      {'L1': 'R1', 'L2': 'R1'},
      false,
    ),
    'match missing': (
      QuestionType.matching,
      {
        'pairs': {'L1': 'R2', 'L2': 'R1'},
      },
      {'L1': 'R2'},
      false,
    ),
    'match extra': (
      QuestionType.matching,
      {
        'pairs': {'L1': 'R2'},
      },
      {'L1': 'R2', 'L9': 'R1'},
      false,
    ),
    'match list given': (
      QuestionType.matching,
      {
        'pairs': {'L1': 'R2'},
      },
      ['R2'],
      false,
    ),
    'match empty key': (
      QuestionType.matching,
      {'pairs': <String, String>{}},
      <String, String>{},
      false,
    ),
    'order right': (
      QuestionType.ordering,
      {
        'order': ['C', 'A', 'B'],
      },
      ['C', 'A', 'B'],
      true,
    ),
    'order wrong': (
      QuestionType.ordering,
      {
        'order': ['C', 'A', 'B'],
      },
      ['A', 'C', 'B'],
      false,
    ),
    'order short': (
      QuestionType.ordering,
      {
        'order': ['C', 'A', 'B'],
      },
      ['C', 'A'],
      false,
    ),
    'order object given': (
      QuestionType.ordering,
      {
        'order': ['C', 'A'],
      },
      {'x': 'C'},
      false,
    ),
  };

  for (final entry in cases.entries) {
    test(entry.key, () {
      final (type, key, response, expected) = entry.value;
      expect(scorer.isCorrect(type, key, response), expected);
    });
  }

  test('every type is handled', () {
    for (final type in QuestionType.values) {
      expect(scorer.isCorrect(type, const {}, 'x'), isFalse);
    }
  });

  test('parseNumber rejects non-finite values', () {
    expect(AnswerScorer.parseNumber(double.infinity), isNull);
    expect(AnswerScorer.parseNumber('1e999'), isNull);
    expect(AnswerScorer.parseNumber(double.nan), isNull);
  });
}
