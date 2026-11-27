import 'dart:math';

import '../data/models.dart';
import 'scoring.dart';

/// The kinds of exam (wire names match the server's attempt modes).
enum ExamKind {
  fixed('exam_fixed'),
  random('exam_random'),
  weak('exam_weak'),
  mistakes('exam_mistakes');

  const ExamKind(this.wire);

  final String wire;

  static ExamKind fromWire(String value) => ExamKind.values.firstWhere(
    (k) => k.wire == value,
    orElse: () => throw FormatException('Unknown exam kind $value'),
  );
}

/// Question types an exam may use: the real paper has choice questions only.
bool isExamType(QuestionType type) => type.isSingleKey;

/// Real exam pace: 90 seconds per question.
const examSecondsPerQuestion = 90;

int examMinutesFor(int questionCount) => (questionCount * examSecondsPerQuestion / 60).ceil();

/// A ready exam: what the student will sit.
class ExamPaper {
  const ExamPaper({
    required this.kind,
    required this.subjectId,
    required this.title,
    required this.minutes,
    required this.questions,
    required this.passages,
    this.mockId,
  });

  factory ExamPaper.fromJson(Map<String, Object?> json) => ExamPaper(
    kind: ExamKind.fromWire(asString(json['kind'])),
    mockId: asIntOrNull(json['mock_id']),
    subjectId: asInt(json['subject_id']),
    title: asString(json['title']),
    minutes: asInt(json['minutes']),
    questions: asList(json['questions']).map((e) => Question.fromJson(asMap(e))).toList(),
    passages: {
      for (final p in asList(json['passages']).map((e) => Passage.fromJson(asMap(e)))) p.id: p,
    },
  );

  final ExamKind kind;
  final int? mockId;
  final int subjectId;
  final String title;
  final int minutes;
  final List<Question> questions;
  final Map<int, Passage> passages;

  Map<String, Object?> toJson() => {
    'kind': kind.wire,
    'mock_id': mockId,
    'subject_id': subjectId,
    'title': title,
    'minutes': minutes,
    'questions': [for (final q in questions) q.toJson()],
    'passages': [for (final p in passages.values) p.toJson()],
  };
}

/// One exam in progress. Real Exam Mode: the clock runs from [startedAt] whatever the
/// student does (leaving the app does not stop it), answers can change until submitting,
/// and nothing is marked right or wrong before the end.
class ExamSession {
  ExamSession({
    required this.paper,
    required this.clientUuid,
    required this.startedAt,
    Map<int, String>? answers,
    Set<int>? flagged,
    Map<int, int>? timeMs,
    this._index = 0,
    DateTime? shownAt,
  }) : answers = answers ?? {},
       flagged = flagged ?? {},
       timeMs = timeMs ?? {},
       _shownAt = shownAt ?? startedAt;

  factory ExamSession.fromJson(Map<String, Object?> json) => ExamSession(
    paper: ExamPaper.fromJson(asMap(json['paper'])),
    clientUuid: asString(json['client_uuid']),
    startedAt: DateTime.parse(asString(json['started_at'])),
    answers: asMap(json['answers']).map((k, v) => MapEntry(int.parse(k), asString(v))),
    flagged: asList(json['flagged']).map(asInt).toSet(),
    timeMs: asMap(json['time_ms']).map((k, v) => MapEntry(int.parse(k), asInt(v))),
    index: asInt(json['index']),
    shownAt: DateTime.parse(asString(json['shown_at'])),
  );

  final ExamPaper paper;
  final String clientUuid;
  final DateTime startedAt;

  /// Question index → chosen option key.
  final Map<int, String> answers;
  final Set<int> flagged;

  /// Question index → time spent looking at it.
  final Map<int, int> timeMs;
  int _index;
  DateTime _shownAt;

  int get index => _index;
  int get total => paper.questions.length;
  Question get current => paper.questions[_index];
  Passage? get currentPassage => paper.passages[current.passageId];
  String? get currentAnswer => answers[_index];
  bool get currentFlagged => flagged.contains(_index);
  int get answeredCount => answers.length;
  int get unansweredCount => total - answers.length;
  DateTime get deadline => startedAt.add(Duration(minutes: paper.minutes));

