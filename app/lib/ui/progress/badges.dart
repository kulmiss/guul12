import 'package:material_ui/material_ui.dart';

import '../../app/engagement_providers.dart';
import '../../domain/engagement.dart';
import '../../l10n/generated/app_localizations.dart';

String achievementName(AppLocalizations l, Achievement a) => switch (a) {
  Achievement.firstSteps => l.badgeFirstSteps,
  Achievement.hundred => l.badgeHundred,
  Achievement.thousand => l.badgeThousand,
  Achievement.streak3 => l.badgeStreak3,
  Achievement.streak7 => l.badgeStreak7,
  Achievement.streak30 => l.badgeStreak30,
  Achievement.firstExam => l.badgeFirstExam,
  Achievement.examStar => l.badgeExamStar,
  Achievement.strongChapter => l.badgeStrongChapter,
  Achievement.strongTen => l.badgeStrongTen,
};

String achievementGoal(AppLocalizations l, Achievement a) => switch (a) {
  Achievement.firstSteps => l.badgeFirstStepsBody,
  Achievement.hundred => l.badgeHundredBody,
  Achievement.thousand => l.badgeThousandBody,
  Achievement.streak3 => l.badgeStreak3Body,
  Achievement.streak7 => l.badgeStreak7Body,
  Achievement.streak30 => l.badgeStreak30Body,
  Achievement.firstExam => l.badgeFirstExamBody,
  Achievement.examStar => l.badgeExamStarBody,
  Achievement.strongChapter => l.badgeStrongChapterBody,
  Achievement.strongTen => l.badgeStrongTenBody,
};

IconData achievementIcon(Achievement a) => switch (a) {
  Achievement.firstSteps => Icons.directions_walk,
  Achievement.hundred => Icons.looks_one_outlined,
  Achievement.thousand => Icons.emoji_events_outlined,
  Achievement.streak3 || Achievement.streak7 || Achievement.streak30 => Icons.local_fire_department,
  Achievement.firstExam => Icons.assignment_turned_in_outlined,
  Achievement.examStar => Icons.star_outline,
  Achievement.strongChapter || Achievement.strongTen => Icons.fitness_center,
};

/// One badge: icon, name, goal, and either "Earned" or the progress towards it.
class AchievementTile extends StatelessWidget {
  const AchievementTile({required this.achievement, required this.view, super.key});

  final Achievement achievement;
  final EngagementView view;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final earned = view.earned(achievement);
    final progress = view.progress(achievement);
    return ListTile(
      leading: CircleAvatar(
        backgroundColor: earned ? scheme.primaryContainer : scheme.surfaceContainerHighest,
        foregroundColor: earned ? scheme.onPrimaryContainer : scheme.outline,
        child: Icon(achievementIcon(achievement)),
      ),
      title: Text(achievementName(l, achievement)),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(achievementGoal(l, achievement)),
          if (!earned) ...[
            const SizedBox(height: 6),
            LinearProgressIndicator(
              value: progress / achievement.goal,
              semanticsLabel: achievementName(l, achievement),
              semanticsValue: '${(100 * progress / achievement.goal).round()}',
            ),
          ],
        ],
      ),
      trailing: Text(earned ? l.badgeEarned : l.badgeProgress(progress, achievement.goal)),
    );
  }
}

/// Streak, best streak and XP in one card.
class StreakCard extends StatelessWidget {
  const StreakCard({required this.view, super.key});

  final EngagementView view;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final e = view.engagement;
    return Card(
      margin: const EdgeInsets.fromLTRB(16, 16, 16, 0),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.local_fire_department, color: theme.colorScheme.secondary),
                const SizedBox(width: 8),
                Expanded(child: Text(l.streakDays(e.streak), style: theme.textTheme.titleMedium)),
                Text(l.engagementBest(e.bestStreak)),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              e.goalReachedToday
                  ? l.streakGoalDone
                  : l.streakToday(e.todayAnswered, EngagementRules.dailyGoal),
            ),
            const SizedBox(height: 6),
            Text('${l.engagementXp(e.xp)} · ${l.engagementWeekXp(e.weekXp)}'),
          ],
        ),
      ),
    );
  }
}
