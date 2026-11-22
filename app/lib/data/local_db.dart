import 'dart:convert';

import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

import '../domain/srs.dart';
import 'models.dart';

/// Everything the student needs offline lives here (SQLite on the phone).
class LocalDb {
  LocalDb._(this._db);

  final Database _db;

  static const _version = 3;

  /// Opens the app database. Tests pass `databaseFactoryFfi` and [inMemoryDatabasePath].
  static Future<LocalDb> open({DatabaseFactory? factory, String? path}) async {
    final f = factory ?? databaseFactory;
    final dbPath = path ?? p.join(await f.getDatabasesPath(), 'guul12.db');
    final db = await f.openDatabase(
      dbPath,
      options: OpenDatabaseOptions(
        version: _version,
        onCreate: _create,
        onUpgrade: _upgrade,
        singleInstance: path != inMemoryDatabasePath,
      ),
    );
    return LocalDb._(db);
  }

  static Future<void> _create(Database db, int version) async {
    final batch = db.batch()
      ..execute('CREATE TABLE kv (key TEXT PRIMARY KEY, value TEXT NOT NULL)')
      ..execute(
        'CREATE TABLE chapters (id INTEGER PRIMARY KEY, json TEXT NOT NULL, saved_at INTEGER NOT NULL)',
      )
      ..execute(
        'CREATE TABLE answers (id INTEGER PRIMARY KEY AUTOINCREMENT, question_id INTEGER NOT NULL, '
        'chapter_id INTEGER NOT NULL, correct INTEGER NOT NULL, answered_at INTEGER NOT NULL)',
      )
      ..execute('CREATE INDEX answers_question ON answers (question_id, answered_at)')
      ..execute(
        'CREATE TABLE pending_attempts (client_uuid TEXT PRIMARY KEY, json TEXT NOT NULL, created_at INTEGER NOT NULL)',
      )
      ..execute(
        'CREATE TABLE bookmarks (question_id INTEGER PRIMARY KEY, synced INTEGER NOT NULL)',
      );
    _createV2(batch);
    _createV3(batch);
    await batch.commit(noResult: true);
  }

  /// Version 3: spaced repetition of mistakes (review after 1, 3, 7 days).
  static void _createV3(Batch batch) {
    batch
      ..execute(
        'CREATE TABLE srs (question_id INTEGER PRIMARY KEY, chapter_id INTEGER NOT NULL, '
        'step INTEGER NOT NULL, due_at INTEGER NOT NULL)',
      )
      ..execute('CREATE INDEX srs_due ON srs (due_at)');
  }

  /// Version 2 (Phase 3): downloaded mock exams and finished exam results.
  static void _createV2(Batch batch) {
    batch
      ..execute(
        'CREATE TABLE mocks (id INTEGER PRIMARY KEY, content_hash TEXT NOT NULL, json TEXT NOT NULL)',
      )
      ..execute(
        'CREATE TABLE exam_results (client_uuid TEXT PRIMARY KEY, finished_at INTEGER NOT NULL, json TEXT NOT NULL)',
      );
  }

  static Future<void> _upgrade(Database db, int from, int to) async {
    final batch = db.batch();
    if (from < 2) _createV2(batch);
    if (from < 3) _createV3(batch);
    await batch.commit(noResult: true);
  }

  Future<void> close() => _db.close();

  // ---- key/value -------------------------------------------------------------------------------

  Future<String?> readString(String key) async {
    final rows = await _db.query('kv', columns: ['value'], where: 'key = ?', whereArgs: [key]);
    return rows.isEmpty ? null : rows.first['value']! as String;
  }

  Future<void> writeString(String key, String value) =>
      _db.insert('kv', {'key': key, 'value': value}, conflictAlgorithm: ConflictAlgorithm.replace);

  Future<Map<String, Object?>?> readJson(String key) async {
    final raw = await readString(key);
    return raw == null ? null : asMap(jsonDecode(raw));
  }

  Future<void> writeJson(String key, Map<String, Object?> value) =>
      writeString(key, jsonEncode(value));

