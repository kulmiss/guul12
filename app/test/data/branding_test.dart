import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/branding.dart';
import 'package:guul12/ui/theme.dart';
import 'package:material_ui/material_ui.dart';

/// What the server sends in /config "branding" for the Nile palette.
Map<String, Object?> nileJson({Map<String, Object?> override = const {}}) => {
  'version': 7,
  'palette': 'nile',
  'light': {'primary': '#1D4E89', 'accent': '#E0A100', 'background': '#F6F8FC', 'text': '#14213D'},
  'dark': {'primary': '#8FB8F0', 'accent': '#F2C14E'},
  'on_primary': '#FFFFFF',
  'on_accent': '#14213D',
  'heading_font': 'poppins',
  'body_font': 'inter',
  'roundness': 'round',
  'radius': {'card': 24, 'button': 28},
  'logo_text': 'Guul 12 Nile',
  'logo_light_url': 'https://guul12.test/storage/branding/logo-light-0123456789abcdef.png',
  'logo_dark_url': null,
  ...override,
};

void main() {
  test('Sunrise is the built-in branding and matches the server default', () {
    const s = Branding.sunrise;
    expect(s.version, 0);
    expect(s.primary, SunriseColors.teal);
    expect(s.accent, SunriseColors.orange);
    expect(s.background, SunriseColors.cream);
    expect(s.text, SunriseColors.ink);
    expect(s.primaryDark, SunriseColors.tealDark);
    expect(s.accentDark, SunriseColors.orangeDark);
    expect(
      (s.headingFont, s.bodyFont, s.cardRadius, s.buttonRadius),
      ('Nunito', 'NotoSans', 18.0, 14.0),
    );
    expect(s.isSunriseLook, isTrue);
  });

  test('reads the server branding', () {
    final b = Branding.fromJson(nileJson());
    expect(b.version, 7);
    expect(b.primary, const Color(0xFF1D4E89));
    expect(b.onPrimary, const Color(0xFFFFFFFF));
    expect(b.onAccent, const Color(0xFF14213D));
    expect(b.primaryDark, const Color(0xFF8FB8F0));
    expect((b.headingFont, b.bodyFont), ('Poppins', 'Inter'));
    expect((b.cardRadius, b.buttonRadius), (24.0, 28.0));
    expect(b.logoText, 'Guul 12 Nile');
    expect(b.logoLightUrl, 'https://guul12.test/storage/branding/logo-light-0123456789abcdef.png');
    expect(b.logoDarkUrl, isNull);
    expect(b.isSunriseLook, isFalse);
    expect(Branding.fromJson(b.toJson()), b, reason: 'kept on the phone and read back the same');
  });

  test('anything broken falls back to Sunrise, never half a branding', () {
    for (final broken in <Map<String, Object?>>[
      nileJson(
        override: {
          'light': {'primary': 'blue'},
        },
      ),
      nileJson(override: {'heading_font': 'comic_sans'}),
      nileJson(
        override: {
          'radius': {'card': -4, 'button': 14},
        },
      ),
      nileJson(
        override: {
          'radius': {'card': 500, 'button': 14},
        },
      ),
      nileJson(override: {'logo_text': ''}),
      nileJson(override: {'logo_light_url': 'http://insecure.test/logo.png'}),
      {'version': 'x'},
      {},
    ]) {
      expect(Branding.fromJson(broken), Branding.sunrise, reason: '$broken');
    }
    expect(Branding.fromJsonOrSunrise(null), Branding.sunrise);
    expect(Branding.fromJsonOrSunrise('not a map'), Branding.sunrise);
  });

  test('Sunrise keeps the hand-tuned theme; another branding changes colours, fonts and shape', () {
    final sunrise = brandTheme(Branding.sunrise, Brightness.light);
    expect(sunrise.colorScheme.primary, SunriseColors.teal);
    expect(sunrise.colorScheme.primaryContainer, const Color(0xFFD3ECF0));
    expect(sunrise.colorScheme.surface, SunriseColors.cream);
    expect(sunrise.textTheme.headlineSmall?.fontFamily, 'Nunito');
    expect(sunrise.textTheme.bodyMedium?.fontFamily, 'NotoSans');
    expect(
      brandTheme(Branding.sunrise, Brightness.dark).colorScheme.primary,
      SunriseColors.tealDark,
    );

    final nile = Branding.fromJson(nileJson());
    final light = brandTheme(nile, Brightness.light);
    expect(light.colorScheme.primary, const Color(0xFF1D4E89));
    expect(light.colorScheme.onPrimary, const Color(0xFFFFFFFF));
    expect(light.colorScheme.secondary, const Color(0xFFE0A100));
    expect(light.colorScheme.onSecondary, const Color(0xFF14213D));
    expect(light.colorScheme.surface, const Color(0xFFF6F8FC));
    expect(light.colorScheme.onSurface, const Color(0xFF14213D));
    expect(light.textTheme.headlineSmall?.fontFamily, 'Poppins');
    expect(light.textTheme.bodyMedium?.fontFamily, 'Inter');
    final card = light.cardTheme.shape! as RoundedRectangleBorder;
    expect(card.borderRadius, BorderRadius.circular(24));

    final dark = brandTheme(nile, Brightness.dark);
    expect(dark.colorScheme.primary, const Color(0xFF8FB8F0));
    expect(dark.colorScheme.onPrimary, SunriseColors.night);
    expect(dark.colorScheme.secondary, const Color(0xFFF2C14E));
    expect(dark.colorScheme.surface, SunriseColors.night);
  });
}
