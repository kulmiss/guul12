import 'engagement.dart';

/// A time of day, e.g. the daily reminder at 19:30.
typedef ClockTime = ({int hour, int minute});

/// Reads "HH:MM" (24-hour). Null for anything else.
ClockTime? parseClockTime(String? wire) {
  if (wire == null) return null;
  final match = RegExp(r'^([01]\d|2[0-3]):([0-5]\d)$').firstMatch(wire);
  if (match == null) return null;
  return (hour: int.parse(match.group(1)!), minute: int.parse(match.group(2)!));
}

/// The next time [t] comes, strictly after [now] (today if it has not passed, else tomorrow).
DateTime nextReminderAt(DateTime now, ClockTime t) {
  final today = DateTime(now.year, now.month, now.day, t.hour, t.minute);
  return today.isAfter(now) ? today : DateTime(now.year, now.month, now.day + 1, t.hour, t.minute);
}

/// The background task runs "about" on time. If it runs much later (the phone was off), it stays
/// silent instead of reminding the student in the middle of the night.
const reminderLateLimit = Duration(hours: 3);

/// True when [now] is from the reminder time today up to [reminderLateLimit] after it.
bool reminderIsDue(DateTime now, ClockTime t) {
  final at = DateTime(now.year, now.month, now.day, t.hour, t.minute);
  return !now.isBefore(at) && now.isBefore(at.add(reminderLateLimit));
}

/// What the reminder says.
sealed class ReminderNote {
  const ReminderNote(this.remaining);

  /// Questions still to answer today for the daily goal.
  final int remaining;
}

class KeepStreakNote extends ReminderNote {
  const KeepStreakNote({required this.streak, required int remaining}) : super(remaining);

  final int streak;
}

class StartNote extends ReminderNote {
  const StartNote({required int remaining}) : super(remaining);
}

/// Null when today's goal is already reached (no reminder needed).
ReminderNote? reminderFor(Engagement e) {
  if (e.goalReachedToday) return null;
  final remaining = EngagementRules.dailyGoal - e.todayAnswered;
  return e.streak > 0
      ? KeepStreakNote(streak: e.streak, remaining: remaining)
      : StartNote(remaining: remaining);
}
