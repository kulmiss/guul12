/// Gregorian ↔ Ethiopian (Amete Mihret) calendar conversion via the Julian Day Number.
///
/// The Ethiopian year has 12 months of 30 days and a 13th month (Pagume) of 5 days,
/// or 6 days in the year before a Gregorian leap year.
class EthiopianDate {
  const EthiopianDate(this.year, this.month, this.day);

  final int year;

  /// 1 = Meskerem … 13 = Pagume.
  final int month;
  final int day;

  static const int _offset = 1723856;
  static const int _unixEpochJdn = 2440588;

  /// Converts a Gregorian calendar date (the time of day is ignored).
  factory EthiopianDate.fromGregorian(DateTime date) {
    final utc = DateTime.utc(date.year, date.month, date.day);
    final jdn = _unixEpochJdn + utc.millisecondsSinceEpoch ~/ Duration.millisecondsPerDay;
    return EthiopianDate.fromJdn(jdn);
  }

  factory EthiopianDate.fromJdn(int jdn) {
    final r = (jdn - _offset) % 1461;
    final n = (r % 365) + 365 * (r ~/ 1460);
    final year = 4 * ((jdn - _offset) ~/ 1461) + r ~/ 365 - r ~/ 1460;
    final month = n ~/ 30 + 1;
    final day = n % 30 + 1;
    return EthiopianDate(year, month, day);
  }

  int toJdn() => (_offset + 365) + 365 * (year - 1) + year ~/ 4 + 30 * month + day - 31;

  DateTime toGregorian() {
    final days = toJdn() - _unixEpochJdn;
    return DateTime.utc(1970).add(Duration(days: days));
  }

  @override
  bool operator ==(Object other) =>
      other is EthiopianDate && other.year == year && other.month == month && other.day == day;

  @override
  int get hashCode => Object.hash(year, month, day);

  @override
  String toString() => 'EthiopianDate($year-$month-$day)';
}

/// Whole days from [today] to [target] (both treated as calendar dates). Negative when [target] has passed.
int daysUntil(DateTime target, DateTime today) {
  final a = DateTime.utc(today.year, today.month, today.day);
  final b = DateTime.utc(target.year, target.month, target.day);
  return b.difference(a).inDays;
}
