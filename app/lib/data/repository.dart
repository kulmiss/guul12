import 'dart:async';

import 'api_client.dart';
import 'local_db.dart';
import 'models.dart';
import 'token_store.dart';

/// Keys in the local key/value table.
abstract final class Keys {
  static const catalog = 'catalog';
  static const cursor = 'sync_cursor';
  static const serverProfile = 'profile_server';
  static const pendingProfile = 'profile_pending';
  static const config = 'config';
  static const recentChapters = 'recent_chapters';
}

/// Offline-first data access. Every read comes from the phone; the network only refreshes it.
class Repository {
  Repository({
    required this.api,
    required this.db,
    required this.tokens,
    DateTime Function()? clock,
  }) : _clock = clock ?? DateTime.now;

  final ApiClient api;
  final LocalDb db;
  final TokenStore tokens;
  final DateTime Function() _clock;

  static const _profileFields = {
    'grade',
    'stream',
    'is_retaker',
    'region',
    'city',
    'gender',
    'target_score',
    'reminder_time',
    'onboarded',
  };

  // ---- session ---------------------------------------------------------------------------------

  /// Makes sure this install has an account and a token. Works silently; throws [ApiException] when offline.
  Future<void> ensureSession({String platform = 'android', String? appVersion}) async {
    if (await tokens.readToken() != null) return;
    await _register(platform, appVersion);
  }

  Future<void> _register(String platform, String? appVersion) async {
    final json = await api.post('auth/device', {
      'install_id': await tokens.installId(),
      'platform': platform,
      'app_version': ?appVersion,
    }, auth: false);
    await tokens.writeToken(asString(json['token']));
    await db.writeJson(Keys.serverProfile, asMap(json['user']));
  }

  /// Runs [call]; if the token was rejected (e.g. account merged elsewhere), registers again once and retries.
  Future<T> _authed<T>(Future<T> Function() call) async {
    try {
      return await call();
    } on ApiException catch (e) {
      if (!e.isUnauthorized) rethrow;
      await tokens.clearToken();
      await _register('android', null);
      return call();
    }
  }

  /// Google sign-in. Returns the new profile. Throws [ApiException] (code `account_conflict`, `invalid_google_token`).
  Future<Profile> signInWithGoogle(String idToken) async {
    final json = await _authed(
      () async =>
          api.post('auth/google', {'id_token': idToken, 'install_id': await tokens.installId()}),
    );
    await tokens.writeToken(asString(json['token']));
    await db.writeJson(Keys.serverProfile, asMap(json['user']));
    return profile();
  }

  // ---- profile ---------------------------------------------------------------------------------

  /// Server profile with any changes not yet uploaded applied on top.
  Future<Profile> profile() async {
    final server = await db.readJson(Keys.serverProfile) ?? _emptyProfileJson;
    final pending = await db.readJson(Keys.pendingProfile) ?? const {};
    return Profile.fromJson({...server, ...pending});
  }

  static const Map<String, Object?> _emptyProfileJson = {
    'student_id': null,
    'signed_in': false,
    'grade': null,
    'stream': null,
    'is_retaker': false,
    'region': null,
    'city': null,
    'gender': null,
    'target_score': null,
    'reminder_time': null,
    'onboarded': false,
    'premium': {'active': false, 'until': null},
  };

  /// Saves profile changes on the phone at once and uploads them when possible.
  Future<void> updateProfile(Map<String, Object?> changes) async {
    final unknown = changes.keys.where((k) => !_profileFields.contains(k));
    if (unknown.isNotEmpty) throw ArgumentError('Unknown profile fields: $unknown');
    final pending = {...?await db.readJson(Keys.pendingProfile), ...changes};
    await db.writeJson(Keys.pendingProfile, pending);
    unawaited(_tryQuietly(uploadProfile));
  }

