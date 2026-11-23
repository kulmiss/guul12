import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/app/ads_providers.dart';
import 'package:guul12/app/app.dart';
import 'package:guul12/app/providers.dart';
import 'package:guul12/data/hook_questions.dart';
import 'package:guul12/data/repository.dart';
import 'package:guul12/services/ads.dart';
import 'package:guul12/services/google_auth.dart';
import 'package:guul12/services/reminders.dart';
import 'package:guul12/services/sharing.dart';
import 'package:guul12/services/speech.dart';
import 'package:guul12/ui/screens/onboarding_screen.dart';
import 'package:material_ui/material_ui.dart';

import 'fake_server.dart';

/// Phone (360 dp wide, the smallest we support) and tablet sizes.
const phoneSize = Size(360, 780);
const tabletSize = Size(1024, 768);

final testNow = DateTime(2026, 11, 22, 10);

class RecordingSpeech implements Speech {
  final spoken = <String>[];

  @override
  Future<void> speak(String text) async => spoken.add(text);

  @override
  Future<void> stop() async {}
}

class FakeGoogleAuth implements GoogleAuth {
  FakeGoogleAuth({this.token = 'google-id-token'});

  final String? token;

  @override
  bool get isConfigured => true;

  @override
  Future<String?> idToken() async => token;
}

/// Starts the whole app against the fake server. Returns the repository and server.
Future<(Repository, FakeServer)> pumpGuul(
  WidgetTester tester, {
  FakeServer? server,
  Size size = phoneSize,
  String appVersion = '1.0.0+1',
  Speech? speech,
  Reminders? reminders,
  Sharing? sharing,
  Ads? ads,
  GoogleAuth? googleAuth,
  Future<void> Function(Repository repo)? before,
  DateTime Function()? clock,
}) async {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);

  final (repo, s, _) = await makeRepository(server: server, noIsolate: true);
  if (before != null) await before(repo);
  await tester.pumpWidget(
    ProviderScope(
      retry: (_, _) => null,
      overrides: [
        repositoryProvider.overrideWithValue(repo),
        clockProvider.overrideWithValue(clock ?? () => testNow),
        appVersionProvider.overrideWithValue(appVersion),
        speechProvider.overrideWithValue(speech ?? const SilentSpeech()),
        remindersProvider.overrideWithValue(reminders ?? const NoReminders()),
        sharingProvider.overrideWithValue(sharing ?? const NoSharing()),
        adsProvider.overrideWithValue(ads ?? const NoAds()),
        googleAuthProvider.overrideWithValue(googleAuth ?? const DisabledGoogleAuth()),
        // The real asset loader uses platform channels; read the same file directly.
        hookQuestionsProvider.overrideWith(
          (ref) => parseHookQuestions(File(hookQuestionsAsset).readAsStringSync()),
        ),
      ],
      child: const GuulApp(),
    ),
  );
  await settle(tester);
  return (repo, s);
}

/// pumpAndSettle with a bound, so a looping animation fails clearly instead of hanging.
Future<void> settle(WidgetTester tester) async {
  for (var i = 0; i < 4; i++) {
    await tester.pumpAndSettle(
      const Duration(milliseconds: 50),
      EnginePhase.sendSemanticsUpdate,
      const Duration(seconds: 10),
    );
    await tester.idle();
  }
}

/// A finished onboarding as the server knows it (Grade 12 natural, Somali).
void onboardedProfile(FakeServer server) {
  server.profile.addAll({
    'grade': 12,
    'stream': 'natural',
    'region': 'somali',
    'city': 'Jijiga',
    'target_score': 450,
    'onboarded': true,
  });
}

/// Scrolls the widget into view, taps it and waits for the app to settle.
Future<void> tapOn(WidgetTester tester, Finder finder) async {
  await tester.ensureVisible(finder.first);
  await tester.pump();
  await tester.tap(finder.first);
  await settle(tester);
}
