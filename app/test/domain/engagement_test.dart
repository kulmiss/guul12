import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/domain/engagement.dart';

// Wednesday, Nov 25, 2026, 20:00 local time.
final now = DateTime(2026, 11, 25, 20);

List<AnswerEvent> day(DateTime d, int count, {int correct = 0, int hour = 9}) => [
  for (var i = 0; i < count; i++)
    (at: DateTime(d.year, d.month, d.day, hour, i ~/ 60, i % 60), correct: i < correct),
];

DateTime ago(int days) => DateTime(now.year, now.month, now.day - days);

Engagement run(List<AnswerEvent> answers, {List<ExamEvent> exams = const []}) =>
    engagementFrom(answers: answers, exams: exams, now: now);

void main() {
  test('nothing yet', () {
    final e = run(const []);
    expect(e.streak, 0);
    expect(e.bestStreak, 0);
    expect(e.xp, 0);
    expect(e.todayAnswered, 0);
    expect(e.bestExamPercent, isNull);
    expect(e.goalReachedToday, isFalse);
  });

  test('XP: 10 per right answer, 2 per wrong, 50 per exam', () {
    final e = run(day(ago(0), 3, correct: 2), exams: [(at: now, correct: 1, total: 4)]);
    expect(e.xp, 2 * 10 + 2 + 50);
    expect(e.totalAnswered, 3);
    expect(e.totalCorrect, 2);
    expect(e.exams, 1);
    expect(e.bestExamPercent, 25);
  });

  test('the goal needs 10 answers in a day', () {
    expect(run(day(ago(0), 9)).goalReachedToday, isFalse);
    expect(run(day(ago(0), 9)).streak, 0);
    expect(run(day(ago(0), 10)).goalReachedToday, isTrue);
    expect(run(day(ago(0), 10)).streak, 1);
    expect(run(day(ago(0), 10)).activeDays, 1);
  });

  test('streak counts days in a row; it still stands today until the day ends', () {
    final three = [...day(ago(3), 10), ...day(ago(2), 12), ...day(ago(1), 10)];
    expect(run(three).streak, 3, reason: 'today not reached yet, yesterday counted');
    expect(run([...three, ...day(ago(0), 4)]).streak, 3);
    expect(run([...three, ...day(ago(0), 10)]).streak, 4);
  });

  test('a missed day breaks the streak; the best run is kept', () {
    final answers = [
      ...day(ago(9), 10),
      ...day(ago(8), 10),
      ...day(ago(7), 10),
      ...day(ago(6), 10),
      // ago(5..2) missed
      ...day(ago(1), 3), // not enough
      ...day(ago(0), 10),
    ];
    final e = run(answers);
    expect(e.streak, 1);
    expect(e.bestStreak, 4);
    expect(e.activeDays, 5);
  });

  test('no streak when the last active day was 2 days ago', () {
    expect(run(day(ago(2), 10)).streak, 0);
    expect(run(day(ago(2), 10)).bestStreak, 1);
  });

  test('answers around midnight count for their own day', () {
    final late = [
      for (var i = 0; i < 10; i++) (at: DateTime(2026, 11, 24, 23, 50, i), correct: true),
    ];
    final early = [
      for (var i = 0; i < 10; i++) (at: DateTime(2026, 11, 25, 0, 5, i), correct: true),
    ];
    expect(run([...late, ...early]).streak, 2);
  });

  test('a month boundary keeps the run going', () {
    final answers = [...day(DateTime(2026, 10, 31), 10), ...day(DateTime(2026, 11, 1), 10)];
    expect(run(answers).bestStreak, 2);
  });

  test('week XP counts from Monday', () {
    expect(weekStart(now), DateTime(2026, 11, 23));
    expect(weekStart(DateTime(2026, 11, 23, 0, 1)), DateTime(2026, 11, 23));
    expect(weekStart(DateTime(2026, 11, 29, 23)), DateTime(2026, 11, 23));
    final e = run(
      [
        ...day(DateTime(2026, 11, 22), 5, correct: 5),
        ...day(DateTime(2026, 11, 23), 1, correct: 1),
      ],
      exams: [
        (at: DateTime(2026, 11, 22, 10), correct: 1, total: 1),
        (at: now, correct: 0, total: 2),
      ],
    );
    expect(e.weekXp, 10 + 50);
    expect(e.xp, 60 + 50 + 50);
    expect(e.bestExamPercent, 100);
  });

  test('an exam with no questions does not set a best score', () {
    expect(run(const [], exams: [(at: now, correct: 0, total: 0)]).bestExamPercent, isNull);
  });

  group('achievements', () {
    test('progress and earned', () {
      final e = run(
        [...day(ago(2), 10), ...day(ago(1), 10), ...day(ago(0), 10)],
        exams: [(at: now, correct: 41, total: 50)],
      );
      expect(Achievement.firstSteps.earned(e, strongChapters: 0), isTrue);
      expect(Achievement.hundred.progress(e, strongChapters: 0), 30);
      expect(Achievement.hundred.earned(e, strongChapters: 0), isFalse);
      expect(Achievement.streak3.earned(e, strongChapters: 0), isTrue);
      expect(Achievement.streak7.progress(e, strongChapters: 0), 3);
      expect(Achievement.firstExam.earned(e, strongChapters: 0), isTrue);
      expect(Achievement.examStar.earned(e, strongChapters: 0), isTrue); // 82%
      expect(Achievement.strongChapter.earned(e, strongChapters: 1), isTrue);
      expect(Achievement.strongTen.progress(e, strongChapters: 25), 10);
    });

    test('ids are unique', () {
      expect(Achievement.values.map((a) => a.id).toSet(), hasLength(Achievement.values.length));
    });
  });
}
