import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../app/providers.dart';
import '../../data/models.dart';
import '../../domain/practice.dart';
import '../practice/practice_screen.dart';
import '../widgets/common.dart';
import '../widgets/math_text.dart';

/// Opens an outside link (official textbook PDF, YouTube) in the browser or app.
Future<void> openExternal(String url) async {
  final uri = Uri.tryParse(url);
  if (uri == null || !(uri.scheme == 'https' || uri.scheme == 'http')) return;
  await launchUrl(uri, mode: LaunchMode.externalApplication);
}

/// Chapter hub: summary, formulas, solved exercises, flashcards, quiz and practice.
class ChapterScreen extends ConsumerStatefulWidget {
  const ChapterScreen({required this.chapterId, super.key});

  final int chapterId;

  @override
  ConsumerState<ChapterScreen> createState() => _ChapterScreenState();
}

class _ChapterScreenState extends ConsumerState<ChapterScreen> {
  int get chapterId => widget.chapterId;

  @override
  void initState() {
    super.initState();
    fireAndForget(_remember());
  }

  Future<void> _remember() async {
    await ref.read(repositoryProvider).rememberChapter(chapterId);
    if (mounted) ref.invalidate(recentChaptersProvider);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final header = ref.watch(catalogProvider).value?.chapter(chapterId);
    final content = ref.watch(chapterProvider(chapterId));
    final title = header == null ? '' : l.studyChapter(header.number, header.title);

    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: content.when(
        loading: () => const LoadingView(),
        error: (_, _) => MessageView(
          message: l.chapterOffline,
          icon: Icons.cloud_off,
          onRetry: () => ref.invalidate(chapterProvider(chapterId)),
        ),
        data: (chapter) => _ChapterBody(chapter: chapter),
      ),
    );
  }
}

class _ChapterBody extends ConsumerWidget {
  const _ChapterBody({required this.chapter});

  final ChapterContent chapter;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final header = chapter.header;
    final premium = ref.watch(profileProvider).value?.premiumActive ?? false;
    final videosOn = ref.watch(configProvider).value?.feature('videos') ?? true;
    final summary = chapter.summary;

    final actions = Wrap(
      spacing: 12,
      runSpacing: 12,
      children: [
        if (chapter.questions.isNotEmpty)
          FilledButton.icon(
            icon: const Icon(Icons.quiz_outlined),
            label: Text(
              '${l.chapterQuiz} · ${l.chapterQuizCount(chapter.questions.length.clamp(0, 30))}',
            ),
            onPressed: () => context.push(
              PracticeScreen.playPath(PracticeMode.chapterQuiz, chapterId: header.id),
            ),
          ),
        if (chapter.questions.isNotEmpty)
          OutlinedButton.icon(
            icon: const Icon(Icons.fitness_center),
            label: Text(l.chapterPractice),
            onPressed: () => context.push(
              Uri(
                path: '/practice',
                queryParameters: {'chapter': '${header.id}', 'unit': '${header.unitId}'},
              ).toString(),
            ),
          ),
        if (chapter.flashcards.isNotEmpty)
          OutlinedButton.icon(
            icon: const Icon(Icons.style_outlined),
            label: Text(l.chapterFlashcards),
            onPressed: () => context.push('/study/chapter/${header.id}/flashcards'),
          ),
      ],
    );

