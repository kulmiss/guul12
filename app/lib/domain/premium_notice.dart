/// A neutral note about Premium ending: no price, no instructions, no link (Play payments rules).
sealed class PremiumNotice {
  const PremiumNotice(this.date);

  final DateTime date;
}

class PremiumEnding extends PremiumNotice {
  const PremiumEnding(super.date);
}

class PremiumEnded extends PremiumNotice {
  const PremiumEnded(super.date);
}

/// Days before the end, and after it, during which the note is shown.
const premiumNoticeDays = 7;

/// [active]/[until]: the profile's Premium. [lastUntil]: the last end date the phone saw.
PremiumNotice? premiumNotice({
  required DateTime now,
  required bool active,
  required DateTime? until,
  required DateTime? lastUntil,
}) {
  const window = Duration(days: premiumNoticeDays);
  if (active && until != null) {
    if (!until.isAfter(now)) return PremiumEnded(until);
    return until.difference(now) <= window ? PremiumEnding(until) : null;
  }
  if (!active &&
      lastUntil != null &&
      !lastUntil.isAfter(now) &&
      now.difference(lastUntil) <= window) {
    return PremiumEnded(lastUntil);
  }
  return null;
}

/// True when the student's Premium is on right now (ads, Home and the Premium page use this).
bool premiumNow({required bool active, required DateTime? until, required DateTime now}) =>
    active && (until == null || until.isAfter(now));
