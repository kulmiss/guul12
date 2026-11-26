/// Numbers and chart series for the Statistics page and the Home cards. Everything is worked
/// out on the phone from the saved answers and exam results; nothing new is sent anywhere.
library;

import 'engagement.dart';
import 'exam.dart';
import 'mastery.dart';

/// One saved answer with its chapter and the time spent on it (null when not known: answers
/// saved before the app kept times, or restored from the server).
typedef AnswerRecord = ({DateTime at, bool correct, int chapterId, int? timeMs});

/// One finished exam for the score history chart.
typedef ExamPoint = ({DateTime at, int percent, String title});

DateTime _day(DateTime t) => DateTime(t.year, t.month, t.day);

/// "morning" (5:00–11:59), "afternoon" (12:00–16:59) or "evening" (the rest of the day and the
/// night), for the Home greeting and the "When you study" chart.
String partOfDay(DateTime t) => switch (t.hour) {
  >= 5 && < 12 => 'morning',
  >= 12 && < 17 => 'afternoon',
  _ => 'evening',
};

/// Right and wrong answers on one day.
class DayCount {
  const DayCount({required this.day, this.right = 0, this.wrong = 0, this.isFuture = false});

  /// Midnight (local time).
  final DateTime day;
  final int right;
  final int wrong;

  /// After today (only in the heatmap, which shows whole weeks).
  final bool isFuture;

  int get total => right + wrong;
}

/// Answers and the share right in one week (Monday to Sunday).
class WeekAccuracy {
  const WeekAccuracy({required this.start, required this.answered, required this.correct});

  /// Monday, midnight (local time).
  final DateTime start;
  final int answered;
  final int correct;

  /// Null in a week without answers.
  int? get percent => answered == 0 ? null : (100 * correct / answered).round();
}

/// Answers and the share right in one subject, or in one part of the day.
class ShareRight {
  const ShareRight({required this.answered, required this.correct});

  final int answered;
  final int correct;

  int? get percent => answered == 0 ? null : (100 * correct / answered).round();
}

class SubjectAccuracy extends ShareRight {
  const SubjectAccuracy({required this.subjectId, required super.answered, required super.correct});

  final int subjectId;
}

class PartOfDayCount extends ShareRight {
  const PartOfDayCount({required this.part, required super.answered, required super.correct});

  /// "morning", "afternoon" or "evening" (see [partOfDay]).
  final String part;
}

class Statistics {
  const Statistics({
    required this.answered,
    required this.correct,
    required this.daysStudied,
    required this.studyTime,
    required this.timedAnswers,
    required this.lastDays,
    required this.weeks,
    required this.heatmap,
    required this.subjects,
    required this.partsOfDay,
    required this.exams,
  });

  /// Days in the "last days" bar chart.
  static const dayCount = 14;

  /// Weeks in the accuracy trend.
  static const weekCount = 8;

  /// Whole weeks in the activity heatmap.
  static const heatmapWeeks = 12;

  /// Exams in the score history.
  static const examCount = 10;

  /// Longest time one answer adds to the study time (a phone left open does not count for hours).
  static const maxTimePerAnswer = Duration(minutes: 5);

  final int answered;
  final int correct;

  /// Days with at least one answer.
  final int daysStudied;

  /// Sum of the known times spent on answers, each at most [maxTimePerAnswer].
  final Duration studyTime;

  /// Answers with a known time.
  final int timedAnswers;

  /// The last [dayCount] days, oldest first, the last one today.
  final List<DayCount> lastDays;

  /// The last [weekCount] weeks, oldest first, the last one this week.
  final List<WeekAccuracy> weeks;

  /// [heatmapWeeks] whole weeks, Monday first, day by day; the last week is this week.
  final List<DayCount> heatmap;

  /// Subjects with answers, in the order given.
  final List<SubjectAccuracy> subjects;

  /// Morning, afternoon and evening, in that order.
  final List<PartOfDayCount> partsOfDay;

  /// The last [examCount] exams, oldest first.
  final List<ExamPoint> exams;

  bool get isEmpty => answered == 0 && exams.isEmpty;

  int? get accuracyPercent => answered == 0 ? null : (100 * correct / answered).round();

  /// Average seconds per answer with a known time; null when none is known.
  int? get secondsPerAnswer =>
      timedAnswers == 0 ? null : (studyTime.inMilliseconds / timedAnswers / 1000).round();

  /// The most answers on one heatmap day (0 when none), for the colour scale.
  int get heatmapMax => heatmap.fold(0, (m, d) => d.total > m ? d.total : m);
}

