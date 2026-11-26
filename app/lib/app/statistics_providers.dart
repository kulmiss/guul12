import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/models.dart';
import '../domain/mastery.dart';
import '../domain/statistics.dart';
import 'progress_providers.dart';
import 'providers.dart';

/// Everything the Statistics page shows.
class StatisticsView {
  const StatisticsView({
    required this.statistics,
    required this.readiness,
    required this.mastery,
    required this.subjectNames,
  });

  final Statistics statistics;
  final Readiness? readiness;

  /// Chapters of the student's subjects at each mastery level.
  final Map<MasteryLevel, int> mastery;

  /// Subject names by id, for the chart labels.
  final Map<int, String> subjectNames;
}

/// The chapter ids of the student's subjects (their stream), in study order.
List<int> studentChapterIds(Catalog catalog, String? stream) => [
  for (final subject in catalog.subjectsFor(stream))
    for (final grade in catalog.gradesFor(subject.id, stream))
      for (final unit in catalog.unitsFor(subject.id, grade, stream))
        for (final c in catalog.chaptersFor(unit.id)) c.id,
];

final statisticsProvider = FutureProvider<StatisticsView>((ref) async {
  ref.watch(syncGenerationProvider);
  final repo = ref.watch(repositoryProvider);
  final now = ref.watch(clockProvider)().toLocal();
  final catalog = await ref.watch(catalogProvider.future);
  final stream = (await ref.watch(profileProvider.future)).effectiveStream;
  final mastery = await ref.watch(masteryProvider.future);
  final records = await repo.db.answerRecords();
  final exams = await repo.examResults();

  final unitSubject = {for (final u in catalog.units) u.id: u.subjectId};
  final chapterSubject = <int, int>{for (final c in catalog.chapters) c.id: ?unitSubject[c.unitId]};
  final subjects = catalog.subjectsFor(stream);

  return StatisticsView(
    statistics: statisticsFrom(
      answers: records,
      exams: [
        for (final e in exams)
          if (e.total > 0)
            (
              at: e.finishedAt.toLocal(),
              percent: (100 * e.correct / e.total).round(),
              title: e.title,
            ),
      ],
      chapterSubject: chapterSubject,
      subjectOrder: [for (final s in subjects) s.id],
      now: now,
    ),
    readiness: readinessFrom(
      answers: [for (final r in records) (at: r.at, correct: r.correct)],
      now: now,
    ),
    mastery: masteryCounts(chapterIds: studentChapterIds(catalog, stream), mastery: mastery),
    subjectNames: {for (final s in catalog.subjects) s.id: s.name},
  );
});

/// The likely exam score for the Home card (null until there are enough answers).
final readinessProvider = FutureProvider<Readiness?>((ref) async {
  ref.watch(syncGenerationProvider);
  final repo = ref.watch(repositoryProvider);
  return readinessFrom(
    answers: await repo.db.answerEvents(),
    now: ref.watch(clockProvider)().toLocal(),
  );
});

/// Today's flashcard from the chapters saved on the phone, with its chapter; null when no saved
/// chapter has flashcards. The same card all day, the next one tomorrow.
final cardOfTheDayProvider = FutureProvider<({Flashcard card, int chapterId})?>((ref) async {
  ref.watch(syncGenerationProvider);
  final repo = ref.watch(repositoryProvider);
  final chapters = [...await repo.db.chapters()]
    ..sort((a, b) => a.header.id.compareTo(b.header.id));
  final cards = [
    for (final c in chapters)
      for (final f in [...c.flashcards]..sort((a, b) => a.id.compareTo(b.id)))
        (card: f, chapterId: c.header.id),
  ];
  return pickOfTheDay(cards, ref.watch(clockProvider)().toLocal());
});
