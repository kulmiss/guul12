import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/challenge_providers.dart';
import '../../app/engagement_providers.dart';
import '../../app/plan_providers.dart';
import '../../app/premium_providers.dart';
import '../../app/progress_providers.dart';
import '../../app/providers.dart';
import '../../app/review_providers.dart';
import '../../data/branding.dart';
import '../../data/models.dart';
import '../../domain/engagement.dart';
import '../../domain/ethiopian_calendar.dart';
import '../../domain/mastery.dart';
import '../../domain/practice.dart';
import '../../domain/premium_notice.dart';
import '../../l10n/generated/app_localizations.dart';
import '../avatars.dart';
import '../plan/plan_screen.dart';
import '../practice/practice_screen.dart';
import '../progress/badges.dart';
import '../widgets/accents.dart';
import '../widgets/brand_logo.dart';
import '../widgets/common.dart';
import 'study_screens.dart';

/// "Sene 23, 2018" style text for an Ethiopian date.
String formatEthiopian(AppLocalizations l, DateTime gregorian) {
  final e = EthiopianDate.fromGregorian(gregorian);
  return l.ethiopianDate(l.ethiopianMonth('m${e.month}'), e.day, e.year);
}

/// "morning" (5:00–11:59), "afternoon" (12:00–16:59) or "evening", for the greeting.
String partOfDay(DateTime now) => switch (now.hour) {
  >= 5 && < 12 => 'morning',
  >= 12 && < 17 => 'afternoon',
  _ => 'evening',
};

/// How many tips the "Tip of the day" card takes turns with (see `homeTip` in the texts).
const homeTipCount = 10;

