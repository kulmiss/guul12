import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../domain/engagement.dart';
import '../domain/mastery.dart';
import 'progress_providers.dart';
import 'providers.dart';

/// Streak, XP and badge numbers, from the answers and exams saved on the phone.
class EngagementView {
  const EngagementView({required this.engagement, required this.strongChapters});

  final Engagement engagement;
  final int strongChapters;

  bool earned(Achievement a) => a.earned(engagement, strongChapters: strongChapters);
  int progress(Achievement a) => a.progress(engagement, strongChapters: strongChapters);
  int get earnedCount => Achievement.values.where(earned).length;
}

final engagementProvider = FutureProvider<EngagementView>((ref) async {
  ref.watch(syncGenerationProvider);
  final repo = ref.watch(repositoryProvider);
  final now = ref.watch(clockProvider)();
  final mastery = await ref.watch(masteryProvider.future);
  final exams = await repo.examResults();
  final engagement = engagementFrom(
    answers: await repo.db.answerEvents(),
    exams: [
      for (final e in exams) (at: e.finishedAt.toLocal(), correct: e.correct, total: e.total),
    ],
    now: now,
  );
  return EngagementView(
    engagement: engagement,
    strongChapters: mastery.values.where((m) => m.level == MasteryLevel.strong).length,
  );
});
