import '../data/models.dart';
import 'scoring.dart';

/// One answered question in a session.
class SessionAnswer {
  const SessionAnswer({required this.response, required this.correct, required this.timeMs});

  final Object? response;
  final bool correct;
  final int timeMs;
}

/// State of one practice run: which question, what was answered, how long it took.
/// Practice shows the explanation right after each check.
class PracticeSession {
  PracticeSession({
    required this.questions,
    required DateTime startedAt,
    this.scorer = const AnswerScorer(),
  }) : _startedAt = startedAt,
       _questionShownAt = startedAt;

  final List<Question> questions;
  final AnswerScorer scorer;
  final DateTime _startedAt;
  DateTime _questionShownAt;

  int _index = 0;
  final Map<int, SessionAnswer> _answers = {};

  DateTime get startedAt => _startedAt;
  int get index => _index;
  int get total => questions.length;
  bool get isEmpty => questions.isEmpty;
  Question get current => questions[_index];
  bool get isLast => _index == questions.length - 1;
  SessionAnswer? answerFor(int index) => _answers[index];
  SessionAnswer? get currentAnswer => _answers[_index];
  bool get currentChecked => _answers.containsKey(_index);
  bool get finished => _answers.length == questions.length;
  int get correctCount => _answers.values.where((a) => a.correct).length;

  /// Checks the current question. Checking twice keeps the first answer.
  SessionAnswer check(Object? response, DateTime now) {
    final existing = _answers[_index];
    if (existing != null) return existing;
    final answer = SessionAnswer(
      response: response,
      correct: scorer.isCorrect(current.type, current.answerKey, response),
      timeMs: now.difference(_questionShownAt).inMilliseconds.clamp(0, 1 << 31),
    );
    _answers[_index] = answer;
    return answer;
  }

  /// Moves to the next question. Returns false when there is none.
  bool next(DateTime now) {
    if (_index >= questions.length - 1) return false;
    _index++;
    _questionShownAt = now;
    return true;
  }

  List<(Question, SessionAnswer)> results() => [
    for (var i = 0; i < questions.length; i++)
      if (_answers[i] != null) (questions[i], _answers[i]!),
  ];
}

/// Human-readable correct answer for the feedback panel.
String describeCorrectAnswer(Question q) {
  String bodyFor(String key) {
    for (final o in q.options) {
      if (o.key.toUpperCase() == key.toUpperCase()) return o.body;
    }
    return key;
  }

  final key = q.answerKey;
  switch (q.type) {
    case QuestionType.singleChoice:
    case QuestionType.imageChoice:
    case QuestionType.passageChoice:
      final c = key['correct'];
      return c is String ? '$c. ${bodyFor(c)}' : '';
    case QuestionType.trueFalse:
      final c = key['correct'];
      return c is String ? c : '';
    case QuestionType.multipleAnswers:
      final c = key['correct'];
      return c is List ? c.whereType<String>().map((k) => '$k. ${bodyFor(k)}').join(' · ') : '';
    case QuestionType.numeric:
      final v = key['value'];
      final unit = key['unit'];
      if (v is! num) return '';
      final text = v == v.roundToDouble() ? v.toInt().toString() : v.toString();
      return unit is String && unit.isNotEmpty ? '$text $unit' : text;
    case QuestionType.fillIn:
      final a = key['accepted'];
      return a is List && a.isNotEmpty && a.first is String ? a.first as String : '';
    case QuestionType.matching:
      final pairs = key['pairs'];
      if (pairs is! Map) return '';
      return pairs.entries
          .map((e) => '${bodyFor('${e.key}')} → ${bodyFor('${e.value}')}')
          .join(' · ');
    case QuestionType.ordering:
      final order = key['order'];
      return order is List ? order.whereType<String>().map(bodyFor).join(' → ') : '';
  }
}

/// Human-readable text of what the student answered, for the review list.
/// Returns null when nothing was answered (time up or skipped).
String? describeResponse(Question q, Object? response) {
  if (response == null) return null;
  String bodyFor(Object? key) {
    final k = '$key';
    for (final o in q.options) {
      if (o.key.toUpperCase() == k.toUpperCase()) return o.body;
    }
    return k;
  }

  switch (q.type) {
    case QuestionType.singleChoice:
    case QuestionType.imageChoice:
    case QuestionType.passageChoice:
      return '$response. ${bodyFor(response)}';
    case QuestionType.trueFalse:
    case QuestionType.numeric:
    case QuestionType.fillIn:
      final text = '$response'.trim();
      return text.isEmpty ? null : text;
    case QuestionType.multipleAnswers:
      if (response is! List || response.isEmpty) return null;
      return response.map((k) => '$k. ${bodyFor(k)}').join(' · ');
    case QuestionType.ordering:
      if (response is! List || response.isEmpty) return null;
      return response.map(bodyFor).join(' → ');
    case QuestionType.matching:
      if (response is! Map || response.isEmpty) return null;
      return response.entries.map((e) => '${bodyFor(e.key)} → ${bodyFor(e.value)}').join(' · ');
  }
}