  Future<int> readInt(String key, {int fallback = 0}) async {
    final raw = await readString(key);
    return raw == null ? fallback : int.tryParse(raw) ?? fallback;
  }

  Future<void> writeInt(String key, int value) => writeString(key, value.toString());

  // ---- chapters --------------------------------------------------------------------------------

  Future<void> saveChapter(int id, Map<String, Object?> json, DateTime now) => _db.insert(
    'chapters',
    {'id': id, 'json': jsonEncode(json), 'saved_at': now.millisecondsSinceEpoch},
    conflictAlgorithm: ConflictAlgorithm.replace,
  );

  Future<ChapterContent?> chapter(int id) async {
    final rows = await _db.query('chapters', columns: ['json'], where: 'id = ?', whereArgs: [id]);
    if (rows.isEmpty) return null;
    return ChapterContent.fromJson(asMap(jsonDecode(rows.first['json']! as String)));
  }

  Future<List<ChapterContent>> chapters([Iterable<int>? ids]) async {
    final List<Map<String, Object?>> rows;
    if (ids == null) {
      rows = await _db.query('chapters', columns: ['json'], orderBy: 'id');
    } else {
      final list = ids.toList();
      if (list.isEmpty) return const [];
      rows = await _db.query(
        'chapters',
        columns: ['json'],
        where: 'id IN (${List.filled(list.length, '?').join(',')})',
        whereArgs: list,
        orderBy: 'id',
      );
    }
    return rows
        .map((r) => ChapterContent.fromJson(asMap(jsonDecode(r['json']! as String))))
        .toList();
  }

  Future<Set<int>> savedChapterIds() async {
    final rows = await _db.query('chapters', columns: ['id']);
    return rows.map((r) => r['id']! as int).toSet();
  }

  Future<void> deleteChapter(int id) => _db.delete('chapters', where: 'id = ?', whereArgs: [id]);

  // ---- answer history (for "only my wrong answers" and weak topics) -----------------------------

  Future<void> recordAnswers(
    List<({int questionId, int chapterId, bool correct})> answers,
    DateTime at,
  ) async {
    final batch = _db.batch();
    for (final a in answers) {
      batch.insert('answers', {
        'question_id': a.questionId,
        'chapter_id': a.chapterId,
        'correct': a.correct ? 1 : 0,
        'answered_at': at.millisecondsSinceEpoch,
      });
    }
    await batch.commit(noResult: true);
  }

  /// Questions whose most recent answer was wrong.
  Future<Set<int>> lastAnswerWrong() async {
    final rows = await _db.rawQuery(
      'SELECT a.question_id FROM answers a '
      'WHERE a.id = (SELECT b.id FROM answers b WHERE b.question_id = a.question_id ORDER BY b.answered_at DESC, b.id DESC LIMIT 1) '
      'AND a.correct = 0',
    );
    return rows.map((r) => r['question_id']! as int).toSet();
  }

  Future<Set<int>> answeredQuestionIds() async {
    final rows = await _db.rawQuery('SELECT DISTINCT question_id FROM answers');
    return rows.map((r) => r['question_id']! as int).toSet();
  }

  /// Per chapter: different questions answered, and how many were right the last time.
  /// Every saved answer's time and result (for streak and XP), oldest first.
  Future<List<({DateTime at, bool correct})>> answerEvents() async {
    final rows = await _db.rawQuery(
      'SELECT answered_at, correct FROM answers ORDER BY answered_at, id',
    );
    return [
      for (final r in rows)
        (
          at: DateTime.fromMillisecondsSinceEpoch(r['answered_at']! as int),
          correct: r['correct'] == 1,
        ),
    ];
  }

  Future<List<ChapterStat>> chapterStats() async {
    final rows = await _db.rawQuery(
      'SELECT a.chapter_id AS chapter_id, COUNT(*) AS answered, SUM(a.correct) AS correct FROM answers a '
      'WHERE a.id = (SELECT b.id FROM answers b WHERE b.question_id = a.question_id ORDER BY b.answered_at DESC, b.id DESC LIMIT 1) '
      'GROUP BY a.chapter_id ORDER BY a.chapter_id',
    );
    return [
      for (final r in rows)
        ChapterStat(
          chapterId: r['chapter_id']! as int,
          answered: r['answered']! as int,
          correct: (r['correct'] as int?) ?? 0,
        ),
    ];
  }

