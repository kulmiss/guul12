import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/providers.dart';
import '../../data/models.dart';
import '../../domain/session.dart';
import '../../services/speech.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/math_text.dart';
import 'explain_more.dart';
import 'question_input.dart';
import 'report_sheet.dart';

/// Plays a list of questions one by one: answer → Check → feedback with explanation → Next.
/// Used by the practice screen and by the onboarding warm-up.
class PracticeRunner extends ConsumerStatefulWidget {
  const PracticeRunner({
    required this.questions,
    required this.onFinished,
    super.key,
    this.passages = const {},
    this.secondsPerQuestion,
    this.allowBookmarks = true,
    this.allowReports = true,
  });

  final List<Question> questions;
  final Map<int, Passage> passages;
  final int? secondsPerQuestion;
  final bool allowBookmarks;

  /// Off for the onboarding warm-up (those questions are not on the server).
  final bool allowReports;
  final ValueChanged<PracticeSession> onFinished;

  @override
  ConsumerState<PracticeRunner> createState() => _PracticeRunnerState();
}

class _PracticeRunnerState extends ConsumerState<PracticeRunner> {
  late final PracticeSession _session;
  Object? _response;

  /// True after "Check" was pressed with no answer: shows a short hint.
  bool _nudge = false;
  Timer? _timer;
  int _secondsLeft = 0;

  /// Kept from initState: `ref` must not be used in dispose().
  late final Speech _speech;

  DateTime _now() => ref.read(clockProvider)();

  @override
  void initState() {
    super.initState();
    _speech = ref.read(speechProvider);
    _session = PracticeSession(questions: widget.questions, startedAt: _now());
    _startTimer();
  }

  @override
  void dispose() {
    _timer?.cancel();
    fireAndForget(_speech.stop());
    super.dispose();
  }

