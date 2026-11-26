import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/engagement_providers.dart';
import '../../app/statistics_providers.dart';
import '../../domain/mastery.dart';
import '../../domain/statistics.dart';
import '../../l10n/generated/app_localizations.dart';
import '../theme.dart';
import '../widgets/common.dart';
import 'charts.dart';
import 'progress_screen.dart';

/// "2 h 5 min", "12 min" or "40 s".
String formatStudyTime(AppLocalizations l, Duration d) {
  final minutes = d.inMinutes;
  if (minutes >= 60) return l.statsHours(minutes ~/ 60, minutes % 60);
  if (minutes > 0) return l.statsMinutes(minutes);
  return l.statsSeconds(d.inSeconds);
}

/// Dates under a chart: with more than [max], only every other one (counted from the last), so
/// they never run into each other on a phone.
List<String> sparseLabels(List<String> labels, {int max = 6}) => labels.length <= max
    ? labels
    : [for (var i = 0; i < labels.length; i++) (labels.length - 1 - i).isEven ? labels[i] : ''];

/// The likely exam score as text: the range, and the change this week when known.
String readinessChangeText(AppLocalizations l, Readiness r) {
  final change = r.change;
  if (change == null) return l.readinessBasedOn(r.basedOn);
  if (change > 0) return l.readinessUp(change);
  if (change < 0) return l.readinessDown(-change);
  return l.readinessSame;
}

/// Statistics: summary numbers and charts, all from the answers and exams saved on the phone.
class StatisticsScreen extends ConsumerWidget {
  const StatisticsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final async = ref.watch(statisticsProvider);
    final engagement = ref.watch(engagementProvider).value?.engagement;
    return Scaffold(
      appBar: AppBar(title: Text(l.statsTitle)),
      body: switch (async) {
        AsyncData(:final value) => _Body(view: value, bestStreak: engagement?.bestStreak),
        AsyncError() => MessageView(
          icon: Icons.error_outline,
          message: l.commonError,
          onRetry: () => ref.invalidate(statisticsProvider),
        ),
        _ => const LoadingView(),
      },
    );
  }
}

class _Body extends StatelessWidget {
  const _Body({required this.view, required this.bestStreak});

  final StatisticsView view;
  final int? bestStreak;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final s = view.statistics;
    if (s.isEmpty) {
      return MessageView(icon: Icons.insights_outlined, message: l.statsEmpty);
    }
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final feedback = context.feedback;
    final locale = l.localeName;
    final dayMonth = DateFormat.MMMd(locale);
    final short = DateFormat.Md(locale);
    final readiness = view.readiness;
    final todayIndex = s.heatmap.lastIndexWhere((d) => !d.isFuture);
    final bestExam = s.exams.isEmpty
        ? null
        : s.exams.map((e) => e.percent).reduce((a, b) => a > b ? a : b);
    final monday = s.heatmap.first.day;

