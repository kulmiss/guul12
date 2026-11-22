import 'ethiopian_calendar.dart';
import 'mastery.dart';

/// Where the student is on the road to the exam.
enum PlanStage {
  /// No exam date known yet: learn and practise, no pace.
  noDate,

  /// More than [StudyPlanner.revisionDays] days left: learn new chapters and fix weak ones.
  learn,

  /// The last [StudyPlanner.revisionDays] days: weak chapters first, weekly mock exams.
  revision,

  /// The day before the exam: only a short review.
  lastDay,

  /// Exam day, or the exam is over: no tasks.
  done,
}

enum PlanTaskKind {
  review('review'),
  learn('learn'),
  practise('practise'),
  smart('smart'),
  mock('mock');

  const PlanTaskKind(this.wire);

  final String wire;

  static PlanTaskKind? fromWire(Object? value) {
    for (final k in values) {
      if (k.wire == value) return k;
    }
    return null;
  }
}

/// One thing to do today.
class PlanTask {
  const PlanTask({required this.kind, required this.minutes, this.chapterId, this.count});

  final PlanTaskKind kind;
  final int minutes;

  /// The chapter for [PlanTaskKind.learn] and [PlanTaskKind.practise].
  final int? chapterId;

  /// Questions to review, for [PlanTaskKind.review].
  final int? count;

  Map<String, Object?> toJson() => {
    'kind': kind.wire,
    'minutes': minutes,
    'chapter_id': chapterId,
    'count': count,
  };

  /// Null when the saved task is not readable (for example an older app wrote it).
  static PlanTask? fromJson(Map<String, Object?> json) {
    final kind = PlanTaskKind.fromWire(json['kind']);
    final minutes = json['minutes'];
    if (kind == null || minutes is! int) return null;
    final chapterId = json['chapter_id'];
    final count = json['count'];
    return PlanTask(
      kind: kind,
      minutes: minutes,
      chapterId: chapterId is int ? chapterId : null,
      count: count is int ? count : null,
    );
  }

  @override
  bool operator ==(Object other) =>
      other is PlanTask &&
      other.kind == kind &&
      other.minutes == minutes &&
      other.chapterId == chapterId &&
      other.count == count;

  @override
  int get hashCode => Object.hash(kind, minutes, chapterId, count);

  @override
  String toString() => 'PlanTask(${kind.wire}, $minutes min, chapter $chapterId, count $count)';
}

/// A chapter the student can study, in book order.
class PlanChapter {
  const PlanChapter({required this.id, required this.subjectId});

  final int id;
  final int subjectId;
}

/// The plan for one day plus the numbers behind it.
class StudyPlan {
  const StudyPlan({
    required this.stage,
    required this.daysLeft,
    required this.tasks,
    required this.chaptersLeft,
    required this.chaptersPerWeek,
    required this.chaptersAtTarget,
    required this.chaptersTotal,
  });

  final PlanStage stage;

  /// Days until the exam; null without an exam date.
  final int? daysLeft;
  final List<PlanTask> tasks;

  /// Chapters not started yet.
  final int chaptersLeft;

  /// New chapters a week needed to finish them before revision starts; null when not useful.
  final int? chaptersPerWeek;

  /// Chapters whose score reached the target.
  final int chaptersAtTarget;
  final int chaptersTotal;

  int get minutes => tasks.fold(0, (sum, t) => sum + t.minutes);
}

/// Builds the day's plan. Pure: the same input always gives the same plan.
class StudyPlanner {
  const StudyPlanner();

  static const revisionDays = 30;
  static const learnMinutes = 20;
  static const practiseMinutes = 10;
  static const smartMinutes = 10;
  static const mockMinutes = 75;
  static const maxReviewMinutes = 10;
  static const minutesChoices = [15, 30, 45, 60, 90];
  static const defaultMinutes = 30;