  Future<void> uploadProfile() async {
    final pending = await db.readJson(Keys.pendingProfile);
    if (pending == null || pending.isEmpty) return;
    final body = {...pending};
    // The server only accepts `onboarded: true`.
    if (body['onboarded'] != true) body.remove('onboarded');
    final json = await _authed(() => api.patch('me', body));
    await db.writeJson(Keys.serverProfile, asMap(json['user']));
    // Only clear what was sent; newer local edits stay pending.
    final now = await db.readJson(Keys.pendingProfile) ?? const {};
    final remaining = {
      for (final e in now.entries)
        if (!pending.containsKey(e.key) || pending[e.key] != e.value) e.key: e.value,
    };
    await db.writeJson(Keys.pendingProfile, remaining);
  }

  Future<void> refreshProfile() async {
    final json = await _authed(() => api.get('me'));
    await db.writeJson(Keys.serverProfile, asMap(json['user']));
  }

  // ---- config ----------------------------------------------------------------------------------

  Future<AppConfig> config() async {
    final json = await db.readJson(Keys.config);
    return json == null ? AppConfig.fallback : AppConfig.fromJson(json);
  }

  Future<AppConfig> refreshConfig() async {
    final json = await _authed(() => api.get('config'));
    await db.writeJson(Keys.config, json);
    return AppConfig.fromJson(json);
  }

  // ---- content ---------------------------------------------------------------------------------

  Future<Catalog> catalog() async {
    final json = await db.readJson(Keys.catalog);
    return json == null ? Catalog.empty : Catalog.fromJson(json);
  }

  Future<Catalog> refreshCatalog() async {
    final json = await _authed(() => api.get('catalog'));
    await db.writeJson(Keys.catalog, json);
    final catalog = Catalog.fromJson(json);
    if (await db.readInt(Keys.cursor) == 0) await db.writeInt(Keys.cursor, catalog.cursor);
    return catalog;
  }

  /// Chapter from the phone; downloads and saves it the first time (needs internet once).
  Future<ChapterContent> chapter(int id) async {
    final local = await db.chapter(id);
    if (local != null) return local;
    return downloadChapter(id);
  }

  Future<ChapterContent> downloadChapter(int id) async {
    final json = await _authed(() => api.get('chapters/$id'));
    await db.saveChapter(id, json, _clock());
    return ChapterContent.fromJson(json);
  }

  /// Downloads only what changed since the last sync. Returns the number of chapters refreshed.
  Future<int> syncContent() async {
    var cursor = await db.readInt(Keys.cursor);
    final saved = await db.savedChapterIds();
    final toRefresh = <int>{};
    var catalogChanged = false;

    while (true) {
      final json = await _authed(() => api.get('sync/changes', query: {'since': '$cursor'}));
      catalogChanged |= asBool(json['catalog_changed']);
      for (final id in asList(json['chapter_ids'])) {
        final chapterId = asInt(id);
        if (saved.contains(chapterId)) toRefresh.add(chapterId);
      }
      cursor = asInt(json['cursor']);
      if (!asBool(json['has_more'])) break;
    }

    if (catalogChanged) await refreshCatalog();

    final active = (await catalog()).chapters.map((c) => c.id).toSet();
    for (final id in toRefresh) {
      if (active.contains(id)) {
        await downloadChapter(id);
      } else {
        await db.deleteChapter(id);
      }
    }

    await db.writeInt(Keys.cursor, cursor);
    return toRefresh.length;
  }

  /// Chapters opened lately, newest first (for "Continue studying").
  Future<List<int>> recentChapters() async {
    final raw = await db.readString(Keys.recentChapters);
    if (raw == null || raw.isEmpty) return const [];
    return raw.split(',').map(int.tryParse).whereType<int>().toList();
  }

  Future<void> rememberChapter(int id, {int keep = 3}) async {
    final list = [id, ...(await recentChapters()).where((e) => e != id)].take(keep);
    await db.writeString(Keys.recentChapters, list.join(','));
  }

  // ---- attempts --------------------------------------------------------------------------------

