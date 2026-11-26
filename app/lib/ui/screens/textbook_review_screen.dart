import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/providers.dart';
import '../../data/api_client.dart';
import '../../data/models.dart';
import '../widgets/common.dart';
import '../widgets/math_text.dart';

/// The solved review exercise of one unit, from the server (a saved copy works offline).
final textbookReviewProvider = FutureProvider.autoDispose.family<TextbookReview, int>((
  ref,
  unitId,
) async {
  ref.watch(syncGenerationProvider);
  return ref.watch(repositoryProvider).textbookReview(unitId);
});

/// The review exercise at the end of a textbook unit: every question as printed in the book,
/// each with a step-by-step solution that stays hidden until the student asks for it.
class TextbookReviewScreen extends ConsumerWidget {
  const TextbookReviewScreen({required this.unitId, super.key});

  final int unitId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final unit = ref.watch(catalogProvider).value?.units.where((u) => u.id == unitId).firstOrNull;
    final async = ref.watch(textbookReviewProvider(unitId));
    return Scaffold(
      appBar: AppBar(title: Text(unit == null ? l.reviewTile : l.reviewTitle(unit.number))),
      body: switch (async) {
        AsyncData(:final value) => _ReviewList(review: value, unitTitle: unit?.title),
        AsyncError(:final error) => MessageView(
          icon: Icons.assignment_outlined,
          message: error is ApiException && error.status == 404 ? l.reviewNotFound : l.commonError,
          onRetry: () => ref.invalidate(textbookReviewProvider(unitId)),
        ),
        _ => const LoadingView(),
      },
    );
  }
}

class _ReviewList extends StatelessWidget {
  const _ReviewList({required this.review, required this.unitTitle});

  final TextbookReview review;
  final String? unitTitle;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final pages = review.pages;
    final intro = review.intro;
    return ListView.builder(
      padding: const EdgeInsets.only(bottom: 32),
      itemCount: review.items.length + 1,
      itemBuilder: (context, i) {
        if (i == 0) {
          return ContentWidth(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (unitTitle != null)
                    Text(
                      unitTitle!,
                      style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
                    ),
                  const SizedBox(height: 4),
                  Text(
                    pages == null ? review.book : l.reviewSource(review.book, pages),
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Icon(Icons.edit_note_rounded, color: theme.colorScheme.primary),
                      const SizedBox(width: 8),
                      Expanded(child: Text(l.reviewHint, style: theme.textTheme.bodyMedium)),
                    ],
                  ),
                  if (intro != null) ...[
                    const SizedBox(height: 12),
                    _Box(
                      color: theme.colorScheme.secondaryContainer,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            l.reviewFormulas,
                            style: theme.textTheme.labelLarge?.copyWith(
                              color: theme.colorScheme.onSecondaryContainer,
                            ),
                          ),
                          const SizedBox(height: 4),
                          MathText(
                            intro,
                            breakLongMath: true,
                            style: TextStyle(color: theme.colorScheme.onSecondaryContainer),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),
          );
        }
        final item = review.items[i - 1];
        return ContentWidth(
          child: _QuestionCard(key: ValueKey('review-${item.ref}'), item: item),
        );
      },
    );
  }
}

class _QuestionCard extends StatelessWidget {
  const _QuestionCard({required this.item, super.key});

