import '../data/models.dart';

/// How well a student knows a chapter, from their own answers on the phone.
enum MasteryLevel { notStarted, learning, good, strong }

class ChapterMastery {
  const ChapterMastery({
    required this.chapterId,
    required this.totalQuestions,
    required this.answered,
    required this.correct,
  });

  final int chapterId;

  /// Questions in the chapter (saved on the phone); 0 when the chapter was never opened.
  final int totalQuestions;

  /// Different questions answered, and how many were right the last time.
  final int answered;
  final int correct;

  double get accuracy => answered == 0 ? 0 : correct / answered;

  /// Share of the chapter's questions tried at least once.
  double get coverage => totalQuestions == 0 ? 0 : (answered / totalQuestions).clamp(0, 1);

  /// 0–100. Accuracy, weighted down until at least [fullWeightAfter] questions were answered,
  /// so two lucky answers do not look like a strong chapter.
  int get score {
    if (answered == 0) return 0;
    final weight = answered >= fullWeightAfter ? 1.0 : answered / fullWeightAfter;
    return (100 * accuracy * weight).round();
  }

  MasteryLevel get level {
    if (answered == 0) return MasteryLevel.notStarted;
    if (accuracy >= 0.85 && answered >= fullWeightAfter && coverage >= 0.5) {
      return MasteryLevel.strong;
    }
    if (accuracy >= 0.6 && answered >= 5) return MasteryLevel.good;
    return MasteryLevel.learning;
  }

  static const fullWeightAfter = 10;
}

/// Mastery for every chapter with saved questions or answers.
List<ChapterMastery> chapterMastery({
  required List<ChapterStat> stats,
  required Map<int, int> questionsPerChapter,
}) {
  final byChapter = {for (final s in stats) s.chapterId: s};
  final ids = {...questionsPerChapter.keys, ...byChapter.keys};
  return [
    for (final id in ids)
      ChapterMastery(
        chapterId: id,
        totalQuestions: questionsPerChapter[id] ?? 0,
        answered: byChapter[id]?.answered ?? 0,
        correct: byChapter[id]?.correct ?? 0,
      ),
  ];
}

/// Weak topics: started chapters below "strong", weakest first (lowest share right, then most answered).
List<ChapterMastery> weakTopics(List<ChapterMastery> all, {int max = 3}) {
  final started = all.where((m) => m.answered > 0 && m.level != MasteryLevel.strong).toList()
    ..sort((a, b) {
      final byScore = a.accuracy.compareTo(b.accuracy);
      return byScore != 0 ? byScore : b.answered.compareTo(a.answered);
    });
  return started.take(max).toList();
}
