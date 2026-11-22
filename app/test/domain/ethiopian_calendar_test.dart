import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/domain/ethiopian_calendar.dart';

void main() {
  group('known dates', () {
    // Dates published with the 2018 E.C. Grade 12 exam calendar and results.
    final known = <DateTime, EthiopianDate>{
      DateTime(2026, 6, 30): const EthiopianDate(2018, 10, 23), // Sene 23
      DateTime(2026, 7, 1): const EthiopianDate(2018, 10, 24), // Sene 24
      DateTime(2026, 7, 23): const EthiopianDate(2018, 11, 16), // Hamle 16
      DateTime(2026, 8, 11): const EthiopianDate(2018, 12, 5), // Nehase 5
      DateTime(2026, 5, 25): const EthiopianDate(2018, 9, 17), // Ginbot 17
      DateTime(2025, 9, 11): const EthiopianDate(2018, 1, 1), // New year 2018
      DateTime(2023, 9, 12): const EthiopianDate(2016, 1, 1), // New year before a leap year
      DateTime(2023, 9, 11): const EthiopianDate(2015, 13, 6), // Pagume 6 in a 6-day Pagume
      DateTime(2025, 9, 10): const EthiopianDate(2017, 13, 5), // Pagume 5
      DateTime(2024, 2, 29): const EthiopianDate(2016, 6, 21), // Gregorian leap day
    };

    known.forEach((gregorian, ethiopian) {
      test('$gregorian → $ethiopian', () {
        expect(EthiopianDate.fromGregorian(gregorian), ethiopian);
        expect(
          ethiopian.toGregorian(),
          DateTime.utc(gregorian.year, gregorian.month, gregorian.day),
        );
      });
    });
  });

  test('round trip for every day of 2020–2032', () {
    var day = DateTime.utc(2020);
    final end = DateTime.utc(2033);
    EthiopianDate? previous;
    while (day.isBefore(end)) {
      final eth = EthiopianDate.fromGregorian(day);
      expect(eth.toGregorian(), day);
      expect(eth.month, inInclusiveRange(1, 13));
      expect(eth.day, inInclusiveRange(1, eth.month == 13 ? 6 : 30));
      if (previous != null) {
        expect(eth.toJdn() - previous.toJdn(), 1);
      }
      previous = eth;
      day = day.add(const Duration(days: 1));
    }
  });

  test('time of day and local time zone do not change the date', () {
    expect(
      EthiopianDate.fromGregorian(DateTime(2026, 6, 30, 23, 59)),
      const EthiopianDate(2018, 10, 23),
    );
    expect(
      EthiopianDate.fromGregorian(DateTime(2026, 6, 30, 0, 1)),
      const EthiopianDate(2018, 10, 23),
    );
  });

  test('daysUntil', () {
    expect(daysUntil(DateTime(2027, 6, 29), DateTime(2027, 6, 29, 22)), 0);
    expect(daysUntil(DateTime(2027, 6, 29), DateTime(2027, 6, 28, 1)), 1);
    expect(daysUntil(DateTime(2027, 6, 29), DateTime(2027, 6, 30)), -1);
    expect(daysUntil(DateTime(2027, 3, 1), DateTime(2027, 2, 1)), 28);
  });
}
