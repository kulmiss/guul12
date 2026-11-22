import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:http/http.dart' as http;
import 'package:material_ui/material_ui.dart';

import 'app/app.dart';
import 'app/providers.dart';
import 'data/api_client.dart';
import 'data/local_db.dart';
import 'data/repository.dart';
import 'data/token_store.dart';
import 'services/google_auth.dart';
import 'services/reminder_background.dart';
import 'services/reminders.dart';
import 'services/speech.dart';

/// Set at build time, e.g. `--dart-define=API_BASE_URL=https://guul12.kulmiss.com`.
const apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'https://guul12.kulmiss.com',
);

/// The *web* OAuth client ID. Empty until the owner creates it; then Google sign-in turns on.
const googleServerClientId = String.fromEnvironment('GOOGLE_SERVER_CLIENT_ID');

/// Keep in step with `version:` in pubspec.yaml (checked by a test).
const appVersion = String.fromEnvironment('APP_VERSION', defaultValue: '1.0.0+1');

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final db = await LocalDb.open();
  final reminders = await PhoneReminders.start(dispatcher: reminderDispatcher);
  final tokens = SecureTokenStore();
  final repository = Repository(
    api: ApiClient(baseUrl: apiBaseUrl, tokens: tokens, client: http.Client()),
    db: db,
    tokens: tokens,
  );
  runApp(
    ProviderScope(
      retry: (_, _) => null,
      overrides: [
        repositoryProvider.overrideWithValue(repository),
        appVersionProvider.overrideWithValue(appVersion),
        speechProvider.overrideWithValue(PhoneSpeech()),
        remindersProvider.overrideWithValue(reminders),
        googleAuthProvider.overrideWithValue(
          googleServerClientId.isEmpty
              ? const DisabledGoogleAuth()
              : PluginGoogleAuth(googleServerClientId),
        ),
      ],
      child: const GuulApp(),
    ),
  );
}
