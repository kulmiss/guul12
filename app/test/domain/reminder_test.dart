import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/domain/engagement.dart';
import 'package:guul12/domain/reminder.dart';

Engagement e({int today = 0, int streak = 0}) => Engagement(
  todayAnswered: today,
  streak: streak,
  bestStreak: streak,
  xp: 0,
  weekXp: 0,
  totalAnswered: today,
  totalCorrect: 0,
  activeDays: streak,
  exams: 0,
  bestExamPercent: null,
);

void main() {
  test('parse HH:MM only', () {
    expect(parseClockTime('19:30'), (hour: 19, minute: 30));
    expect(parseClockTime('00:00'), (hour: 0, minute: 0));
    expect(parseClockTime('23:59'), (hour: 23, minute: 59));
    for (final bad in [null, '', '24:00', '7:30', '19:60', '19:30:00', 'ab:cd']) {
      expect(parseClockTime(bad), isNull, reason: '$bad');
    }
  });

  test('next reminder: later today, or tomorrow once the time has passed', () {
    const t = (hour: 19, minute: 30);
    expect(nextReminderAt(DateTime(2026, 11, 23, 8), t), DateTime(2026, 11, 23, 19, 30));
    expect(nextReminderAt(DateTime(2026, 11, 23, 19, 30), t), DateTime(2026, 11, 24, 19, 30));
    expect(nextReminderAt(DateTime(2026, 11, 23, 22), t), DateTime(2026, 11, 24, 19, 30));
    // Month and year ends.
    expect(nextReminderAt(DateTime(2026, 11, 30, 21), t), DateTime(2026, 12, 1, 19, 30));
    expect(nextReminderAt(DateTime(2026, 12, 31, 21), t), DateTime(2027, 1, 1, 19, 30));
  });

  test('due only from the time up to 3 hours later', () {
    const t = (hour: 19, minute: 30);
    expect(reminderIsDue(DateTime(2026, 11, 23, 19, 29), t), isFalse);
    expect(reminderIsDue(DateTime(2026, 11, 23, 19, 30), t), isTrue);
    expect(reminderIsDue(DateTime(2026, 11, 23, 22, 29), t), isTrue);
    expect(reminderIsDue(DateTime(2026, 11, 23, 22, 30), t), isFalse);
    expect(reminderIsDue(DateTime(2026, 11, 24, 0, 30), t), isFalse, reason: 'late night');
  });

  test('what the reminder says', () {
    expect(reminderFor(e(today: 10)), isNull);
    expect(reminderFor(e(today: 12, streak: 4)), isNull);
    final start = reminderFor(e(today: 3));
    expect(start, isA<StartNote>());
    expect(start!.remaining, 7);
    final keep = reminderFor(e(today: 0, streak: 5));
    expect(keep, isA<KeepStreakNote>());
    expect((keep! as KeepStreakNote).streak, 5);
    expect(keep.remaining, 10);
  });
}