/// The tip for a day: each day the next one, the same all day long.
int tipOfTheDay(DateTime now) =>
    DateTime.utc(now.year, now.month, now.day).millisecondsSinceEpoch ~/
    Duration.millisecondsPerDay %
    homeTipCount;

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final config = ref.watch(configProvider).value;
    final online = ref.watch(onlineProvider);
    final catalog = ref.watch(catalogProvider).value;
    final profile = ref.watch(profileProvider).value;
    final recent = ref.watch(recentChaptersProvider).value ?? const <int>[];
    final saved = ref.watch(savedChaptersProvider).value ?? const <int>{};
    final now = ref.watch(clockProvider)();
    final maintenance = config?.maintenanceMessage;
    final due = ref.watch(reviewDueCountProvider).value ?? 0;
    final plan = ref.watch(todayPlanProvider).value;
    final engagementView = ref.watch(engagementProvider).value;
    final engagement = engagementView?.engagement ?? Engagement.empty;
    final mastery = ref.watch(masteryProvider).value ?? const <int, ChapterMastery>{};
    final weakest = learningTopics(mastery.values, max: 1).firstOrNull;
    final weakChapter = weakest == null ? null : catalog?.chapter(weakest.chapterId);
    final premiumNote = ref.watch(premiumNoticeProvider).value;
    final challengeOn = config?.feature('daily_challenge') ?? false;
    final battlesOn = config?.feature('battles') ?? false;
    final challenge = challengeOn ? ref.watch(dailyChallengeProvider).value : null;
    final mistakes = ref.watch(mistakesProvider).value ?? const <int, List<Mistake>>{};
    final mistakeCount = mistakes.values.fold(0, (sum, list) => sum + list.length);
    final brand = config?.branding ?? Branding.sunrise;

    final recentHeaders = [for (final id in recent) ?catalog?.chapter(id)];
    final subjects = catalog?.subjectsFor(profile?.stream) ?? const <Subject>[];

    final tiles = <_TileData>[
      if (plan != null)
        _TileData(
          icon: plan.allDone ? Icons.task_alt : Icons.checklist_rounded,
          title: l.planTitle,
          line: plan.allDone
              ? l.planAllDone
              : plan.saved.tasks.isEmpty
              ? planStageText(l, plan.plan)
              : '${l.planSummary(plan.saved.tasks.length, plan.minutes)} · '
                    '${plan.saved.done.length}/${plan.saved.tasks.length}',
          onTap: () => context.push('/home/plan'),
        ),
      if (challenge != null && challengeOn && challenge.questions.isNotEmpty)
        _TileData(
          icon: challenge.result == null ? Icons.flag_rounded : Icons.emoji_events_rounded,
          title: l.challengeTitle,
          line: _challengeLine(l, challenge),
          onTap: () => context.push(PracticeScreen.playPath(PracticeMode.dailyChallenge)),
        ),
      _TileData(
        icon: Icons.bolt_rounded,
        title: l.homeQuick10,
        line: saved.isEmpty ? l.homeNoContentYet : l.homeTileQuick,
        onTap: () => saved.isEmpty
            ? context.go('/study')
            : context.push(PracticeScreen.playPath(PracticeMode.quick10)),
      ),
      if (saved.isNotEmpty)
        _TileData(
          icon: Icons.auto_awesome_rounded,
          title: l.practiceModeSmart,
          line: l.homeTileSmart,
          onTap: () => context.push(PracticeScreen.playPath(PracticeMode.smart)),
        ),
      if (due > 0)
        _TileData(
          icon: Icons.event_repeat_rounded,
          title: l.homeTileReview,
          line: l.homeReviewDue(due),
          onTap: () => context.push(PracticeScreen.playPath(PracticeMode.review)),
        ),
      if (mistakeCount > 0)
        _TileData(
          icon: Icons.menu_book_rounded,
          title: l.homeMistakes,
          line: l.homeMistakesBody(mistakeCount),
          onTap: () => context.push('/mistakes'),
        ),
      if (battlesOn)
        _TileData(
          icon: Icons.sports_kabaddi_rounded,
          title: l.battleTitle,
          line: l.homeTileBattle,
          onTap: () => context.push('/home/battles'),
        ),
      if (saved.isNotEmpty)
        _TileData(
          icon: Icons.tune_rounded,
          title: l.homeTileMore,
          line: l.homeTileMoreLine,
          onTap: () => context.push('/practice'),
        ),
    ];

    var order = 0;
    Widget appear(Widget child) => _Appear(order: order++, child: child);

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator(
          onRefresh: () => runSync(ref),
          child: ListView(
            // Pull to refresh must work even when the list is shorter than the screen.
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.only(bottom: 24),
            children: [
              ContentWidth(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Padding(
                      padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
                      child: Row(
                        children: [
                          const BrandLogo(size: 28),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              brandName(l, brand),
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w900),
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (!online) _Banner(icon: Icons.cloud_off, text: l.commonOffline),
                    if (maintenance != null && maintenance.isNotEmpty)
                      _Banner(icon: Icons.campaign_outlined, text: maintenance),
                    if (premiumNote != null)
                      _Banner(
                        icon: Icons.workspace_premium_outlined,
                        text: switch (premiumNote) {
                          PremiumEnding(:final date) => l.premiumEnding(
                            DateFormat.yMMMd(l.localeName).format(date.toLocal()),
                          ),
                          PremiumEnded(:final date) => l.premiumEnded(
                            DateFormat.yMMMd(l.localeName).format(date.toLocal()),
                          ),
                        },
                      ),
                    appear(
                      _Hero(
                        greeting: profile?.nickname == null
                            ? l.homeGreeting(partOfDay(now))
                            : l.homeGreetingName(partOfDay(now), profile!.nickname!),
                        engagement: engagement,
                        examDate: config?.examDate,
                        today: now,
                        avatarStyle: profile?.avatarStyle,
                        avatarSeed: profile?.avatarSeed,
                      ),
                    ),
                    if (engagementView != null && engagementView.week.isNotEmpty)
                      appear(_WeekCard(week: engagementView.week)),
                    appear(
                      _ContinueCard(
                        chapter: recentHeaders.firstOrNull,
                        subject: recentHeaders.isEmpty || catalog == null
                            ? null
                            : _subjectOf(catalog, recentHeaders.first),
                      ),
                    ),
                    appear(SectionTitle(l.homeForYou)),
                    appear(_TileGrid(tiles: tiles)),
                    if (weakest != null && weakChapter != null)
                      appear(
                        _WeakCard(
                          mastery: weakest,
                          chapter: weakChapter,
                          subject: catalog == null ? null : _subjectOf(catalog, weakChapter),
                        ),
                      ),
                    if (engagementView?.next case final next?)
                      appear(_NextBadgeCard(achievement: next, view: engagementView!)),
                    if (subjects.isNotEmpty) ...[
                      appear(
                        _SectionHeader(
                          title: l.homeSubjects,
                          action: l.homeSeeAll,
                          onAction: () => context.go('/study'),
                        ),
                      ),
                      appear(_SubjectRow(subjects: subjects)),
                    ],
                    appear(_TipCard(tip: l.homeTip('${tipOfTheDay(now)}'))),
                    if (recentHeaders.length > 1) ...[
                      SectionTitle(l.homeContinueStudying),
                      for (final c in recentHeaders.skip(1))
                        Card(
                          margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                          child: ListTile(
                            leading: const Icon(Icons.menu_book_outlined),
                            title: Text(l.studyChapter(c.number, c.title)),
                            trailing: const Icon(Icons.chevron_right),
                            onTap: () => context.push('/study/chapter/${c.id}'),
                          ),
                        ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

String _challengeLine(AppLocalizations l, DailyChallengeInfo c) {
  final r = c.result;
  if (r == null) return l.homeTileChallenge(c.questions.length);
  final compare = r.betterThanPercent == null
      ? l.challengeCompareLater
      : l.challengeBetterThan(r.betterThanPercent!);
  return '${l.challengeDone(r.correct, r.total)} · $compare';
}

Subject? _subjectOf(Catalog catalog, ChapterHeader chapter) {
  final unit = catalog.units.where((u) => u.id == chapter.unitId).firstOrNull;
  if (unit == null) return null;
  return catalog.subjects.where((s) => s.id == unit.subjectId).firstOrNull;
}

/// Fades and slides a section in once, a little after the one above it. No looping animation;
/// nothing moves when the phone asks for less motion.
class _Appear extends StatelessWidget {
  const _Appear({required this.order, required this.child});

  final int order;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.disableAnimationsOf(context)) return child;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: Duration(milliseconds: 300 + order * 70),
      curve: Curves.easeOutCubic,
      builder: (_, t, child) => Opacity(
        opacity: t,
        child: Transform.translate(offset: Offset(0, 16 * (1 - t)), child: child),
      ),
      child: child,
    );
  }
}

/// The coloured card at the top: greeting, avatar, today's goal ring, streak and exam countdown.
class _Hero extends StatelessWidget {
  const _Hero({
    required this.greeting,
    required this.engagement,
    required this.examDate,
    required this.today,
    required this.avatarStyle,
    required this.avatarSeed,
  });

  final String greeting;
  final Engagement engagement;
  final DateTime? examDate;
  final DateTime today;
  final String? avatarStyle;
  final String? avatarSeed;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final on = scheme.onPrimary;
    const goal = EngagementRules.dailyGoal;
    final done = engagement.todayAnswered;
    final mood = engagement.goalReachedToday
        ? l.homeMoodDone
        : done == 0
        ? l.homeMoodStart
        : l.homeMoodGoing(goal - done);
    final date = examDate;
    final days = date == null ? null : daysUntil(date, today);
    final countdown = date == null || days! < 0 ? l.homeExamDateUnknown : l.homeExamCountdown(days);

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(color: scheme.primary, borderRadius: BorderRadius.circular(28)),
      child: Stack(
        children: [
          // Soft circles for a little life; pure decoration.
          Positioned(right: -40, top: -50, child: _Bubble(size: 160, color: on)),
          Positioned(left: -30, bottom: -60, child: _Bubble(size: 140, color: on)),
          Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Semantics(
                            header: true,
                            child: Text(
                              greeting,
                              style: theme.textTheme.headlineSmall?.copyWith(
                                color: on,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(mood, style: theme.textTheme.bodyMedium?.copyWith(color: on)),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    DecoratedBox(
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(color: on, width: 2),
                      ),
                      child: AvatarImage(style: avatarStyle, seed: avatarSeed, size: 52),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    _GoalRing(done: done, goal: goal),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Like before: no streak talk until the first answer.
                          if (engagement.totalAnswered > 0) ...[
                            _HeroChip(
                              icon: Icons.local_fire_department_rounded,
                              text: l.streakDays(engagement.streak),
                            ),
                            const SizedBox(height: 8),
                          ],
                          _HeroChip(icon: Icons.event_rounded, text: countdown),
                          if (date != null && days! >= 0)
                            Padding(
                              padding: const EdgeInsets.only(top: 6, left: 4),
                              child: Text(
                                l.homeExamDates(
                                  DateFormat.yMMMMd(l.localeName).format(date),
                                  formatEthiopian(l, date),
                                ),
                                style: theme.textTheme.bodySmall?.copyWith(color: on),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) => IgnorePointer(
    child: Container(
      width: size,
      height: size,
      decoration: BoxDecoration(shape: BoxShape.circle, color: color.withValues(alpha: 0.08)),
    ),
  );
}

class _HeroChip extends StatelessWidget {
  const _HeroChip({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.fromLTRB(6, 6, 12, 6),
      decoration: BoxDecoration(
        color: scheme.onPrimary.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            padding: const EdgeInsets.all(4),
            decoration: BoxDecoration(color: scheme.secondary, shape: BoxShape.circle),
            child: Icon(icon, size: 16, color: scheme.onSecondary),
          ),
          const SizedBox(width: 8),
          Flexible(
            child: Text(
              text,
              style: Theme.of(context).textTheme.labelLarge
                  ?.copyWith(color: scheme.onPrimary, fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }
}

/// Today's answers towards the daily goal, as a ring that fills up once.
class _GoalRing extends StatelessWidget {
  const _GoalRing({required this.done, required this.goal});

  final int done;
  final int goal;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final on = theme.colorScheme.onPrimary;
    final value = (done / goal).clamp(0.0, 1.0);
    final reduceMotion = MediaQuery.disableAnimationsOf(context);
    return Semantics(
      label: l.homeGoalRing(done, goal),
      child: ExcludeSemantics(
        child: Column(
          children: [
            SizedBox(
              width: 72,
              height: 72,
              child: Stack(
                alignment: Alignment.center,
                children: [
                  Positioned.fill(
                    child: TweenAnimationBuilder<double>(
                      tween: Tween(begin: reduceMotion ? value : 0, end: value),
                      duration: Duration(milliseconds: reduceMotion ? 0 : 900),
                      curve: Curves.easeOutCubic,
                      builder: (_, v, _) => CircularProgressIndicator(
                        value: v,
                        strokeWidth: 8,
                        strokeCap: StrokeCap.round,
                        color: theme.colorScheme.secondary,
                        backgroundColor: on.withValues(alpha: 0.22),
                      ),
                    ),
                  ),
                  if (done >= goal)
                    Icon(Icons.check_rounded, color: on, size: 32)
                  else
                    Text(
                      '$done/$goal',
                      style: theme.textTheme.titleMedium?.copyWith(
                        color: on,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 6),
            Text(l.homeGoalLabel, style: theme.textTheme.labelMedium?.copyWith(color: on)),
          ],
        ),
      ),
    );
  }
}

/// The big "carry on" card: the last chapter opened, or how to start.
class _ContinueCard extends StatelessWidget {
  const _ContinueCard({required this.chapter, required this.subject});

  final ChapterHeader? chapter;
  final Subject? subject;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final accent = accents(context)[1];
    final c = chapter;
    final label = c == null ? l.homeStartTitle : l.homeContinueTitle;
    final title = c == null ? l.homeStartBody : l.studyChapter(c.number, c.title);
    final icon = c == null ? Icons.rocket_launch_rounded : subjectIcon(subject?.code ?? '');
    return Card(
      key: const ValueKey('home-continue'),
      margin: const EdgeInsets.fromLTRB(16, 16, 16, 0),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => c == null ? context.go('/study') : context.push('/study/chapter/${c.id}'),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  color: accent.strong,
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Icon(icon, color: accent.onStrong, size: 30),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      label,
                      style: theme.textTheme.labelLarge?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    if (subject != null) Text(subject!.name, style: theme.textTheme.bodySmall),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              CircleAvatar(
                radius: 22,
                backgroundColor: theme.colorScheme.primary,
                child: Icon(Icons.play_arrow_rounded, color: theme.colorScheme.onPrimary),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TileData {
  const _TileData({
    required this.icon,
    required this.title,
    required this.line,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String line;
  final VoidCallback onTap;
}

/// Colourful activity tiles: 2 per row on a phone, 4 on a tablet. Tiles in a row share a height.
class _TileGrid extends StatelessWidget {
  const _TileGrid({required this.tiles});

  final List<_TileData> tiles;

  @override
  Widget build(BuildContext context) {
    final colours = accents(context);
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: LayoutBuilder(
        builder: (context, box) {
          final perRow = box.maxWidth >= 640 ? 4 : 2;
          return Column(
            children: [
              for (var start = 0; start < tiles.length; start += perRow)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: IntrinsicHeight(
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        for (var i = start; i < start + perRow; i++) ...[
                          if (i > start) const SizedBox(width: 12),
                          Expanded(
                            child: i < tiles.length
                                ? _Tile(data: tiles[i], accent: colours[i % colours.length])
                                : const SizedBox.shrink(),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

class _Tile extends StatelessWidget {
  const _Tile({required this.data, required this.accent});

  final _TileData data;
  final Accent accent;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Material(
      color: accent.soft,
      borderRadius: BorderRadius.circular(20),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: data.onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(color: accent.strong, shape: BoxShape.circle),
                child: Icon(data.icon, color: accent.onStrong, size: 22),
              ),
              const SizedBox(height: 10),
              Text(
                data.title,
                style: theme.textTheme.titleSmall?.copyWith(
                  color: accent.onSoft,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 2),
              Text(data.line, style: theme.textTheme.bodySmall?.copyWith(color: accent.onSoft)),
            ],
          ),
        ),
      ),
    );
  }
}

/// Monday to Sunday: a day fills up (green tick) once it reaches the daily goal.
class _WeekCard extends StatelessWidget {
  const _WeekCard({required this.week});

  final List<WeekDay> week;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final green = accents(context)[2];
    final reached = week.where((d) => d.reached).length;
    return Card(
      key: const ValueKey('home-week'),
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              l.homeWeekTitle,
              style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 10),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                for (final d in week)
                  Semantics(
                    container: true, // one spoken item per day
                    label: d.reached
                        ? l.homeWeekDayReached(DateFormat.EEEE(l.localeName).format(d.day))
                        : l.homeWeekDayCount(
                            DateFormat.EEEE(l.localeName).format(d.day),
                            d.answered,
                            EngagementRules.dailyGoal,
                          ),
                    child: ExcludeSemantics(
                      child: Column(
                        children: [
                          Text(
                            DateFormat.E(l.localeName).format(d.day),
                            style: theme.textTheme.labelSmall?.copyWith(
                              fontWeight: d.isToday ? FontWeight.w900 : FontWeight.w500,
                              color: d.isToday ? scheme.primary : scheme.onSurfaceVariant,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Container(
                            width: 32,
                            height: 32,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: d.reached
                                  ? green.strong
                                  : d.isFuture
                                  ? scheme.surfaceContainerHigh
                                  : Colors.transparent,
                              border: d.reached || d.isFuture
                                  ? null
                                  : Border.all(
                                      color: d.isToday ? scheme.primary : scheme.outlineVariant,
                                      width: d.isToday ? 2.5 : 1.5,
                                    ),
                            ),
                            child: d.reached
                                ? Icon(Icons.check_rounded, size: 20, color: green.onStrong)
                                : null,
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 10),
            Text(l.homeWeekCount(reached), style: theme.textTheme.bodySmall),
          ],
        ),
      ),
    );
  }
}

/// The weakest chapter the student started (same rule as the Progress tab), one tap to practise.
class _WeakCard extends StatelessWidget {
  const _WeakCard({required this.mastery, required this.chapter, required this.subject});

  final ChapterMastery mastery;
  final ChapterHeader chapter;
  final Subject? subject;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final accent = accents(context)[1];
    final percent = (100 * mastery.accuracy).round();
    return Card(
      key: const ValueKey('home-weak'),
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 0),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(color: accent.soft, shape: BoxShape.circle),
                  child: Icon(Icons.fitness_center_rounded, color: accent.onSoft),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        l.homeWeakTitle,
                        style: theme.textTheme.labelLarge?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                      Text(
                        l.studyChapter(chapter.number, chapter.title),
                        style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      Text(
                        subject == null
                            ? l.homeWeakPercent(percent)
                            : l.homeWeakLine(subject!.name, percent),
                        style: theme.textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Align(
              alignment: AlignmentDirectional.centerEnd,
              child: FilledButton.tonalIcon(
                icon: const Icon(Icons.play_arrow_rounded),
                label: Text(l.homeWeakAction),
                onPressed: () => context.push(
                  PracticeScreen.playPath(PracticeMode.chapter, chapterId: chapter.id),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// The badge closest to done, with how far the student is. Opens the Progress tab.
class _NextBadgeCard extends StatelessWidget {
  const _NextBadgeCard({required this.achievement, required this.view});

  final Achievement achievement;
  final EngagementView view;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final accent = accents(context)[0];
    final progress = view.progress(achievement);
    return Card(
      key: const ValueKey('home-badge'),
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.go('/progress'),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(color: accent.strong, shape: BoxShape.circle),
                child: Icon(achievementIcon(achievement), color: accent.onStrong),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      l.homeBadgeNext(achievementName(l, achievement)),
                      style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    Text(achievementGoal(l, achievement), style: theme.textTheme.bodySmall),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(6),
                            child: LinearProgressIndicator(
                              value: progress / achievement.goal,
                              minHeight: 8,
                              semanticsLabel: achievementName(l, achievement),
                              semanticsValue: '${(100 * progress / achievement.goal).round()}%',
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Text(
                          l.badgeProgress(progress, achievement.goal),
                          style: theme.textTheme.labelMedium,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.title, required this.action, required this.onAction});

  final String title;
  final String action;
  final VoidCallback onAction;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 12, 8, 0),
    child: Row(
      children: [
        Expanded(
          child: Semantics(
            header: true,
            child: Text(title, style: Theme.of(context).textTheme.titleMedium),
          ),
        ),
        TextButton(onPressed: onAction, child: Text(action)),
      ],
    ),
  );
}

/// The student's subjects as round shortcuts in one scrolling row.
class _SubjectRow extends StatelessWidget {
  const _SubjectRow({required this.subjects});

  final List<Subject> subjects;

  @override
  Widget build(BuildContext context) {
    final colours = accents(context);
    final theme = Theme.of(context);
    return SizedBox(
      height: 104,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: subjects.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, i) {
          final s = subjects[i];
          final accent = colours[i % colours.length];
          return SizedBox(
            width: 80,
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: () => context.push('/study/subject/${s.id}'),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Column(
                  children: [
                    Container(
                      width: 56,
                      height: 56,
                      decoration: BoxDecoration(color: accent.soft, shape: BoxShape.circle),
                      child: Icon(subjectIcon(s.code), color: accent.onSoft),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      s.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      textAlign: TextAlign.center,
                      style: theme.textTheme.labelMedium,
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _TipCard extends StatelessWidget {
  const _TipCard({required this.tip});

  final String tip;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final accent = accents(context)[2];
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: accent.soft, borderRadius: BorderRadius.circular(20)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.lightbulb_rounded, color: accent.onSoft, size: 28),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  l.homeTipTitle,
                  style: theme.textTheme.titleSmall?.copyWith(
                    color: accent.onSoft,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(tip, style: theme.textTheme.bodyMedium?.copyWith(color: accent.onSoft)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Banner extends StatelessWidget {
  const _Banner({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: scheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(icon, size: 20),
          const SizedBox(width: 8),
          Expanded(child: Text(text)),
        ],
      ),
    );
  }
}
