import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/repository.dart';
import 'package:guul12/ui/progress/charts.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';
import '../support/stats_seed.dart';

Finder inKey(String key, Finder matching) =>
    find.descendant(of: find.byKey(ValueKey(key)), matching: matching);

var _attempts = 0;

/// [count] answers in chapter 21 at [at], the first [right] of them right, 30 s each.
Future<void> answer(Repository repo, DateTime at, int count, {required int right}) =>
    repo.saveAttempt(
      clientUuid: '00000000-0000-4000-8000-${(++_attempts).toString().padLeft(12, '0')}',
      mode: 'quick10',
      chapterId: 21,
      startedAt: at,
      finishedAt: at,
      answers: [
        for (var i = 0; i < count; i++)
          AttemptAnswer(
            questionId: 2101 + i,
            chapterId: 21,
            version: 1,
            response: 'A',
            timeMs: 30000,
            correctLocally: i < right,
          ),
      ],
    );

Future<void> openStats(WidgetTester tester) async {
  await tapOn(tester, find.text('Progress').last);
  await tapOn(tester, find.text('See your statistics'));
}

void main() {
  testWidgets('no answers yet: a friendly message, no charts', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    await pumpGuul(tester, server: server);
    await openStats(tester);
    expect(find.text('Statistics'), findsOneWidget);
    expect(find.text('Answer some questions and your charts will appear here.'), findsOneWidget);
    expect(find.text('Last 14 days'), findsNothing);
  });

  testWidgets('summary numbers, charts and the tapped bar', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    await pumpGuul(
      tester,
      server: server,
      before: (repo) async {
        await repo.syncAll();
        await repo.chapter(21);
        await answer(repo, testNow, 3, right: 2);
      },
    );
    await openStats(tester);

    expect(find.text('3'), findsWidgets);
    expect(find.text('67%'), findsOneWidget);
    expect(find.text('1 day'), findsOneWidget);
    expect(find.text('1 min'), findsOneWidget, reason: '3 × 30 s');
    expect(find.text('30 s'), findsOneWidget);
    expect(find.text('Answer 17 more questions to see your likely exam score.'), findsOneWidget);

    // The readout starts on today; tapping the first bar shows 13 days ago.
    expect(inKey('stats-days', find.text('Nov 22: 2 right, 1 wrong')), findsOneWidget);
    final chart = find.byType(StackedBarChart);
    await tester.ensureVisible(chart);
    await tester.pump();
    // The bars sit under the readout line; tap the left end of the bar area.
    await tester.tapAt(tester.getBottomLeft(chart) + const Offset(4, -60));
    await settle(tester);
    expect(inKey('stats-days', find.text('Nov 9: 0 right, 0 wrong')), findsOneWidget);

    // Screen readers get every day, not only the tapped one.
    final semantics = tester.ensureSemantics();
    expect(
      find.bySemanticsLabel(RegExp('Nov 9: 0 right, 0 wrong.*Nov 22: 2 right, 1 wrong')),
      findsOneWidget,
    );
    semantics.dispose();

    expect(find.text('Finish an exam to see your scores here.'), findsOneWidget);
    await tester.ensureVisible(find.text('Accuracy by subject'));
    expect(inKey('stats-subjects', find.text('Physics')), findsOneWidget);
    expect(inKey('stats-subjects', find.text('67% · 3 answers')), findsOneWidget);
    await tester.ensureVisible(find.text('When you study'));
    expect(inKey('stats-parts', find.text('Morning')), findsOneWidget);
    expect(inKey('stats-parts', find.text('No answers')), findsNWidgets(2));
  });

  testWidgets('a long history: readiness, exams, subjects, mastery and the heatmap', (
    tester,
  ) async {
    final server = FakeServer();
    onboardedProfile(server);
    addEnglishChapter(server);
    await pumpGuul(
      tester,
      server: server,
      before: (repo) async {
        await repo.syncAll();
        await repo.chapter(21);
        await repo.chapter(31);
        await seedStudyHistory(repo);
      },
    );
    await openStats(tester);
    expect(find.textContaining('Likely score:'), findsOneWidget);
    expect(inKey('stats-exams', find.text('Nov 20 · Physics mock exam: 80%')), findsOneWidget);
    await tester.ensureVisible(find.text('Accuracy by subject'));
    expect(inKey('stats-subjects', find.text('English')), findsOneWidget);
    expect(inKey('stats-subjects', find.text('Physics')), findsOneWidget);
    await tester.ensureVisible(find.text('Chapters by strength'));
    expect(inKey('stats-mastery', find.textContaining('Not started:')), findsOneWidget);
    await tester.ensureVisible(find.text('Activity'));
    expect(inKey('stats-heatmap', find.text('Nov 22: 6 questions')), findsOneWidget);
  });

  testWidgets('Home: review, readiness, rank and card of the day', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    server.profile['nickname'] = 'Ayan';
    server.leaderboard = {
      'week_start': '2026-11-16',
      'top': [
        for (final (rank, nick, xp) in [(1, 'Hodan', 900), (2, 'Abdi', 700), (3, 'Ayan', 650)])
          {
            'rank': rank,
            'nickname': nick,
            'avatar_style': null,
            'avatar_seed': null,
            'xp': xp,
            'is_me': nick == 'Ayan',
          },
      ],
      'me': {'has_nickname': true, 'xp': 650, 'rank': 3},
    };
    await pumpGuul(
      tester,
      server: server,
      before: (repo) async {
        await repo.syncAll();
        await repo.chapter(21);
        // 30 answers a day ago (some wrong, so a review is due today).
        await answer(repo, testNow.subtract(const Duration(days: 1)), 30, right: 21);
      },
    );

    expect(inKey('home-review', find.text('9 questions to review today')), findsOneWidget);
    expect(inKey('home-readiness', find.text('Exam readiness')), findsOneWidget);
    expect(inKey('home-readiness', find.textContaining('Likely score:')), findsOneWidget);

    await tester.ensureVisible(find.byKey(const ValueKey('home-rank')));
    expect(inKey('home-rank', find.text('Place 3 · 650 XP this week')), findsOneWidget);
    expect(inKey('home-rank', find.text('51 XP to move up one place')), findsOneWidget);

    await tester.ensureVisible(find.byKey(const ValueKey('home-card-of-day')));
    expect(inKey('home-card-of-day', find.text('Projectile')), findsOneWidget);
    await tapOn(tester, inKey('home-card-of-day', find.text('Tap to see the answer')));
    expect(
      inKey('home-card-of-day', find.text('An object moving only under gravity.')),
      findsOneWidget,
    );

    await tapOn(tester, find.text('More cards'));
    expect(find.text('Projectile'), findsOneWidget, reason: 'the chapter flashcards');
  });

  testWidgets('Home: the rank card opens the leaderboard; the review card starts a review', (
    tester,
  ) async {
    final server = FakeServer();
    onboardedProfile(server);
    server.profile['nickname'] = 'Ayan';
    server.leaderboard = {
      'week_start': '2026-11-16',
      'top': <Object?>[],
      'me': {'has_nickname': true, 'xp': 0, 'rank': null},
    };
    await pumpGuul(
      tester,
      server: server,
      before: (repo) async {
        await repo.syncAll();
        await repo.chapter(21);
        await answer(repo, testNow.subtract(const Duration(days: 1)), 1, right: 0);
      },
    );
    expect(inKey('home-rank', find.text('Answer questions to get on the board.')), findsOneWidget);
    expect(
      inKey('home-readiness', find.text('Answer 19 more questions to see your likely exam score.')),
      findsOneWidget,
    );

    await tapOn(tester, inKey('home-rank', find.text('Your rank this week')));
    expect(find.text('Weekly leaderboard'), findsWidgets);

    await tapOn(tester, find.text('Home'));
    await tapOn(tester, find.text('Start review'));
    expect(find.text('Question 1 of 1'), findsOneWidget);
  });

  testWidgets('Home: no rank card offline, without a nickname or with the leaderboard off', (
    tester,
  ) async {
    final server = FakeServer();
    onboardedProfile(server);
    server.features['leaderboard'] = false;
    server.profile['nickname'] = 'Ayan';
    await pumpGuul(tester, server: server);
    expect(find.byKey(const ValueKey('home-rank')), findsNothing);
    expect(find.byKey(const ValueKey('home-readiness')), findsNothing, reason: 'no answers yet');
    expect(
      find.byKey(const ValueKey('home-card-of-day')),
      findsNothing,
      reason: 'no chapter saved',
    );
    expect(find.text('Statistics'), findsNothing, reason: 'the tile comes with the first answer');
  });

  testWidgets('Home: without a nickname no leaderboard is asked for', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    await pumpGuul(tester, server: server);
    expect(find.byKey(const ValueKey('home-rank')), findsNothing);
    expect(server.calls.where((c) => c.endsWith('/leaderboard')), isEmpty);
  });

  testWidgets('the Statistics tile on Home opens the page', (tester) async {
    final server = FakeServer();
    onboardedProfile(server);
    await pumpGuul(
      tester,
      server: server,
      before: (repo) async {
        await repo.syncAll();
        await repo.chapter(21);
        await answer(repo, testNow, 2, right: 2);
      },
    );
    await tapOn(tester, find.text('Charts of your progress'));
    expect(find.text('Last 14 days'), findsOneWidget);
  });
}
