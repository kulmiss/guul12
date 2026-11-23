import 'dart:async';
import 'dart:typed_data';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

import '../data/models.dart';
import '../data/repository.dart';
import '../services/google_auth.dart';
import '../services/reminders.dart';
import '../services/sharing.dart';
import '../services/speech.dart';

/// Overridden in main() and in tests.
final repositoryProvider = Provider<Repository>(
  (ref) => throw UnimplementedError('repositoryProvider'),
);
final googleAuthProvider = Provider<GoogleAuth>((ref) => const DisabledGoogleAuth());
final speechProvider = Provider<Speech>((ref) => const SilentSpeech());
final remindersProvider = Provider<Reminders>((ref) => const NoReminders());
final sharingProvider = Provider<Sharing>((ref) => const NoSharing());
final clockProvider = Provider<DateTime Function()>((ref) => DateTime.now);
final appVersionProvider = Provider<String>((ref) => '1.0.0+1');

class ProfileNotifier extends AsyncNotifier<Profile> {
  @override
  Future<Profile> build() => ref.watch(repositoryProvider).profile();

  Future<void> save(Map<String, Object?> changes) async {
    final repo = ref.read(repositoryProvider);
    await repo.updateProfile(changes);
    state = AsyncData(await repo.profile());
  }

  Future<void> saveIdentity({
    required String nickname,
    required String avatarStyle,
    required String avatarSeed,
  }) async {
    final repo = ref.read(repositoryProvider);
    await repo.saveIdentity(nickname: nickname, avatarStyle: avatarStyle, avatarSeed: avatarSeed);
    state = AsyncData(await repo.profile());
  }

  Future<void> reload() async {
    state = AsyncData(await ref.read(repositoryProvider).profile());
  }
}

final profileProvider = AsyncNotifierProvider<ProfileNotifier, Profile>(ProfileNotifier.new);

final catalogProvider = FutureProvider<Catalog>((ref) async {
  ref.watch(syncGenerationProvider);
  return ref.watch(repositoryProvider).catalog();
});

final configProvider = FutureProvider<AppConfig>((ref) async {
  ref.watch(syncGenerationProvider);
  return ref.watch(repositoryProvider).config();
});

/// The branding logo on the phone (light or dark mode), or null for the built-in icon.
final brandLogoProvider = FutureProvider.family<Uint8List?, bool>((ref, dark) async {
  ref.watch(syncGenerationProvider);
  return ref.watch(repositoryProvider).brandLogo(dark: dark);
});

final savedChaptersProvider = FutureProvider<Set<int>>((ref) async {
  ref.watch(syncGenerationProvider);
  return ref.watch(repositoryProvider).db.savedChapterIds();
});

/// Loads a chapter from the phone, downloading it the first time.
final chapterProvider = FutureProvider.family<ChapterContent, int>((ref, id) async {
  ref.watch(syncGenerationProvider);
  final chapter = await ref.watch(repositoryProvider).chapter(id);
  ref.invalidate(savedChaptersProvider);
  return chapter;
});

final recentChaptersProvider = FutureProvider<List<int>>((ref) async {
  ref.watch(syncGenerationProvider);
  return ref.watch(repositoryProvider).recentChapters();
});

final bookmarksProvider = FutureProvider<Set<int>>((ref) async {
  ref.watch(syncGenerationProvider);
  return ref.watch(repositoryProvider).bookmarks();
});

/// Goes up by one after every sync so screens reload fresh data from the phone.
class SyncGeneration extends Notifier<int> {
  @override
  int build() => 0;

  void bump() => state++;
}

final syncGenerationProvider = NotifierProvider<SyncGeneration, int>(SyncGeneration.new);

/// Whether the last sync reached the server.
class OnlineState extends Notifier<bool> {
  @override
  bool build() => true;

  void set(bool value) => state = value;
}

final onlineProvider = NotifierProvider<OnlineState, bool>(OnlineState.new);

/// Prepares a background sync. Everything is read now, so the sync can finish
/// even after the screen that started it has closed. Never throws.
Future<void> Function() syncTask(WidgetRef ref) {
  final repo = ref.read(repositoryProvider);
  final version = ref.read(appVersionProvider);
  final online = ref.read(onlineProvider.notifier);
  final generation = ref.read(syncGenerationProvider.notifier);
  final profile = ref.read(profileProvider.notifier);
  return () async {
    final ok = await repo.syncAll(appVersion: version);
    online.set(ok);
    generation.bump();
    await profile.reload();
  };
}

/// Runs a background sync now; never throws.
Future<void> runSync(WidgetRef ref) => syncTask(ref)();

class ThemeModeNotifier extends AsyncNotifier<ThemeMode> {
  static const _key = 'theme_mode';

  @override
  Future<ThemeMode> build() async {
    final raw = await ref.watch(repositoryProvider).db.readString(_key);
    return ThemeMode.values.firstWhere((m) => m.name == raw, orElse: () => ThemeMode.system);
  }

  Future<void> set(ThemeMode mode) async {
    state = AsyncData(mode);
    await ref.read(repositoryProvider).db.writeString(_key, mode.name);
  }
}

final themeModeProvider = AsyncNotifierProvider<ThemeModeNotifier, ThemeMode>(
  ThemeModeNotifier.new,
);

/// Fire-and-forget helper that keeps the analyzer happy about intentionally unawaited futures.
void fireAndForget(Future<void> future) => unawaited(future);