    return ListView(
      padding: const EdgeInsets.only(bottom: 32),
      children: [
        ContentWidth(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(padding: const EdgeInsets.fromLTRB(16, 16, 16, 0), child: actions),
              SectionTitle(l.chapterSummary),
              if (summary == null)
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: Text(l.chapterNoSummary),
                )
              else ...[
                if (summary.keyPoints.isNotEmpty)
                  Card(
                    margin: const EdgeInsets.symmetric(horizontal: 16),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(l.chapterKeyPoints, style: theme.textTheme.labelLarge),
                          const SizedBox(height: 8),
                          for (final point in summary.keyPoints)
                            Padding(
                              padding: const EdgeInsets.only(bottom: 6),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Padding(
                                    padding: EdgeInsetsDirectional.only(top: 2, end: 8),
                                    child: Icon(Icons.circle, size: 8),
                                  ),
                                  Expanded(child: MathText(point)),
                                ],
                              ),
                            ),
                        ],
                      ),
                    ),
                  ),
                if (summary.formulas.isNotEmpty) ...[
                  SectionTitle(l.chapterFormulas),
                  for (final f in summary.formulas)
                    Card(
                      margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(f.label, style: theme.textTheme.labelLarge),
                            const SizedBox(height: 4),
                            MathText('\$\$${f.latex}\$\$'),
                          ],
                        ),
                      ),
                    ),
                ],
                if (summary.body != null && summary.body!.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                    child: MathText(summary.body!),
                  ),
              ],
              if (chapter.solvedExercises.isNotEmpty) ...[
                SectionTitle(l.chapterSolvedExercises),
                for (final ex in chapter.solvedExercises) _SolvedExerciseCard(exercise: ex),
              ],
              if (header.textbookUrl != null || (videosOn && header.videoYoutubeId != null))
                const SizedBox(height: 12),
              if (header.textbookUrl != null)
                ListTile(
                  leading: const Icon(Icons.picture_as_pdf_outlined),
                  title: Text(l.chapterTextbook),
                  trailing: const Icon(Icons.open_in_new),
                  onTap: () => openExternal(header.textbookUrl!),
                ),
              if (videosOn && header.videoYoutubeId != null)
                ListTile(
                  leading: const Icon(Icons.play_circle_outline),
                  title: Text(l.chapterVideo),
                  trailing: header.videoIsPremium && !premium
                      ? const PremiumLock()
                      : const Icon(Icons.open_in_new),
                  onTap: header.videoIsPremium && !premium
                      ? null
                      : () => openExternal(
                          'https://www.youtube.com/watch?v=${Uri.encodeComponent(header.videoYoutubeId!)}',
                        ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class _SolvedExerciseCard extends StatelessWidget {
  const _SolvedExerciseCard({required this.exercise});

  final SolvedExercise exercise;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    return Card(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
      clipBehavior: Clip.antiAlias,
      child: ExpansionTile(
        title: MathText(exercise.problem),
        subtitle: Text(l.chapterShowSolution),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        expandedCrossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (final (i, step) in exercise.steps.indexed) ...[
            Text(l.chapterSolvedStep(i + 1), style: theme.textTheme.labelLarge),
            const SizedBox(height: 2),
            MathText(step),
            const SizedBox(height: 8),
          ],
          Text(l.chapterAnswer, style: theme.textTheme.labelLarge),
          MathText(exercise.finalAnswer),
        ],
      ),
    );
  }
}

/// Flashcards: tap to flip, swipe or buttons for next/previous.
class FlashcardsScreen extends ConsumerStatefulWidget {
  const FlashcardsScreen({required this.chapterId, super.key});

  final int chapterId;

  @override
  ConsumerState<FlashcardsScreen> createState() => _FlashcardsScreenState();
}

class _FlashcardsScreenState extends ConsumerState<FlashcardsScreen> {
  final _pages = PageController();
  int _index = 0;
  final Set<int> _flipped = {};

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final chapter = ref.watch(chapterProvider(widget.chapterId));
    return Scaffold(
      appBar: AppBar(title: Text(l.chapterFlashcards)),
      body: chapter.when(
        loading: () => const LoadingView(),
        error: (_, _) => MessageView(message: l.chapterOffline, icon: Icons.cloud_off),
        data: (chapter) {
          final cards = chapter.flashcards;
          if (cards.isEmpty) return MessageView(message: l.studyEmpty);
          return Column(
            children: [
              Padding(
                padding: const EdgeInsets.all(12),
                child: Text(l.chapterFlashcardHint, textAlign: TextAlign.center),
              ),
              Expanded(
                child: PageView.builder(
                  controller: _pages,
                  itemCount: cards.length,
                  onPageChanged: (i) => setState(() => _index = i),
                  itemBuilder: (context, i) {
                    final card = cards[i];
                    final flipped = _flipped.contains(i);
                    return Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 8),
                      child: ContentWidth(
                        maxWidth: 560,
                        child: Semantics(
                          button: true,
                          label: l.chapterFlashcardHint,
                          child: GestureDetector(
                            onTap: () => setState(() {
                              flipped ? _flipped.remove(i) : _flipped.add(i);
                            }),
                            child: AnimatedSwitcher(
                              duration: const Duration(milliseconds: 220),
                              child: Card(
                                key: ValueKey('$i-$flipped'),
                                color: flipped
                                    ? Theme.of(context).colorScheme.secondaryContainer
                                    : null,
                                child: Center(
                                  child: Padding(
                                    padding: const EdgeInsets.all(24),
                                    child: MathText(
                                      flipped ? card.definition : card.term,
                                      textAlign: TextAlign.center,
                                      style: flipped
                                          ? Theme.of(context).textTheme.bodyLarge
                                          : Theme.of(context).textTheme.headlineSmall,
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
              SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      IconButton(
                        tooltip: l.commonPrevious,
                        icon: const Icon(Icons.chevron_left),
                        onPressed: _index == 0
                            ? null
                            : () => _pages.previousPage(
                                duration: const Duration(milliseconds: 200),
                                curve: Curves.easeOut,
                              ),
                      ),
                      Text(l.chapterFlashcardCount(_index + 1, cards.length)),
                      IconButton(
                        tooltip: l.commonNext,
                        icon: const Icon(Icons.chevron_right),
                        onPressed: _index >= cards.length - 1
                            ? null
                            : () => _pages.nextPage(
                                duration: const Duration(milliseconds: 200),
                                curve: Curves.easeOut,
                              ),
                      ),
                    ],
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
