import 'dart:convert';
import 'dart:io';

import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/app/router.dart';
import 'package:guul12/data/hook_questions.dart';
import 'package:guul12/data/models.dart';
import 'package:guul12/main.dart' as app;
import 'package:guul12/services/speech.dart';
import 'package:guul12/ui/screens/profile_fields.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

Profile profile({required bool onboarded}) => Profile(
  studentId: null,
  signedIn: false,
  grade: onboarded ? 12 : null,
  stream: null,
  isRetaker: false,
  region: null,
  city: null,
  gender: null,
  targetScore: null,
  reminderTime: null,
  onboarded: onboarded,
  premiumActive: false,
  premiumUntil: null,
);

AppConfig configWithMin(int build) => AppConfig(
  examDate: null,
  minSupportedBuild: build,
  recommendedBuild: build,
  maintenanceMessage: null,
  features: const {},
  premium: false,
  showAds: false,
);

void main() {
  group('redirectFor', () {
    String? go(String at, AsyncValue<Profile> p, {AppConfig? config, int build = 1}) =>
        redirectFor(location: at, profile: p, config: config, build: build);

    test('waits on /loading until the profile is read', () {
      expect(go('/home', const AsyncLoading()), '/loading');
      expect(go('/loading', const AsyncLoading()), isNull);
      expect(go('/home', AsyncError(Exception('db'), StackTrace.empty)), '/loading');
    });

    test('new students go to onboarding and cannot skip it', () {
      final p = AsyncData(profile(onboarded: false));
      expect(go('/loading', p), '/welcome');
      expect(go('/study', p), '/welcome');
      expect(go('/welcome', p), isNull);
    });

    test('onboarded students never see onboarding again', () {
      final p = AsyncData(profile(onboarded: true));
      expect(go('/welcome', p), '/home');
      expect(go('/loading', p), '/home');
      expect(go('/study/chapter/3', p), isNull);
    });

    test('too old builds are sent to the update screen, and only those', () {
      final p = AsyncData(profile(onboarded: true));
      expect(go('/home', p, config: configWithMin(5), build: 4), '/update');
      expect(go('/update', p, config: configWithMin(5), build: 4), isNull);
      expect(go('/home', p, config: configWithMin(5), build: 5), isNull);
      expect(go('/update', p, config: configWithMin(5), build: 5), '/loading');
      // An unknown build number (0) is never blocked.
      expect(go('/home', p, config: configWithMin(5), build: 0), isNull);
    });
  });

  test('buildNumber reads the part after +', () {
    expect(buildNumber('1.0.0+7'), 7);
    expect(buildNumber('1.0.0'), 0);
    expect(buildNumber('1.0.0+x'), 0);
  });

  test('the version in main.dart matches pubspec.yaml', () {
    final pubspec = File('pubspec.yaml').readAsStringSync();
    final version = RegExp(r'^version:\s*(\S+)', multiLine: true).firstMatch(pubspec)!.group(1);
    expect(app.appVersion, version);
  });

  test('reminder time ↔ API format', () {
    expect(timeToWire(const TimeOfDay(hour: 7, minute: 5)), '07:05');
    expect(timeToWire(null), isNull);
    expect(timeFromWire('19:30'), const TimeOfDay(hour: 19, minute: 30));
    expect(timeFromWire('24:00'), isNull);
    expect(timeFromWire('7:30'), isNull);
    expect(timeFromWire(null), isNull);
  });

  test('speakable removes LaTeX so the voice reads words', () {
    expect(
      speakable(r'What is $\frac{1}{2}$ of $8 \times 3$?'),
      'What is fraction 1 2 of 8 times 3 ?',
    );
    expect(speakable(r'$$\sqrt{9}$$'), 'square root of 9');
  });

  testWidgets('the bundled warm-up file loads through the asset bundle', (tester) async {
    final text = await tester.runAsync(() => rootBundle.loadString(hookQuestionsAsset));
    expect(parseHookQuestions(text!).keys, containsAll([9, 10, 11, 12]));
  });

  testWidgets('an old build shows only the update screen', (tester) async {
    final server = FakeServer()..minSupportedBuild = 2;
    onboardedProfile(server);
    await pumpGuul(tester, server: server, appVersion: '1.0.0+1');
    expect(find.text('Please update Guul 12'), findsOneWidget);
    expect(find.byType(NavigationBar), findsNothing);
  });

  group('Google Play rules: no price, no buying, no payment links in the app', () {
    final banned = RegExp(
      r'\b(birr|br\.?|etb|price|prices|buy|purchase|upgrade|subscribe|pay|payment|whatsapp|telebirr|cbe ?birr|t\.me|wa\.me)\b',
      caseSensitive: false,
    );

    test('UI texts', () {
      final arb = jsonDecode(File('lib/l10n/app_en.arb').readAsStringSync()) as Map;
      final texts = arb.entries.where((e) => !'${e.key}'.startsWith('@'));
      expect(texts.length, greaterThan(100));
      for (final e in texts) {
        expect(banned.hasMatch('${e.value}'), isFalse, reason: '${e.key}: "${e.value}"');
      }
    });

    test('code', () {
      for (final file in Directory('lib').listSync(recursive: true).whereType<File>()) {
        if (!file.path.endsWith('.dart') || file.path.contains('/generated/')) continue;
        final code = file.readAsStringSync();
        // Strings only (comments may explain the rule itself).
        for (final m in RegExp(r"'([^'\n]*)'").allMatches(code)) {
          expect(banned.hasMatch(m.group(1)!), isFalse, reason: '${file.path}: ${m.group(0)}');
        }
      }
    });

    test('Android manifest has no exact-alarm or location/contacts/camera permissions', () {
      final manifest = File('android/app/src/main/AndroidManifest.xml').readAsStringSync();
      for (final p in [
        'SCHEDULE_EXACT_ALARM',
        'USE_EXACT_ALARM',
        'ACCESS_FINE_LOCATION',
        'ACCESS_COARSE_LOCATION',
        'READ_CONTACTS',
        'CAMERA',
        'RECORD_AUDIO',
      ]) {
        expect(manifest.contains(p), isFalse, reason: p);
      }
    });

    test('Android manifest: internet, notifications, boot (reminder); foreground work and ad tracking removed', () {
      final manifest = File('android/app/src/main/AndroidManifest.xml').readAsStringSync();
      final permissions = RegExp(r'<uses-permission ([^>]*)/>').allMatches(manifest).map((m) {
        final name = RegExp(r'android:name="([^"]+)"').firstMatch(m.group(1)!)!.group(1)!;
        return (name, m.group(1)!.contains('tools:node="remove"'));
      }).toList();
      expect(
        [
          for (final (name, removed) in permissions)
            if (!removed) name,
        ],
        [
          'android.permission.INTERNET',
          'android.permission.POST_NOTIFICATIONS',
          'android.permission.RECEIVE_BOOT_COMPLETED',
        ],
      );
      expect(
        [
          for (final (name, removed) in permissions)
            if (removed) name,
        ],
        unorderedEquals([
          'android.permission.FOREGROUND_SERVICE',
          'android.permission.FOREGROUND_SERVICE_SHORT_SERVICE',
          'android.permission.ACCESS_ADSERVICES_TOPICS',
          'android.permission.ACCESS_ADSERVICES_ATTRIBUTION',
          'android.permission.ACCESS_ADSERVICES_AD_ID',
        ]),
      );
      expect(
        RegExp(
          r'android:name="androidx\.work\.impl\.foreground\.SystemForegroundService"\s+tools:node="remove"',
        ).hasMatch(manifest),
        isTrue,
      );
    });
  });
}