  Duration remaining(DateTime now) {
    final left = deadline.difference(now);
    return left.isNegative ? Duration.zero : left;
  }

  bool isTimeUp(DateTime now) => !now.isBefore(deadline);

  void choose(String key, DateTime now) {
    if (isTimeUp(now)) return;
    answers[_index] = key;
  }

  void clear(DateTime now) {
    if (isTimeUp(now)) return;
    answers.remove(_index);
  }

  void toggleFlag() => flagged.contains(_index) ? flagged.remove(_index) : flagged.add(_index);

  /// Moves to question [target], adding the time spent on the current one.
  void goTo(int target, DateTime now) {
    if (target < 0 || target >= total) return;
    _addTime(now);
    _index = target;
  }

  void _addTime(DateTime now) {
    final end = now.isAfter(deadline) ? deadline : now;
    final spent = end.difference(_shownAt).inMilliseconds;
    if (spent > 0) timeMs[_index] = (timeMs[_index] ?? 0) + spent;
    _shownAt = now;
  }

  /// Ends the exam (by the student or when time is up) and marks every answer.
  ExamOutcome finish(DateTime now, {AnswerScorer scorer = const AnswerScorer()}) {
    _addTime(now);
    final finishedAt = now.isAfter(deadline) ? deadline : now;
    final items = <ExamItem>[
      for (var i = 0; i < total; i++)
        ExamItem(
          question: paper.questions[i],
          response: answers[i],
          correct: scorer.isCorrect(
            paper.questions[i].type,
            paper.questions[i].answerKey,
            answers[i],
          ),
          timeMs: timeMs[i] ?? 0,
        ),
    ];
    return ExamOutcome(items: items, startedAt: startedAt, finishedAt: finishedAt);
  }

  Map<String, Object?> toJson() => {
    'paper': paper.toJson(),
    'client_uuid': clientUuid,
    'started_at': startedAt.toUtc().toIso8601String(),
    'answers': answers.map((k, v) => MapEntry('$k', v)),
    'flagged': flagged.toList()..sort(),
    'time_ms': timeMs.map((k, v) => MapEntry('$k', v)),
    'index': _index,
    'shown_at': _shownAt.toUtc().toIso8601String(),
  };
}

class ExamItem {
  const ExamItem({
    required this.question,
    required this.response,
    required this.correct,
    required this.timeMs,
  });

  final Question question;
  final String? response;
  final bool correct;
  final int timeMs;
}

/// Right answers in one chapter.
class ChapterScore {
  const ChapterScore({required this.chapterId, required this.total, required this.correct});

  final int chapterId;
  final int total;
  final int correct;

  double get ratio => total == 0 ? 0 : correct / total;
}

class ExamOutcome {
  const ExamOutcome({required this.items, required this.startedAt, required this.finishedAt});

  final List<ExamItem> items;
  final DateTime startedAt;
  final DateTime finishedAt;

  int get total => items.length;
  int get correct => items.where((i) => i.correct).length;

  /// Per chapter, in the order chapters first appear in the exam.
  List<ChapterScore> get chapters => chapterScores(items);
}

List<ChapterScore> chapterScores(List<ExamItem> items) {
  final order = <int>[];
  final totals = <int, int>{};
  final rights = <int, int>{};
  for (final i in items) {
    final c = i.question.chapterId;
    if (!totals.containsKey(c)) order.add(c);
    totals[c] = (totals[c] ?? 0) + 1;
    rights[c] = (rights[c] ?? 0) + (i.correct ? 1 : 0);
  }
  return [
    for (final c in order) ChapterScore(chapterId: c, total: totals[c]!, correct: rights[c]!),
  ];
}