  /// Answers restored from the server, each with its own time. An answer the phone already
  /// has (same question, same second) is skipped. Returns how many were added.
  Future<int> restoreAnswers(
    List<({int questionId, int chapterId, bool correct, DateTime at})> answers,
  ) async {
    var added = 0;
    await _db.transaction((txn) async {
      for (final a in answers) {
        final second = a.at.millisecondsSinceEpoch ~/ 1000;
        final existing = await txn.rawQuery(
          'SELECT 1 FROM answers WHERE question_id = ? AND answered_at / 1000 = ? LIMIT 1',
          [a.questionId, second],
        );
        if (existing.isNotEmpty) continue;
        await txn.insert('answers', {
          'question_id': a.questionId,
          'chapter_id': a.chapterId,
          'correct': a.correct ? 1 : 0,
          'answered_at': a.at.millisecondsSinceEpoch,
        });
        added++;
      }
    });
    return added;
  }

  // ---- spaced repetition ------------------------------------------------------------------------

  /// Updates the review list after answers given at [at].
  Future<void> applySrs(
    List<({int questionId, int chapterId, bool correct})> answers,
    DateTime at,
  ) async {
    await _db.transaction((txn) async {
      for (final a in answers) {
        final rows = await txn.query('srs', where: 'question_id = ?', whereArgs: [a.questionId]);
        final current = rows.isEmpty ? null : _srsItem(rows.first);
        final next = srsAfterAnswer(
          item: current,
          questionId: a.questionId,
          chapterId: a.chapterId,
          correct: a.correct,
          now: at,
        );
        if (next == null) {
          if (current != null) {
            await txn.delete('srs', where: 'question_id = ?', whereArgs: [a.questionId]);
          }
        } else if (!identical(next, current)) {
          await txn.insert('srs', {
            'question_id': next.questionId,
            'chapter_id': next.chapterId,
            'step': next.step,
            'due_at': next.dueAt.millisecondsSinceEpoch,
          }, conflictAlgorithm: ConflictAlgorithm.replace);
        }
      }
    });
  }

  /// Puts questions whose last answer was wrong on the review list (due at [at]) when they are
  /// not there yet. Used after answers come back from the server on a new phone.
  Future<int> seedSrsFromMistakes(DateTime at) async {
    final rows = await _db.rawQuery(
      'SELECT a.question_id AS question_id, a.chapter_id AS chapter_id FROM answers a '
      'WHERE a.id = (SELECT b.id FROM answers b WHERE b.question_id = a.question_id ORDER BY b.answered_at DESC, b.id DESC LIMIT 1) '
      'AND a.correct = 0 AND a.question_id NOT IN (SELECT question_id FROM srs)',
    );
    final batch = _db.batch();
    for (final r in rows) {
      batch.insert('srs', {
        'question_id': r['question_id'],
        'chapter_id': r['chapter_id'],
        'step': 0,
        'due_at': at.millisecondsSinceEpoch,
      });
    }
    await batch.commit(noResult: true);
    return rows.length;
  }

  Future<List<SrsItem>> srsItems() async =>
      (await _db.query('srs', orderBy: 'due_at, question_id')).map(_srsItem).toList();

  Future<Set<int>> dueQuestionIds(DateTime now) async {
    final rows = await _db.query(
      'srs',
      columns: ['question_id'],
      where: 'due_at <= ?',
      whereArgs: [now.millisecondsSinceEpoch],
    );
    return rows.map((r) => r['question_id']! as int).toSet();
  }

  static SrsItem _srsItem(Map<String, Object?> r) => SrsItem(
    questionId: r['question_id']! as int,
    chapterId: r['chapter_id']! as int,
    step: r['step']! as int,
    dueAt: DateTime.fromMillisecondsSinceEpoch(r['due_at']! as int, isUtc: true),
  );

  // ---- mock exams ------------------------------------------------------------------------------