    return ListView(
      padding: const EdgeInsets.only(bottom: 24),
      children: [
        ContentWidth(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                child: Text(l.statsTapHint, style: theme.textTheme.bodySmall),
              ),
              _SummaryGrid(
                tiles: [
                  (Icons.quiz_outlined, l.statsAnswered, '${s.answered}'),
                  (
                    Icons.check_circle_outline,
                    l.statsRight,
                    s.accuracyPercent == null ? l.statsNoValue : l.statsPercent(s.accuracyPercent!),
                  ),
                  (Icons.calendar_month_outlined, l.statsDaysStudied, l.statsDays(s.daysStudied)),
                  (
                    Icons.local_fire_department_outlined,
                    l.statsBestStreak,
                    bestStreak == null ? l.statsNoValue : l.statsDays(bestStreak!),
                  ),
                  (Icons.assignment_outlined, l.statsExams, '${s.exams.length}'),
                  (
                    Icons.emoji_events_outlined,
                    l.statsBestExam,
                    bestExam == null ? l.statsNoValue : l.statsPercent(bestExam),
                  ),
                  (
                    Icons.timer_outlined,
                    l.statsStudyTime,
                    s.timedAnswers == 0 ? l.statsNoValue : formatStudyTime(l, s.studyTime),
                  ),
                  (
                    Icons.speed_outlined,
                    l.statsPerQuestion,
                    s.secondsPerAnswer == null
                        ? l.statsNoValue
                        : l.statsSeconds(s.secondsPerAnswer!),
                  ),
                ],
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                child: Text(l.statsStudyTimeNote, style: theme.textTheme.bodySmall),
              ),
              _ChartCard(
                key: const ValueKey('stats-readiness'),
                title: l.homeReadinessTitle,
                child: readiness == null
                    ? Text(l.readinessNeedMore(Readiness.minAnswers - s.answered.clamp(0, 19)))
                    : Column(
                        children: [
                          RangeGauge(
                            low: readiness.low,
                            high: readiness.high,
                            color: scheme.primary,
                            track: scheme.surfaceContainerHighest,
                            width: 200,
                            centre: Text(
                              '${readiness.low}–${readiness.high}%',
                              style: theme.textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            '${l.readinessRange(readiness.low, readiness.high)} · '
                            '${readinessChangeText(l, readiness)}',
                            textAlign: TextAlign.center,
                            style: theme.textTheme.titleSmall,
                          ),
                          const SizedBox(height: 6),
                          Text(
                            l.readinessNote,
                            textAlign: TextAlign.center,
                            style: theme.textTheme.bodySmall,
                          ),
                        ],
                      ),
              ),
              _ChartCard(
                key: const ValueKey('stats-days'),
                title: l.statsDaysTitle,
                subtitle: l.statsDaysSubtitle,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    StackedBarChart(
                      bars: [for (final d in s.lastDays) (bottom: d.right, top: d.wrong)],
                      labels: [for (final d in s.lastDays) '${d.day.day}'],
                      describe: (i) => l.statsDayDetail(
                        dayMonth.format(s.lastDays[i].day),
                        s.lastDays[i].right,
                        s.lastDays[i].wrong,
                      ),
                      bottomColor: feedback.correct,
                      topColor: feedback.wrong,
                    ),
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 16,
                      children: [
                        ChartLegendItem(color: feedback.correct, label: l.statsRight),
                        ChartLegendItem(color: feedback.wrong, label: l.statsWrong),
                      ],
                    ),
                  ],
                ),
              ),
              _ChartCard(
                key: const ValueKey('stats-weeks'),
                title: l.statsWeeksTitle,
                subtitle: l.statsWeeksSubtitle,
                child: PercentLineChart(
                  values: [for (final w in s.weeks) w.percent],
                  labels: sparseLabels([for (final w in s.weeks) short.format(w.start)]),
                  describe: (i) {
                    final w = s.weeks[i];
                    final date = dayMonth.format(w.start);
                    return w.percent == null
                        ? l.statsWeekNone(date)
                        : l.statsWeekDetail(date, w.percent!, w.answered);
                  },
                  color: scheme.primary,
                ),
              ),
              _ChartCard(
                key: const ValueKey('stats-exams'),
                title: l.statsExamsTitle,
                subtitle: l.statsExamsSubtitle,
                child: s.exams.isEmpty
                    ? Text(l.statsExamsNone)
                    : PercentLineChart(
                        values: [for (final e in s.exams) e.percent],
                        labels: sparseLabels([for (final e in s.exams) short.format(e.at)]),
                        describe: (i) => l.statsExamDetail(
                          dayMonth.format(s.exams[i].at),
                          s.exams[i].title,
                          s.exams[i].percent,
                        ),
                        color: scheme.primary,
                      ),
              ),
              if (s.subjects.isNotEmpty)
                _ChartCard(
                  key: const ValueKey('stats-subjects'),
                  title: l.statsSubjectsTitle,
                  child: ShareBars(
                    color: scheme.primary,
                    rows: [
                      for (final x in s.subjects)
                        (
                          label: view.subjectNames[x.subjectId] ?? '',
                          percent: x.percent,
                          value: _shareText(l, x),
                        ),
                    ],
                  ),
                ),
              _ChartCard(
                key: const ValueKey('stats-mastery'),
                title: l.statsMasteryTitle,
                child: SegmentBar(
                  segments: [
                    for (final (level, color) in [
                      (MasteryLevel.strong, scheme.primary),
                      (MasteryLevel.good, scheme.secondary),
                      (MasteryLevel.learning, scheme.error),
                      (MasteryLevel.notStarted, scheme.surfaceContainerHighest),
                    ])
                      (
                        value: view.mastery[level] ?? 0,
                        color: color,
                        label: l.statsMasteryItem(masteryLabel(l, level), view.mastery[level] ?? 0),
                      ),
                  ],
                ),
              ),
              _ChartCard(
                key: const ValueKey('stats-heatmap'),
                title: l.statsHeatmapTitle,
                subtitle: l.statsHeatmapSubtitle,
                child: ActivityHeatmap(
                  days: [for (final d in s.heatmap) (count: d.total, isFuture: d.isFuture)],
                  initial: todayIndex < 0 ? 0 : todayIndex,
                  describe: (i) =>
                      l.statsHeatmapDetail(dayMonth.format(s.heatmap[i].day), s.heatmap[i].total),
                  color: scheme.primary,
                  dayLabels: [
                    for (var i = 0; i < 7; i++)
                      DateFormat.E(locale)
                          .format(DateTime(monday.year, monday.month, monday.day + i)),
                  ],
                  lessLabel: l.statsLess,
                  moreLabel: l.statsMore,
                ),
              ),
              _ChartCard(
                key: const ValueKey('stats-parts'),
                title: l.statsPartsTitle,
                child: ShareBars(
                  color: scheme.secondary,
                  rows: [
                    for (final p in s.partsOfDay)
                      (label: l.statsPart(p.part), percent: p.percent, value: _shareText(l, p)),
                  ],
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

String _shareText(AppLocalizations l, ShareRight x) =>
    x.percent == null ? l.statsShareNone : l.statsShareLine(x.percent!, x.answered);

/// Summary numbers, two per row on a phone and four on a tablet.
class _SummaryGrid extends StatelessWidget {
  const _SummaryGrid({required this.tiles});

  final List<(IconData, String, String)> tiles;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      child: LayoutBuilder(
        builder: (context, box) {
          final perRow = box.maxWidth >= 640 ? 4 : 2;
          final width = (box.maxWidth - 8 * (perRow - 1)) / perRow;
          return Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final (icon, label, value) in tiles)
                SizedBox(
                  width: width,
                  child: MergeSemantics(
                    child: Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: theme.colorScheme.surfaceContainerLow,
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: Row(
                        children: [
                          Icon(icon, color: theme.colorScheme.primary, size: 22),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  value,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: theme.textTheme.titleMedium?.copyWith(
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                                Text(
                                  label,
                                  maxLines: 2,
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    color: theme.colorScheme.onSurfaceVariant,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
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

class _ChartCard extends StatelessWidget {
  const _ChartCard({required this.title, required this.child, this.subtitle, super.key});

  final String title;
  final String? subtitle;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      margin: const EdgeInsets.fromLTRB(16, 16, 16, 0),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Semantics(
              header: true,
              child: Text(
                title,
                style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
              ),
            ),
            if (subtitle != null)
              Text(
                subtitle!,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            const SizedBox(height: 12),
            child,
          ],
        ),
      ),
    );
  }
}
