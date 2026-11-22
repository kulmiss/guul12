import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/exam_providers.dart';
import '../../app/providers.dart';
import '../../data/models.dart';
import '../../domain/exam.dart';
import '../widgets/common.dart';
import '../widgets/math_text.dart';

/// Real Exam Mode: one question at a time, a clock that never stops, flags, a question map,
/// and nothing marked right or wrong until the exam is submitted (by the student or the clock).
class ExamScreen extends ConsumerStatefulWidget {
  const ExamScreen({super.key});

  @override
  ConsumerState<ExamScreen> createState() => _ExamScreenState();
}

class _ExamScreenState extends ConsumerState<ExamScreen> with WidgetsBindingObserver {
  ExamSession? _session;
  bool _loaded = false;
  bool _submitting = false;
  Timer? _ticker;

  DateTime _now() => ref.read(clockProvider)();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    fireAndForget(_load());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _ticker?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // The clock kept running while the app was away; catch up at once.
    if (state == AppLifecycleState.resumed) _tick();
  }

  Future<void> _load() async {
    final session = await ref.read(repositoryProvider).examInProgress();
    if (!mounted) return;
    setState(() {
      _session = session;
      _loaded = true;
    });
    if (session == null) return;
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) => _tick());
    _tick();
  }

  void _tick() {
    final session = _session;
    if (!mounted || session == null || _submitting) return;
    if (session.isTimeUp(_now())) {
      fireAndForget(_submit(timeUp: true));
    } else {
      setState(() {});
    }
  }

  Future<void> _save() async {
    final session = _session;
    if (session != null) await ref.read(repositoryProvider).saveExamProgress(session);
  }

  void _change(void Function(ExamSession s) change) {
    final session = _session;
    if (session == null || _submitting) return;
    setState(() => change(session));
    fireAndForget(_save());
  }

  Future<void> _submit({required bool timeUp}) async {
    final session = _session;
    if (session == null || _submitting) return;
    setState(() => _submitting = true);
    _ticker?.cancel();
    final l = context.l10n;
    final messenger = ScaffoldMessenger.of(context);
    final router = GoRouter.of(context);
    final record = await ref
        .read(repositoryProvider)
        .submitExam(session, now: _now(), appVersion: ref.read(appVersionProvider));
    ref
      ..invalidate(examInProgressProvider)
      ..invalidate(examResultsProvider);
    if (timeUp) messenger.showSnackBar(SnackBar(content: Text(l.examTimeUp)));
    if (mounted) fireAndForget(router.pushReplacement('/exam/result/${record.clientUuid}'));
  }

  Future<void> _confirmSubmit() async {
    final session = _session;
    if (session == null) return;
    final l = context.l10n;
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l.examSubmitTitle),
        content: Text(
          l.examSubmitBody(
            session.answeredCount,
            session.total,
            session.unansweredCount,
            session.flagged.length,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(l.examKeepGoing),
          ),
          FilledButton(onPressed: () => Navigator.of(context).pop(true), child: Text(l.examSubmit)),
        ],
      ),
    );
    if (ok == true && mounted) await _submit(timeUp: false);
  }

  Future<bool> _confirmLeave() async {
    final l = context.l10n;
    final leave = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l.examLeaveTitle),
        content: Text(l.examLeaveBody),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(l.examKeepGoing),
          ),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: Text(l.examLeave)),
        ],
      ),
    );
    return leave ?? false;
  }

  Future<void> _openMap() async {
    final session = _session;
    if (session == null) return;
    final target = await showModalBottomSheet<int>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (context) => _QuestionMap(session: session),
    );
    if (target != null) _change((s) => s.goTo(target, _now()));
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final session = _session;
    if (!_loaded) return const Scaffold(body: LoadingView());
    if (session == null) {
      return Scaffold(
        appBar: AppBar(),
        body: MessageView(message: l.commonError, icon: Icons.assignment_late_outlined),
      );
    }

    final q = session.current;
    final passage = session.currentPassage;
    final left = session.remaining(_now());
    final low = left.inMinutes < 5;
    final scheme = Theme.of(context).colorScheme;

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop || _submitting) return;
        if (await _confirmLeave() && context.mounted) context.pop();
      },
      child: Scaffold(
        appBar: AppBar(
          title: Semantics(
            label: l.examTimeLeft(formatDuration(left)),
            excludeSemantics: true,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.timer_outlined, color: low ? scheme.error : null),
                const SizedBox(width: 6),
                Text(
                  formatDuration(left),
                  style: TextStyle(
                    color: low ? scheme.error : null,
                    fontFeatures: const [FontFeature.tabularFigures()],
                  ),
                ),
              ],
            ),
          ),
          actions: [
            IconButton(
              tooltip: session.currentFlagged ? l.examUnflag : l.examFlag,
              isSelected: session.currentFlagged,
              icon: const Icon(Icons.outlined_flag),
              selectedIcon: Icon(Icons.flag, color: scheme.tertiary),
              onPressed: () => _change((s) => s.toggleFlag()),
            ),
            IconButton(tooltip: l.examMap, icon: const Icon(Icons.grid_view), onPressed: _openMap),
          ],
        ),
        body: Column(
          children: [
            LinearProgressIndicator(value: session.answeredCount / session.total),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                children: [
                  ContentWidth(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: Text(
                            l.examQuestionOf(session.index + 1, session.total),
                            style: Theme.of(context).textTheme.labelLarge,
                          ),
                        ),
                        if (passage != null)
                          Card(
                            margin: const EdgeInsets.only(bottom: 12),
                            child: ExpansionTile(
                              initiallyExpanded: true,
                              title: Text(l.practiceReadPassage),
                              subtitle: Text(passage.title),
                              childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                              children: [MathText(passage.body)],
                            ),
                          ),
                        MathText(q.stem, style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: 16),
                        for (final o in q.options)
                          _ChoiceTile(
                            key: ValueKey('${q.id}-${o.key}'),
                            option: o,
                            trueFalse: q.type == QuestionType.trueFalse,
                            selected: session.currentAnswer == o.key,
                            onTap: () => _change((s) => s.choose(o.key, _now())),
                          ),
                        if (session.currentAnswer != null)
                          Align(
                            alignment: AlignmentDirectional.centerEnd,
                            child: TextButton(
                              onPressed: () => _change((s) => s.clear(_now())),
                              child: Text(l.examClear),
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                child: ContentWidth(
                  child: Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: session.index == 0
                              ? null
                              : () => _change((s) => s.goTo(s.index - 1, _now())),
                          child: Text(l.examPrevious),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: session.index == session.total - 1
                            ? FilledButton(
                                key: const ValueKey('submit'),
                                onPressed: _submitting ? null : _confirmSubmit,
                                child: Text(l.examSubmit),
                              )
                            : FilledButton(
                                key: const ValueKey('next'),
                                onPressed: () => _change((s) => s.goTo(s.index + 1, _now())),
                                child: Text(l.commonNext),
                              ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ChoiceTile extends StatelessWidget {
  const _ChoiceTile({
    required this.option,
    required this.trueFalse,
    required this.selected,
    required this.onTap,
    super.key,
  });

  final QuestionOption option;
  final bool trueFalse;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final scheme = Theme.of(context).colorScheme;
    final label = trueFalse
        ? (option.key.toUpperCase() == 'TRUE' ? l.practiceTrue : l.practiceFalse)
        : option.body;
    return Semantics(
      selected: selected,
      button: true,
      child: Card(
        margin: const EdgeInsets.only(bottom: 8),
        color: selected ? scheme.primaryContainer : null,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(
            color: selected ? scheme.primary : scheme.outlineVariant,
            width: selected ? 2 : 1,
          ),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: [
                if (!trueFalse) ...[
                  CircleAvatar(
                    radius: 14,
                    backgroundColor: selected ? scheme.primary : scheme.surfaceContainerHighest,
                    foregroundColor: selected ? scheme.onPrimary : scheme.onSurface,
                    child: Text(option.key, style: const TextStyle(fontWeight: FontWeight.w700)),
                  ),
                  const SizedBox(width: 12),
                ],
                Expanded(child: MathText(label)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _QuestionMap extends StatelessWidget {
  const _QuestionMap({required this.session});

  final ExamSession session;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final scheme = Theme.of(context).colorScheme;
    Widget legend(Color fill, IconData? icon, String text) => Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 16,
          height: 16,
          decoration: BoxDecoration(
            color: fill,
            border: Border.all(color: scheme.outline),
            borderRadius: BorderRadius.circular(4),
          ),
          child: icon == null ? null : Icon(icon, size: 12, color: scheme.tertiary),
        ),
        const SizedBox(width: 6),
        Text(text),
      ],
    );
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l.examMap, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Wrap(
              spacing: 16,
              runSpacing: 4,
              children: [
                legend(scheme.primaryContainer, null, l.examMapAnswered),
                legend(scheme.surface, Icons.flag, l.examMapFlagged),
                legend(scheme.surface, null, l.examMapEmpty),
              ],
            ),
            const SizedBox(height: 12),
            Flexible(
              child: SingleChildScrollView(
                child: Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (var i = 0; i < session.total; i++)
                      _MapCell(
                        number: i + 1,
                        answered: session.answers.containsKey(i),
                        flagged: session.flagged.contains(i),
                        current: i == session.index,
                        onTap: () => Navigator.of(context).pop(i),
                      ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MapCell extends StatelessWidget {
  const _MapCell({
    required this.number,
    required this.answered,
    required this.flagged,
    required this.current,
    required this.onTap,
  });

  final int number;
  final bool answered;
  final bool flagged;
  final bool current;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final scheme = Theme.of(context).colorScheme;
    final state = [
      if (answered) l.examMapAnswered else l.examMapEmpty,
      if (flagged) l.examMapFlagged,
    ].join(', ');
    return Semantics(
      button: true,
      label: '$number, $state',
      excludeSemantics: true,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Container(
          width: 48,
          height: 48,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: answered ? scheme.primaryContainer : scheme.surface,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
              color: current ? scheme.primary : scheme.outline,
              width: current ? 3 : 1,
            ),
          ),
          child: Stack(
            alignment: Alignment.center,
            children: [
              Text('$number', style: const TextStyle(fontWeight: FontWeight.w700)),
              if (flagged)
                Positioned(
                  top: 2,
                  right: 2,
                  child: Icon(Icons.flag, size: 12, color: scheme.tertiary),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
