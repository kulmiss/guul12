import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/models.dart';
import '../domain/srs.dart';
import 'providers.dart';

/// Questions whose spaced-repetition review is due now.
final reviewDueCountProvider = FutureProvider<int>((ref) async {
  ref.watch(syncGenerationProvider);
  final repo = ref.watch(repositoryProvider);
  return (await repo.db.dueQuestionIds(ref.watch(clockProvider)())).length;
});

/// One entry in the mistakes notebook.
class Mistake {
  const Mistake({required this.question, required this.review});

  final Question question;

  /// Null when the question is not on the review list (e.g. learned before it went wrong again
  /// on another phone).
  final SrsItem? review;
}

/// Questions whose last answer was wrong, from chapters saved on the phone, by chapter.
final mistakesProvider = FutureProvider<Map<int, List<Mistake>>>((ref) async {
  ref.watch(syncGenerationProvider);
  final repo = ref.watch(repositoryProvider);
  final wrong = await repo.db.lastAnswerWrong();
  if (wrong.isEmpty) return const {};
  final reviews = {for (final s in await repo.db.srsItems()) s.questionId: s};
  final out = <int, List<Mistake>>{};
  for (final chapter in await repo.db.chapters()) {
    for (final q in chapter.questions) {
      if (wrong.contains(q.id)) {
        (out[chapter.header.id] ??= []).add(Mistake(question: q, review: reviews[q.id]));
      }
    }
  }
  return out;
});
