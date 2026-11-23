import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/exam_providers.dart';
import '../../app/providers.dart';
import '../../data/api_client.dart';
import '../../data/models.dart';
import '../../domain/exam.dart';
import '../../domain/practice.dart';
import '../../domain/session.dart';
import '../../l10n/generated/app_localizations.dart';
import '../practice/explain_more.dart';
import '../practice/practice_screen.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/math_text.dart';

/// Score, likely real-exam range, chapters, comparison with other students, and the review.
class ExamResultScreen extends ConsumerStatefulWidget {
  const ExamResultScreen({required this.clientUuid, super.key});

  final String clientUuid;

  @override
  ConsumerState<ExamResultScreen> createState() => _ExamResultScreenState();
}

class _ExamResultScreenState extends ConsumerState<ExamResultScreen> {
  bool _showReview = false;
  bool _refreshing = false;

  Future<void> _refresh() async {
    setState(() => _refreshing = true);
    try {
      await ref
          .read(repositoryProvider)
          .tryUploadAttempts(appVersion: ref.read(appVersionProvider));
      await ref.read(repositoryProvider).refreshExamResult(widget.clientUuid);
    } on ApiException {
      // Offline: the saved result stays.
    } finally {
      if (mounted) {
        setState(() => _refreshing = false);
        ref
          ..invalidate(examResultProvider(widget.clientUuid))
          ..invalidate(examResultsProvider);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final result = ref.watch(examResultProvider(widget.clientUuid));
    return Scaffold(
      appBar: AppBar(title: Text(l.examResultTitle)),
      body: result.when(
        loading: () => const LoadingView(),
        error: (_, _) => MessageView(message: l.commonError),
        data: (record) =>
            record == null ? MessageView(message: l.examResultNotFound) : _body(context, record),
      ),
    );
  }

  Widget _body(BuildContext context, ExamRecord r) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final catalog = ref.watch(catalogProvider).value;
    final paperMax = ref.watch(configProvider).value?.paperMaxScore ?? 100;
    final (low, high) = estimatedScoreRange(correct: r.correct, total: r.total, paperMax: paperMax);
    final percent = r.total == 0 ? 0 : (100 * r.correct / r.total).round();
    final chapters = [...r.chapters]..sort((a, b) => a.ratio.compareTo(b.ratio));
    final weakest = chapters.firstOrNull;

    String chapterName(int id) {
      final c = catalog?.chapter(id);
      return c == null ? l.examUnknownChapter(id) : l.studyChapter(c.number, c.title);
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        ContentWidth(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(r.title, style: theme.textTheme.titleMedium, textAlign: TextAlign.center),
              const SizedBox(height: 8),
              Text(
                l.practiceResultScore(r.correct, r.total),
                style: theme.textTheme.displaySmall?.copyWith(color: theme.colorScheme.primary),
                textAlign: TextAlign.center,
              ),
              Text(l.examResultPercent(percent), textAlign: TextAlign.center),
              const SizedBox(height: 12),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        l.examResultEstimate(low, high, paperMax),
                        style: theme.textTheme.titleSmall,
                      ),
                      const SizedBox(height: 4),
                      Text(l.examResultEstimateNote, style: theme.textTheme.bodySmall),
                      const SizedBox(height: 8),
                      Text(l.examResultTime(formatDuration(r.timeUsed))),
                      if (r.kind == ExamKind.fixed) ...[
                        const SizedBox(height: 8),
                        _Comparison(record: r, refreshing: _refreshing, onRefresh: _refresh),
                      ],
                    ],
                  ),
                ),
              ),
              SectionTitle(l.examResultChapters),
              for (final c in chapters)
                Padding(
                  padding: const EdgeInsets.fromLTRB(4, 0, 4, 12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          Expanded(child: Text(chapterName(c.chapterId))),
                          Text(l.examResultChapterScore(c.correct, c.total)),
                        ],
                      ),
                      const SizedBox(height: 4),
                      LinearProgressIndicator(
                        value: c.ratio,
                        minHeight: 8,
                        borderRadius: BorderRadius.circular(4),
                        semanticsLabel: chapterName(c.chapterId),
                      ),
                    ],
                  ),
                ),
              const SizedBox(height: 8),
              if (weakest != null &&
                  weakest.correct < weakest.total &&
                  catalog?.chapter(weakest.chapterId) != null)
                OutlinedButton.icon(
                  icon: const Icon(Icons.fitness_center),
                  label: Text(l.examResultPractiseWeakest),
                  onPressed: () => context.push(
                    PracticeScreen.playPath(PracticeMode.chapter, chapterId: weakest.chapterId),
                  ),
                ),
              const SizedBox(height: 8),
              OutlinedButton(
                onPressed: () => setState(() => _showReview = !_showReview),
                child: Text(_showReview ? l.examResultHideReview : l.examResultReview),
              ),
              const SizedBox(height: 8),
              FilledButton(onPressed: () => context.pop(), child: Text(l.commonDone)),
              if (_showReview) ...[
                SectionTitle(l.practiceReview),
                for (final (i, item) in r.items.indexed) _ReviewCard(number: i + 1, item: item),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _Comparison extends StatelessWidget {
  const _Comparison({required this.record, required this.refreshing, required this.onRefresh});

  final ExamRecord record;
  final bool refreshing;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final better = record.betterThanPercent;
    final text = better != null
        ? l.examResultBetterThan(better)
        : record.uploaded
        ? l.examResultNotEnough
        : l.examResultConnect;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(better != null ? Icons.groups : Icons.groups_outlined),
            const SizedBox(width: 8),
            Expanded(child: Text(text, style: Theme.of(context).textTheme.bodyLarge)),
          ],
        ),
        if (better == null)
          Align(
            alignment: AlignmentDirectional.centerEnd,
            child: refreshing
                ? const Padding(
                    padding: EdgeInsets.all(12),
                    child: SizedBox(
                      width: 24,
                      height: 24,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  )
                : TextButton(onPressed: onRefresh, child: Text(l.examResultRefresh)),
          ),
      ],
    );
  }
}

class _ReviewCard extends StatelessWidget {
  const _ReviewCard({required this.number, required this.item});

  final int number;
  final ExamItem item;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final colours = context.feedback;
    final q = item.question;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  item.correct ? Icons.check_circle : Icons.cancel,
                  color: item.correct ? colours.correct : colours.wrong,
                ),
                const SizedBox(width: 8),
                Expanded(child: MathText('$number. ${q.stem}')),
              ],
            ),
            const SizedBox(height: 6),
            MathText(l.practiceYourAnswer(_answer(l, q, item.response) ?? l.practiceNoAnswer)),
            if (!item.correct) MathText(l.practiceCorrectAnswer(_correct(l, q))),
            if (q.explanation.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(l.practiceExplanation, style: Theme.of(context).textTheme.labelLarge),
              MathText(q.explanation),
            ],
            ExplainMoreButton(questionId: q.id),
          ],
        ),
      ),
    );
  }

  static String? _answer(AppLocalizations l, Question q, String? response) {
    if (q.type == QuestionType.trueFalse && response != null) {
      return response.toUpperCase() == 'TRUE' ? l.practiceTrue : l.practiceFalse;
    }
    return describeResponse(q, response);
  }

  static String _correct(AppLocalizations l, Question q) {
    if (q.type == QuestionType.trueFalse) {
      return q.answerKey['correct'] == 'TRUE' ? l.practiceTrue : l.practiceFalse;
    }
    return describeCorrectAnswer(q);
  }
}