  /// [chapters]: every chapter for the student's stream, in book order.
  /// [targetPercent]: the target score as a share of the paper (0–100).
  /// [dueReviews]: questions whose spaced-repetition review is due today.
  StudyPlan build({
    required DateTime today,
    required DateTime? examDate,
    required int minutesPerDay,
    required List<PlanChapter> chapters,
    required Map<int, ChapterMastery> mastery,
    required int targetPercent,
    required int dueReviews,
  }) {
    final daysLeft = examDate == null ? null : daysUntil(examDate, today);
    final stage = switch (daysLeft) {
      null => PlanStage.noDate,
      <= 0 => PlanStage.done,
      1 => PlanStage.lastDay,
      <= revisionDays => PlanStage.revision,
      _ => PlanStage.learn,
    };

    bool started(PlanChapter c) => (mastery[c.id]?.answered ?? 0) > 0;
    int score(PlanChapter c) => mastery[c.id]?.score ?? 0;

    final notStarted = chapters.where((c) => !started(c)).toList();
    final atTarget = chapters.where((c) => started(c) && score(c) >= targetPercent).length;

    int? perWeek;
    final learnDays = stage == PlanStage.learn ? daysLeft! - revisionDays : 0;
    // A weekly pace only makes sense with at least a week of learning left.
    if (learnDays >= 7 && notStarted.isNotEmpty) {
      perWeek = (notStarted.length * 7 / learnDays).ceil();
    }

    final tasks = <PlanTask>[];
    var budget = minutesPerDay;
    void add(PlanTask t) {
      tasks.add(t);
      budget -= t.minutes;
    }

    if (dueReviews > 0 && stage != PlanStage.done) {
      add(
        PlanTask(
          kind: PlanTaskKind.review,
          minutes: dueReviews.clamp(1, maxReviewMinutes),
          count: dueReviews,
        ),
      );
    }

    if (stage == PlanStage.revision && daysLeft! % 7 == 0) {
      // Mock exam day: one full exam (it can take longer than the usual minutes).
      add(const PlanTask(kind: PlanTaskKind.mock, minutes: mockMinutes));
    } else if (stage != PlanStage.done && stage != PlanStage.lastDay) {
      _fill(
        add: add,
        budget: () => budget,
        hasTasks: () => tasks.any((t) => t.kind != PlanTaskKind.review),
        learnFirst: stage != PlanStage.revision,
        toLearn: _learnOrder(chapters, started),
        weak: _weakOrder(chapters, mastery, targetPercent),
        anyChapter: chapters.isNotEmpty,
      );
    }

    return StudyPlan(
      stage: stage,
      daysLeft: daysLeft,
      tasks: List.unmodifiable(tasks),
      chaptersLeft: notStarted.length,
      chaptersPerWeek: perWeek,
      chaptersAtTarget: atTarget,
      chaptersTotal: chapters.length,
    );
  }

  /// Alternates new chapters and weak chapters while minutes are left, then smart practice.
  /// If nothing fits, the shortest task is still added so every day has something to do.
  void _fill({
    required void Function(PlanTask) add,
    required int Function() budget,
    required bool Function() hasTasks,
    required bool learnFirst,
    required List<int> toLearn,
    required List<int> weak,
    required bool anyChapter,
  }) {
    var learnNext = learnFirst;
    var li = 0;
    var wi = 0;
    while (true) {
      final canLearn = li < toLearn.length && budget() >= learnMinutes;
      final canPractise = wi < weak.length && budget() >= practiseMinutes;
      if (!canLearn && !canPractise) break;
      if ((learnNext && canLearn) || !canPractise) {
        add(PlanTask(kind: PlanTaskKind.learn, minutes: learnMinutes, chapterId: toLearn[li++]));
      } else {
        add(PlanTask(kind: PlanTaskKind.practise, minutes: practiseMinutes, chapterId: weak[wi++]));
      }
      learnNext = !learnNext;
    }
    if (!anyChapter) return;
    if (budget() >= smartMinutes || !hasTasks()) {
      add(const PlanTask(kind: PlanTaskKind.smart, minutes: smartMinutes));
    }
  }

  /// Chapters not started, taking turns between subjects: the subject with the smallest share
  /// of started chapters goes next (ties: the subject that comes first), in book order inside it.
  List<int> _learnOrder(List<PlanChapter> chapters, bool Function(PlanChapter) started) {
    final subjects = <int>[];
    final total = <int, int>{};
    final done = <int, int>{};
    final queue = <int, List<int>>{};
    for (final c in chapters) {
      if (!total.containsKey(c.subjectId)) subjects.add(c.subjectId);
      total[c.subjectId] = (total[c.subjectId] ?? 0) + 1;
      if (started(c)) {
        done[c.subjectId] = (done[c.subjectId] ?? 0) + 1;
      } else {
        (queue[c.subjectId] ??= []).add(c.id);
      }
    }
    final out = <int>[];
    while (true) {
      int? best;
      for (final s in subjects) {
        if ((queue[s] ?? const []).isEmpty) continue;
        if (best == null || (done[s] ?? 0) * total[best]! < (done[best] ?? 0) * total[s]!) {
          best = s;
        }
      }
      if (best == null) return out;
      out.add(queue[best]!.removeAt(0));
      done[best] = (done[best] ?? 0) + 1;
    }
  }

  /// Started chapters below the target, weakest first (more answers first on a tie).
  List<int> _weakOrder(
    List<PlanChapter> chapters,
    Map<int, ChapterMastery> mastery,
    int targetPercent,
  ) {
    final list =
        [
          for (final c in chapters)
            if ((mastery[c.id]?.answered ?? 0) > 0 && mastery[c.id]!.score < targetPercent)
              mastery[c.id]!,
        ]..sort((a, b) {
          final byScore = a.score.compareTo(b.score);
          return byScore != 0 ? byScore : b.answered.compareTo(a.answered);
        });
    return [for (final m in list) m.chapterId];
  }
}
