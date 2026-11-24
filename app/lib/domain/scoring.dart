import '../data/models.dart';

/// Instant feedback in the app. Mirrors the server's AnswerScorer exactly;
/// the server re-scores every uploaded answer and its result is the one that counts.
///
/// Response shapes:
/// - single-key types: `String` ("B")
/// - multipleAnswers: `List<String>`
/// - numeric: `String` or `num`
/// - fillIn: `String`
/// - matching: `Map<String, String>` (left key → right key)
/// - ordering: `List<String>`
/// - skipped: `null`
class AnswerScorer {
  const AnswerScorer();

  bool isCorrect(QuestionType type, Map<String, Object?> key, Object? response) {
    if (response == null) return false;

    return switch (type) {
      QuestionType.singleChoice ||
      QuestionType.trueFalse ||
      QuestionType.imageChoice ||
      QuestionType.passageChoice => _single(key, response),
      QuestionType.multipleAnswers => _multiple(key, response),
      QuestionType.numeric => _numeric(key, response),
      QuestionType.fillIn => _fillIn(key, response),
      QuestionType.matching => _matching(key, response),
      QuestionType.ordering => _ordering(key, response),
    };
  }

  bool _single(Map<String, Object?> key, Object response) {
    final correct = key['correct'];
    return response is String &&
        correct is String &&
        response.trim().toUpperCase() == correct.toUpperCase();
  }

  bool _multiple(Map<String, Object?> key, Object response) {
    final correct = key['correct'];
    if (response is! List || correct is! List) return false;
    final given = _normaliseKeys(response);
    final expected = _normaliseKeys(correct);
    if (given == null || expected == null || expected.isEmpty) return false;
    return given.length == expected.length && given.containsAll(expected);
  }

  Set<String>? _normaliseKeys(List<Object?> keys) {
    final out = <String>{};
    for (final k in keys) {
      if (k is! String) return null;
      out.add(k.trim().toUpperCase());
    }
    return out;
  }

  bool _numeric(Map<String, Object?> key, Object response) {
    final expected = key['value'];
    final tolerance = key['tolerance'] ?? 0;
    if (expected is! num || tolerance is! num) return false;
    final given = parseNumber(response);
    if (given == null) return false;
    final e = expected.toDouble();
    final epsilon = 1e-9 * (e.abs() > 1 ? e.abs() : 1);
    return (given - e).abs() <= tolerance.toDouble().abs() + epsilon;
  }

  /// Reads "2.5", "2,5", "-3", "5/2", "1.2e-4" and numbers. Returns null for anything else.
  static double? parseNumber(Object? value) {
    if (value is num) {
      final d = value.toDouble();
      return d.isFinite ? d : null;
    }
    if (value is! String) return null;

    var text = value.trim().replaceAll(' ', '').replaceAll('−', '-');

    final fraction = RegExp(r'^([+-]?\d+)/(\d+)$').firstMatch(text);
    if (fraction != null) {
      final denominator = int.parse(fraction.group(2)!);
      if (denominator == 0) return null;
      return int.parse(fraction.group(1)!) / denominator;
    }

    if (','.allMatches(text).length == 1 && !text.contains('.')) {
      text = text.replaceAll(',', '.');
    }

    if (!RegExp(r'^[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?$').hasMatch(text)) return null;

    final number = double.tryParse(text);
    return (number != null && number.isFinite) ? number : null;
  }

  bool _fillIn(Map<String, Object?> key, Object response) {
    final accepted = key['accepted'];
    if (response is! String || accepted is! List) return false;
    final given = normaliseText(response);
    if (given.isEmpty) return false;
    return accepted.whereType<String>().any((a) => normaliseText(a) == given);
  }

  static String normaliseText(String text) {
    var t = text.trim().toLowerCase().replaceAll(RegExp(r'\s+'), ' ');
    while (t.isNotEmpty && ' .,;:!?'.contains(t[t.length - 1])) {
      t = t.substring(0, t.length - 1);
    }
    return t;
  }

  bool _matching(Map<String, Object?> key, Object response) {
    final pairs = key['pairs'];
    if (response is! Map || pairs is! Map || pairs.isEmpty) return false;
    if (response.length != pairs.length) return false;
    for (final entry in pairs.entries) {
      final given = response[entry.key];
      final right = entry.value;
      if (given is! String ||
          right is! String ||
          given.trim().toUpperCase() != right.toUpperCase()) {
        return false;
      }
    }
    return true;
  }

  bool _ordering(Map<String, Object?> key, Object response) {
    final order = key['order'];
    if (response is! List || order is! List || response.length != order.length) return false;
    for (var i = 0; i < order.length; i++) {
      final given = response[i];
      final expected = order[i];
      if (given is! String ||
          expected is! String ||
          given.trim().toUpperCase() != expected.toUpperCase()) {
        return false;
      }
    }
    return true;
  }
}
