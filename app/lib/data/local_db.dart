import 'dart:convert';

import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

import 'models.dart';

/// Everything the student needs offline lives here (SQLite on the phone).
class LocalDb {
  LocalDb._(this._db);

  final Database _db;

  static const _version = 1;

  /// Opens the app database. Tests pass `databaseFactoryFfi` and [inMemoryDatabasePath].
  static Future<LocalDb> open({DatabaseFactory? factory, String? path}) async {
    final f = factory ?? databaseFactory;
    final dbPath = path ?? p.join(await f.getDatabasesPath(), 'guul12.db');
    final db = await f.openDatabase(
      dbPath,
      options: OpenDatabaseOptions(
        version: _version,
        onCreate: _create,
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
