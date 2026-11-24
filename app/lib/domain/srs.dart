/// Spaced repetition for mistakes: a wrong answer comes back after 1 day; each right answer
/// on the due day moves it to the next step (3 days, then 7 days); after the 7-day review it is learned.
library;

const srsIntervals = [Duration(days: 1), Duration(days: 3), Duration(days: 7)];

class SrsItem {
  const SrsItem({
    required this.questionId,
    required this.chapterId,
    required this.step,
    required this.dueAt,
  });

  final int questionId;
  final int chapterId;

  /// 0 = first review (1 day), 1 = second (3 days), 2 = last (7 days).
  final int step;
  final DateTime dueAt;

  bool isDue(DateTime now) => !now.isBefore(dueAt);
}

/// What the item becomes after an answer. Null means "not in the review list"
/// (never wrong, or learned).
SrsItem? srsAfterAnswer({
  required SrsItem? item,
  required int questionId,
  required int chapterId,
  required bool correct,
  required DateTime now,
}) {
  if (!correct) {
    return SrsItem(
      questionId: questionId,
      chapterId: chapterId,
      step: 0,
      dueAt: now.add(srsIntervals[0]),
    );
  }
  if (item == null) return null;
  // Right before the review day: good, but it does not count as a review.
  if (!item.isDue(now)) return item;
  final next = item.step + 1;
  if (next >= srsIntervals.length) return null;
  return SrsItem(
    questionId: questionId,
    chapterId: chapterId,
    step: next,
    dueAt: now.add(srsIntervals[next]),
  );
}
