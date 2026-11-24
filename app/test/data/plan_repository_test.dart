import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/repository.dart';
import 'package:guul12/domain/study_plan.dart';

import '../support/fake_server.dart';

void main() {
  final today = DateTime(2026, 11, 23, 22, 30);
  const tasks = [
    PlanTask(kind: PlanTaskKind.review, minutes: 3, count: 3),
    PlanTask(kind: PlanTaskKind.learn, minutes: 20, chapterId: 21),
  ];

  test('planDay pads the date', () {
    expect(planDay(DateTime(2027, 1, 5, 23, 59)), '2027-01-05');
  });

  test('minutes: default, saved, and only allowed values', () async {
    final (repo, _, _) = await makeRepository();
    expect(await repo.planMinutes(), StudyPlanner.defaultMinutes);
    await repo.setPlanMinutes(60);
    expect(await repo.planMinutes(), 60);
    expect(() => repo.setPlanMinutes(17), throwsArgumentError);
    expect(await repo.planMinutes(), 60);
    // A strange stored value falls back to the default.
    await repo.db.writeString(Keys.planMinutes, '999');
    expect(await repo.planMinutes(), StudyPlanner.defaultMinutes);
  });

  test('saved plan lasts for the day only, keeps ticks, and drops on new minutes', () async {
    final (repo, _, _) = await makeRepository();
    expect(await repo.savedPlan(today), isNull);

    await repo.savePlan(SavedPlan(day: planDay(today), minutes: 30, tasks: tasks, done: const {1}));
    final back = (await repo.savedPlan(today))!;
    expect(back.tasks, tasks);
    expect(back.done, {1});
    expect(back.minutes, 30);

    expect(await repo.savedPlan(today.add(const Duration(hours: 2))), isNull, reason: 'next day');

    await repo.setPlanMinutes(45);
    expect(await repo.savedPlan(today), isNull);
  });

  test('unreadable saved data is ignored, not a crash', () async {
    final (repo, _, _) = await makeRepository();
    final day = planDay(today);
    for (final bad in <Map<String, Object?>>[
      {'day': day, 'minutes': '30', 'tasks': <Object?>[], 'done': <Object?>[]},
      {'day': day, 'minutes': 30, 'tasks': 'x', 'done': <Object?>[]},
      {
        'day': day,
        'minutes': 30,
        'tasks': [
          {'kind': 'unknown', 'minutes': 5},
        ],
        'done': <Object?>[],
      },
    ]) {
      await repo.db.writeJson(Keys.planToday, bad);
      expect(await repo.savedPlan(today), isNull, reason: '$bad');
    }
    // Ticks outside the task list are dropped.
    await repo.db.writeJson(Keys.planToday, {
      'day': day,
      'minutes': 30,
      'tasks': [for (final t in tasks) t.toJson()],
      'done': [0, 5, -1, 'x'],
    });
    expect((await repo.savedPlan(today))!.done, {0});
  });
}