  Future<void> saveMock(int id, String contentHash, Map<String, Object?> json) => _db.insert(
    'mocks',
    {'id': id, 'content_hash': contentHash, 'json': jsonEncode(json)},
    conflictAlgorithm: ConflictAlgorithm.replace,
  );

  /// The saved mock, only if it still has [contentHash].
  Future<Map<String, Object?>?> mock(int id, String contentHash) async {
    final rows = await _db.query(
      'mocks',
      columns: ['json'],
      where: 'id = ? AND content_hash = ?',
      whereArgs: [id, contentHash],
    );
    return rows.isEmpty ? null : asMap(jsonDecode(rows.first['json']! as String));
  }

  // ---- exam results ----------------------------------------------------------------------------

  Future<void> saveExamResult(String clientUuid, DateTime finishedAt, Map<String, Object?> json) =>
      _db.insert('exam_results', {
        'client_uuid': clientUuid,
        'finished_at': finishedAt.millisecondsSinceEpoch,
        'json': jsonEncode(json),
      }, conflictAlgorithm: ConflictAlgorithm.replace);

  Future<Map<String, Object?>?> examResult(String clientUuid) async {
    final rows = await _db.query(
      'exam_results',
      columns: ['json'],
      where: 'client_uuid = ?',
      whereArgs: [clientUuid],
    );
    return rows.isEmpty ? null : asMap(jsonDecode(rows.first['json']! as String));
  }

  /// Newest first.
  Future<List<Map<String, Object?>>> examResults() async {
    final rows = await _db.query('exam_results', columns: ['json'], orderBy: 'finished_at DESC');
    return rows.map((r) => asMap(jsonDecode(r['json']! as String))).toList();
  }

  // ---- attempts waiting for upload --------------------------------------------------------------

  Future<void> queueAttempt(String clientUuid, Map<String, Object?> payload, DateTime now) =>
      _db.insert('pending_attempts', {
        'client_uuid': clientUuid,
        'json': jsonEncode(payload),
        'created_at': now.millisecondsSinceEpoch,
      }, conflictAlgorithm: ConflictAlgorithm.ignore);

  Future<List<(String, Map<String, Object?>)>> pendingAttempts() async {
    final rows = await _db.query('pending_attempts', orderBy: 'created_at');
    return rows
        .map((r) => (r['client_uuid']! as String, asMap(jsonDecode(r['json']! as String))))
        .toList();
  }

  Future<void> removeAttempt(String clientUuid) =>
      _db.delete('pending_attempts', where: 'client_uuid = ?', whereArgs: [clientUuid]);

  // ---- bookmarks -------------------------------------------------------------------------------

  Future<Set<int>> bookmarks() async {
    final rows = await _db.query('bookmarks', columns: ['question_id'], where: 'synced >= 0');
    return rows.map((r) => r['question_id']! as int).toSet();
  }

  /// synced: 1 = server knows, 0 = add not uploaded, -1 = removal not uploaded.
  Future<void> setBookmark(int questionId, {required bool on}) => _db.insert('bookmarks', {
    'question_id': questionId,
    'synced': on ? 0 : -1,
  }, conflictAlgorithm: ConflictAlgorithm.replace);

  Future<List<(int, bool)>> unsyncedBookmarks() async {
    final rows = await _db.query('bookmarks', where: 'synced != 1');
    return rows.map((r) => (r['question_id']! as int, (r['synced']! as int) == 0)).toList();
  }

  Future<void> markBookmarkSynced(int questionId, {required bool on}) async {
    if (on) {
      await _db.update(
        'bookmarks',
        {'synced': 1},
        where: 'question_id = ?',
        whereArgs: [questionId],
      );
    } else {
      await _db.delete('bookmarks', where: 'question_id = ?', whereArgs: [questionId]);
    }
  }

  Future<void> replaceBookmarksFromServer(Set<int> ids) async {
    await _db.transaction((txn) async {
      await txn.delete('bookmarks', where: 'synced = 1');
      for (final id in ids) {
        await txn.insert('bookmarks', {
          'question_id': id,
          'synced': 1,
        }, conflictAlgorithm: ConflictAlgorithm.ignore);
      }
    });
  }
}
