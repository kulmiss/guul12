import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';

FakeServer onboarded() {
  final s = FakeServer();
  onboardedProfile(s);
  return s;
}

void main() {
  testWidgets('Me: student ID can be copied; free plan shows no price or buy text', (tester) async {
    String? copied;
    tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, (
      call,
    ) async {
      if (call.method == 'Clipboard.setData') {
        copied = (call.arguments as Map)['text'] as String?;
      }
      return null;
    });
    addTearDown(
      () => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
        SystemChannels.platform,
        null,
      ),
    );

    await pumpGuul(tester, server: onboarded());
    await tapOn(tester, find.text('Me'));

    expect(find.text('ST-7K3Q9'), findsOneWidget);
    expect(find.text('Free plan'), findsOneWidget);
    expect(find.text('Grade 12'), findsOneWidget);
    expect(find.text('Natural Science'), findsOneWidget);
    expect(find.text('Somali'), findsOneWidget);
    expect(find.text('450 / 600'), findsOneWidget);
    for (final word in ['Br', 'Birr', 'price', 'Buy', 'Upgrade', 'WhatsApp', 'Pay']) {
      expect(find.textContaining(word), findsNothing, reason: 'no payment text: $word');
    }

    await tapOn(tester, find.text('Copy'));
    expect(copied, 'ST-7K3Q9');
    expect(find.text('Copied'), findsOneWidget);
  });

  testWidgets('Me: Premium shows only the end date', (tester) async {
    final server = onboarded();
    server.profile['premium'] = {'active': true, 'until': '2027-07-31T20:59:59Z'};
    await pumpGuul(tester, server: server);
    await tapOn(tester, find.text('Me'));
    expect(find.text('Premium until Jul 31, 2027'), findsOneWidget);
  });

  testWidgets('Google sign-in: not set up yet → friendly message', (tester) async {
    await pumpGuul(tester, server: onboarded());
    await tapOn(tester, find.text('Me'));
    await tapOn(tester, find.text('Sign in with Google'));
    expect(find.text('Google sign-in is not available yet.'), findsOneWidget);
  });

  testWidgets('Google sign-in works, and a conflict is explained', (tester) async {
    final server = onboarded()..googleStatus = 409;
    await pumpGuul(tester, server: server, googleAuth: FakeGoogleAuth());
    await tapOn(tester, find.text('Me'));
    await tapOn(tester, find.text('Sign in with Google'));
    expect(
      find.text('This phone is already signed in with another Google account.'),
      findsOneWidget,
    );

    server.googleStatus = 200;
    await tapOn(tester, find.text('Sign in with Google'));
    expect(find.text('Signed in'), findsOneWidget);
    expect(find.text('Sign in with Google'), findsNothing);
  });

  testWidgets('cancelling Google sign-in changes nothing', (tester) async {
    await pumpGuul(tester, server: onboarded(), googleAuth: FakeGoogleAuth(token: null));
    await tapOn(tester, find.text('Me'));
    await tapOn(tester, find.text('Sign in with Google'));
    expect(find.text('Sign in with Google'), findsOneWidget);
    expect(find.byType(SnackBar), findsNothing);
  });

  testWidgets('theme choice is kept on the phone', (tester) async {
    final (repo, _) = await pumpGuul(tester, server: onboarded());
    await tapOn(tester, find.text('Me'));
    await tapOn(tester, find.text('Dark'));
    expect(Theme.of(tester.element(find.text('Me').first)).brightness, Brightness.dark);
    expect(await repo.db.readString('theme_mode'), 'dark');
  });

  testWidgets('edit profile: change grade to 11 social, Grade 12 retaker switch disappears', (
    tester,
  ) async {
    final (_, server) = await pumpGuul(tester, server: onboarded());
    await tapOn(tester, find.text('Me'));
    await tapOn(tester, find.text('Edit study profile').last);
    expect(find.text('Retaking the exam'), findsOneWidget);

    await tapOn(tester, find.text('11'));
    expect(find.text('Retaking the exam'), findsNothing);
    await tapOn(tester, find.text('Social Science'));
    await tapOn(tester, find.text('Save'));

    expect(find.text('Grade 11'), findsOneWidget);
    expect(server.profile['grade'], 11);
    expect(server.profile['stream'], 'social');
    expect(server.profile['is_retaker'], false);
    expect(server.profile['region'], 'somali');
  });
}
