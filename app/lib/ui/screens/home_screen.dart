import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/providers.dart';
import '../../app/review_providers.dart';
import '../../domain/ethiopian_calendar.dart';
import '../../domain/practice.dart';
import '../../l10n/generated/app_localizations.dart';
import '../practice/practice_screen.dart';
import '../theme.dart';
import '../widgets/common.dart';

/// "Sene 23, 2018" style text for an Ethiopian date.
String formatEthiopian(AppLocalizations l, DateTime gregorian) {
  final e = EthiopianDate.fromGregorian(gregorian);
  return l.ethiopianDate(l.ethiopianMonth('m${e.month}'), e.day, e.year);
}

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final config = ref.watch(configProvider).value;
    final online = ref.watch(onlineProvider);
    final catalog = ref.watch(catalogProvider).value;
    final recent = ref.watch(recentChaptersProvider).value ?? const <int>[];
    final saved = ref.watch(savedChaptersProvider).value ?? const <int>{};
    final today = ref.watch(clockProvider)();
    final maintenance = config?.maintenanceMessage;
    final due = ref.watch(reviewDueCountProvider).value ?? 0;
    final mistakes = ref.watch(mistakesProvider).value ?? const <int, List<Mistake>>{};
    final mistakeCount = mistakes.values.fold(0, (sum, list) => sum + list.length);

    final recentHeaders = [for (final id in recent) ?catalog?.chapter(id)];

    return Scaffold(
      appBar: AppBar(title: Text(l.appName)),
      body: RefreshIndicator(
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
                  if (!online) _Banner(icon: Icons.cloud_off, text: l.commonOffline),
                  if (maintenance != null && maintenance.isNotEmpty)
                    _Banner(icon: Icons.campaign_outlined, text: maintenance),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                    child: Text(l.homeGreeting, style: Theme.of(context).textTheme.headlineSmall),
                  ),
                  _CountdownCard(examDate: config?.examDate, today: today),
                  Card(
                    margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                    child: ListTile(
                      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                      leading: const CircleAvatar(child: Icon(Icons.bolt)),
                      title: Text(l.homeQuick10, style: Theme.of(context).textTheme.titleMedium),
                      subtitle: Text(saved.isEmpty ? l.homeNoContentYet : l.homeQuick10Body),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => saved.isEmpty
                          ? context.go('/study')
                          : context.push(PracticeScreen.playPath(PracticeMode.quick10)),
                    ),
                  ),
                  if (saved.isNotEmpty)
                    Card(
                      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                      child: ListTile(
                        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                        leading: const CircleAvatar(child: Icon(Icons.auto_awesome_outlined)),
                        title: Text(
                          l.practiceModeSmart,
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        subtitle: Text(l.homeSmartBody),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () => context.push(PracticeScreen.playPath(PracticeMode.smart)),
                      ),
                    ),
                  if (due > 0)
                    Card(
                      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                      color: Theme.of(context).colorScheme.secondaryContainer,
                      child: ListTile(
                        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                        leading: const CircleAvatar(child: Icon(Icons.event_repeat)),
                        title: Text(
                          l.homeReviewDue(due),
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        subtitle: Text(l.homeReviewBody),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () => context.push(PracticeScreen.playPath(PracticeMode.review)),
                      ),
                    ),
                  if (mistakeCount > 0)
                    Card(
                      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                      child: ListTile(
                        leading: const Icon(Icons.menu_book_outlined),
                        title: Text(l.homeMistakes),
                        subtitle: Text(l.homeMistakesBody(mistakeCount)),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () => context.push('/mistakes'),
                      ),
                    ),
                  if (saved.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                      child: Align(
                        alignment: AlignmentDirectional.centerStart,
                        child: TextButton.icon(
                          icon: const Icon(Icons.tune),
                          label: Text(l.practiceTitle),
                          onPressed: () => context.push('/practice'),
                        ),
                      ),
                    ),
                  if (recentHeaders.isNotEmpty) ...[
                    SectionTitle(l.homeContinueStudying),
                    for (final c in recentHeaders)
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
    );
  }
}

class _CountdownCard extends StatelessWidget {
  const _CountdownCard({required this.examDate, required this.today});

  final DateTime? examDate;
  final DateTime today;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final date = examDate;
    final days = date == null ? null : daysUntil(date, today);
    return Card(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      color: theme.colorScheme.primary,
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              date == null || days! < 0 ? l.homeExamDateUnknown : l.homeExamCountdown(days),
              style: theme.textTheme.headlineSmall?.copyWith(
                color: theme.colorScheme.onPrimary,
                fontFamily: 'Nunito',
                fontWeight: FontWeight.w900,
              ),
            ),
            if (date != null && days! >= 0) ...[
              const SizedBox(height: 6),
              Text(
                l.homeExamDates(
                  DateFormat.yMMMMd(l.localeName).format(date),
                  formatEthiopian(l, date),
                ),
                style: theme.textTheme.bodyMedium?.copyWith(color: SunriseColors.cream),
              ),
            ],
          ],
        ),
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
