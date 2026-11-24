import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/providers.dart';
import '../../data/models.dart';
import '../widgets/common.dart';

/// Material icon for each subject code (the server's `icon` is a hint for the landing page).
IconData subjectIcon(String code) => switch (code) {
  'english' => Icons.translate,
  'mathematics' => Icons.calculate_outlined,
  'aptitude' => Icons.psychology_outlined,
  'physics' => Icons.bolt_outlined,
  'chemistry' => Icons.science_outlined,
  'biology' => Icons.eco_outlined,
  'geography' => Icons.public,
  'history' => Icons.history_edu_outlined,
  'economics' => Icons.trending_up,
  _ => Icons.menu_book_outlined,
};

/// Study tab: the subjects for the student's stream.
class StudyScreen extends ConsumerWidget {
  const StudyScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final catalog = ref.watch(catalogProvider);
    final stream = ref.watch(profileProvider).value?.effectiveStream;
    return Scaffold(
      appBar: AppBar(title: Text(l.studyTitle)),
      body: catalog.when(
        loading: () => const LoadingView(),
        error: (_, _) =>
            MessageView(message: l.commonError, onRetry: () => ref.invalidate(catalogProvider)),
        data: (catalog) {
          final subjects = catalog.subjectsFor(stream);
          if (subjects.isEmpty) {
            return MessageView(
              message: l.studyCatalogEmpty,
              icon: Icons.cloud_download_outlined,
              onRetry: () => runSync(ref),
            );
          }
          return LayoutBuilder(
            builder: (context, constraints) {
              final columns = constraints.maxWidth >= 900
                  ? 3
                  : constraints.maxWidth >= wideBreakpoint
                  ? 2
                  : 1;
              return GridView.builder(
                padding: const EdgeInsets.all(16),
                gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: columns,
                  mainAxisExtent: 76,
                  crossAxisSpacing: 12,
                  mainAxisSpacing: 12,
                ),
                itemCount: subjects.length,
                itemBuilder: (context, i) {
                  final s = subjects[i];
                  return Card(
                    margin: EdgeInsets.zero,
                    child: ListTile(
                      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                      leading: CircleAvatar(child: Icon(subjectIcon(s.code))),
                      title: Text(s.name, style: Theme.of(context).textTheme.titleMedium),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => context.push('/study/subject/${s.id}'),
                    ),
                  );
                },
              );
            },
          );
        },
      ),
    );
  }
}

/// One subject: pick a grade, then a unit and a chapter.
class SubjectScreen extends ConsumerStatefulWidget {
  const SubjectScreen({required this.subjectId, super.key});

  final int subjectId;

  @override
  ConsumerState<SubjectScreen> createState() => _SubjectScreenState();
}

class _SubjectScreenState extends ConsumerState<SubjectScreen> {
  int? _grade;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final catalog = ref.watch(catalogProvider).value;
    final profile = ref.watch(profileProvider).value;
    final saved = ref.watch(savedChaptersProvider).value ?? const <int>{};
    final subject = catalog?.subject(widget.subjectId);
    if (catalog == null || subject == null) {
      return Scaffold(appBar: AppBar(), body: const LoadingView());
    }
    final stream = profile?.effectiveStream;
    final grades = catalog.gradesFor(subject.id, stream);
    final grade =
        _grade ?? (grades.contains(profile?.grade) ? profile!.grade! : grades.firstOrNull);
    final units = grade == null ? const <Unit>[] : catalog.unitsFor(subject.id, grade, stream);

    return Scaffold(
      appBar: AppBar(title: Text(subject.name)),
      body: ListView(
        padding: const EdgeInsets.only(bottom: 24),
        children: [
          ContentWidth(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (grades.length > 1)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
                    child: SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: SegmentedButton<int>(
                        segments: [
                          for (final g in grades)
                            ButtonSegment(value: g, label: Text(l.studyGrade(g))),
                        ],
                        selected: {?grade},
                        onSelectionChanged: (s) => setState(() => _grade = s.first),
                      ),
                    ),
                  ),
                if (units.isEmpty) MessageView(message: l.studyEmpty),
                for (final unit in units)
                  _UnitTile(unit: unit, chapters: catalog.chaptersFor(unit.id), saved: saved),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _UnitTile extends StatelessWidget {
  const _UnitTile({required this.unit, required this.chapters, required this.saved});

  final Unit unit;
  final List<ChapterHeader> chapters;
  final Set<int> saved;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Card(
      margin: const EdgeInsets.fromLTRB(16, 8, 16, 0),
      clipBehavior: Clip.antiAlias,
      child: ExpansionTile(
        title: Text(l.studyUnit(unit.number, unit.title)),
        children: [
          if (chapters.isEmpty) ListTile(title: Text(l.studyNoChapters)),
          for (final c in chapters)
            ListTile(
              title: Text(l.studyChapter(c.number, c.title)),
              trailing: saved.contains(c.id)
                  ? Tooltip(
                      message: l.studyDownloadedOffline,
                      child: const Icon(Icons.offline_pin_outlined),
                    )
                  : const Icon(Icons.chevron_right),
              onTap: () => context.push('/study/chapter/${c.id}'),
            ),
        ],
      ),
    );
  }
}
