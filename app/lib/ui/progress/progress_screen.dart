import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/progress_providers.dart';
import '../../app/providers.dart';
import '../../data/models.dart';
import '../../domain/mastery.dart';
import '../../domain/practice.dart';
import '../../l10n/generated/app_localizations.dart';
import '../practice/practice_screen.dart';
import '../screens/study_screens.dart';
import '../widgets/common.dart';

String masteryLabel(AppLocalizations l, MasteryLevel level) => switch (level) {
  MasteryLevel.notStarted => l.masteryNotStarted,
  MasteryLevel.learning => l.masteryLearning,
  MasteryLevel.good => l.masteryGood,
  MasteryLevel.strong => l.masteryStrong,
};

/// A small coloured label for a chapter's mastery level.
class MasteryChip extends StatelessWidget {
  const MasteryChip({required this.level, super.key});

  final MasteryLevel level;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final (bg, fg) = switch (level) {
      MasteryLevel.notStarted => (scheme.surfaceContainerHighest, scheme.onSurfaceVariant),
      MasteryLevel.learning => (scheme.errorContainer, scheme.onErrorContainer),
      MasteryLevel.good => (scheme.secondaryContainer, scheme.onSecondaryContainer),
      MasteryLevel.strong => (scheme.primaryContainer, scheme.onPrimaryContainer),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(8)),
      child: Text(
        masteryLabel(context.l10n, level),
        style: Theme.of(context).textTheme.labelMedium?.copyWith(color: fg),
      ),
    );
  }
}

/// Progress tab: how much is done, the weakest chapters, and every chapter's strength.
class ProgressScreen extends ConsumerWidget {
  const ProgressScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final mastery = ref.watch(masteryProvider).value ?? const <int, ChapterMastery>{};
    final catalog = ref.watch(catalogProvider).value ?? Catalog.empty;
    final stream = ref.watch(profileProvider).value?.effectiveStream;

    final answered = mastery.values.fold(0, (sum, m) => sum + m.answered);
    final correct = mastery.values.fold(0, (sum, m) => sum + m.correct);
    // Only chapters still at "learning" count as weak here; the smart mix also uses "good" ones.
    final weak = weakTopics(
      mastery.values.toList(),
      max: mastery.length,
    ).where((m) => m.level == MasteryLevel.learning).take(3).toList();

    String chapterName(int id) {
      final c = catalog.chapter(id);
      return c == null ? l.examUnknownChapter(id) : l.studyChapter(c.number, c.title);
    }

    return Scaffold(
      appBar: AppBar(title: Text(l.progressTitle)),
      body: answered == 0
          ? MessageView(message: l.progressEmpty, icon: Icons.insights_outlined)
          : ListView(
              padding: const EdgeInsets.only(bottom: 24),
              children: [
                ContentWidth(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Card(
                        margin: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                l.progressAnswered(answered),
                                style: Theme.of(context).textTheme.titleMedium,
                              ),
                              const SizedBox(height: 4),
                              Text(l.progressAccuracy((100 * correct / answered).round())),
                            ],
                          ),
                        ),
                      ),
                      SectionTitle(l.progressWeak),
                      if (weak.isEmpty)
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                          child: Text(l.progressWeakNone),
                        ),
                      for (final m in weak)
                        Card(
                          margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                          child: ListTile(
                            title: Text(chapterName(m.chapterId)),
                            subtitle: Text(l.progressChapterInfo(m.score, m.answered)),
                            trailing: TextButton(
                              onPressed: () => context.push(
                                PracticeScreen.playPath(
                                  PracticeMode.chapter,
                                  chapterId: m.chapterId,
                                ),
                              ),
                              child: Text(l.progressPractise),
                            ),
                          ),
                        ),
                      SectionTitle(l.progressStrength),
                      for (final subject in catalog.subjectsFor(stream))
                        _SubjectStrength(
                          subject: subject,
                          catalog: catalog,
                          stream: stream,
                          mastery: mastery,
                        ),
                    ],
                  ),
                ),
              ],
            ),
    );
  }
}

class _SubjectStrength extends StatelessWidget {
  const _SubjectStrength({
    required this.subject,
    required this.catalog,
    required this.stream,
    required this.mastery,
  });

  final Subject subject;
  final Catalog catalog;
  final String? stream;
  final Map<int, ChapterMastery> mastery;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final chapters = [
      for (final grade in catalog.gradesFor(subject.id, stream))
        for (final unit in catalog.unitsFor(subject.id, grade, stream))
          ...catalog.chaptersFor(unit.id),
    ];
    if (chapters.isEmpty) return const SizedBox.shrink();
    final started = chapters.where((c) => (mastery[c.id]?.answered ?? 0) > 0).length;
    return Card(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
      child: ExpansionTile(
        leading: Icon(subjectIcon(subject.code)),
        title: Text(subject.name),
        subtitle: Text(l.progressSubjectStarted(started, chapters.length)),
        shape: const Border(),
        collapsedShape: const Border(),
        children: [
          for (final c in chapters)
            ListTile(
              dense: true,
              title: Text(l.studyChapter(c.number, c.title)),
              subtitle: (mastery[c.id]?.answered ?? 0) > 0
                  ? Text(l.progressChapterInfo(mastery[c.id]!.score, mastery[c.id]!.answered))
                  : null,
              trailing: MasteryChip(level: mastery[c.id]?.level ?? MasteryLevel.notStarted),
              onTap: () => context.push('/study/chapter/${c.id}'),
            ),
        ],
      ),
    );
  }
}
