import 'dart:math';

import '../data/models.dart';

/// Practice modes. `wire` matches the backend PracticeMode enum.
enum PracticeMode {
  onboarding('onboarding'),
  quick10('quick10'),
  chapter('chapter'),
  unit('unit'),
  mixed('mixed'),
  timed('timed'),
  wrongOnly('wrong_only'),
  quickRevise('quick_revise'),
  chapterQuiz('chapter_quiz'),
  review('review'),
  smart('smart'),
  dailyChallenge('daily_challenge');

  const PracticeMode(this.wire);

  final String wire;

  static PracticeMode fromWire(String value) =>
      PracticeMode.values.firstWhere((m) => m.wire == value, orElse: () => PracticeMode.quick10);

  /// Seconds per question in timed mode; null = no timer.
  int? get secondsPerQuestion => this == timed ? 60 : null;
}

/// Builds the list of questions for a practice set. Pure and seedable, so it is easy to test.
class PracticeBuilder {
  PracticeBuilder({Random? random}) : _random = random ?? Random();

  final Random _random;

  static const quickCount = 10;
  static const mixedCount = 20;
  static const chapterMax = 30;
  static const reviseCount = 5;
  static const reviewMax = 20;

  /// [pool]: questions from chapters saved on the phone.
  /// [wrong]: questions whose last answer was wrong. [answered]: questions answered at least once.
  /// [due]: questions whose spaced-repetition review is due.
  /// [weakChapters]: chapter ids, weakest first (for smart practice).
  List<Question> build({
    required PracticeMode mode,
    required List<Question> pool,
    Set<int> wrong = const {},
    Set<int> answered = const {},
    Set<int> due = const {},
    List<int> weakChapters = const [],
  }) {
    final unique = {for (final q in pool) q.id: q}.values.toList();

    switch (mode) {
      case PracticeMode.wrongOnly:
        return _take(unique.where((q) => wrong.contains(q.id)).toList(), mixedCount);
      case PracticeMode.review:
        return _take(unique.where((q) => due.contains(q.id)).toList(), reviewMax);
      case PracticeMode.smart:
        return _smart(unique, wrong, answered, due, weakChapters);
      case PracticeMode.quickRevise:
        return _take(unique, reviseCount);
      case PracticeMode.chapter:
      case PracticeMode.chapterQuiz:
      case PracticeMode.unit:
        return _take(unique, chapterMax);
      case PracticeMode.mixed:
        return _take(unique, mixedCount);
      case PracticeMode.onboarding:
      case PracticeMode.dailyChallenge:
        // The set was chosen elsewhere (warm-up file, or the server's challenge): keep its order.
        return List.of(unique);
      case PracticeMode.quick10:
      case PracticeMode.timed:
        return _prioritised(unique, wrong, answered, quickCount);
    }
  }

  static const smartCount = 10;
  static const smartMaxDue = 3;
  static const smartMaxPerChapter = 4;

  /// Smart practice: a few due reviews, then the weakest chapters (their wrong and new questions
  /// first), then chapters not started yet, then anything else.
  List<Question> _smart(
    List<Question> list,
    Set<int> wrong,
    Set<int> answered,
    Set<int> due,
    List<int> weakChapters,
  ) {
    final out = <Question>[
      ...(list.where((q) => due.contains(q.id)).toList()..shuffle(_random)).take(smartMaxDue),
    ];
    int priority(Question q) => wrong.contains(q.id)
        ? 0
        : answered.contains(q.id)
        ? 2
        : 1;
    for (final chapterId in weakChapters) {
      if (out.length >= smartCount) break;
      final inChapter = list.where((q) => q.chapterId == chapterId && !out.contains(q)).toList()
        ..shuffle(_random);
      final ordered = [
        for (final p in [0, 1]) ...inChapter.where((q) => priority(q) == p),
      ];
      out.addAll(ordered.take(min(smartMaxPerChapter, smartCount - out.length)));
    }
    if (out.length < smartCount) {
      final startedChapters = {
        for (final q in list)
          if (answered.contains(q.id)) q.chapterId,
      };
      final fresh =
          list.where((q) => !startedChapters.contains(q.chapterId) && !out.contains(q)).toList()
            ..shuffle(_random);
      out.addAll(fresh.take(smartCount - out.length));
    }
    if (out.length < smartCount) {
      for (final q in _prioritised(list, wrong, answered, list.length)) {
        if (out.length >= smartCount) break;
        if (!out.contains(q)) out.add(q);
      }
    }
    return out;
  }

  List<Question> _take(List<Question> list, int max) {
    final copy = List.of(list)..shuffle(_random);
    return copy.take(max).toList();
  }

  /// Wrong answers first, then questions never seen, then the rest.
  List<Question> _prioritised(List<Question> list, Set<int> wrong, Set<int> answered, int max) {
    final wrongQs = list.where((q) => wrong.contains(q.id)).toList()..shuffle(_random);
    final fresh = list.where((q) => !answered.contains(q.id)).toList()..shuffle(_random);
    final rest = list.where((q) => answered.contains(q.id) && !wrong.contains(q.id)).toList()
      ..shuffle(_random);

    // Never more than half the set from earlier mistakes, so it does not feel like punishment.
    final out = <Question>[...wrongQs.take(max ~/ 2)];
    for (final q in [...fresh, ...wrongQs.skip(max ~/ 2), ...rest]) {
      if (out.length >= max) break;
      if (!out.contains(q)) out.add(q);
    }
    return out;
  }
}
