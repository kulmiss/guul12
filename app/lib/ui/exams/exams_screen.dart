import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:material_ui/material_ui.dart';
import 'package:uuid/uuid.dart';

import '../../app/exam_providers.dart';
import '../../app/providers.dart';
import '../../data/api_client.dart';
import '../../data/models.dart';
import '../../domain/exam.dart';
import '../screens/study_screens.dart';
import '../widgets/common.dart';

/// Exams tab: fixed mocks per subject, the random / weak-topic / mistakes exams, and past results.
class ExamsScreen extends ConsumerStatefulWidget {
  const ExamsScreen({super.key});

  @override
  ConsumerState<ExamsScreen> createState() => _ExamsScreenState();
}

class _ExamsScreenState extends ConsumerState<ExamsScreen> {
  int? _subjectId;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    fireAndForget(_refresh());
  }

  /// Quietly refreshes the mock list (also tells the app which Premium mocks are open).
  Future<void> _refresh() async {
    try {
      await ref.read(repositoryProvider).refreshMocks();
      if (mounted) ref.invalidate(mocksProvider);
    } on ApiException {
      // Offline: the saved list is shown.
    }
  }

  Future<void> _start(Subject subject, ExamKind kind, {MockHeader? mock}) async {
    if (_busy) return;
    final l = context.l10n;
    final messenger = ScaffoldMessenger.of(context);
    final repo = ref.read(repositoryProvider);
    if (await repo.examInProgress() != null) {
      messenger.showSnackBar(SnackBar(content: Text(l.examsFinishFirst)));
      return;
    }
    if (!mounted) return;
    final title = switch (kind) {
      ExamKind.fixed => mock!.title,
      ExamKind.random => '${subject.name} · ${l.examsRandom}',
      ExamKind.weak => '${subject.name} · ${l.examsWeak}',
      ExamKind.mistakes => '${subject.name} · ${l.examsMistakes}',
    };

    setState(() => _busy = true);
    ExamPaper? paper;
    String? problem;
    try {
      paper = switch (kind) {
        ExamKind.fixed => await repo.mockPaper(mock!),
        ExamKind.random => await repo.randomPaper(subjectId: subject.id, title: title),
        ExamKind.weak || ExamKind.mistakes => await repo.personalPaper(
          kind: kind,
          subjectId: subject.id,
          title: title,
        ),
      };
      if (paper == null) problem = l.examsNeedPractice;
      if (paper != null && paper.questions.isEmpty) problem = l.examsNoQuestions;
    } on ApiException catch (e) {
      problem = switch (e.code) {
        'daily_limit' => l.examsDailyLimit,
        'premium_required' => l.studyPremiumLocked,
        'no_questions' => l.examsNoQuestions,
        _ => e.isOffline ? l.examsOffline : l.commonError,
      };
    } finally {
      if (mounted) setState(() => _busy = false);
    }
    if (!mounted) return;
    if (problem != null || paper == null) {
      messenger.showSnackBar(SnackBar(content: Text(problem ?? l.commonError)));
      return;
    }

    final ready = paper;
    final go = await showDialog<bool>(
      context: context,
      builder: (context) => _IntroDialog(paper: ready),
    );
    if (go != true || !mounted) return;
    final session = ExamSession(
      paper: ready,
      clientUuid: const Uuid().v4(),
      startedAt: ref.read(clockProvider)(),
    );
    await repo.saveExamProgress(session);
    ref.invalidate(examInProgressProvider);
    if (mounted) await context.push('/exam');
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final catalog = ref.watch(catalogProvider).value;
    final profile = ref.watch(profileProvider).value;
    final premium = profile?.premiumActive ?? false;
    final mocks = ref.watch(mocksProvider).value ?? const <MockHeader>[];
    final inProgress = ref.watch(examInProgressProvider).value;
    final results = ref.watch(examResultsProvider).value ?? const <ExamRecord>[];
    final now = ref.watch(clockProvider)();

    final subjects = (catalog?.subjectsFor(profile?.effectiveStream) ?? const <Subject>[])
        .where((s) => s.isExamSubject)
        .toList();
    final selected = subjects.where((s) => s.id == _subjectId).firstOrNull ?? subjects.firstOrNull;
    final subjectMocks = [
      for (final m in mocks)
        if (m.subjectId == selected?.id) m,
    ];
    final subjectResults = [
      for (final r in results)
        if (r.subjectId == selected?.id) r,
    ];

    return Scaffold(
      appBar: AppBar(
        title: Text(l.examsTitle),
        bottom: _busy
            ? const PreferredSize(
                preferredSize: Size.fromHeight(4),
                child: LinearProgressIndicator(),
              )
            : null,
      ),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: ListView(
          padding: const EdgeInsets.only(bottom: 24),
          children: [
            ContentWidth(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (inProgress != null)
                    Card(
                      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                      color: Theme.of(context).colorScheme.secondaryContainer,
                      child: ListTile(
                        leading: const Icon(Icons.timer_outlined),
                        title: Text(l.examsContinue),
                        subtitle: Text(
                          l.examsContinueBody(
                            inProgress.paper.title,
                            formatDuration(inProgress.remaining(now)),
                          ),
                        ),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () => context.push('/exam'),
                      ),
                    ),
                  if (subjects.isNotEmpty)
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                      child: Row(
                        children: [
                          for (final s in subjects)
                            Padding(
                              padding: const EdgeInsetsDirectional.only(end: 8),
                              child: ChoiceChip(
                                avatar: Icon(subjectIcon(s.code), size: 18),
                                label: Text(s.name),
                                selected: s.id == selected?.id,
                                onSelected: (_) => setState(() => _subjectId = s.id),
                              ),
                            ),
                        ],
                      ),
                    ),
                  if (selected != null) ...[
                    SectionTitle(l.examsMocks),
                    if (subjectMocks.isEmpty)
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: Text(l.examsNoMocks),
                      ),
                    for (final m in subjectMocks)
                      _ExamTile(
                        icon: Icons.assignment_outlined,
                        title: m.title,
                        subtitle: l.examsMockInfo(m.questionCount, m.minutes),
                        locked: m.locked,
                        onTap: () => _start(selected, ExamKind.fixed, mock: m),
                      ),
                    SectionTitle(l.examsMore),
                    _ExamTile(
                      icon: Icons.shuffle,
                      title: l.examsRandom,
                      subtitle: l.examsRandomBody,
                      locked: false,
                      onTap: () => _start(selected, ExamKind.random),
                    ),
                    _ExamTile(
                      icon: Icons.trending_down,
                      title: l.examsWeak,
                      subtitle: l.examsWeakBody,
                      locked: !premium,
                      onTap: () => _start(selected, ExamKind.weak),
                    ),
                    _ExamTile(
                      icon: Icons.replay,
                      title: l.examsMistakes,
                      subtitle: l.examsMistakesBody,
                      locked: !premium,
                      onTap: () => _start(selected, ExamKind.mistakes),
                    ),
                    if (subjectResults.isNotEmpty) ...[
                      SectionTitle(l.examsPast),
                      for (final r in subjectResults)
                        _ExamTile(
                          icon: Icons.fact_check_outlined,
                          title: r.title,
                          subtitle: l.examsResultLine(
                            r.correct,
                            r.total,
                            DateFormat.yMMMd(l.localeName).format(r.finishedAt.toLocal()),
                          ),
                          locked: false,
                          onTap: () => context.push('/exam/result/${r.clientUuid}'),
                        ),
                    ],
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

class _ExamTile extends StatelessWidget {
  const _ExamTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.locked,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  /// Shows "🔒 Premium" and does nothing on tap (Google Play: no buy path in the app).
  final bool locked;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
    child: ListTile(
      leading: Icon(icon),
      title: Text(title),
      subtitle: Text(subtitle),
      trailing: locked ? const PremiumLock() : const Icon(Icons.chevron_right),
      onTap: locked ? null : onTap,
    ),
  );
}

class _IntroDialog extends StatelessWidget {
  const _IntroDialog({required this.paper});

  final ExamPaper paper;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final rules = [l.examIntroTimer, l.examIntroNoFeedback, l.examIntroChange, l.examIntroAuto];
    return AlertDialog(
      title: Text(paper.title),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l.examsMockInfo(paper.questions.length, paper.minutes)),
            const SizedBox(height: 12),
            Text(l.examIntroRules, style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 4),
            for (final r in rules)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('• '),
                    Expanded(child: Text(r)),
                  ],
                ),
              ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(false),
          child: Text(MaterialLocalizations.of(context).cancelButtonLabel),
        ),
        FilledButton(onPressed: () => Navigator.of(context).pop(true), child: Text(l.examStart)),
      ],
    );
  }
}
