import 'package:material_ui/material_ui.dart';

import '../../l10n/generated/app_localizations.dart';

extension L10nX on BuildContext {
  AppLocalizations get l10n => AppLocalizations.of(this);
}

/// Wide screens (tablets, landscape) get two columns and a navigation rail.
const wideBreakpoint = 720.0;

bool isWide(BuildContext context) => MediaQuery.sizeOf(context).width >= wideBreakpoint;

/// Keeps reading width comfortable on tablets.
class ContentWidth extends StatelessWidget {
  const ContentWidth({required this.child, super.key, this.maxWidth = 720});

  final Widget child;
  final double maxWidth;

  @override
  Widget build(BuildContext context) => Align(
    alignment: Alignment.topCenter,
    child: ConstrainedBox(
      constraints: BoxConstraints(maxWidth: maxWidth),
      child: child,
    ),
  );
}

class LoadingView extends StatelessWidget {
  const LoadingView({super.key});

  @override
  Widget build(BuildContext context) => Center(
    child: Semantics(label: context.l10n.commonLoading, child: const CircularProgressIndicator()),
  );
}

/// A friendly message with an optional retry button.
class MessageView extends StatelessWidget {
  const MessageView({required this.message, super.key, this.icon, this.onRetry});

  final String message;
  final IconData? icon;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon ?? Icons.info_outline, size: 40, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          if (onRetry != null) ...[
            const SizedBox(height: 16),
            OutlinedButton(onPressed: onRetry, child: Text(context.l10n.commonRetry)),
          ],
        ],
      ),
    ),
  );
}

/// The only Premium wording allowed in the app (Google Play rule): a lock and the word.
class PremiumLock extends StatelessWidget {
  const PremiumLock({super.key});

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: scheme.secondaryContainer,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        context.l10n.studyPremiumLocked,
        style: TextStyle(color: scheme.onSecondaryContainer, fontWeight: FontWeight.w700),
      ),
    );
  }
}

class BetaChip extends StatelessWidget {
  const BetaChip({super.key});

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
      decoration: BoxDecoration(
        border: Border.all(color: scheme.outline),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(context.l10n.betaLabel, style: Theme.of(context).textTheme.labelSmall),
    );
  }
}

/// Section title used on Home, Chapter and Me.
class SectionTitle extends StatelessWidget {
  const SectionTitle(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 20, 16, 8),
    child: Semantics(
      header: true,
      child: Text(text, style: Theme.of(context).textTheme.titleMedium),
    ),
  );
}
