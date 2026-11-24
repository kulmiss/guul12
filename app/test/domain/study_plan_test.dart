import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/domain/mastery.dart';
import 'package:guul12/domain/study_plan.dart';

final today = DateTime(2026, 11, 23, 9);
DateTime inDays(int days) => DateTime(2026, 11, 23).add(Duration(days: days));

// Subject 1: chapters 11–14, subject 2: chapters 21–22.
const chapters = [
  PlanChapter(id: 11, subjectId: 1),
  PlanChapter(id: 12, subjectId: 1),
  PlanChapter(id: 13, subjectId: 1),
  PlanChapter(id: 14, subjectId: 1),
  PlanChapter(id: 21, subjectId: 2),
  PlanChapter(id: 22, subjectId: 2),
];

ChapterMastery m(int id, int answered, int correct) =>
    ChapterMastery(chapterId: id, totalQuestions: 20, answered: answered, correct: correct);

StudyPlan plan({
  DateTime? exam,
  bool noExam = false,
  int minutes = 30,
  Map<int, ChapterMastery> mastery = const {},
  int target = 67,
  int due = 0,
  List<PlanChapter> list = chapters,
}) => const StudyPlanner().build(
  today: today,
  examDate: noExam ? null : (exam ?? inDays(200)),
  minutesPerDay: minutes,
  chapters: list,
  mastery: mastery,
  targetPercent: target,
  dueReviews: due,
);

String describe(StudyPlan p) => p.tasks
    .map(
      (t) =>
          '${t.kind.wire}${t.chapterId ?? ''}${t.count == null ? '' : 'x${t.count}'}:${t.minutes}',
    )
    .join(' ');

void main() {
  group('stage', () {
    test('by days left', () {
      expect(plan(noExam: true).stage, PlanStage.noDate);
      expect(plan(exam: inDays(31)).stage, PlanStage.learn);
      expect(plan(exam: inDays(30)).stage, PlanStage.revision);
      expect(plan(exam: inDays(2)).stage, PlanStage.revision);
      expect(plan(exam: inDays(1)).stage, PlanStage.lastDay);
      expect(plan(exam: inDays(0)).stage, PlanStage.done);
      expect(plan(exam: inDays(-3)).stage, PlanStage.done);
      expect(plan(exam: inDays(31)).daysLeft, 31);
      expect(plan(noExam: true).daysLeft, isNull);
    });
  });

  group('learn phase', () {
    test('fresh student, 30 min: one new chapter then smart practice', () {
      final p = plan();
      expect(describe(p), 'learn11:20 smart:10');
      expect(p.minutes, 30);
      expect(p.chaptersLeft, 6);
      expect(p.chaptersAtTarget, 0);
      expect(p.chaptersTotal, 6);
    });

    test('subjects take turns: the one with fewer started chapters goes first', () {
      // Subject 1 has 1 of 4 started (25%), subject 2 has 0 of 2: subject 2 first.
      // Then 25% vs 50%: subject 1. Then 50% vs 50%: a tie goes to the first subject.
      final p = plan(minutes: 60, mastery: {11: m(11, 10, 9)});
      expect(describe(p), 'learn21:20 learn12:20 learn13:20');
    });

    test('reviews first, then new and weak chapters take turns', () {
      final p = plan(
        minutes: 60,
        due: 4,
        mastery: {11: m(11, 10, 4), 21: m(21, 10, 9), 12: m(12, 10, 5)},
      );
      // Weak: 11 (40) then 12 (50); 21 (90) is at target.
      expect(describe(p), 'reviewx4:4 learn13:20 practise11:10 learn22:20');
      expect(p.minutes, 54);
      expect(p.chaptersAtTarget, 1);
    });

    test('review time is capped at 10 minutes', () {
      final p = plan(due: 40);
      expect(p.tasks.first, const PlanTask(kind: PlanTaskKind.review, minutes: 10, count: 40));
    });

    test('15 minutes: a new chapter does not fit, a weak one does', () {
      expect(describe(plan(minutes: 15, mastery: {11: m(11, 10, 3)})), 'practise11:10');
    });

    test('15 minutes and nothing weak: smart practice so the day is not empty', () {
      expect(describe(plan(minutes: 15)), 'smart:10');
    });

    test('everything learned and strong: smart practice fills the time', () {
      final all = {for (final c in chapters) c.id: m(c.id, 20, 19)};
      final p = plan(mastery: all);
      expect(describe(p), 'smart:10');
      expect(p.chaptersLeft, 0);
      expect(p.chaptersPerWeek, isNull);
      expect(p.chaptersAtTarget, 6);
    });

    test('pace: chapters a week to finish before revision', () {
      // 6 chapters, 100 - 30 = 70 learning days → 0.6 a week → 1.
      expect(plan(exam: inDays(100)).chaptersPerWeek, 1);
      // 6 chapters in 7 learning days → 6 a week.
      expect(plan(exam: inDays(37)).chaptersPerWeek, 6);
      // Less than a week of learning left: no weekly pace.
      expect(plan(exam: inDays(36)).chaptersPerWeek, isNull);
      expect(plan(noExam: true).chaptersPerWeek, isNull);
    });

    test('no chapters at all: no tasks except reviews', () {
      expect(plan(list: const []).tasks, isEmpty);
      expect(describe(plan(list: const [], due: 2)), 'reviewx2:2');
    });

    test('the same input gives the same plan', () {
      expect(describe(plan(minutes: 90, due: 3)), describe(plan(minutes: 90, due: 3)));
    });
  });

  group('revision', () {
    test('weak chapters come before new ones', () {
      final p = plan(exam: inDays(20), minutes: 45, mastery: {11: m(11, 10, 4)});
      expect(describe(p), 'practise11:10 learn21:20 smart:10');
    });

    test('every 7 days before the exam is a mock exam day', () {
      for (final d in [28, 21, 14, 7]) {
        final p = plan(exam: inDays(d), due: 2);
        expect(describe(p), 'reviewx2:2 mock:75', reason: '$d days left');
      }
      expect(plan(exam: inDays(27)).tasks.any((t) => t.kind == PlanTaskKind.mock), isFalse);
    });

    test('the day before: only reviews; exam day: nothing', () {
      expect(describe(plan(exam: inDays(1), due: 3)), 'reviewx3:3');
      expect(plan(exam: inDays(1)).tasks, isEmpty);
      expect(plan(exam: inDays(0), due: 3).tasks, isEmpty);
    });
  });

  group('PlanTask json', () {
    test('round trip and unreadable data', () {
      const t = PlanTask(kind: PlanTaskKind.learn, minutes: 20, chapterId: 7);
      expect(PlanTask.fromJson(t.toJson()), t);
      const r = PlanTask(kind: PlanTaskKind.review, minutes: 3, count: 3);
      expect(PlanTask.fromJson(r.toJson()), r);
      expect(PlanTask.fromJson({'kind': 'dance', 'minutes': 5}), isNull);
      expect(PlanTask.fromJson({'kind': 'learn'}), isNull);
    });
  });
}
