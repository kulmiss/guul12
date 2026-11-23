import 'dart:ui' show Color;

import 'package:flutter/foundation.dart' show immutable;

/// The look set in Nova → Settings → Branding: colours, fonts, roundness and logo.
///
/// Sent in `/config` and kept on the phone with it, so it works offline. Anything missing or
/// broken means the whole branding falls back to [Branding.sunrise] (never half of one).
@immutable
class Branding {
  const Branding({
    required this.version,
    required this.palette,
    required this.primary,
    required this.accent,
    required this.background,
    required this.text,
    required this.primaryDark,
    required this.accentDark,
    required this.onPrimary,
    required this.onAccent,
    required this.headingFont,
    required this.bodyFont,
    required this.cardRadius,
    required this.buttonRadius,
    required this.logoText,
    this.logoLightUrl,
    this.logoDarkUrl,
  });

  /// The built-in look (design direction A), the same as the server's default.
  static const sunrise = Branding(
    version: 0,
    palette: 'sunrise',
    primary: Color(0xFF0E5E6F),
    accent: Color(0xFFF28C28),
    background: Color(0xFFFFF8EE),
    text: Color(0xFF1C2B30),
    primaryDark: Color(0xFF5FC1CF),
    accentDark: Color(0xFFF5A35A),
    onPrimary: Color(0xFFFFFFFF),
    onAccent: Color(0xFF1C2B30),
    headingFont: 'Nunito',
    bodyFont: 'NotoSans',
    cardRadius: 18,
    buttonRadius: 14,
    logoText: 'Guul 12',
  );

  /// Server font key → font family bundled in the app (pubspec.yaml).
  static const fonts = {
    'nunito': 'Nunito',
    'noto_sans': 'NotoSans',
    'inter': 'Inter',
    'poppins': 'Poppins',
  };

  final int version;
  final String palette;
  final Color primary;
  final Color accent;
  final Color background;
  final Color text;
  final Color primaryDark;
  final Color accentDark;
  final Color onPrimary;
  final Color onAccent;
  final String headingFont;
  final String bodyFont;
  final double cardRadius;
  final double buttonRadius;
  final String logoText;
  final String? logoLightUrl;
  final String? logoDarkUrl;

  /// True when it looks exactly like Sunrise (the hand-tuned colours can be used).
  bool get isSunriseLook =>
      primary == sunrise.primary &&
      accent == sunrise.accent &&
      background == sunrise.background &&
      text == sunrise.text &&
      primaryDark == sunrise.primaryDark &&
      accentDark == sunrise.accentDark;

  static Branding fromJsonOrSunrise(Object? json) =>
      json is Map ? Branding.fromJson(json.cast<String, Object?>()) : sunrise;

  factory Branding.fromJson(Map<String, Object?> json) {
    try {
      final light = (json['light']! as Map).cast<String, Object?>();
      final dark = (json['dark']! as Map).cast<String, Object?>();
      final radius = (json['radius']! as Map).cast<String, Object?>();
      final logoText = (json['logo_text']! as String).trim();
      if (logoText.isEmpty || logoText.length > 30) return sunrise;
      return Branding(
        version: json['version']! as int,
        palette: json['palette']! as String,
        primary: _colour(light['primary']),
        accent: _colour(light['accent']),
        background: _colour(light['background']),
        text: _colour(light['text']),
        primaryDark: _colour(dark['primary']),
        accentDark: _colour(dark['accent']),
        onPrimary: _colour(json['on_primary']),
        onAccent: _colour(json['on_accent']),
        headingFont: fonts[json['heading_font']]!,
        bodyFont: fonts[json['body_font']]!,
        cardRadius: _radius(radius['card']),
        buttonRadius: _radius(radius['button']),
        logoText: logoText,
        logoLightUrl: _url(json['logo_light_url']),
        logoDarkUrl: _url(json['logo_dark_url']),
      );
    } on Object {
      // A missing key, a wrong type or a bad value: the safe look.
      return sunrise;
    }
  }

  /// The same shape the server sends, to keep it on the phone.
  Map<String, Object?> toJson() => {
    'version': version,
    'palette': palette,
    'light': {
      'primary': _hex(primary),
      'accent': _hex(accent),
      'background': _hex(background),
      'text': _hex(text),
    },
    'dark': {'primary': _hex(primaryDark), 'accent': _hex(accentDark)},
    'on_primary': _hex(onPrimary),
    'on_accent': _hex(onAccent),
    'heading_font': fonts.entries.firstWhere((e) => e.value == headingFont).key,
    'body_font': fonts.entries.firstWhere((e) => e.value == bodyFont).key,
    'radius': {'card': cardRadius.round(), 'button': buttonRadius.round()},
    'logo_text': logoText,
    'logo_light_url': logoLightUrl,
    'logo_dark_url': logoDarkUrl,
  };

  static Color _colour(Object? value) {
    final match = RegExp(r'^#([0-9A-Fa-f]{6})$').firstMatch(value! as String)!;
    return Color(0xFF000000 | int.parse(match.group(1)!, radix: 16));
  }

  static double _radius(Object? value) {
    final r = (value! as num).toDouble();
    if (r < 0 || r > 40) throw const FormatException('radius');
    return r;
  }

  /// Only https addresses (or none).
  static String? _url(Object? value) {
    if (value == null) return null;
    final url = value as String;
    if (!url.startsWith('https://')) throw const FormatException('url');
    return url;
  }

  static String _hex(Color c) =>
      '#${(c.toARGB32() & 0xFFFFFF).toRadixString(16).padLeft(6, '0').toUpperCase()}';

  @override
  bool operator ==(Object other) {
    if (other is! Branding) return false;
    final (a, b) = (_key, other._key);
    for (var i = 0; i < a.length; i++) {
      if (a[i] != b[i]) return false;
    }
    return true;
  }

  @override
  int get hashCode => Object.hashAll(_key);

  List<Object?> get _key => [
    version,
    palette,
    primary,
    accent,
    background,
    text,
    primaryDark,
    accentDark,
    onPrimary,
    onAccent,
    headingFont,
    bodyFont,
    cardRadius,
    buttonRadius,
    logoText,
    logoLightUrl,
    logoDarkUrl,
  ];
}
