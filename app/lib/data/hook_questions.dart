import 'dart:convert';

import 'models.dart';

/// Path of the bundled warm-up questions shown during onboarding (works offline).
const hookQuestionsAsset = 'assets/hook/hook_questions.json';

/// Parses the bundled warm-up file: the same few very easy questions for every grade.
/// Keys starting with `_` are notes and are skipped.
List<Question> parseHookQuestions(String source) {
  final root = asMap(jsonDecode(source), 'hook file');
  if (!root.containsKey('questions')) throw const FormatException('No "questions" in hook file');
  return asList(root['questions']).map((e) => Question.fromJson(asMap(e))).toList(growable: false);
}
