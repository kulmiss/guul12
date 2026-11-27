import '../data/local_db.dart';
import '../data/repository.dart';
import '../domain/engagement.dart';
import '../domain/exam.dart';
import '../domain/reminder.dart';
import '../l10n/generated/app_localizations.dart';
import '../services/reminders.dart';

/// Plans the next reminder from the profile's reminder time, or cancels it when none is set.
Future<void> syncReminder({
  required LocalDb db,
  required Reminders reminders,
  required DateTime now,
}) async {
  final time = parseClockTime((await Repository.profileFrom(db)).reminderTime);
  if (time == null) {
    await reminders.cancel();
  } else {
    await reminders.scheduleAt(nextReminderAt(now, time), now: now);
  }
}

/// The background run: shows the reminder when it is due and today's goal is not reached,
/// then plans the next one.
Future<void> runReminder({
  required LocalDb db,
  required Reminders reminders,
  required DateTime now,
  required AppLocalizations l,
}) async {
  final time = parseClockTime((await Repository.profileFrom(db)).reminderTime);
  if (time != null && reminderIsDue(now, time)) {
    final exams = (await db.examResults()).map(ExamRecord.fromJson);
    final engagement = engagementFrom(
      answers: await db.answerEvents(),
      exams: [
        for (final e in exams)
          (at: e.finishedAt.toLocal(), correct: e.correct, total: e.total, answered: e.answered),
      ],
      now: now,
    );
    final note = reminderFor(engagement);
    if (note != null) {
      await reminders.show(
        title: l.reminderTitle,
        body: switch (note) {
          KeepStreakNote(:final streak, :final remaining) => l.reminderKeepStreak(
            streak,
            remaining,
          ),
          StartNote(:final remaining) => l.reminderStart(remaining),
        },
        channelName: l.reminderChannel,
      );
    }
  }
  await syncReminder(db: db, reminders: reminders, now: now);
}
