import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:uuid/uuid.dart';

import '../../app/challenge_providers.dart';
import '../../app/practice_set.dart';
import '../../app/progress_providers.dart';
import '../../app/providers.dart';
import '../../app/review_providers.dart';
import '../../data/models.dart';
import '../../data/repository.dart';
import '../../domain/practice.dart';
import '../../domain/session.dart';
import '../../l10n/generated/app_localizations.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/math_text.dart';
import 'practice_runner.dart';

/// Builds a practice set, plays it, saves the attempt and shows the result.
class PlayerScreen extends ConsumerStatefulWidget {
  const PlayerScreen({required this.request, super.key});

  final PracticeRequest request;

  @override
  ConsumerState<PlayerScreen> createState() => _PlayerScreenState();
}

class _PlayerScreenState extends ConsumerState<PlayerScreen> {
  late PracticeRequest _request = widget.request;
  PracticeSession? _finished;
  bool? _synced;

  Future<void> _onFinished(PracticeSession session) async {
    setState(() {
      _finished = session;
      _synced = null;
    });
    final repo = ref.read(repositoryProvider);
    await repo.saveAttempt(
      clientUuid: const Uuid().v4(),
      mode: _request.mode.wire,
      chapterId: _request.chapterId,
      challengeDate: ref.read(practiceSetProvider(_request)).value?.challengeDate,
      startedAt: session.startedAt,
      finishedAt: ref.read(clockProvider)(),
      answers: [
        for (final (q, a) in session.results())
          AttemptAnswer(
            questionId: q.id,
            chapterId: q.chapterId,
            version: q.version,
            response: a.response,
            timeMs: a.timeMs,
            correctLocally: a.correct,
          ),
      ],
    );
    // Mistakes, reviews and chapter strength changed.
    ref
      ..invalidate(mistakesProvider)
      ..invalidate(reviewDueCountProvider)
      ..invalidate(masteryProvider);
    final synced = await repo.tryUploadAttempts(appVersion: ref.read(appVersionProvider));
    // The challenge result (and how others did) comes from the server after the upload.
    if (_request.mode == PracticeMode.dailyChallenge) ref.invalidate(dailyChallengeProvider);
    if (mounted) setState(() => _synced = synced);
  }

  Future<bool> _confirmLeave() async {
    final l = context.l10n;
    final leave = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l.practiceLeaveTitle),
        content: Text(l.practiceLeaveBody),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(l.practiceStay),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(l.practiceLeave),
          ),
        ],
      ),
    );
    return leave ?? false;
  }

  String _title(AppLocalizations l) => practiceModeLabel(l, _request.mode);

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final finished = _finished;
    final set = ref.watch(practiceSetProvider(_request));
    final playing = finished == null && (set.value?.isEmpty == false);

    return PopScope(
      canPop: !playing,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        if (await _confirmLeave() && context.mounted) context.pop();
      },
      child: Scaffold(
        appBar: AppBar(title: Text(_title(l))),
        body: finished != null
            ? _ResultView(
                session: finished,
                synced: _synced,
                onAgain: () => setState(() {
                  _finished = null;
                  _request = PracticeRequest(
                    mode: _request.mode,
                    chapterId: _request.chapterId,
                    unitId: _request.unitId,
                    seed: _request.seed + 1,
                  );
                }),
                onDone: () => context.pop(),
              )
            : set.when(
                loading: () => const LoadingView(),
                error: (_, _) => MessageView(
                  message: l.chapterOffline,
                  icon: Icons.cloud_off,
                  onRetry: () => ref.invalidate(practiceSetProvider(_request)),
                ),
                data: (set) => set.isEmpty
                    ? MessageView(
                        message: switch (_request.mode) {
                          PracticeMode.wrongOnly => l.practiceNoWrongAnswers,
                          PracticeMode.review => l.practiceNothingToReview,
                          _ => l.practiceNoQuestions,
                        },
                        icon: Icons.school_outlined,
                      )
                    : PracticeRunner(
                        key: ValueKey(_request.seed),
                        questions: set.questions,
                        passages: set.passages,
                        secondsPerQuestion: _request.mode.secondsPerQuestion,
                        onFinished: _onFinished,
                      ),
              ),
      ),
    );
  }
}

String practiceModeLabel(AppLocalizations l, PracticeMode mode) => switch (mode) {
  PracticeMode.quick10 => l.practiceModeQuick10,
  PracticeMode.chapter => l.practiceModeChapter,
  PracticeMode.chapterQuiz => l.chapterQuiz,
  PracticeMode.unit => l.practiceModeUnit,
  PracticeMode.mixed => l.practiceModeMixed,
  PracticeMode.timed => l.practiceModeTimed,
  PracticeMode.wrongOnly => l.practiceModeWrongOnly,
  PracticeMode.quickRevise => l.practiceModeQuickRevise,
  PracticeMode.review => l.practiceModeReview,
  PracticeMode.smart => l.practiceModeSmart,
  PracticeMode.onboarding => l.onboardingHookTitle,
  PracticeMode.dailyChallenge => l.challengeTitle,
};

class _ResultView extends StatelessWidget {
  const _ResultView({
    required this.session,
    required this.synced,
    required this.onAgain,
    required this.onDone,
  });

  final PracticeSession session;
  final bool? synced;
  final VoidCallback onAgain;
  final VoidCallback onDone;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final colours = context.feedback;
    final results = session.results();
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        ContentWidth(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                l.practiceResultTitle,
                style: theme.textTheme.headlineSmall,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              Text(
                l.practiceResultScore(session.correctCount, session.total),
                style: theme.textTheme.displaySmall?.copyWith(color: theme.colorScheme.primary),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              Text(
                synced == null
                    ? l.commonLoading
                    : synced!
                    ? l.practiceResultSynced
                    : l.practiceResultSaved,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(onPressed: onAgain, child: Text(l.practiceAgain)),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: FilledButton(onPressed: onDone, child: Text(l.commonDone)),
                  ),
                ],
              ),
              SectionTitle(l.practiceReview),
              for (final (i, (q, a)) in results.indexed)
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Icon(
                              a.correct ? Icons.check_circle : Icons.cancel,
                              color: a.correct ? colours.correct : colours.wrong,
                            ),
                            const SizedBox(width: 8),
                            Expanded(child: MathText('${i + 1}. ${q.stem}')),
                          ],
                        ),
                        const SizedBox(height: 6),
                        MathText(
                          l.practiceYourAnswer(_answerText(l, q, a.response) ?? l.practiceNoAnswer),
                        ),
                        if (!a.correct) MathText(l.practiceCorrectAnswer(_correctText(l, q))),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }

  static String? _answerText(AppLocalizations l, Question q, Object? response) {
    if (q.type == QuestionType.trueFalse && response is String) {
      return response.toUpperCase() == 'TRUE' ? l.practiceTrue : l.practiceFalse;
    }
    return describeResponse(q, response);
  }

  static String _correctText(AppLocalizations l, Question q) {
    if (q.type == QuestionType.trueFalse) {
      return q.answerKey['correct'] == 'TRUE' ? l.practiceTrue : l.practiceFalse;
    }
    return describeCorrectAnswer(q);
  }
}
