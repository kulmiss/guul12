import 'package:material_ui/material_ui.dart';

/// Design direction A · Sunrise (owner decision #2).
abstract final class SunriseColors {
  static const teal = Color(0xFF0E5E6F);
  static const tealDark = Color(0xFF5FC1CF);
  static const orange = Color(0xFFF28C28);
  static const orangeDark = Color(0xFFF5A35A);
  static const cream = Color(0xFFFFF8EE);
  static const ink = Color(0xFF1C2B30);
  static const inkSoft = Color(0xFF4A5A60);
  static const green = Color(0xFF1E8A5A);
  static const greenDark = Color(0xFF5CC98F);
  static const red = Color(0xFFC2412D);
  static const redDark = Color(0xFFF08A78);
  static const night = Color(0xFF0F1C20);
  static const nightCard = Color(0xFF172A30);
}

/// Colours for right/wrong feedback that work in both themes.
@immutable
class FeedbackColors extends ThemeExtension<FeedbackColors> {
  const FeedbackColors({
    required this.correct,
    required this.correctContainer,
    required this.wrong,
    required this.wrongContainer,
  });

  final Color correct;
  final Color correctContainer;
  final Color wrong;
  final Color wrongContainer;

  static const light = FeedbackColors(
    correct: Color(0xFF145C3C),
    correctContainer: Color(0xFFE3F4EA),
    wrong: Color(0xFF9A2F1F),
    wrongContainer: Color(0xFFFBE4DF),
  );

  static const dark = FeedbackColors(
    correct: SunriseColors.greenDark,
    correctContainer: Color(0xFF173A2A),
    wrong: SunriseColors.redDark,
    wrongContainer: Color(0xFF3F1F19),
  );

  @override
  FeedbackColors copyWith({
    Color? correct,
    Color? correctContainer,
    Color? wrong,
    Color? wrongContainer,
  }) => FeedbackColors(
    correct: correct ?? this.correct,
    correctContainer: correctContainer ?? this.correctContainer,
    wrong: wrong ?? this.wrong,
    wrongContainer: wrongContainer ?? this.wrongContainer,
  );

  @override
  FeedbackColors lerp(FeedbackColors? other, double t) {
    if (other == null) return this;
    return FeedbackColors(
      correct: Color.lerp(correct, other.correct, t)!,
      correctContainer: Color.lerp(correctContainer, other.correctContainer, t)!,
      wrong: Color.lerp(wrong, other.wrong, t)!,
      wrongContainer: Color.lerp(wrongContainer, other.wrongContainer, t)!,
    );
  }
}

extension FeedbackColorsX on BuildContext {
  FeedbackColors get feedback => Theme.of(this).extension<FeedbackColors>()!;
}

ThemeData sunriseTheme(Brightness brightness) {
  final isDark = brightness == Brightness.dark;

  final scheme = isDark
      ? const ColorScheme(
          brightness: Brightness.dark,
          primary: SunriseColors.tealDark,
          onPrimary: SunriseColors.night,
          primaryContainer: Color(0xFF14444F),
          onPrimaryContainer: Color(0xFFCDEFF4),
          secondary: SunriseColors.orangeDark,
          onSecondary: SunriseColors.night,
          secondaryContainer: Color(0xFF4A3016),
          onSecondaryContainer: Color(0xFFFFDDBE),
          error: SunriseColors.redDark,
          onError: SunriseColors.night,
          surface: SunriseColors.night,
          onSurface: Color(0xFFE6EEF0),
          onSurfaceVariant: Color(0xFFAEC0C5),
          surfaceContainerLowest: Color(0xFF0B1518),
          surfaceContainerLow: Color(0xFF132428),
          surfaceContainer: SunriseColors.nightCard,
          surfaceContainerHigh: Color(0xFF1D3339),
          surfaceContainerHighest: Color(0xFF243D44),
          outline: Color(0xFF5C7379),
          outlineVariant: Color(0xFF2E474E),
        )
      : const ColorScheme(
          brightness: Brightness.light,
          primary: SunriseColors.teal,
          onPrimary: Colors.white,
          primaryContainer: Color(0xFFD3ECF0),
          onPrimaryContainer: Color(0xFF07343E),
          secondary: SunriseColors.orange,
          onSecondary: SunriseColors.ink,
          secondaryContainer: Color(0xFFFCE3C8),
          onSecondaryContainer: Color(0xFF4A2A06),
          error: SunriseColors.red,
          onError: Colors.white,
          surface: SunriseColors.cream,
          onSurface: SunriseColors.ink,
          onSurfaceVariant: SunriseColors.inkSoft,
          surfaceContainerLowest: Colors.white,
          surfaceContainerLow: Color(0xFFFFFCF6),
          surfaceContainer: Colors.white,
          surfaceContainerHigh: Color(0xFFF7EEE0),
          surfaceContainerHighest: Color(0xFFF1E6D6),
          outline: Color(0xFF8A9A9F),
          outlineVariant: Color(0xFFEADFCD),
        );

  const heading = 'Nunito';
  const body = 'NotoSans';

  final base = ThemeData(useMaterial3: true, colorScheme: scheme, fontFamily: body);
  final text = base.textTheme.copyWith(
    displaySmall: base.textTheme.displaySmall?.copyWith(
      fontFamily: heading,
      fontWeight: FontWeight.w900,
    ),
    headlineLarge: base.textTheme.headlineLarge?.copyWith(
      fontFamily: heading,
      fontWeight: FontWeight.w900,
    ),
    headlineMedium: base.textTheme.headlineMedium?.copyWith(
      fontFamily: heading,
      fontWeight: FontWeight.w900,
    ),
    headlineSmall: base.textTheme.headlineSmall?.copyWith(
      fontFamily: heading,
      fontWeight: FontWeight.w800,
    ),
    titleLarge: base.textTheme.titleLarge?.copyWith(
      fontFamily: heading,
      fontWeight: FontWeight.w800,
    ),
    titleMedium: base.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
    bodyLarge: base.textTheme.bodyLarge?.copyWith(height: 1.45),
    bodyMedium: base.textTheme.bodyMedium?.copyWith(height: 1.45),
  );

  return base.copyWith(
    textTheme: text,
    scaffoldBackgroundColor: scheme.surface,
    extensions: [isDark ? FeedbackColors.dark : FeedbackColors.light],
    appBarTheme: AppBarTheme(
      backgroundColor: scheme.surface,
      foregroundColor: scheme.onSurface,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: false,
      // Theme text styles get their sizes only inside Theme.of(); the app bar needs one here.
      titleTextStyle: TextStyle(
        fontFamily: heading,
        fontWeight: FontWeight.w800,
        fontSize: 20,
        height: 1.3,
        color: scheme.onSurface,
      ),
    ),
    cardTheme: CardThemeData(
      color: scheme.surfaceContainer,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        minimumSize: const Size(64, 52),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: const TextStyle(fontFamily: body, fontWeight: FontWeight.w700, fontSize: 16),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(64, 52),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        side: BorderSide(color: scheme.outline),
        textStyle: const TextStyle(fontFamily: body, fontWeight: FontWeight.w700, fontSize: 16),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: scheme.surfaceContainer,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: scheme.surfaceContainer,
      indicatorColor: scheme.primaryContainer,
    ),
    listTileTheme: const ListTileThemeData(
      contentPadding: EdgeInsetsDirectional.symmetric(horizontal: 16),
    ),
  );
}
