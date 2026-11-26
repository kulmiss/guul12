import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/models.dart';
import 'package:guul12/domain/engagement.dart';
import 'package:guul12/domain/mastery.dart';
import 'package:guul12/domain/statistics.dart';

// Wednesday, Nov 25, 2026, 20:00 local time.
final now = DateTime(2026, 11, 25, 20);

DateTime ago(int days, {int hour = 9}) => DateTime(now.year, now.month, now.day - days, hour);

List<AnswerRecord> answers(
  DateTime at,
  int count, {
  int correct = 0,
  int chapterId = 1,
  int? timeMs,
}) => [
  for (var i = 0; i < count; i++)
    (at: at.add(Duration(seconds: i)), correct: i < correct, chapterId: chapterId, timeMs: timeMs),
];

Statistics run(
  List<AnswerRecord> list, {
  List<ExamPoint> exams = const [],
  Map<int, int> chapterSubject = const {1: 10, 2: 20},
  List<int> subjectOrder = const [10, 20],
}) => statisticsFrom(
  answers: list,
  exams: exams,
  chapterSubject: chapterSubject,
  subjectOrder: subjectOrder,
  now: now,
);

void main() {
  group('statistics', () {
    test('nothing yet: zeros, empty series, but every day and week is there', () {
      final s = run(const []);
      expect(s.answered, 0);
      expect(s.correct, 0);
      expect(s.accuracyPercent, isNull);
      expect(s.daysStudied, 0);
      expect(s.studyTime, Duration.zero);
      expect(s.secondsPerAnswer, isNull);
      expect(s.lastDays, hasLength(Statistics.dayCount));
      expect(s.lastDays.every((d) => d.total == 0), isTrue);
      expect(s.weeks, hasLength(Statistics.weekCount));
      expect(s.weeks.every((w) => w.percent == null), isTrue);
      expect(s.heatmap, hasLength(Statistics.heatmapWeeks * 7));
      expect(s.subjects, isEmpty);
      expect(s.exams, isEmpty);
      expect(s.isEmpty, isTrue);
    });

    test('totals, accuracy and days studied', () {
      final s = run([...answers(ago(0), 4, correct: 3), ...answers(ago(2), 6, correct: 3)]);
      expect(s.answered, 10);
      expect(s.correct, 6);
      expect(s.accuracyPercent, 60);
      expect(s.daysStudied, 2);
      expect(s.isEmpty, isFalse);
    });

    test('the last 14 days, oldest first, ending today, right and wrong apart', () {
      final s = run([
        ...answers(ago(0), 5, correct: 2),
        ...answers(ago(13), 3, correct: 3),
        // 14 days ago: outside the chart.
        ...answers(ago(14), 7, correct: 7),
      ]);
      expect(s.lastDays.first.day, DateTime(2026, 11, 12));
      expect(s.lastDays.last.day, DateTime(2026, 11, 25));
      expect((s.lastDays.last.right, s.lastDays.last.wrong), (2, 3));
      expect((s.lastDays.first.right, s.lastDays.first.wrong), (3, 0));
      expect(s.lastDays.skip(1).take(12).every((d) => d.total == 0), isTrue);
      // Totals still count every answer.
      expect(s.answered, 15);
    });

    test('weeks start on Monday; the last one is this week', () {
      final s = run([
        // Monday Nov 23 (this week) and Sunday Nov 22 (last week).
        ...answers(DateTime(2026, 11, 23, 8), 4, correct: 1),
        ...answers(DateTime(2026, 11, 22, 23), 2, correct: 2),
      ]);
      expect(s.weeks.last.start, DateTime(2026, 11, 23));
      expect(s.weeks.last.percent, 25);
      expect(s.weeks[s.weeks.length - 2].start, DateTime(2026, 11, 16));
      expect(s.weeks[s.weeks.length - 2].percent, 100);
      expect(s.weeks.first.start, DateTime(2026, 10, 5));
    });

    test('a clock change in the window never shifts a day', () {
      // Calendar days, not 24-hour steps.
      final s = run(const []);
      for (var i = 1; i < s.lastDays.length; i++) {
        final a = s.lastDays[i - 1].day;
        final b = s.lastDays[i].day;
        expect(DateTime(a.year, a.month, a.day + 1), b);
      }
      for (var i = 1; i < s.heatmap.length; i++) {
        final a = s.heatmap[i - 1].day;
        expect(DateTime(a.year, a.month, a.day + 1), s.heatmap[i].day);
      }
    });

    test('heatmap: 12 whole weeks from Monday, the last one this week, future days marked', () {
      final s = run(answers(ago(0), 3));
      expect(s.heatmap.first.day.weekday, DateTime.monday);
      expect(s.heatmap.first.day, DateTime(2026, 9, 7));
      expect(s.heatmap.last.day, DateTime(2026, 11, 29));
      final today = s.heatmap.firstWhere((d) => d.day == DateTime(2026, 11, 25));
      expect(today.total, 3);
      expect(s.heatmap.where((d) => d.isFuture).map((d) => d.day.day), [26, 27, 28, 29]);
      expect(s.heatmapMax, 3);
    });

    test('subjects in the given order, only those with answers; unknown chapters left out', () {
      final s = run(
        [
          ...answers(ago(1), 4, correct: 1, chapterId: 2),
          ...answers(ago(1), 2, correct: 2, chapterId: 1),
          ...answers(ago(1), 5, correct: 5, chapterId: 99),
        ],
        subjectOrder: const [20, 10, 30],
      );
      expect([for (final x in s.subjects) x.subjectId], [20, 10]);
      expect(s.subjects.first.percent, 25);
      expect(s.subjects.last.percent, 100);
      // The chapter with no subject still counts in the totals.
      expect(s.answered, 11);
    });

    test('study time adds known times only, at most 5 minutes an answer', () {
      final s = run([
        ...answers(ago(0), 2, timeMs: 30000),
        // Phone left open: counts as 5 minutes.
        ...answers(ago(0), 1, timeMs: 3600000),
        // Saved before time was kept.
        ...answers(ago(1), 5),
      ]);
      expect(s.studyTime, const Duration(seconds: 30 + 30 + 300));
      expect(s.timedAnswers, 3);
      expect(s.secondsPerAnswer, 120);
    });

    test('part of the day: morning, afternoon, evening', () {
      final s = run([
        ...answers(ago(1, hour: 6), 2, correct: 2),
        ...answers(ago(1, hour: 13), 3, correct: 0),
        ...answers(ago(1, hour: 22), 1, correct: 1),
        ...answers(ago(1, hour: 2), 1, correct: 0),
      ]);
      expect([for (final p in s.partsOfDay) p.part], ['morning', 'afternoon', 'evening']);
      expect([for (final p in s.partsOfDay) p.answered], [2, 3, 2]);
      expect([for (final p in s.partsOfDay) p.percent], [100, 0, 50]);
    });

    test('exams: the last 10, oldest first', () {
      final exams = [
        for (var i = 0; i < 12; i++) (at: ago(12 - i), percent: i * 5, title: 'Mock $i'),
      ];
      final s = run(const [], exams: exams.reversed.toList());
      expect(s.exams, hasLength(10));
      expect(s.exams.first.title, 'Mock 2');
      expect(s.exams.last.title, 'Mock 11');
      expect(s.isEmpty, isFalse);
    });
  });

  group('readiness', () {
    List<AnswerEvent> events(DateTime at, int count, {required int correct}) => [
      for (var i = 0; i < count; i++) (at: at.add(Duration(seconds: i)), correct: i < correct),
    ];

    test('needs 20 answers', () {
      expect(readinessFrom(answers: events(ago(0), 19, correct: 19), now: now), isNull);
      expect(readinessFrom(answers: events(ago(0), 20, correct: 10), now: now), isNotNull);
    });

    test('a range around the share right; more answers, a narrower range', () {
      final few = readinessFrom(answers: events(ago(0), 20, correct: 14), now: now)!;
      final many = readinessFrom(answers: events(ago(0), 100, correct: 70), now: now)!;
      expect(few.low, lessThan(70));
      expect(few.high, greaterThan(70));
      expect(many.high - many.low, lessThan(few.high - few.low));
      expect(many.low, inInclusiveRange(55, 70));
      expect(many.high, inInclusiveRange(70, 85));
      expect(many.basedOn, 100);
    });

    test('only the last 100 answers count', () {
      final r = readinessFrom(
        answers: [...events(ago(30), 300, correct: 0), ...events(ago(0), 100, correct: 100)],
        now: now,
      )!;
      expect(r.basedOn, 100);
      expect(r.high, 100);
      expect(r.low, greaterThan(90));
    });

    test('change this week: against the estimate 7 days ago', () {
      final r = readinessFrom(
        answers: [...events(ago(10), 40, correct: 20), ...events(ago(1), 60, correct: 60)],
        now: now,
      )!;
      final before = readinessFrom(answers: events(ago(10), 40, correct: 20), now: now)!;
      expect(r.change, r.middle - before.middle);
      expect(r.change, greaterThan(0));
    });

    test('no change shown when there was not enough a week ago', () {
      final r = readinessFrom(
        answers: [...events(ago(10), 5, correct: 5), ...events(ago(1), 30, correct: 10)],
        now: now,
      )!;
      expect(r.change, isNull);
    });

    test('answers after now (a wrong phone clock) are ignored', () {
      expect(
        readinessFrom(answers: events(now.add(const Duration(days: 1)), 50, correct: 50), now: now),
        isNull,
      );
    });
  });

  group('mastery counts', () {
    test('chapters never started count as not started', () {
      const mastery = {
        1: ChapterMastery(chapterId: 1, totalQuestions: 20, answered: 20, correct: 19),
        2: ChapterMastery(chapterId: 2, totalQuestions: 20, answered: 6, correct: 2),
        // Not one of the student's chapters.
        9: ChapterMastery(chapterId: 9, totalQuestions: 20, answered: 6, correct: 6),
      };
      final counts = masteryCounts(chapterIds: const [1, 2, 3, 4], mastery: mastery);
      expect(counts, {
        MasteryLevel.notStarted: 2,
        MasteryLevel.learning: 1,
        MasteryLevel.good: 0,
        MasteryLevel.strong: 1,
      });
    });
  });

  group('pick of the day', () {
    test('the same all day, the next one tomorrow, round and round', () {
      const items = ['a', 'b', 'c'];
      final today = pickOfTheDay(items, DateTime(2026, 11, 25, 6));
      expect(pickOfTheDay(items, DateTime(2026, 11, 25, 23, 59)), today);
      final tomorrow = pickOfTheDay(items, DateTime(2026, 11, 26, 1));
      expect(tomorrow, isNot(today));
      expect(items.indexOf(tomorrow!), (items.indexOf(today!) + 1) % 3);
      expect(pickOfTheDay(const <String>[], now), isNull);
    });
  });

  group('XP to the next place', () {
    LeaderboardEntry entry(int rank, int xp, {bool me = false}) => LeaderboardEntry(
      rank: rank,
      nickname: 'N$rank',
      avatarStyle: null,
      avatarSeed: null,
      xp: xp,
      isMe: me,
    );

    WeeklyLeaderboard board(List<LeaderboardEntry> top, {required int? rank, required int xp}) =>
        WeeklyLeaderboard(
          weekStart: DateTime(2026, 11, 23),
          top: top,
          myXp: xp,
          myRank: rank,
          hasNickname: true,
        );

    test('one more than the student just above', () {
      final b = board([entry(1, 500), entry(2, 320), entry(3, 300, me: true)], rank: 3, xp: 300);
      expect(b.xpToNextPlace, 21);
    });

    test('first place, or no rank: nothing to show', () {
      expect(board([entry(1, 500, me: true)], rank: 1, xp: 500).xpToNextPlace, isNull);
      expect(board([entry(1, 500)], rank: null, xp: 0).xpToNextPlace, isNull);
    });

    test('tied ranks still find the student above', () {
      final b = board([entry(1, 400), entry(1, 400), entry(3, 390, me: true)], rank: 3, xp: 390);
      expect(b.xpToNextPlace, 11);
    });

    test('not everyone above is listed: the gap is not known', () {
      final b = board([for (var r = 1; r <= 10; r++) entry(r, 1000 - r)], rank: 25, xp: 40);
      expect(b.xpToNextPlace, isNull);
    });

    test('never less than 1', () {
      final b = board([entry(1, 300), entry(2, 300, me: true)], rank: 2, xp: 300);
      expect(b.xpToNextPlace, 1);
    });
  });
}
