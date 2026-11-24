import 'dart:math';

import '../data/models.dart';
import 'exam.dart';

/// Most questions in a "My weak topics" or "My mistakes" exam.
const personalExamSize = 30;

/// True when the key has the same letter 4 times in a row, or A, B, C, D, A… 5 in a row.
bool keyHasPattern(List<Question> questions) {
  const next = {'A': 'B', 'B': 'C', 'C': 'D', 'D': 'A'};
  var same = 0;
  var cycle = 0;
  String? previous;
  for (final q in questions) {
    final c = q.answerKey['correct'];
    final letter = c is String ? c : null;
    same = letter != null && letter == previous ? same + 1 : 1;
    cycle = letter != null && previous != null && next[previous] == letter ? cycle + 1 : 1;
    if (same >= 4 || cycle >= 5) return true;
    previous = letter;
  }
  return false;
}

/// Shuffles the questions (a passage's questions stay together) until the key shows no pattern.
List<Question> examOrder(List<Question> questions, Random random) {
  final groups = <List<Question>>[];
  final byPassage = <int, List<Question>>{};
  for (final q in questions) {
    final p = q.passageId;
    if (p == null) {
      groups.add([q]);
    } else if (byPassage.containsKey(p)) {
      byPassage[p]!.add(q);
    } else {
      final group = [q];
      byPassage[p] = group;
      groups.add(group);
    }
  }
  var flat = [for (final g in groups) ...g];
  for (var attempt = 0; attempt < 2000; attempt++) {
    groups.shuffle(random);
    flat = [for (final g in groups) ...g];
    if (!keyHasPattern(flat)) return flat;
  }
  // A handful of questions all with the same letter cannot avoid it; the exam is still fair.
  return flat;
}

/// "My mistakes": choice questions whose last answer was wrong.
List<Question> mistakesExamQuestions({
  required List<Question> pool,
  required Set<int> lastWrong,
  required Random random,
  int size = personalExamSize,
}) {
  final candidates = pool.where((q) => isExamType(q.type) && lastWrong.contains(q.id)).toList()
    ..shuffle(random);
  return examOrder(candidates.take(size).toList(), random);
}

/// "My weak topics": the chapters with the lowest share of right answers (at least one
/// question answered), filled weakest first; inside a chapter, wrong and new questions first.
List<Question> weakExamQuestions({
  required List<Question> pool,
  required List<ChapterStat> stats,
  required Set<int> lastWrong,
  required Set<int> answered,
  required Random random,
  int size = personalExamSize,
}) {
  final ranked = stats.where((s) => s.answered > 0).toList()
    ..sort((a, b) {
      final byAccuracy = a.accuracy.compareTo(b.accuracy);
      return byAccuracy != 0 ? byAccuracy : b.answered.compareTo(a.answered);
    });
  int priority(Question q) => lastWrong.contains(q.id)
      ? 0
      : answered.contains(q.id)
      ? 2
      : 1;
  final byChapter = <int, List<Question>>{};
  for (final stat in ranked) {
    final inChapter =
        pool.where((q) => q.chapterId == stat.chapterId && isExamType(q.type)).toList()
          ..shuffle(random);
    // Stable sort keeps the shuffle inside each priority group.
    byChapter[stat.chapterId] = [
      for (final p in [0, 1, 2]) ...inChapter.where((q) => priority(q) == p),
    ];
  }
  final picked = <Question>[];
  // First pass: at most a third of the exam from one chapter, so several weak topics get practice.
  // Second pass: fill what is left, weakest chapter first.
  for (final cap in [max(1, (size / 3).ceil()), size]) {
    for (final stat in ranked) {
      if (picked.length >= size) break;
      final rest = byChapter[stat.chapterId]!.where((q) => !picked.contains(q)).toList();
      final already = picked.where((q) => q.chapterId == stat.chapterId).length;
      picked.addAll(rest.take(min(max(0, cap - already), size - picked.length)));
    }
  }
  return examOrder(picked, random);
}
