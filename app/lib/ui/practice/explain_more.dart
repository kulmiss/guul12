import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/providers.dart';
import '../../data/api_client.dart';
import '../../data/models.dart';
import '../widgets/common.dart';
import '../widgets/math_text.dart';

/// "Explain more" under an explanation. Premium: opens an AI explanation. Free: a plain
/// "🔒 Premium" label (no price, no buy button). Hidden when the feature is off.
class ExplainMoreButton extends ConsumerWidget {
  const ExplainMoreButton({required this.questionId, super.key});

  final int questionId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final on = ref.watch(configProvider).value?.feature('explain_more') ?? false;
    if (!on) return const SizedBox.shrink();
    final premium = ref.watch(profileProvider).value?.premiumActive ?? false;
    if (!premium) {
      return Padding(
        padding: const EdgeInsets.only(top: 8),
        child: Text(l.explainLocked, style: Theme.of(context).textTheme.labelLarge),
      );
    }
    return Align(
      alignment: AlignmentDirectional.centerStart,
      child: TextButton.icon(
        icon: const Icon(Icons.auto_awesome_outlined),
        label: Text(l.explainMore),
        onPressed: () => showModalBottomSheet<void>(
          context: context,
          isScrollControlled: true,
          showDragHandle: true,
          builder: (_) => _ExplainSheet(questionId: questionId),
        ),
      ),
    );
  }
}

class _ExplainSheet extends ConsumerStatefulWidget {
  const _ExplainSheet({required this.questionId});

  final int questionId;

  @override
  ConsumerState<_ExplainSheet> createState() => _ExplainSheetState();
}

class _ExplainSheetState extends ConsumerState<_ExplainSheet> {
  late final Future<AiExplanation> _future = ref
      .read(repositoryProvider)
      .explainMore(widget.questionId);
  bool? _vote;

  Future<void> _rate(AiExplanation e, bool helpful) async {
    setState(() => _vote = helpful);
    try {
      await ref.read(repositoryProvider).rateExplanation(e.id, helpful: helpful);
    } on ApiException {
      // A lost vote is not worth bothering the student about.
    }
  }

  String _error(Object e) {
    final l = context.l10n;
    if (e is! ApiException) return l.commonError;
    if (e.isOffline) return l.explainOffline;
    return switch (e.code) {
      'explain_limit' => l.explainLimit,
      'premium_required' => l.studyPremiumLocked,
      _ => l.explainUnavailable,
    };
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        child: FutureBuilder<AiExplanation>(
          future: _future,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const SizedBox(height: 160, child: LoadingView());
            }
            if (snap.hasError) {
              return Padding(
                padding: const EdgeInsets.symmetric(vertical: 24),
                child: Text(_error(snap.error!)),
              );
            }
            final e = snap.data!;
            return SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(l.explainTitle, style: theme.textTheme.titleMedium),
                  const SizedBox(height: 8),
                  MathText(e.body),
                  const SizedBox(height: 12),
                  Text(l.explainAiNote, style: theme.textTheme.bodySmall),
                  const SizedBox(height: 8),
                  if (_vote != null)
                    Text(l.explainThanks)
                  else
                    Wrap(
                      spacing: 8,
                      children: [
                        OutlinedButton.icon(
                          icon: const Icon(Icons.thumb_up_outlined),
                          label: Text(l.explainHelpful),
                          onPressed: () => _rate(e, true),
                        ),
                        OutlinedButton.icon(
                          icon: const Icon(Icons.thumb_down_outlined),
                          label: Text(l.explainNotHelpful),
                          onPressed: () => _rate(e, false),
                        ),
                      ],
                    ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}