/// Builds the statistics. [chapterSubject] maps a chapter to its subject; [subjectOrder] is the
/// order of the student's subjects. [now] is the phone's local time.
Statistics statisticsFrom({
  required List<AnswerRecord> answers,
  required List<ExamPoint> exams,
  required Map<int, int> chapterSubject,
  required List<int> subjectOrder,
  required DateTime now,
}) {
  final today = _day(now);
  // Calendar days (not 24-hour steps), so a clock change can never shift a day.
  DateTime dayFrom(DateTime d, int offset) => DateTime(d.year, d.month, d.day + offset);

  final right = <DateTime, int>{};
  final wrong = <DateTime, int>{};
  final subjectAnswered = <int, int>{};
  final subjectCorrect = <int, int>{};
  final partAnswered = <String, int>{};
  final partCorrect = <String, int>{};
  var correct = 0;
  var studyMs = 0;
  var timed = 0;
  final maxMs = Statistics.maxTimePerAnswer.inMilliseconds;

  for (final a in answers) {
    final day = _day(a.at);
    final c = a.correct ? 1 : 0;
    correct += c;
    if (a.correct) {
      right[day] = (right[day] ?? 0) + 1;
    } else {
      wrong[day] = (wrong[day] ?? 0) + 1;
    }
    final subject = chapterSubject[a.chapterId];
    if (subject != null) {
      subjectAnswered[subject] = (subjectAnswered[subject] ?? 0) + 1;
      subjectCorrect[subject] = (subjectCorrect[subject] ?? 0) + c;
    }
    final part = partOfDay(a.at);
    partAnswered[part] = (partAnswered[part] ?? 0) + 1;
    partCorrect[part] = (partCorrect[part] ?? 0) + c;
    final time = a.timeMs;
    if (time != null && time >= 0) {
      studyMs += time > maxMs ? maxMs : time;
      timed++;
    }
  }

  DayCount count(DateTime day) => DayCount(
    day: day,
    right: right[day] ?? 0,
    wrong: wrong[day] ?? 0,
    isFuture: day.isAfter(today),
  );

  final lastDays = [for (var i = Statistics.dayCount - 1; i >= 0; i--) count(dayFrom(today, -i))];

  final thisWeek = weekStart(now);
  final weeks = <WeekAccuracy>[];
  for (var w = Statistics.weekCount - 1; w >= 0; w--) {
    final start = dayFrom(thisWeek, -7 * w);
    var answered = 0;
    var ok = 0;
    for (var d = 0; d < 7; d++) {
      final day = dayFrom(start, d);
      answered += (right[day] ?? 0) + (wrong[day] ?? 0);
      ok += right[day] ?? 0;
    }
    weeks.add(WeekAccuracy(start: start, answered: answered, correct: ok));
  }

  final firstMonday = dayFrom(thisWeek, -7 * (Statistics.heatmapWeeks - 1));
  final heatmap = [
    for (var i = 0; i < Statistics.heatmapWeeks * 7; i++) count(dayFrom(firstMonday, i)),
  ];

  final sortedExams = [...exams]..sort((a, b) => a.at.compareTo(b.at));

  return Statistics(
    answered: answers.length,
    correct: correct,
    daysStudied: {...right.keys, ...wrong.keys}.length,
    studyTime: Duration(milliseconds: studyMs),
    timedAnswers: timed,
    lastDays: lastDays,
    weeks: weeks,
    heatmap: heatmap,
    subjects: [
      for (final id in subjectOrder)
        if ((subjectAnswered[id] ?? 0) > 0)
          SubjectAccuracy(
            subjectId: id,
            answered: subjectAnswered[id]!,
            correct: subjectCorrect[id] ?? 0,
          ),
    ],
    partsOfDay: [
      for (final part in const ['morning', 'afternoon', 'evening'])
        PartOfDayCount(
          part: part,
          answered: partAnswered[part] ?? 0,
          correct: partCorrect[part] ?? 0,
        ),
    ],
    exams: sortedExams.length > Statistics.examCount
        ? sortedExams.sublist(sortedExams.length - Statistics.examCount)
        : sortedExams,
  );
}

/// A likely exam score (in percent) from the student's recent answers.
class Readiness {
  const Readiness({required this.low, required this.high, required this.basedOn, this.change});

  /// Answers needed before an estimate is shown.
  static const minAnswers = 20;

  /// Only the most recent answers count, so old mistakes stop weighing on the estimate.
  static const window = 100;

  final int low;
  final int high;

  /// Answers the estimate is based on (at most [window]).
  final int basedOn;

  /// Points up or down against the estimate 7 days ago; null when there was no estimate then.
  final int? change;

  int get middle => ((low + high) / 2).round();
}

/// The estimate at [now] from [answers] (oldest first or in any order). Answers after [now]
/// (a phone clock set wrong) are ignored. Null with fewer than [Readiness.minAnswers].
Readiness? readinessFrom({required List<AnswerEvent> answers, required DateTime now}) {
  final current = _estimate(answers, now);
  if (current == null) return null;
  final before = _estimate(answers, now.subtract(const Duration(days: 7)));
  return Readiness(
    low: current.low,
    high: current.high,
    basedOn: current.basedOn,
    change: before == null ? null : current.middle - before.middle,
  );
}

Readiness? _estimate(List<AnswerEvent> answers, DateTime at) {
  final upTo = answers.where((a) => !a.at.isAfter(at)).toList()
    ..sort((a, b) => a.at.compareTo(b.at));
  if (upTo.length < Readiness.minAnswers) return null;
  final recent = upTo.length > Readiness.window
      ? upTo.sublist(upTo.length - Readiness.window)
      : upTo;
  final correct = recent.where((a) => a.correct).length;
  final (low, high) = estimatedScoreRange(correct: correct, total: recent.length, paperMax: 100);
  return Readiness(low: low, high: high, basedOn: recent.length);
}

/// How many of [chapterIds] are at each mastery level (chapters never started count as
/// "not started"). Every level is in the map, in order.
Map<MasteryLevel, int> masteryCounts({
  required Iterable<int> chapterIds,
  required Map<int, ChapterMastery> mastery,
}) {
  final counts = {for (final level in MasteryLevel.values) level: 0};
  for (final id in chapterIds.toSet()) {
    final level = mastery[id]?.level ?? MasteryLevel.notStarted;
    counts[level] = counts[level]! + 1;
  }
  return counts;
}

/// One of [items] for the day that holds [now]: the same all day, the next one tomorrow.
/// Null when [items] is empty.
T? pickOfTheDay<T>(List<T> items, DateTime now) {
  if (items.isEmpty) return null;
  final dayNumber =
      DateTime.utc(now.year, now.month, now.day).millisecondsSinceEpoch ~/
      Duration.millisecondsPerDay;
  return items[dayNumber % items.length];
}
