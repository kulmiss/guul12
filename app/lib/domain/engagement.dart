/// Streak, XP and badges. Everything is worked out on the phone from the saved answers and
/// exam results, so nothing new about the student is sent anywhere.
library;

/// One saved answer (practice or exam).
typedef AnswerEvent = ({DateTime at, bool correct});

/// One finished exam.
/// A finished exam. [answered] = questions really answered (not skipped).
typedef ExamEvent = ({DateTime at, int correct, int total, int answered});

abstract final class EngagementRules {
  /// Questions to answer in a day for the day to count in the streak.
  static const dailyGoal = 10;
  static const xpCorrect = 10;
  static const xpWrong = 2;
  static const xpExam = 50;

  /// An exam gives its [xpExam] points only with this many answered questions (the server's
  /// leaderboard uses the same rule, so a one-question "exam" cannot be repeated for points).
  static const examMinAnswered = 10;
}

DateTime _day(DateTime t) => DateTime(t.year, t.month, t.day);

/// Monday of the week that holds [t] (local time).
DateTime weekStart(DateTime t) => _day(t).subtract(Duration(days: t.weekday - DateTime.monday));

class Engagement {
  const Engagement({
    required this.todayAnswered,
    required this.streak,
    required this.bestStreak,
    required this.xp,
    required this.weekXp,
    required this.totalAnswered,
    required this.totalCorrect,
    required this.activeDays,
    required this.exams,
    required this.bestExamPercent,
  });

  static const empty = Engagement(
    todayAnswered: 0,
    streak: 0,
    bestStreak: 0,
    xp: 0,
    weekXp: 0,
    totalAnswered: 0,
    totalCorrect: 0,
    activeDays: 0,
    exams: 0,
    bestExamPercent: null,
  );

  /// Answers given today (towards [EngagementRules.dailyGoal]).
  final int todayAnswered;

  /// Days in a row the goal was reached, up to today. If today's goal is not reached yet, the
  /// streak still stands while yesterday counted.
  final int streak;
  final int bestStreak;
  final int xp;

  /// XP earned since Monday.
  final int weekXp;
  final int totalAnswered;
  final int totalCorrect;

  /// Days on which the goal was reached.
  final int activeDays;
  final int exams;

  /// Best exam score in percent; null before the first exam.
  final int? bestExamPercent;

  bool get goalReachedToday => todayAnswered >= EngagementRules.dailyGoal;
}

/// Builds the numbers from every saved answer and exam. [now] is the phone's local time.
Engagement engagementFrom({
  required List<AnswerEvent> answers,
  required List<ExamEvent> exams,
  required DateTime now,
}) {
  final today = _day(now);
  final week = weekStart(now);
  final perDay = <DateTime, int>{};
  var xp = 0;
  var weekXp = 0;
  var correct = 0;
  for (final a in answers) {
    final day = _day(a.at);
    perDay[day] = (perDay[day] ?? 0) + 1;
    final points = a.correct ? EngagementRules.xpCorrect : EngagementRules.xpWrong;
    xp += points;
    if (!day.isBefore(week) && !day.isAfter(today)) weekXp += points;
    if (a.correct) correct++;
  }
  int? best;
  for (final e in exams) {
    if (e.answered >= EngagementRules.examMinAnswered) {
      xp += EngagementRules.xpExam;
      final day = _day(e.at);
      if (!day.isBefore(week) && !day.isAfter(today)) weekXp += EngagementRules.xpExam;
    }
    if (e.total > 0) {
      final percent = (100 * e.correct / e.total).round();
      if (best == null || percent > best) best = percent;
    }
  }

  final active = {
    for (final e in perDay.entries)
      if (e.value >= EngagementRules.dailyGoal) e.key,
  };

  // Best run: walk the active days in order (calendar days, so a time change does not break it).
  final sorted = active.toList()..sort();
  var bestStreak = 0;
  var run = 0;
  DateTime? previous;
  for (final d in sorted) {
    run = previous != null && _nextDay(previous) == d ? run + 1 : 1;
    if (run > bestStreak) bestStreak = run;
    previous = d;
  }

  // Current run: from today (or yesterday, if today's goal is not reached yet) backwards.
  var streak = 0;
  var cursor = active.contains(today) ? today : _previousDay(today);
  while (active.contains(cursor)) {
    streak++;
    cursor = _previousDay(cursor);
  }

  return Engagement(
    todayAnswered: perDay[today] ?? 0,
    streak: streak,
    bestStreak: bestStreak,
    xp: xp,
    weekXp: weekXp,
    totalAnswered: answers.length,
    totalCorrect: correct,
    activeDays: active.length,
    exams: exams.length,
    bestExamPercent: best,
  );
}

