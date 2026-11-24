import 'package:material_ui/material_ui.dart';

import '../../data/models.dart';
import '../../domain/scoring.dart';
import '../theme.dart';
import '../widgets/common.dart';

/// The onboarding warm-up: a few very easy questions, one tap each, with friendly feedback at
/// once (no typing, no "Check" step). It is local only: nothing is sent to the server.
class WarmUpQuiz extends StatefulWidget {
  const WarmUpQuiz({required this.questions, required this.onFinished, super.key});

  final List<Question> questions;

  /// Called once, after the last question, with the number of right answers.
  final void Function(int correct, int total) onFinished;

  @override
  State<WarmUpQuiz> createState() => _WarmUpQuizState();
}

class _WarmUpQuizState extends State<WarmUpQuiz> {
  static const _scorer = AnswerScorer();

  int _index = 0;
  int _correct = 0;
  String? _picked;

  Question get _question => widget.questions[_index];
  bool get _isLast => _index == widget.questions.length - 1;

  bool _isRight(String key) => _scorer.isCorrect(_question.type, _question.answerKey, key);

  void _pick(String key) {
    if (_picked != null) return; // one try per question
    setState(() {
      _picked = key;
      if (_isRight(key)) _correct++;
    });
  }

  void _next() {
    if (_isLast) {
      widget.onFinished(_correct, widget.questions.length);
      return;
    }
    setState(() {
      _index++;
      _picked = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final total = widget.questions.length;
    final picked = _picked;
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
      children: [
        ContentWidth(
          maxWidth: 560,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Semantics(
                header: true,
                child: Text(
                  l.onboardingHookTitle,
                  style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
                ),
              ),
              const SizedBox(height: 4),
              Text(l.warmUpHint, style: theme.textTheme.bodyMedium),
              const SizedBox(height: 16),
              Row(
                children: [
                  Text(l.warmUpProgress(_index + 1, total), style: theme.textTheme.labelLarge),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ExcludeSemantics(
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: LinearProgressIndicator(
                          value: (_index + (picked == null ? 0 : 1)) / total,
                          minHeight: 8,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 28),
              Text(
                _question.stem,
                key: ValueKey('warmup-q${_question.id}'),
                style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 20),
              for (final option in _question.options)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _AnswerButton(
                    text: option.body,
                    state: picked == null
                        ? _AnswerState.open
                        : _isRight(option.key)
                        ? _AnswerState.right
                        : option.key == picked
                        ? _AnswerState.wrong
                        : _AnswerState.other,
                    onTap: () => _pick(option.key),
                  ),
                ),
              if (picked != null) ...[
                const SizedBox(height: 4),
                _Feedback(
                  right: _isRight(picked),
                  title: _isRight(picked)
                      ? switch (_index % 3) {
                          0 => l.warmUpPraise1,
                          1 => l.warmUpPraise2,
                          _ => l.warmUpPraise3,
                        }
                      : l.warmUpAlmost(_question.options.firstWhere((o) => _isRight(o.key)).body),
                  body: _question.explanation,
                ),
                const SizedBox(height: 20),
                FilledButton(
                  key: const ValueKey('warmup-next'),
                  onPressed: _next,
                  child: Text(_isLast ? l.warmUpSeeResult : l.warmUpNext),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

enum _AnswerState { open, right, wrong, other }

class _AnswerButton extends StatelessWidget {
  const _AnswerButton({required this.text, required this.state, required this.onTap});

  final String text;
  final _AnswerState state;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final feedback = context.feedback;
    final (background, foreground, border, icon) = switch (state) {
      _AnswerState.open => (scheme.surfaceContainerLowest, scheme.onSurface, scheme.outline, null),
      _AnswerState.right => (
        feedback.correctContainer,
        feedback.correct,
        feedback.correct,
        Icons.check_circle,
      ),
      _AnswerState.wrong => (feedback.wrongContainer, feedback.wrong, feedback.wrong, Icons.cancel),
      _AnswerState.other => (
        scheme.surfaceContainerLowest,
        scheme.onSurfaceVariant,
        scheme.outlineVariant,
        null,
      ),
    };
    return Semantics(
      button: true,
      selected: state == _AnswerState.right || state == _AnswerState.wrong,
      child: Material(
        color: background,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(color: border, width: state == _AnswerState.open ? 1.5 : 2),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: state == _AnswerState.open ? onTap : null,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 64),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      text,
                      style: Theme.of(context).textTheme.titleMedium
                          ?.copyWith(color: foreground, fontWeight: FontWeight.w700),
                    ),
                  ),
                  if (icon != null) Icon(icon, color: foreground),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _Feedback extends StatelessWidget {
  const _Feedback({required this.right, required this.title, required this.body});

  final bool right;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    final feedback = context.feedback;
    final theme = Theme.of(context);
    final color = right ? feedback.correct : feedback.wrong;
    return Semantics(
      liveRegion: true,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: right ? feedback.correctContainer : feedback.wrongContainer,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(right ? Icons.celebration : Icons.lightbulb_outline, color: color, size: 28),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: color,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  if (body.isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Text(body, style: theme.textTheme.bodyMedium?.copyWith(color: color)),
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
