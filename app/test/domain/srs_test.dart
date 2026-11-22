import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/domain/srs.dart';

void main() {
  final day0 = DateTime.utc(2026, 11, 22, 10);
  SrsItem? answer(SrsItem? item, bool correct, DateTime at) =>
      srsAfterAnswer(item: item, questionId: 7, chapterId: 3, correct: correct, now: at);

  test('wrong → 1 day → 3 days → 7 days → learned', () {
    final a = answer(null, false, day0)!;
    expect([a.step, a.dueAt], [0, day0.add(const Duration(days: 1))]);

    final b = answer(a, true, day0.add(const Duration(days: 1)))!;
    expect([b.step, b.dueAt], [1, day0.add(const Duration(days: 4))]);

    final c = answer(b, true, day0.add(const Duration(days: 4)))!;
    expect([c.step, c.dueAt], [2, day0.add(const Duration(days: 11))]);

    expect(answer(c, true, day0.add(const Duration(days: 11))), isNull);
  });

  test('a wrong answer at any step starts again at 1 day', () {
    final b = answer(answer(null, false, day0), true, day0.add(const Duration(days: 1)))!;
    final again = answer(b, false, day0.add(const Duration(days: 4)))!;
    expect([again.step, again.dueAt], [0, day0.add(const Duration(days: 5))]);
  });

  test('a right answer before the review day changes nothing; never-wrong stays out', () {
    final a = answer(null, false, day0)!;
    expect(answer(a, true, day0.add(const Duration(hours: 5))), same(a));
    expect(answer(null, true, day0), isNull);
    expect(a.isDue(day0.add(const Duration(hours: 23, minutes: 59))), isFalse);
    expect(a.isDue(day0.add(const Duration(days: 1))), isTrue);
  });
}
