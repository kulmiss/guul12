import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/providers.dart';
import '../../app/review_providers.dart';
import '../../data/models.dart';
import '../../domain/practice.dart';
import '../../domain/session.dart';
import '../../l10n/generated/app_localizations.dart';
import '../practice/practice_screen.dart';
import '../widgets/common.dart';
import '../widgets/math_text.dart';

/// Mistakes notebook: every question whose last answer was wrong, by chapter, with the right
/// answer, the explanation and when it comes back for review.
class MistakesScreen extends ConsumerWidget {
  const MistakesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final mistakes = ref.watch(mistakesProvider);
    final catalog = ref.watch(catalogProvider).value;
    final now = ref.watch(clockProvider)();
    return Scaffold(
      appBar: AppBar(title: Text(l.mistakesTitle)),
      body: mistakes.when(
        loading: () => const LoadingView(),
        error: (_, _) => MessageView(message: l.commonError),
        data: (byChapter) {
          if (byChapter.isEmpty) {
            return MessageView(message: l.mistakesEmpty, icon: Icons.emoji_events_outlined);
          }
          return ListView(
            padding: const EdgeInsets.only(bottom: 24),
            children: [
              ContentWidth(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                      child: FilledButton.icon(
                        icon: const Icon(Icons.replay),
                        label: Text(l.mistakesPractise),
                        onPressed: () =>
                            context.push(PracticeScreen.playPath(PracticeMode.wrongOnly)),
                      ),
                    ),
                    for (final MapEntry(key: chapterId, value: items) in byChapter.entries) ...[
                      SectionTitle(_chapterName(l, catalog, chapterId)),
                      for (final m in items) _MistakeCard(mistake: m, now: now),
                    ],
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  static String _chapterName(AppLocalizations l, Catalog? catalog, int id) {
    final c = catalog?.chapter(id);
    return c == null ? l.examUnknownChapter(id) : l.studyChapter(c.number, c.title);
  }
}

class _MistakeCard extends StatelessWidget {
  const _MistakeCard({required this.mistake, required this.now});

  final Mistake mistake;
  final DateTime now;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final q = mistake.question;
    final review = mistake.review;
    final when = review == null
        ? null
        : review.isDue(now)
        ? l.mistakesReviewToday
        : l.mistakesNextReview(DateFormat.MMMd(l.localeName).format(review.dueAt.toLocal()));
    final correct = q.type == QuestionType.trueFalse
        ? (q.answerKey['correct'] == 'TRUE' ? l.practiceTrue : l.practiceFalse)
        : describeCorrectAnswer(q);
    return Card(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
      child: ExpansionTile(
        title: MathText(q.stem),
        subtitle: when == null ? null : Text(when),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        expandedAlignment: AlignmentDirectional.centerStart,
        expandedCrossAxisAlignment: CrossAxisAlignment.start,
        // No divider lines inside the card.
        shape: const Border(),
        collapsedShape: const Border(),
        children: [
          if (correct.isNotEmpty) MathText(l.practiceCorrectAnswer(correct)),
          if (q.explanation.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(l.practiceExplanation, style: Theme.of(context).textTheme.labelLarge),
            MathText(q.explanation),
          ],
        ],
      ),
    );
  }
}