DateTime _nextDay(DateTime d) => DateTime(d.year, d.month, d.day + 1);
DateTime _previousDay(DateTime d) => DateTime(d.year, d.month, d.day - 1);

/// Badges (called achievements in code; Flutter has a Badge widget). `id` is stable (saved on the phone to know which ones are new).
enum Achievement {
  firstSteps('first_steps', 10),
  hundred('hundred', 100),
  thousand('thousand', 1000),
  streak3('streak_3', 3),
  streak7('streak_7', 7),
  streak30('streak_30', 30),
  firstExam('first_exam', 1),
  examStar('exam_80', 80),
  strongChapter('strong_1', 1),
  strongTen('strong_10', 10);

  const Achievement(this.id, this.goal);

  final String id;

  /// The number to reach (answers, streak days, exams, percent or strong chapters).
  final int goal;

  /// How far the student is, from 0 to [goal].
  int progress(Engagement e, {required int strongChapters}) {
    final value = switch (this) {
      firstSteps || hundred || thousand => e.totalAnswered,
      streak3 || streak7 || streak30 => e.bestStreak,
      firstExam => e.exams,
      examStar => e.bestExamPercent ?? 0,
      strongChapter || strongTen => strongChapters,
    };
    return value.clamp(0, goal);
  }

  bool earned(Engagement e, {required int strongChapters}) =>
      progress(e, strongChapters: strongChapters) >= goal;
}

/// One day of this week on the Home "This week" strip.
class WeekDay {
  const WeekDay({
    required this.day,
    required this.answered,
    required this.isToday,
    required this.isFuture,
  });

  /// Midnight (local time) of the day.
  final DateTime day;
  final int answered;
  final bool isToday;
  final bool isFuture;

  bool get reached => answered >= EngagementRules.dailyGoal;
}

/// Monday to Sunday of the week that holds [now], with the answers given on each day.
List<WeekDay> weekDays({required List<AnswerEvent> answers, required DateTime now}) {
  final today = _day(now);
  // Calendar days (not 24-hour steps), so a clock change can never shift a day.
  final mondayOffset = now.weekday - DateTime.monday;
  final days = [
    for (var i = 0; i < 7; i++) DateTime(today.year, today.month, today.day - mondayOffset + i),
  ];
  final counts = {for (final d in days) d: 0};
  for (final a in answers) {
    final d = _day(a.at);
    if (counts.containsKey(d)) counts[d] = counts[d]! + 1;
  }
  return [
    for (final d in days)
      WeekDay(day: d, answered: counts[d]!, isToday: d == today, isFuture: d.isAfter(today)),
  ];
}

/// The badge not earned yet that is closest to done (largest share of its goal); on a tie, the
/// one first in the list. Null when every badge is earned.
Achievement? nextAchievement(Engagement e, {required int strongChapters}) {
  Achievement? best;
  var bestShare = -1.0;
  for (final a in Achievement.values) {
    if (a.earned(e, strongChapters: strongChapters)) continue;
    final share = a.progress(e, strongChapters: strongChapters) / a.goal;
    if (share > bestShare) {
      best = a;
      bestShare = share;
    }
  }
  return best;
}
