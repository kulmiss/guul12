import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/models.dart';
import '../data/repository.dart';
import '../domain/study_plan.dart';
import '../ui/screens/profile_fields.dart' show defaultTarget, maxTarget;
import 'progress_providers.dart';
import 'providers.dart';
import 'review_providers.dart';

/// Today's plan: the saved task list (it stays the same all day) and fresh numbers.
class TodayPlan {
  const TodayPlan({required this.plan, required this.saved});

  /// Built now: stage, days left, pace and chapters at target are always current.
  final StudyPlan plan;

  /// The tasks for today, saved the first time the plan was built today, and the ticks.
  final SavedPlan saved;

  int get minutes => saved.tasks.fold(0, (sum, t) => sum + t.minutes);
  bool get allDone => saved.tasks.isNotEmpty && saved.done.length == saved.tasks.length;
}

final planMinutesProvider = FutureProvider<int>(
  (ref) => ref.watch(repositoryProvider).planMinutes(),
);

/// Every chapter for the student's stream, in book order (subject, grade, unit, chapter).
List<PlanChapter> planChapters(Catalog catalog, String? stream) => [
  for (final subject in catalog.subjectsFor(stream))
    for (final grade in catalog.gradesFor(subject.id, stream))
      for (final unit in catalog.unitsFor(subject.id, grade, stream))
        for (final c in catalog.chaptersFor(unit.id)) PlanChapter(id: c.id, subjectId: subject.id),
];

final todayPlanProvider = FutureProvider<TodayPlan>((ref) async {
  final repo = ref.watch(repositoryProvider);
  final today = ref.watch(clockProvider)();
  final catalog = await ref.watch(catalogProvider.future);
  final profile = await ref.watch(profileProvider.future);
  final config = await ref.watch(configProvider.future);
  final mastery = await ref.watch(masteryProvider.future);
  final due = await ref.watch(reviewDueCountProvider.future);
  final minutes = await ref.watch(planMinutesProvider.future);

  final target = (profile.targetScore ?? defaultTarget).clamp(0, maxTarget);
  final plan = const StudyPlanner().build(
    today: today,
    examDate: config.examDate,
    minutesPerDay: minutes,
    chapters: planChapters(catalog, profile.effectiveStream),
    mastery: mastery,
    targetPercent: (100 * target / maxTarget).round(),
    dueReviews: due,
  );

  var saved = await repo.savedPlan(today);
  // Build it again when the minutes changed, or when an empty plan (no chapters yet) can now
  // have tasks. Otherwise the saved tasks stay, so the list does not move while the student works.
  if (saved == null || saved.minutes != minutes || (saved.tasks.isEmpty && plan.tasks.isNotEmpty)) {
    saved = SavedPlan(day: planDay(today), minutes: minutes, tasks: plan.tasks, done: const {});
    await repo.savePlan(saved);
  }
  return TodayPlan(plan: plan, saved: saved);
});