  final ReviewItem item;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final table = item.table;
    final figure = item.figure;
    final note = item.note;
    return Card(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (item.bookError)
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Chip(
                    avatar: Icon(Icons.report_outlined, size: 18, color: theme.colorScheme.error),
                    label: Text(l.reviewBookError),
                    visualDensity: VisualDensity.compact,
                  ),
                ),
              ),
            _Numbered(label: '${item.ref}.', text: item.question, bold: true),
            if (table != null) ...[const SizedBox(height: 10), _DataTable(table: table)],
            if (figure != null) ...[
              const SizedBox(height: 8),
              Text(
                l.reviewFigure(figure),
                style: theme.textTheme.bodySmall?.copyWith(fontStyle: FontStyle.italic),
              ),
            ],
            if (note != null) ...[
              const SizedBox(height: 10),
              _Box(
                color: theme.colorScheme.tertiaryContainer,
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(
                      Icons.info_outline_rounded,
                      size: 18,
                      color: theme.colorScheme.onTertiaryContainer,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: MathText(
                        note,
                        breakLongMath: true,
                        style: TextStyle(color: theme.colorScheme.onTertiaryContainer),
                      ),
                    ),
                  ],
                ),
              ),
            ],
            if (item.hasSolution) _Solution(key: ValueKey('sol-${item.ref}'), part: item),
            for (final part in item.parts)
              Padding(
                padding: const EdgeInsetsDirectional.only(top: 14, start: 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _Numbered(label: '${part.ref}.', text: part.question),
                    if (part.note != null) ...[
                      const SizedBox(height: 6),
                      MathText(part.note!, breakLongMath: true, style: theme.textTheme.bodySmall),
                    ],
                    if (part.hasSolution)
                      _Solution(key: ValueKey('sol-${item.ref}-${part.ref}'), part: part),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// "1." or "a." next to the question text.
class _Numbered extends StatelessWidget {
  const _Numbered({required this.label, required this.text, this.bold = false});

  final String label;
  final String text;
  final bool bold;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 34,
          child: Text(
            label,
            style: theme.textTheme.titleSmall?.copyWith(
              color: theme.colorScheme.primary,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
        Expanded(
          child: MathText(
            text,
            breakLongMath: true,
            style: theme.textTheme.bodyLarge?.copyWith(
              fontWeight: bold ? FontWeight.w600 : FontWeight.w400,
            ),
          ),
        ),
      ],
    );
  }
}

/// The worked steps and the answer, hidden until the student taps "Show solution".
class _Solution extends StatefulWidget {
  const _Solution({required this.part, super.key});

  final ReviewPart part;

  @override
  State<_Solution> createState() => _SolutionState();
}

class _SolutionState extends State<_Solution> {
  var _open = false;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final answer = widget.part.answer;
    final steps = widget.part.steps;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Align(
          alignment: AlignmentDirectional.centerStart,
          child: TextButton.icon(
            onPressed: () => setState(() => _open = !_open),
            icon: Icon(_open ? Icons.visibility_off_outlined : Icons.lightbulb_outline_rounded),
            label: Text(_open ? l.reviewHideSolution : l.reviewShowSolution),
          ),
        ),
        if (_open) ...[
          for (final (i, step) in steps.indexed)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    width: 24,
                    child: Text(
                      '${i + 1}.',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ),
                  Expanded(child: MathText(step, breakLongMath: true)),
                ],
              ),
            ),
          if (answer != null)
            _Box(
              color: theme.colorScheme.primaryContainer,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    l.reviewAnswer,
                    style: theme.textTheme.labelLarge?.copyWith(
                      color: theme.colorScheme.onPrimaryContainer,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 2),
                  MathText(
                    answer,
                    breakLongMath: true,
                    style: TextStyle(color: theme.colorScheme.onPrimaryContainer),
                  ),
                ],
              ),
            ),
        ],
      ],
    );
  }
}

/// A small data table from the book (column names down the side, as printed).
class _DataTable extends StatelessWidget {
  const _DataTable({required this.table});

  final Map<String, List<String>> table;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final border = BorderSide(color: theme.colorScheme.outlineVariant);
    Widget cell(String text, {bool head = false}) => Padding(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      child: MathText(
        text,
        style: theme.textTheme.bodyMedium?.copyWith(
          fontWeight: head ? FontWeight.w700 : FontWeight.w400,
        ),
      ),
    );
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Table(
        defaultColumnWidth: const IntrinsicColumnWidth(),
        border: TableBorder(
          top: border,
          bottom: border,
          left: border,
          right: border,
          horizontalInside: border,
          verticalInside: border,
        ),
        children: [
          for (final e in table.entries)
            TableRow(
              decoration: BoxDecoration(color: theme.colorScheme.surfaceContainerLow),
              children: [cell(e.key, head: true), for (final c in e.value) cell(c)],
            ),
        ],
      ),
    );
  }
}

class _Box extends StatelessWidget {
  const _Box({required this.color, required this.child});

  final Color color;
  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(12)),
    child: child,
  );
}
