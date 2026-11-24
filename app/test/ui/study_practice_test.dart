import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_launcher.dart';
import '../support/fake_server.dart';

FakeServer onboardedServer() => FakeServer()..let(onboardedProfile);

extension<T> on T {
  T let(void Function(T) f) {
    f(this);
    return this;
  }
}

void main() {
  testWidgets('home shows the exam countdown in both calendars', (tester) async {
    await pumpGuul(tester, server: onboardedServer());
    expect(find.text('Good morning!'), findsOneWidget);
    // 22 Nov 2026 → 29 Jun 2027 is 219 days; 29 Jun 2027 = Sene 22, 2019 E.C.
    expect(find.text('219 days to the exam'), findsOneWidget);
    expect(find.text('June 29, 2027 · Sene 22, 2019 E.C.'), findsOneWidget);
  });

  testWidgets('study → subject → chapter → quiz → result is saved and synced', (tester) async {
    final (repo, server) = await pumpGuul(tester, server: onboardedServer());

    await tapOn(tester, find.text('Study'));
    // Grade 12 natural: Physics and English, never the social-stream History.
    expect(find.text('Physics'), findsOneWidget);
    expect(find.text('English'), findsOneWidget);
    expect(find.text('History'), findsNothing);

    await tapOn(tester, find.text('Physics'));
    await tapOn(tester, find.text('Unit 2: Two-dimensional motion'));
    await tapOn(tester, find.text('1. Chapter 21'));

    expect(find.text('A projectile falls with constant acceleration g.'), findsOneWidget);
    expect(find.text('Fall time'), findsOneWidget);
    expect(server.chapterDownloads[21], 1);

    await tapOn(tester, find.textContaining('Chapter quiz'));
    expect(find.text('Question 1 of 1'), findsOneWidget);

    // Check with no answer only asks for one.
    await tapOn(tester, find.byKey(const ValueKey('check')));
    expect(find.text('Choose or type an answer first.'), findsOneWidget);
    expect(find.byKey(const ValueKey('next')), findsNothing);

    await tapOn(tester, find.text('4'));
    expect(find.text('Choose or type an answer first.'), findsNothing);
    await tapOn(tester, find.byKey(const ValueKey('check')));
    expect(find.text('Correct!'), findsOneWidget);
    expect(find.text('2 + 2 = 4.'), findsOneWidget);
    await tapOn(tester, find.byKey(const ValueKey('next')));

    expect(find.text('Practice finished'), findsOneWidget);
    expect(find.text('1 / 1 correct'), findsOneWidget);
    expect(find.text('Saved and synced.'), findsOneWidget);
    expect(server.receivedAttempts.single['mode'], 'chapter_quiz');
    expect(server.receivedAttempts.single['chapter_id'], 21);
    expect((server.receivedAttempts.single['answers']! as List).single, {
      'question_id': 2101,
      'version': 1,
      'response': 'B',
      'time_ms': 0,
    });

    await tapOn(tester, find.text('Done'));
    expect(find.text('Chapter quiz · 1 question'), findsOneWidget);

    // Back home: the big "Continue" card shows the chapter, Quick 10 is now possible.
    await tapOn(tester, find.text('Home'));
    expect(find.text('Continue where you left off'), findsOneWidget);
    expect(
      find.descendant(
        of: find.byKey(const ValueKey('home-continue')),
        matching: find.text('1. Chapter 21'),
      ),
      findsOneWidget,
    );
    expect(await repo.db.savedChapterIds(), {21});
  });

  testWidgets('a wrong answer offline: saved, shown again in "Only my wrong answers"', (
    tester,
  ) async {
    final server = onboardedServer();
    final (_, _) = await pumpGuul(
      tester,
      server: server,
      before: (repo) async {
        await repo.syncAll();
        await repo.chapter(21);
      },
    );
    server.online = false;

    await tapOn(tester, find.text('Quick 10'));
    expect(find.text('Question 1 of 1'), findsOneWidget);
    await tapOn(tester, find.text('22'));
    await tapOn(tester, find.byKey(const ValueKey('check')));
    expect(find.text('Not quite'), findsOneWidget);
    expect(find.text('Correct answer: B. 4'), findsOneWidget);
    await tapOn(tester, find.byKey(const ValueKey('next')));
    expect(find.text('0 / 1 correct'), findsOneWidget);
    expect(find.text('Saved. It will sync when you are online.'), findsOneWidget);
    expect(find.text('Your answer: D. 22'), findsOneWidget);
    expect(server.receivedAttempts, isEmpty);

    await tapOn(tester, find.text('Done'));
    await tapOn(tester, find.text('More practice'));
    await tapOn(tester, find.text('Only my wrong answers'));
    expect(find.text('What is 2 + 2?'), findsOneWidget);
    await tapOn(tester, find.text('4'));
    await tapOn(tester, find.byKey(const ValueKey('check')));
    await tapOn(tester, find.byKey(const ValueKey('next')));
    await tapOn(tester, find.text('Done'));

    await tapOn(tester, find.text('Only my wrong answers'));
    expect(find.text('No wrong answers to practise. Well done!'), findsOneWidget);
  });

  testWidgets('leaving a practice half way asks first', (tester) async {
    await pumpGuul(
      tester,
      server: onboardedServer(),
      before: (repo) async {
        await repo.syncAll();
        await repo.chapter(21);
      },
    );
    await tapOn(tester, find.text('Quick 10'));
    await tester.binding.handlePopRoute();
    await settle(tester);
    expect(find.text('Stop practising?'), findsOneWidget);
    await tapOn(tester, find.text('Keep going'));
    expect(find.text('Question 1 of 1'), findsOneWidget);
    await tester.binding.handlePopRoute();
    await settle(tester);
    await tapOn(tester, find.text('Stop'));
    expect(find.text('Good morning!'), findsOneWidget);
  });

  testWidgets('tablet uses a navigation rail', (tester) async {
    await pumpGuul(tester, server: onboardedServer(), size: tabletSize);
    expect(find.byType(NavigationRail), findsOneWidget);
    expect(find.byType(NavigationBar), findsNothing);
  });

  testWidgets('report a problem from the feedback panel reaches the server', (tester) async {
    final (_, server) = await pumpGuul(
      tester,
      server: onboardedServer(),
      before: (repo) async {
        await repo.syncAll();
        await repo.chapter(21);
      },
    );
    await tapOn(tester, find.text('Quick 10'));
    expect(find.text('Report a problem'), findsNothing, reason: 'only after Check');
    await tapOn(tester, find.text('4'));
    await tapOn(tester, find.byKey(const ValueKey('check')));

    await tapOn(tester, find.text('Report a problem'));
    expect(find.text('What is wrong with this question?'), findsOneWidget);
    final send = find.byKey(const ValueKey('report-send'));
    expect(tester.widget<FilledButton>(send).onPressed, isNull, reason: 'pick a reason first');
    await tapOn(tester, find.text('There is a typo'));
    await tester.enterText(find.byType(TextField), 'Two plus two is spelt wrong');
    await tapOn(tester, send);

    expect(find.text('Thank you! We will check this question.'), findsOneWidget);
    expect(server.receivedReports, [
      {'question_id': 2101, 'reason': 'typo', 'note': 'Two plus two is spelt wrong'},
    ]);
  });

  testWidgets('the chapter summary PDF opens only when the chapter has one', (tester) async {
    final launcher = fakeLauncher();
    const url = 'https://guul12.kulmiss.com/storage/summaries/g12-physics-2-1-0123456789ab.pdf';
    final server = onboardedServer()..summaryPdfUrls[21] = url;
    await pumpGuul(tester, server: server);

    await tapOn(tester, find.text('Study'));
    await tapOn(tester, find.text('Physics'));
    await tapOn(tester, find.text('Unit 2: Two-dimensional motion'));
    await tapOn(tester, find.text('1. Chapter 21'));
    await tapOn(tester, find.text('Chapter summary (PDF)'));
    expect(launcher.opened, [url]);

    await tester.pageBack();
    await settle(tester);
    await tapOn(tester, find.text('2. Chapter 22'));
    expect(find.text('Chapter summary (PDF)'), findsNothing);
  });
}
