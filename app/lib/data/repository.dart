import 'dart:async';
import 'dart:math';

import '../domain/exam.dart';
import '../domain/exam_builder.dart';
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
  static const pendingReports = 'pending_reports';
  static const mocks = 'mocks';
  static const examInProgress = 'exam_in_progress';
  static const historyRestored = 'history_restored';
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

  /// The repository's clock (tests replace it).
  DateTime now() => _clock();

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
    // The account may now be an older one with its own history: fetch it on the next sync.
    await db.writeString(Keys.historyRestored, '');
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
    int? mockId,
  }) async {
    final payload = <String, Object?>{
      'client_uuid': clientUuid,
      'mode': mode,
      'chapter_id': chapterId,
      'mock_id': ?mockId,
      'started_at': startedAt.toUtc().toIso8601String(),
      'finished_at': finishedAt.toUtc().toIso8601String(),
      'answers': [for (final a in answers) a.toJson()],
    };
    await db.queueAttempt(clientUuid, payload, _clock());
    final results = [
      for (final a in answers)
        (questionId: a.questionId, chapterId: a.chapterId, correct: a.correctLocally),
    ];
    await db.recordAnswers(results, finishedAt);
    // Skipped questions count as wrong, so they come back for review too.
    await db.applySrs(results, finishedAt);
  }

  /// Uploads attempts that were saved offline. Returns how many were uploaded.
  Future<int> uploadAttempts() async {
    var uploaded = 0;
    for (final (uuid, payload) in await db.pendingAttempts()) {
      try {
        final json = await _authed(() => api.post('attempts', payload));
        await _keepExamDetails(uuid, json);
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

  // ---- exams -----------------------------------------------------------------------------------

  /// Fixed mocks as last downloaded (works offline).
  Future<List<MockHeader>> mocks() async {
    final json = await db.readJson(Keys.mocks);
    return json == null
        ? const []
        : asList(json['mocks']).map((e) => MockHeader.fromJson(asMap(e))).toList();
  }

  Future<List<MockHeader>> refreshMocks() async {
    final json = await _authed(() => api.get('mocks'));
    await db.writeJson(Keys.mocks, json);
    return asList(json['mocks']).map((e) => MockHeader.fromJson(asMap(e))).toList();
  }

  /// A fixed mock, from the phone if its questions have not changed; downloaded otherwise.
  /// Throws [ApiException] (`premium_required`, offline).
  Future<ExamPaper> mockPaper(MockHeader header) async {
    final saved = await db.mock(header.id, header.contentHash);
    final Map<String, Object?> json;
    if (saved != null) {
      json = saved;
    } else {
      json = await _authed(() => api.get('mocks/${header.id}'));
      await db.saveMock(header.id, header.contentHash, json);
    }
    return ExamPaper(
      kind: ExamKind.fixed,
      mockId: header.id,
      subjectId: header.subjectId,
      title: header.title,
      minutes: header.minutes,
      questions: asList(json['questions']).map((e) => Question.fromJson(asMap(e))).toList(),
      passages: {
        for (final p in asList(json['passages']).map((e) => Passage.fromJson(asMap(e)))) p.id: p,
      },
    );
  }

  /// A random mock from the server. Throws [ApiException] (`daily_limit`, `no_questions`, offline).
  Future<ExamPaper> randomPaper({required int subjectId, required String title}) async {
    final json = await _authed(() => api.post('exams/random', {'subject_id': subjectId}));
    final exam = asMap(json['exam']);
    return ExamPaper(
      kind: ExamKind.random,
      subjectId: subjectId,
      title: title,
      minutes: asInt(exam['minutes']),
      questions: asList(json['questions']).map((e) => Question.fromJson(asMap(e))).toList(),
      passages: {
        for (final p in asList(json['passages']).map((e) => Passage.fromJson(asMap(e)))) p.id: p,
      },
    );
  }

  /// "My weak topics" or "My mistakes" for one subject, built on the phone from saved chapters
  /// and the student's own answers. Null when there is nothing to build from yet.
  Future<ExamPaper?> personalPaper({
    required ExamKind kind,
    required int subjectId,
    required String title,
    Random? random,
  }) async {
    assert(kind == ExamKind.weak || kind == ExamKind.mistakes, 'only personal exams');
    final catalog = await this.catalog();
    final unitIds = catalog.units.where((u) => u.subjectId == subjectId).map((u) => u.id).toSet();
    final chapterIds = catalog.chapters.where((c) => unitIds.contains(c.unitId)).map((c) => c.id);
    final chapters = await db.chapters(chapterIds);
    final pool = [for (final c in chapters) ...c.questions];
    final r = random ?? Random();
    final lastWrong = await db.lastAnswerWrong();
    final questions = kind == ExamKind.mistakes
        ? mistakesExamQuestions(pool: pool, lastWrong: lastWrong, random: r)
        : weakExamQuestions(
            pool: pool,
            stats: await db.chapterStats(),
            lastWrong: lastWrong,
            answered: await db.answeredQuestionIds(),
            random: r,
          );
    if (questions.isEmpty) return null;
    final passageIds = questions.map((q) => q.passageId).whereType<int>().toSet();
    return ExamPaper(
      kind: kind,
      subjectId: subjectId,
      title: title,
      minutes: examMinutesFor(questions.length),
      questions: questions,
      passages: {
        for (final c in chapters)
          for (final p in c.passages)
            if (passageIds.contains(p.id)) p.id: p,
      },
    );
  }

  /// The exam being sat, saved after every change so closing the app loses nothing.
  Future<void> saveExamProgress(ExamSession session) =>
      db.writeJson(Keys.examInProgress, session.toJson());

  Future<ExamSession?> examInProgress() async {
    final json = await db.readJson(Keys.examInProgress);
    if (json == null || json.isEmpty) return null;
    return ExamSession.fromJson(json);
  }

  Future<void> clearExamProgress() => db.writeJson(Keys.examInProgress, const {});

  /// Ends an exam: keeps the result on the phone, queues the attempt and tries to upload it.
  /// [now] is the screen's clock (the same one the exam's timer uses).
  Future<ExamRecord> submitExam(ExamSession session, {DateTime? now, String? appVersion}) async {
    final outcome = session.finish(now ?? _clock());
    final record = ExamRecord.fromOutcome(session, outcome);
    await db.saveExamResult(record.clientUuid, record.finishedAt, record.toJson());
    await saveAttempt(
      clientUuid: session.clientUuid,
      mode: session.paper.kind.wire,
      chapterId: null,
      mockId: session.paper.mockId,
      startedAt: outcome.startedAt,
      finishedAt: outcome.finishedAt,
      answers: [
        for (final i in outcome.items)
          AttemptAnswer(
            questionId: i.question.id,
            chapterId: i.question.chapterId,
            version: i.question.version,
            response: i.response,
            timeMs: i.timeMs,
            correctLocally: i.correct,
          ),
      ],
    );
    await clearExamProgress();
    await tryUploadAttempts(appVersion: appVersion);
    return await examResult(record.clientUuid) ?? record;
  }

  Future<ExamRecord?> examResult(String clientUuid) async {
    final json = await db.examResult(clientUuid);
    return json == null ? null : ExamRecord.fromJson(json);
  }

  Future<List<ExamRecord>> examResults() async =>
      (await db.examResults()).map(ExamRecord.fromJson).toList();

  /// Asks the server again, e.g. for "better than X%" once more students took the mock.
  Future<ExamRecord?> refreshExamResult(String clientUuid) async {
    final json = await _authed(() => api.get('attempts/$clientUuid'));
    await _keepExamDetails(clientUuid, json);
    return examResult(clientUuid);
  }

  Future<void> _keepExamDetails(String clientUuid, Map<String, Object?> json) async {
    final attempt = json['attempt'];
    if (attempt is! Map) return;
    final exam = attempt['exam'];
    if (exam is! Map) return;
    final record = await examResult(clientUuid);
    if (record == null) return;
    final updated = record.withServer(
      betterThanPercent: asIntOrNull(exam['better_than_percent']),
      students: asIntOrNull(exam['students']),
    );
    await db.saveExamResult(clientUuid, updated.finishedAt, updated.toJson());
  }

  // ---- answer history on a new phone ----------------------------------------------------------

  /// Brings back the student's own answers from the server once per account on this phone
  /// (new phone, or after Google sign-in joined another account). Answers the phone already
  /// has are not added twice. Returns how many were added.
  Future<int> restoreHistory() async {
    if ((await db.readString(Keys.historyRestored) ?? '').isNotEmpty) return 0;
    var after = 0;
    var restored = 0;
    while (true) {
      final json = await _authed(() => api.get('history', query: {'after': '$after'}));
      final answers = [
        for (final a in asList(json['answers']).map(asMap))
          (
            questionId: asInt(a['question_id']),
            chapterId: asInt(a['chapter_id']),
            correct: asBool(a['correct']),
            at: DateTime.parse(asString(a['answered_at'])),
          ),
      ];
      restored += await db.restoreAnswers(answers);
      after = asInt(json['next_after']);
      if (!asBool(json['has_more'])) break;
    }
    await db.seedSrsFromMistakes(_clock());
    await db.writeString(Keys.historyRestored, 'done');
    return restored;
  }

  // ---- reports ---------------------------------------------------------------------------------

  /// Saves a "Report a problem" on the phone and sends it when possible.
  /// A second report for the same question replaces the first one still waiting.
  Future<void> reportQuestion(int questionId, {required String reason, String? note}) async {
    final pending = await _pendingReports()
      ..removeWhere((r) => r['question_id'] == questionId);
    final text = note?.trim();
    pending.add({
      'question_id': questionId,
      'reason': reason,
      if (text != null && text.isNotEmpty) 'note': text,
    });
    await db.writeJson(Keys.pendingReports, {'items': pending});
    unawaited(_tryQuietly(() async => uploadReports()));
  }

  Future<List<Map<String, Object?>>> _pendingReports() async {
    final json = await db.readJson(Keys.pendingReports);
    return json == null ? [] : asList(json['items']).map(asMap).toList();
  }

  Future<int>? _reportsInFlight;

  /// Sends waiting reports. Returns how many were sent. Calls made while a send is running
  /// share it, so a report is never sent twice.
  Future<int> uploadReports() =>
      _reportsInFlight ??= _sendReports().whenComplete(() => _reportsInFlight = null);

  Future<int> _sendReports() async {
    var sent = 0;
    for (final report in await _pendingReports()) {
      final id = asInt(report['question_id']);
      try {
        await _authed(
          () => api.post('questions/$id/reports', {
            'reason': report['reason'],
            if (report['note'] != null) 'note': report['note'],
          }),
        );
        sent++;
      } on ApiException catch (e) {
        // Offline or busy: keep it for later. Refused (question gone, daily limit): drop it.
        final retryLater =
            e.isOffline || e.status >= 500 || (e.status == 429 && e.code != 'report_limit');
        if (retryLater) rethrow;
      }
      final left = await _pendingReports()
        ..removeWhere((r) => r['question_id'] == id && r['reason'] == report['reason']);
      await db.writeJson(Keys.pendingReports, {'items': left});
    }
    return sent;
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
      await restoreHistory();
      await uploadAttempts();
      await uploadReports();
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