/// A likely range for the real paper's score, from a 95% Wilson interval around the share
/// of right answers. Fewer questions give a wider range. Returns (low, high) on [paperMax].
(int, int) estimatedScoreRange({required int correct, required int total, required int paperMax}) {
  if (total <= 0) return (0, paperMax);
  const z = 1.96;
  final n = total.toDouble();
  final p = correct / n;
  const z2 = z * z;
  final centre = p + z2 / (2 * n);
  final spread = z * sqrt(p * (1 - p) / n + z2 / (4 * n * n));
  final denominator = 1 + z2 / n;
  final low = ((centre - spread) / denominator).clamp(0.0, 1.0);
  final high = ((centre + spread) / denominator).clamp(0.0, 1.0);
  return ((low * paperMax).round(), (high * paperMax).round());
}

/// A finished exam as kept on the phone: enough to show the result and review it offline.
class ExamRecord {
  const ExamRecord({
    required this.clientUuid,
    required this.kind,
    required this.subjectId,
    required this.title,
    required this.startedAt,
    required this.finishedAt,
    required this.items,
    this.mockId,
    this.uploaded = false,
    this.betterThanPercent,
    this.students,
  });

  factory ExamRecord.fromOutcome(ExamSession session, ExamOutcome outcome) => ExamRecord(
    clientUuid: session.clientUuid,
    kind: session.paper.kind,
    mockId: session.paper.mockId,
    subjectId: session.paper.subjectId,
    title: session.paper.title,
    startedAt: outcome.startedAt,
    finishedAt: outcome.finishedAt,
    items: outcome.items,
  );

  factory ExamRecord.fromJson(Map<String, Object?> json) => ExamRecord(
    clientUuid: asString(json['client_uuid']),
    kind: ExamKind.fromWire(asString(json['kind'])),
    mockId: asIntOrNull(json['mock_id']),
    subjectId: asInt(json['subject_id']),
    title: asString(json['title']),
    startedAt: DateTime.parse(asString(json['started_at'])),
    finishedAt: DateTime.parse(asString(json['finished_at'])),
    items: [
      for (final e in asList(json['items']).map(asMap))
        ExamItem(
          question: Question.fromJson(asMap(e['question'])),
          response: asStringOrNull(e['response']),
          correct: asBool(e['correct']),
          timeMs: asInt(e['time_ms']),
        ),
    ],
    uploaded: asBool(json['uploaded']),
    betterThanPercent: asIntOrNull(json['better_than_percent']),
    students: asIntOrNull(json['students']),
  );

  final String clientUuid;
  final ExamKind kind;
  final int? mockId;
  final int subjectId;
  final String title;
  final DateTime startedAt;
  final DateTime finishedAt;
  final List<ExamItem> items;

  /// The server has this exam (its answers count for the comparison with others).
  final bool uploaded;

  /// "Better than X% of Guul 12 students"; null until enough students took this mock.
  final int? betterThanPercent;

  /// Other students who took this mock (null when not known yet, or not a fixed mock).
  final int? students;

  int get total => items.length;
  int get correct => items.where((i) => i.correct).length;

  /// Questions really answered (not skipped).
  int get answered => items.where((i) => i.response?.isNotEmpty ?? false).length;
  List<ChapterScore> get chapters => chapterScores(items);
  Duration get timeUsed => finishedAt.difference(startedAt);

  ExamRecord withServer({required int? betterThanPercent, required int? students}) => ExamRecord(
    clientUuid: clientUuid,
    kind: kind,
    mockId: mockId,
    subjectId: subjectId,
    title: title,
    startedAt: startedAt,
    finishedAt: finishedAt,
    items: items,
    uploaded: true,
    betterThanPercent: betterThanPercent,
    students: students,
  );

  Map<String, Object?> toJson() => {
    'client_uuid': clientUuid,
    'kind': kind.wire,
    'mock_id': mockId,
    'subject_id': subjectId,
    'title': title,
    'started_at': startedAt.toUtc().toIso8601String(),
    'finished_at': finishedAt.toUtc().toIso8601String(),
    'items': [
      for (final i in items)
        {
          'question': i.question.toJson(),
          'response': i.response,
          'correct': i.correct,
          'time_ms': i.timeMs,
        },
    ],
    'uploaded': uploaded,
    'better_than_percent': betterThanPercent,
    'students': students,
  };
}
