import 'dart:convert';

import 'models.dart';

/// Path of the bundled warm-up questions shown during onboarding (works offline).
const hookQuestionsAsset = 'assets/hook/hook_questions.json';

/// Parses the bundled warm-up file into questions per grade (9–12).
/// Keys starting with `_` are notes and are skipped.
Map<int, List<Question>> parseHookQuestions(String source) {
  final root = asMap(jsonDecode(source), 'hook file');
  final result = <int, List<Question>>{};
  for (final entry in root.entries) {
    if (entry.key.startsWith('_')) continue;
    final grade = int.tryParse(entry.key);
    if (grade == null) throw FormatException('Bad grade key ${entry.key}');
    result[grade] = asList(entry.value)
        .map((e) => Question.fromJson(asMap(e)))
        .toList(growable: false);
  }
  return result;
}