  void _startTimer() {
    final seconds = widget.secondsPerQuestion;
    _timer?.cancel();
    if (seconds == null) return;
    _secondsLeft = seconds;
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) return;
      setState(() => _secondsLeft--);
      if (_secondsLeft <= 0) {
        timer.cancel();
        _check(timeUp: true);
      }
    });
  }

  void _check({bool timeUp = false}) {
    if (_session.currentChecked) return;
    _timer?.cancel();
    setState(() => _session.check(timeUp ? null : _response, _now()));
    fireAndForget(_speech.stop());
  }

  void _next() {
    fireAndForget(_speech.stop());
    if (_session.next(_now())) {
      setState(() {
        _response = null;
        _nudge = false;
      });
      _startTimer();
    } else {
      widget.onFinished(_session);
    }
  }

  Future<void> _report(Question q) async {
    final messenger = ScaffoldMessenger.of(context);
    final thanks = context.l10n.reportThanks;
    final repo = ref.read(repositoryProvider);
    final choice = await showReportSheet(context);
    if (choice == null) return;
    await repo.reportQuestion(q.id, reason: choice.reason, note: choice.note);
    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(thanks)));
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    if (_session.isEmpty) return MessageView(message: l.practiceNoQuestions);
    final q = _session.current;
    final answer = _session.currentAnswer;
    final passage = widget.passages[q.passageId];
    final bookmarks = widget.allowBookmarks ? ref.watch(bookmarksProvider).value : null;
    final bookmarked = bookmarks?.contains(q.id) ?? false;
    final readAloud = ref.watch(configProvider).value?.feature('read_aloud') ?? true;

    return Column(
      children: [
        LinearProgressIndicator(
          value: (_session.index + (answer == null ? 0 : 1)) / _session.total,
          semanticsLabel: l.practiceQuestionOf(_session.index + 1, _session.total),
        ),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
            children: [
              ContentWidth(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            l.practiceQuestionOf(_session.index + 1, _session.total),
                            style: Theme.of(context).textTheme.labelLarge,
                          ),
                        ),
                        if (q.isBeta)
                          const Padding(
                            padding: EdgeInsetsDirectional.only(end: 8),
                            child: BetaChip(),
                          ),
                        if (widget.secondsPerQuestion != null)
                          _TimerChip(secondsLeft: answer == null ? _secondsLeft : null),
                        if (readAloud)
                          IconButton(
                            tooltip: l.practiceReadAloud,
                            icon: const Icon(Icons.volume_up_outlined),
                            onPressed: () => fireAndForget(
                              _speech.speak(
                                [
                                  q.stem,
                                  for (final o in q.options) '${o.key}. ${o.body}',
                                ].join('. '),
                              ),
                            ),
                          ),
                        if (bookmarks != null)
                          IconButton(
                            tooltip: bookmarked ? l.practiceBookmarked : l.practiceBookmark,
                            isSelected: bookmarked,
                            icon: const Icon(Icons.bookmark_border),
                            selectedIcon: const Icon(Icons.bookmark),
                            onPressed: () async {
                              await ref
                                  .read(repositoryProvider)
                                  .toggleBookmark(q.id, on: !bookmarked);
                              ref.invalidate(bookmarksProvider);
                            },
                          ),
                      ],
                    ),
                    if (passage != null) _PassageCard(passage: passage),
                    const SizedBox(height: 8),
                    MathText(q.stem, style: Theme.of(context).textTheme.titleMedium),
                    if (q.mediaUrl != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 12),
                        child: Image.network(
                          q.mediaUrl!,
                          height: 180,
                          fit: BoxFit.contain,
                          errorBuilder: (_, _, _) => const Icon(Icons.image_not_supported),
                        ),
                      ),
                    const SizedBox(height: 16),
                    QuestionInput(
                      key: ValueKey('q-${_session.index}-${q.id}'),
                      question: q,
                      locked: answer != null,
                      onChanged: (value) {
                        _response = value;
                        if (_nudge && value != null) setState(() => _nudge = false);
                      },
                    ),
                    if (answer != null) _FeedbackPanel(question: q, answer: answer),
                    if (answer != null && widget.allowReports)
                      Align(
                        alignment: AlignmentDirectional.centerEnd,
                        child: TextButton.icon(
                          icon: const Icon(Icons.outlined_flag),
                          label: Text(l.practiceReport),
                          onPressed: () => _report(q),
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
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (_nudge && answer == null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Semantics(
                        liveRegion: true,
                        child: Text(
                          l.practicePickAnswer,
                          textAlign: TextAlign.center,
                          style: TextStyle(color: Theme.of(context).colorScheme.error),
                        ),
                      ),
                    ),
                  if (answer == null)
                    FilledButton(
                      key: const ValueKey('check'),
                      onPressed: () {
                        if (_response != null) {
                          _check();
                        } else {
                          setState(() => _nudge = true);
                        }
                      },
                      child: Text(l.commonCheck),
                    )
                  else
                    FilledButton(
                      key: const ValueKey('next'),
                      onPressed: _next,
                      child: Text(_session.isLast ? l.practiceFinish : l.commonNext),
                    ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _TimerChip extends StatelessWidget {
  const _TimerChip({required this.secondsLeft});

  final int? secondsLeft;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final s = secondsLeft;
    final low = s != null && s <= 10;
    final scheme = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsetsDirectional.only(end: 4),
      child: Chip(
        avatar: Icon(Icons.timer_outlined, size: 18, color: low ? scheme.error : null),
        label: Text(s == null ? '—' : l.practiceTimeLeft(s.clamp(0, 9999))),
        visualDensity: VisualDensity.compact,
      ),
    );
  }
}

class _PassageCard extends StatelessWidget {
  const _PassageCard({required this.passage});

  final Passage passage;

  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(top: 8),
    child: ExpansionTile(
      initiallyExpanded: true,
      title: Text(context.l10n.practiceReadPassage),
      subtitle: Text(passage.title),
      childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      children: [MathText(passage.body)],
    ),
  );
}

class _FeedbackPanel extends StatelessWidget {
  const _FeedbackPanel({required this.question, required this.answer});

  final Question question;
  final SessionAnswer answer;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final colours = context.feedback;
    final right = answer.correct;
    final correctText = question.type == QuestionType.trueFalse
        ? (correctChoiceKeys(question).contains('TRUE') ? l.practiceTrue : l.practiceFalse)
        : describeCorrectAnswer(question);
    return Semantics(
      liveRegion: true,
      child: Container(
        margin: const EdgeInsets.only(top: 8),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: right ? colours.correctContainer : colours.wrongContainer,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  right ? Icons.check_circle : Icons.cancel,
                  color: right ? colours.correct : colours.wrong,
                ),
                const SizedBox(width: 8),
                Text(
                  right
                      ? l.practiceCorrect
                      : (answer.response == null ? l.practiceTimeUp : l.practiceWrong),
                  style: Theme.of(context).textTheme.titleMedium
                      ?.copyWith(color: right ? colours.correct : colours.wrong),
                ),
              ],
            ),
            if (!right && correctText.isNotEmpty) ...[
              const SizedBox(height: 8),
              MathText(l.practiceCorrectAnswer(correctText)),
            ],
            if (question.explanation.isNotEmpty) ...[
              const SizedBox(height: 12),
              Text(l.practiceExplanation, style: Theme.of(context).textTheme.labelLarge),
              const SizedBox(height: 4),
              MathText(question.explanation),
            ],
            ExplainMoreButton(questionId: question.id),
          ],
        ),
      ),
    );
  }
}
