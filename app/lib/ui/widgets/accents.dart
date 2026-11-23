import 'package:material_ui/material_ui.dart';

import '../theme.dart';

/// One of three lively colours used to tell cards and drawings apart: the brand's main colour,
/// its accent colour and a green. Each has a strong colour (with a readable colour on top) and a
/// soft container colour (with a readable text colour on top). They follow the branding and the
/// light/dark theme, so every pair keeps the contrast the theme was checked for.
@immutable
class Accent {
  const Accent({
    required this.strong,
    required this.onStrong,
    required this.soft,
    required this.onSoft,
  });

  final Color strong;
  final Color onStrong;
  final Color soft;
  final Color onSoft;
}

/// The three accents, in a fixed order: main, accent, green.
List<Accent> accents(BuildContext context) {
  final scheme = Theme.of(context).colorScheme;
  final feedback = context.feedback;
  final dark = Theme.of(context).brightness == Brightness.dark;
  return [
    Accent(
      strong: scheme.primary,
      onStrong: scheme.onPrimary,
      soft: scheme.primaryContainer,
      onSoft: scheme.onPrimaryContainer,
    ),
    Accent(
      strong: scheme.secondary,
      onStrong: scheme.onSecondary,
      soft: scheme.secondaryContainer,
      onSoft: scheme.onSecondaryContainer,
    ),
    Accent(
      strong: feedback.correct,
      onStrong: dark ? SunriseColors.night : Colors.white,
      soft: feedback.correctContainer,
      onSoft: feedback.correct,
    ),
  ];
}
