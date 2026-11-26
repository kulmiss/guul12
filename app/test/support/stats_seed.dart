import 'package:guul12/data/repository.dart';
import 'package:guul12/domain/exam.dart';

import 'app_harness.dart';
import 'fake_server.dart';

/// Adds an English unit with chapter 31 to the catalog, for a second subject in the charts.
void addEnglishChapter(FakeServer server) {
  server.extraUnits.add({
    'id': 12,
    'subject_id': 2,
    'grade': 12,
    'stream': null,
    'number': 1,
    'title': 'Reading',
    'sort': 1,
  });
  server.extraChapters.add({
    ...server.chapterHeader(31),
    'unit_id': 12,
    'number': 1,
    'title': 'Reading for detail',
  });
}

/// A few weeks of study before [testNow]: answers on most days (more right as time goes on),
/// in Physics (chapter 21) and English (chapter 31), at different times of day, and three
/// finished exams. Call after the chapters were saved.
Future<void> seedStudyHistory(Repository repo) async {
  final today = DateTime(testNow.year, testNow.month, testNow.day);
  for (var daysAgo = 40; daysAgo >= 0; daysAgo--) {
    if (daysAgo % 6 == 3) continue; // a day off now and then
    final count = 6 + (daysAgo * 7) % 9;
    final rightShare = 0.45 + 0.4 * (40 - daysAgo) / 40;
    final hour = [7, 14, 19][daysAgo % 3];
    final at = DateTime(today.year, today.month, today.day - daysAgo, hour);
    await repo.db.recordAnswers(
      [
        for (var i = 0; i < count; i++)
          (
            questionId: 9000 + daysAgo * 20 + i,
            chapterId: i.isEven ? 21 : 31,
            correct: i < (count * rightShare).round(),
          ),
      ],
      at,
      timeMs: {for (var i = 0; i < count; i++) 9000 + daysAgo * 20 + i: 25000 + i * 3000},
    );
  }
  final questions = (await repo.db.chapter(21))!.questions;
  for (final (daysAgo, right) in [(20, 5), (9, 6), (2, 8)]) {
    final at = today.subtract(Duration(days: daysAgo)).add(const Duration(hours: 16));
    final record = ExamRecord(
      clientUuid: '00000000-0000-4000-8000-0000000e00$daysAgo',
      kind: ExamKind.fixed,
      subjectId: 1,
      title: 'Physics mock exam',
      startedAt: at.subtract(const Duration(minutes: 30)).toUtc(),
      finishedAt: at.toUtc(),
      items: [
        for (var i = 0; i < 10; i++)
          ExamItem(
            question: questions[i % questions.length],
            response: 'A',
            correct: i < right,
            timeMs: 30000,
          ),
      ],
    );
    await repo.db.saveExamResult(record.clientUuid, record.finishedAt, record.toJson());
  }
}
