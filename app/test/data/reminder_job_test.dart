import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/app/reminder_job.dart';
import 'package:guul12/data/repository.dart';
import 'package:guul12/l10n/generated/app_localizations.dart';
import 'package:material_ui/material_ui.dart';

import '../support/fake_reminders.dart';
import '../support/fake_server.dart';

void main() {
  final l = lookupAppLocalizations(const Locale('en'));
  var n = 0;

  Future<void> answer(Repository repo, DateTime at, int count) => repo.saveAttempt(
    clientUuid: '00000000-0000-4000-8000-${(++n).toString().padLeft(12, '0')}',
    mode: 'quick10',
    chapterId: 21,
    startedAt: at,
    finishedAt: at,
    answers: [
      for (var i = 0; i < count; i++)
        AttemptAnswer(
          questionId: 2100 + n * 100 + i,
          chapterId: 21,
          version: 1,
          response: 'B',
          timeMs: 4000,
          correctLocally: true,
        ),
    ],
  );

  Future<(Repository, FakeReminders)> setUp({String? time}) async {
    final (repo, _, _) = await makeRepository();
    if (time != null) await repo.db.writeJson(Keys.pendingProfile, {'reminder_time': time});
    return (repo, FakeReminders());
  }

  test('no reminder time: the planned reminder is cancelled', () async {
    final (repo, r) = await setUp();
    await syncReminder(db: repo.db, reminders: r, now: DateTime(2026, 11, 23, 8));
    expect(r.cancels, 1);
    expect(r.scheduled, isEmpty);
  });

  test('a reminder time plans the next run', () async {
    final (repo, r) = await setUp(time: '19:30');
    await syncReminder(db: repo.db, reminders: r, now: DateTime(2026, 11, 23, 8));
    expect(r.scheduled, [DateTime(2026, 11, 23, 19, 30)]);
    expect(r.cancels, 0);
  });

  test('due and goal not reached: shows the reminder, plans tomorrow', () async {
    final (repo, r) = await setUp(time: '19:30');
    final now = DateTime(2026, 11, 23, 19, 35);
    await answer(repo, DateTime(2026, 11, 23, 9), 3);
    await runReminder(db: repo.db, reminders: r, now: now, l: l);
    expect(r.shown, [
      ('Time to practise', '7 questions today to reach your daily goal.', 'Daily reminder'),
    ]);
    expect(r.scheduled, [DateTime(2026, 11, 24, 19, 30)]);
  });

  test('a running streak is named in the reminder', () async {
    final (repo, r) = await setUp(time: '19:30');
    await answer(repo, DateTime(2026, 11, 22, 9), 10);
    await answer(repo, DateTime(2026, 11, 23, 9), 9);
    await runReminder(db: repo.db, reminders: r, now: DateTime(2026, 11, 23, 19, 30), l: l);
    expect(r.shown.single.$2, 'Keep your 1-day streak: 1 more question today.');
  });

  test('goal reached: no reminder, next one still planned', () async {
    final (repo, r) = await setUp(time: '19:30');
    await answer(repo, DateTime(2026, 11, 23, 9), 10);
    await runReminder(db: repo.db, reminders: r, now: DateTime(2026, 11, 23, 19, 31), l: l);
    expect(r.shown, isEmpty);
    expect(r.scheduled, [DateTime(2026, 11, 24, 19, 30)]);
  });

  test('a very late run (phone was off) stays silent', () async {
    final (repo, r) = await setUp(time: '19:30');
    await runReminder(db: repo.db, reminders: r, now: DateTime(2026, 11, 24, 0, 40), l: l);
    expect(r.shown, isEmpty);
    expect(r.scheduled, [DateTime(2026, 11, 24, 19, 30)]);
  });

  test('reminder switched off after it was planned: the run cancels it', () async {
    final (repo, r) = await setUp();
    await runReminder(db: repo.db, reminders: r, now: DateTime(2026, 11, 23, 19, 30), l: l);
    expect(r.shown, isEmpty);
    expect(r.cancels, 1);
  });
}
