import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';
import '../support/review_fixture.dart';

/// Bug hunt: students with large text in the phone settings. Every main screen on the smallest
/// phone (360 dp) must draw without overflowing (a layout overflow fails the test).
void main() {
  for (final scale in [1.3, 2.0]) {
    testWidgets('large text ×$scale on a 360 dp phone: the main screens fit', (tester) async {
      tester.platformDispatcher.textScaleFactorTestValue = scale;
      addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
      final server = FakeServer()..reviews[11] = sampleReview();
      onboardedProfile(server);
      await pumpGuul(tester, server: server);

      // Home (scrolled to the end) and the Premium page.
      await tapOn(tester, find.text('Become Premium'));
      await tester.drag(find.byType(Scrollable).first, const Offset(0, -3000));
      await settle(tester);
      await tester.pageBack();
      await settle(tester);
      await tester.drag(find.byType(Scrollable).first, const Offset(0, -3000));
      await settle(tester);

      // Study → unit → textbook review (solution open) → chapter → quiz.
      await tapOn(tester, find.text('Study'));
      await tapOn(tester, find.text('Physics'));
      await tapOn(tester, find.text('Unit 2: Two-dimensional motion'));
      await tapOn(tester, find.text('Review exercise (textbook)'));
      for (var i = 0; i < 4; i++) {
        await tester.drag(find.byType(Scrollable).first, const Offset(0, -1500));
        await settle(tester);
      }
      await tapOn(tester, find.text('Show solution').last);
      await tester.pageBack();
      await settle(tester);
      await tapOn(tester, find.text('1. Chapter 21'));
      await tapOn(tester, find.textContaining('Chapter quiz'));
      await tapOn(tester, find.text('4'));
      await tapOn(tester, find.byKey(const ValueKey('check')));
      expect(find.text('Correct!'), findsOneWidget);
      await tapOn(tester, find.byKey(const ValueKey('next')));
      expect(find.text('Practice finished'), findsOneWidget);
      // Back to the tabs.
      for (var i = 0; i < 6 && find.text('Exams').evaluate().isEmpty; i++) {
        await tester.pageBack();
        await settle(tester);
      }

      // A mock exam in Real Exam Mode.
      await tapOn(tester, find.text('Exams').last);
      await tapOn(tester, find.widgetWithText(ChoiceChip, 'Physics'));
      await tapOn(tester, find.text('Physics mock exam 1'));
      await tapOn(tester, find.text('Start exam'));
      expect(find.text('Option A'), findsOneWidget);
      await tapOn(tester, find.text('Option A'));
      await tester.pageBack();
      await settle(tester);
      await tapOn(tester, find.text('Leave'));

      for (final tab in ['Exams', 'Progress', 'Me']) {
        await tapOn(tester, find.text(tab).last);
        await tester.drag(find.byType(Scrollable).first, const Offset(0, -3000));
        await settle(tester);
      }
      await tapOn(tester, find.text('Invite friends'));
      expect(find.text('Q4WX7P'), findsOneWidget);
    });
  }
}
