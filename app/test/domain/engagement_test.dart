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
    final e = run(
      day(ago(0), 3, correct: 2),
      exams: [(at: now, correct: 1, total: 40, answered: 10)],
    );
    expect(e.xp, 2 * 10 + 2 + 50);
    expect(e.totalAnswered, 3);
    expect(e.totalCorrect, 2);
    expect(e.exams, 1);
    expect(e.bestExamPercent, 3);
  });

  test(
    'an exam gives its 50 points only with 10 answered questions (the server does the same)',
    () {
      final few = run(const [], exams: [(at: now, correct: 3, total: 50, answered: 9)]);
      expect(few.xp, 0);
      expect(few.exams, 1, reason: 'it still counts as an exam taken');
      expect(few.bestExamPercent, 6);
      expect(run(const [], exams: [(at: now, correct: 3, total: 50, answered: 10)]).xp, 50);
    },
  );

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
        (at: DateTime(2026, 11, 22, 10), correct: 10, total: 10, answered: 10),
        (at: now, correct: 0, total: 20, answered: 10),
      ],
    );
    expect(e.weekXp, 10 + 50);
    expect(e.xp, 60 + 50 + 50);
    expect(e.bestExamPercent, 100);
  });

  test('an exam with no questions does not set a best score', () {
    expect(
      run(const [], exams: [(at: now, correct: 0, total: 0, answered: 0)]).bestExamPercent,
      isNull,
    );
  });

  group('achievements', () {
    test('progress and earned', () {
      final e = run(
        [...day(ago(2), 10), ...day(ago(1), 10), ...day(ago(0), 10)],
        exams: [(at: now, correct: 41, total: 50, answered: 50)],
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

  group('this week (Monday to Sunday)', () {
    test('seven days from Monday; today and the days after it are marked', () {
      final week = weekDays(answers: const [], now: now); // Wednesday
      expect(week, hasLength(7));
      expect(week.first.day, DateTime(2026, 11, 23)); // Monday
      expect(week.last.day, DateTime(2026, 11, 29)); // Sunday
      expect([for (final d in week) d.isToday], [false, false, true, false, false, false, false]);
      expect([for (final d in week) d.isFuture], [false, false, false, true, true, true, true]);
    });

    test('a day is filled once it reaches the goal; other weeks do not count', () {
      final week = weekDays(
        answers: [
          ...day(ago(2), 10), // Monday: goal reached
          ...day(ago(1), 9), // Tuesday: one short
          ...day(ago(0), 12), // Wednesday (today): reached
          ...day(ago(3), 30), // last Sunday: another week
        ],
        now: now,
      );
      expect([for (final d in week) d.answered], [10, 9, 12, 0, 0, 0, 0]);
      expect([for (final d in week) d.reached], [true, false, true, false, false, false, false]);
      expect(week.where((d) => d.reached), hasLength(2));
    });

    test('answers just before midnight count for their own day', () {
      final late = [
        for (var i = 0; i < 10; i++) (at: DateTime(2026, 11, 24, 23, 50, i), correct: true),
      ];
      final week = weekDays(answers: late, now: now);
      expect(week[1].reached, isTrue); // Tuesday
      expect(week[2].answered, 0); // not Wednesday
    });

    test('on Sunday night the week is still Monday to Sunday', () {
      final week = weekDays(answers: const [], now: DateTime(2026, 11, 29, 23, 59));
      expect(week.first.day, DateTime(2026, 11, 23));
      expect(week.last.isToday, isTrue);
      expect(week.where((d) => d.isFuture), isEmpty);
    });
  });

  group('next badge', () {
    test('nothing yet: the first badge in the list', () {
      expect(nextAchievement(Engagement.empty, strongChapters: 0), Achievement.firstSteps);
    });

    test('the unearned badge closest to done wins', () {
      // 60 answers: first steps earned; hundred at 60 %. Best streak 2 of 3: 67 %.
      final e = run([...day(ago(1), 10), ...day(ago(0), 50)]);
      expect(e.bestStreak, 2);
      expect(nextAchievement(e, strongChapters: 0), Achievement.streak3);
      // One strong chapter is a whole badge (100 %), so it comes first.
      expect(nextAchievement(e, strongChapters: 1), isNot(Achievement.strongChapter));
      expect(Achievement.strongChapter.earned(e, strongChapters: 1), isTrue);
    });

    test('a tie keeps the order of the badge list', () {
      // 5 answers: first steps 50 %, nothing else started.
      final e = run(day(ago(0), 5));
      expect(nextAchievement(e, strongChapters: 0), Achievement.firstSteps);
    });

    test('every badge earned: no next badge', () {
      const e = Engagement(
        todayAnswered: 0,
        streak: 30,
        bestStreak: 30,
        xp: 0,
        weekXp: 0,
        totalAnswered: 1000,
        totalCorrect: 1000,
        activeDays: 30,
        exams: 1,
        bestExamPercent: 80,
      );
      expect(nextAchievement(e, strongChapters: 10), isNull);
    });
  });
}
