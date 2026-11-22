import 'dart:math';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/api_client.dart';
import '../data/models.dart';
import '../data/repository.dart';
import '../domain/mastery.dart';
import '../domain/practice.dart';
import 'providers.dart';

/// What the student asked to practise.
class PracticeRequest {
  const PracticeRequest({required this.mode, this.chapterId, this.unitId, this.seed = 0});

  final PracticeMode mode;
  final int? chapterId;
  final int? unitId;

  /// Changes for "Practise again" so a new set is built.
  final int seed;

  @override
  bool operator ==(Object other) =>
      other is PracticeRequest &&
      other.mode == mode &&
      other.chapterId == chapterId &&
      other.unitId == unitId &&
      other.seed == seed;

  @override
  int get hashCode => Object.hash(mode, chapterId, unitId, seed);
}

/// A ready-to-play set plus the passages its questions need.
class PracticeSet {
  const PracticeSet({required this.questions, required this.passages});

  final List<Question> questions;
  final Map<int, Passage> passages;

  bool get isEmpty => questions.isEmpty;
}

/// Builds a set from chapters saved on the phone. Chapter and unit modes
/// download missing chapters first (when online).
Future<PracticeSet> buildPracticeSet(
  Repository repo,
  PracticeRequest request, {
  Random? random,
  DateTime? now,
}) async {
  final List<ChapterContent> chapters;
  switch (request.mode) {
    case PracticeMode.chapter:
    case PracticeMode.chapterQuiz:
      final id = request.chapterId;
      chapters = id == null ? const [] : [await repo.chapter(id)];
    case PracticeMode.unit:
      final unitId = request.unitId;
      if (unitId == null) {
        chapters = const [];
      } else {
        final catalog = await repo.catalog();
        final loaded = <ChapterContent>[];
        for (final header in catalog.chaptersFor(unitId)) {
          try {
            loaded.add(await repo.chapter(header.id));
          } on ApiException {
            // Offline and not saved yet: practise with the chapters we have.
          }
        }
        chapters = loaded;
      }
    case PracticeMode.quick10:
    case PracticeMode.mixed:
    case PracticeMode.timed:
    case PracticeMode.wrongOnly:
    case PracticeMode.review:
    case PracticeMode.smart:
    case PracticeMode.quickRevise:
    case PracticeMode.onboarding:
      chapters = await repo.db.chapters();
  }

  final pool = [for (final c in chapters) ...c.questions];
  final passages = {
    for (final c in chapters)
      for (final p in c.passages) p.id: p,
  };
  final smart = request.mode == PracticeMode.smart;
  final mastery = smart
      ? chapterMastery(
          stats: await repo.db.chapterStats(),
          questionsPerChapter: {for (final c in chapters) c.header.id: c.questions.length},
        )
      : const <ChapterMastery>[];
  final weak = weakTopics(mastery, max: mastery.length).map((m) => m.chapterId).toList();
  final questions = PracticeBuilder(random: random ?? Random(request.seed)).build(
    mode: request.mode,
    pool: pool,
    wrong: await repo.db.lastAnswerWrong(),
    answered: await repo.db.answeredQuestionIds(),
    due: request.mode == PracticeMode.review || smart
        ? await repo.db.dueQuestionIds(now ?? repo.now())
        : const {},
    weakChapters: weak,
  );
  return PracticeSet(questions: questions, passages: passages);
}

final practiceSetProvider = FutureProvider.autoDispose.family<PracticeSet, PracticeRequest>(
  (ref, request) => buildPracticeSet(
    ref.watch(repositoryProvider),
    request,
    // A fresh shuffle every time; `seed` only makes "Practise again" build a new set.
    random: Random(),
    now: ref.read(clockProvider)(),
  ),
);
