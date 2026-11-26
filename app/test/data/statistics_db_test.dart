import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/local_db.dart';
import 'package:guul12/data/repository.dart';
import 'package:path/path.dart' as p;
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

import '../support/fake_server.dart';

void main() {
  test('a practice set saves the time spent on each answer', () async {
    final (repo, _, _) = await makeRepository();
    final at = DateTime.utc(2026, 11, 22, 10);
    await repo.saveAttempt(
      clientUuid: '00000000-0000-4000-8000-000000000001',
      mode: 'quick10',
      chapterId: null,
      startedAt: at,
      finishedAt: at,
      answers: const [
        AttemptAnswer(
          questionId: 2101,
          chapterId: 21,
          version: 1,
          response: 'B',
          timeMs: 12000,
          correctLocally: true,
        ),
        AttemptAnswer(
          questionId: 2102,
          chapterId: 21,
          version: 1,
          response: null,
          timeMs: 4000,
          correctLocally: false,
        ),
      ],
    );
    final records = await repo.db.answerRecords();
    expect(
      [for (final r in records) (r.chapterId, r.correct, r.timeMs)],
      [(21, true, 12000), (21, false, 4000)],
    );
  });

  test('answers restored from the server have no time', () async {
    final (repo, _, _) = await makeRepository();
    await repo.db.restoreAnswers([
      (questionId: 5, chapterId: 2, correct: true, at: DateTime.utc(2026, 11, 1)),
    ]);
    expect((await repo.db.answerRecords()).single.timeMs, isNull);
  });

  test('a negative time is not saved', () async {
    final (repo, _, _) = await makeRepository();
    await repo.db.recordAnswers(
      [(questionId: 1, chapterId: 1, correct: true)],
      DateTime.utc(2026, 11, 1),
      timeMs: {1: -5},
    );
    expect((await repo.db.answerRecords()).single.timeMs, isNull);
  });

  test('updating from version 3 keeps every answer (with no time)', () async {
    sqfliteFfiInit();
    final dir = await Directory.systemTemp.createTemp('guul12_db');
    addTearDown(() => dir.delete(recursive: true));
    final path = p.join(dir.path, 'old.db');

    // The version 3 answers table, as phones have it today.
    final old = await databaseFactoryFfi.openDatabase(
      path,
      options: OpenDatabaseOptions(
        version: 3,
        onCreate: (db, _) async {
          await db.execute(
            'CREATE TABLE answers (id INTEGER PRIMARY KEY AUTOINCREMENT, question_id INTEGER NOT NULL, '
            'chapter_id INTEGER NOT NULL, correct INTEGER NOT NULL, answered_at INTEGER NOT NULL)',
          );
          await db.insert('answers', {
            'question_id': 7,
            'chapter_id': 3,
            'correct': 1,
            'answered_at': DateTime.utc(2026, 10, 1).millisecondsSinceEpoch,
          });
        },
      ),
    );
    await old.close();

    final db = await LocalDb.open(factory: databaseFactoryFfi, path: path);
    addTearDown(db.close);
    final records = await db.answerRecords();
    expect(records, hasLength(1));
    expect(
      (records.single.chapterId, records.single.correct, records.single.timeMs),
      (3, true, null),
    );
    await db.recordAnswers(
      [(questionId: 8, chapterId: 3, correct: false)],
      DateTime.utc(2026, 11, 1),
      timeMs: {8: 2500},
    );
    expect((await db.answerRecords()).last.timeMs, 2500);
  });
}
