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
  chapterQuiz('chapter_quiz');

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

  /// [pool]: questions from chapters saved on the phone.
  /// [wrong]: questions whose last answer was wrong. [answered]: questions answered at least once.
  List<Question> build({
    required PracticeMode mode,
    required List<Question> pool,
    Set<int> wrong = const {},
    Set<int> answered = const {},
  }) {
    final unique = {for (final q in pool) q.id: q}.values.toList();

    switch (mode) {
      case PracticeMode.wrongOnly:
        return _take(unique.where((q) => wrong.contains(q.id)).toList(), mixedCount);
      case PracticeMode.quickRevise:
        return _take(unique, reviseCount);
      case PracticeMode.chapter:
      case PracticeMode.chapterQuiz:
      case PracticeMode.unit:
        return _take(unique, chapterMax);
      case PracticeMode.mixed:
        return _take(unique, mixedCount);
      case PracticeMode.onboarding:
        return List.of(unique);
      case PracticeMode.quick10:
      case PracticeMode.timed:
        return _prioritised(unique, wrong, answered, quickCount);
    }
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