  /// Stores a finished practice set on the phone, then tries to upload it.
  Future<void> saveAttempt({
    required String clientUuid,
    required String mode,
    required int? chapterId,
    required DateTime startedAt,
    required DateTime finishedAt,
    required List<AttemptAnswer> answers,
  }) async {
    final payload = <String, Object?>{
      'client_uuid': clientUuid,
      'mode': mode,
      'chapter_id': chapterId,
      'started_at': startedAt.toUtc().toIso8601String(),
      'finished_at': finishedAt.toUtc().toIso8601String(),
      'answers': [for (final a in answers) a.toJson()],
    };
    await db.queueAttempt(clientUuid, payload, _clock());
    await db.recordAnswers([
      for (final a in answers)
        (questionId: a.questionId, chapterId: a.chapterId, correct: a.correctLocally),
    ], finishedAt);
  }

  /// Uploads attempts that were saved offline. Returns how many were uploaded.
  Future<int> uploadAttempts() async {
    var uploaded = 0;
    for (final (uuid, payload) in await db.pendingAttempts()) {
      try {
        await _authed(() => api.post('attempts', payload));
      } on ApiException catch (e) {
        // A rejected attempt (e.g. finish time in the future) would block the queue forever: drop it.
        if (e.isOffline || e.status >= 500 || e.status == 429) rethrow;
      }
      await db.removeAttempt(uuid);
      uploaded++;
    }
    return uploaded;
  }

  /// Uploads waiting attempts right away. True when nothing is left waiting. Never throws.
  Future<bool> tryUploadAttempts({String? appVersion}) async {
    try {
      await ensureSession(appVersion: appVersion);
      await uploadAttempts();
    } on ApiException {
      // Stays queued; syncAll retries later.
    }
    return (await db.pendingAttempts()).isEmpty;
  }

  // ---- bookmarks -------------------------------------------------------------------------------

  Future<Set<int>> bookmarks() => db.bookmarks();

  Future<void> toggleBookmark(int questionId, {required bool on}) async {
    await db.setBookmark(questionId, on: on);
    unawaited(_tryQuietly(syncBookmarks));
  }

  Future<void> syncBookmarks() async {
    for (final (id, on) in await db.unsyncedBookmarks()) {
      try {
        await _authed(() => on ? api.put('bookmarks/$id') : api.delete('bookmarks/$id'));
      } on ApiException catch (e) {
        if (e.isOffline || e.status >= 500 || e.status == 429) rethrow;
        // 404: the question is gone; forget the bookmark.
        await db.markBookmarkSynced(id, on: false);
        continue;
      }
      await db.markBookmarkSynced(id, on: on);
    }
    final json = await _authed(() => api.get('bookmarks'));
    await db.replaceBookmarksFromServer(asList(json['question_ids']).map(asInt).toSet());
  }

  // ---- everything ------------------------------------------------------------------------------

  /// Background sync on start and when the app comes back online. Never throws.
  Future<bool> syncAll({String? appVersion}) async {
    try {
      await ensureSession(appVersion: appVersion);
      await uploadProfile();
      await refreshConfig();
      await uploadAttempts();
      await syncBookmarks();
      if ((await catalog()).subjects.isEmpty) await refreshCatalog();
      await syncContent();
      await refreshProfile();
      return true;
    } on ApiException {
      return false;
    }
  }

  Future<void> _tryQuietly(Future<void> Function() action) async {
    try {
      await action();
    } on ApiException {
      // Offline or server busy: it stays queued and is retried by syncAll.
    }
  }
}

class AttemptAnswer {
  const AttemptAnswer({
    required this.questionId,
    required this.chapterId,
    required this.version,
    required this.response,
    required this.timeMs,
    required this.correctLocally,
  });

  final int questionId;
  final int chapterId;
  final int version;
  final Object? response;
  final int timeMs;

  /// The phone's own check, used for offline history. The server decides the official result.
  final bool correctLocally;

  Map<String, Object?> toJson() => {
    'question_id': questionId,
    'version': version,
    'response': response,
    'time_ms': timeMs,
  };
}
