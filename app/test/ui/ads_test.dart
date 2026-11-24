import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/services/ads.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

class FakeAds implements Ads {
  FakeAds({this.rewardEarned = true});

  var starts = 0;
  var rewardedShown = 0;
  bool rewardEarned;

  @override
  Future<void> start() async => starts++;

  @override
  Widget banner() => const SizedBox(key: ValueKey('test-ad-banner'), height: 50, width: 320);

  @override
  Future<bool> showRewarded() async {
    rewardedShown++;
    return rewardEarned;
  }
}

final banner = find.byKey(const ValueKey('test-ad-banner'));

FakeServer student() {
  final s = FakeServer();
  onboardedProfile(s);
  return s;
}

void main() {
  testWidgets('free student: a banner above the tabs, but not in a practice quiz', (tester) async {
    final ads = FakeAds();
    await pumpGuul(tester, server: student(), ads: ads);
    expect(banner, findsOneWidget);
    expect(ads.starts, 1);

    await tapOn(tester, find.text('Study'));
    expect(banner, findsOneWidget, reason: 'study lists may show it');
    await tapOn(tester, find.text('Physics'));
    await tapOn(tester, find.text('Unit 2: Two-dimensional motion'));
    await tapOn(tester, find.text('1. Chapter 21'));
    await tapOn(tester, find.textContaining('Chapter quiz'));
    expect(find.text('What is 2 + 2?'), findsOneWidget);
    expect(banner, findsNothing);
  });

  testWidgets('free student: no banner in Real Exam Mode', (tester) async {
    await pumpGuul(tester, server: student(), ads: FakeAds());
    await tapOn(tester, find.text('Exams').last);
    expect(banner, findsOneWidget, reason: 'the exam list may show it');
    await tapOn(tester, find.widgetWithText(ChoiceChip, 'Physics'));
    await tapOn(tester, find.text('Physics mock exam 1'));
    await tapOn(tester, find.text('Start exam'));
    expect(find.text('Question 1 of 4'), findsOneWidget);
    expect(banner, findsNothing);
  });

  testWidgets('Premium: no banner and the ad SDK is never started', (tester) async {
    final server = student();
    server
      ..showAds = false
      ..profile['premium'] = {'active': true, 'until': '2027-07-01T00:00:00Z'};
    final ads = FakeAds();
    await pumpGuul(tester, server: server, ads: ads);
    expect(banner, findsNothing);
    expect(ads.starts, 0);
  });

  testWidgets('Premium known on the phone wins even if the server still says ads', (tester) async {
    final server = student();
    server.profile['premium'] = {'active': true, 'until': '2027-07-01T00:00:00Z'};
    final ads = FakeAds();
    await pumpGuul(tester, server: server, ads: ads);
    expect(banner, findsNothing);
    expect(ads.starts, 0);
  });

  testWidgets('ads switched off on the server: none', (tester) async {
    final server = student()..showAds = false;
    final ads = FakeAds();
    await pumpGuul(tester, server: server, ads: ads);
    expect(banner, findsNothing);
    expect(ads.starts, 0);
  });

  group('rewarded video for one more random exam', () {
    Future<FakeAds> reachLimit(WidgetTester tester, FakeServer server, {bool earned = true}) async {
      server.randomStatus = 429;
      final ads = FakeAds(rewardEarned: earned);
      await pumpGuul(tester, server: server, ads: ads);
      await tapOn(tester, find.text('Exams').last);
      await tapOn(tester, find.widgetWithText(ChoiceChip, 'Physics'));
      await tapOn(tester, find.text('Random mock'));
      expect(find.text('One more random exam?'), findsOneWidget);
      return ads;
    }

    testWidgets('watched to the end: unlocked and the exam starts', (tester) async {
      final server = student();
      final ads = await reachLimit(tester, server);
      await tapOn(tester, find.text('Watch video'));
      expect(ads.rewardedShown, 1);
      expect(server.rewardsLeft, 1);
      expect(find.text('Real Exam Mode'), findsOneWidget);
    });

    testWidgets('not finished: nothing unlocked', (tester) async {
      final server = student();
      await reachLimit(tester, server, earned: false);
      await tapOn(tester, find.text('Watch video'));
      expect(find.text('The video was not finished, so nothing was unlocked.'), findsOneWidget);
      expect(server.rewardsLeft, 2);
    });

    testWidgets('daily extra limit reached: a clear message', (tester) async {
      final server = student()..rewardsLeft = 0;
      await reachLimit(tester, server);
      await tapOn(tester, find.text('Watch video'));
      expect(
        find.text("That's all the extra exams for today. Come back tomorrow!"),
        findsOneWidget,
      );
    });

    testWidgets('no offer when rewarded videos are switched off', (tester) async {
      final server = student()..randomStatus = 429;
      server.features['rewarded_ads'] = false;
      await pumpGuul(tester, server: server, ads: FakeAds());
      await tapOn(tester, find.text('Exams').last);
      await tapOn(tester, find.widgetWithText(ChoiceChip, 'Physics'));
      await tapOn(tester, find.text('Random mock'));
      expect(find.text('One more random exam?'), findsNothing);
      expect(
        find.text("Today's free random mock is used. A new one is ready tomorrow."),
        findsOneWidget,
      );
    });
  });
}
