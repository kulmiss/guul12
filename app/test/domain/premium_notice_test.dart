import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/domain/premium_notice.dart';

final now = DateTime.utc(2026, 11, 23, 9);
DateTime days(int d) => now.add(Duration(days: d));

void main() {
  test('no note far from the end, or for a student who never had Premium', () {
    expect(premiumNotice(now: now, active: true, until: days(30), lastUntil: days(30)), isNull);
    expect(premiumNotice(now: now, active: false, until: null, lastUntil: null), isNull);
    expect(premiumNotice(now: now, active: true, until: null, lastUntil: null), isNull);
  });

  test('ends within 7 days', () {
    final n = premiumNotice(now: now, active: true, until: days(7), lastUntil: days(7));
    expect(n, isA<PremiumEnding>());
    expect(n!.date, days(7));
    expect(premiumNotice(now: now, active: true, until: days(8), lastUntil: null), isNull);
  });

  test('ended: from the remembered date, for 7 days', () {
    final n = premiumNotice(now: now, active: false, until: null, lastUntil: days(-2));
    expect(n, isA<PremiumEnded>());
    expect(n!.date, days(-2));
    expect(premiumNotice(now: now, active: false, until: null, lastUntil: days(-8)), isNull);
    // A remembered future date while not active (e.g. revoked): no "ended" note yet.
    expect(premiumNotice(now: now, active: false, until: null, lastUntil: days(3)), isNull);
  });

  test('a stale "active" profile past its end date counts as ended', () {
    expect(
      premiumNotice(now: now, active: true, until: days(-1), lastUntil: days(-1)),
      isA<PremiumEnded>(),
    );
  });
}
