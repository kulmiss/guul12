@Tags(['golden'])
library;

import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/app/practice_set.dart';
import 'package:guul12/data/repository.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';
import 'screens_golden_test.dart' show loadFonts, prepared, richServer;

/// Pictures of the app for the landing page, with real Grade 12 lessons and questions
/// (Physics, unit 2, our own writing, exported from the content files). Drawn at 2× so they
/// stay sharp on phones. `tool/landing_images.sh` copies them to the website as WebP.

final _content = jsonDecode(
  File('test/golden/fixtures/landing_content.json').readAsStringSync(),
) as Map<String, Object?>;

Map<String, Object?> _chapter(int id) =>
    ((_content['chapters']! as Map<String, Object?>)['$id']! as Map<String, Object?>);

List<Map<String, Object?>> _questions(int id) =>
    (_chapter(id)['questions']! as List).cast<Map<String, Object?>>();

String _key(Map<String, Object?> q) =>
    (q['answer_key']! as Map<String, Object?>)['correct']! as String;

String _option(Map<String, Object?> q, String key) =>
    (q['options']! as List).cast<Map<String, Object?>>().firstWhere((o) => o['key'] == key)['body']!
        as String;

/// Plain-text options can be tapped by their text (maths options are drawn as formulas).
bool _plain(Map<String, Object?> q) => (q['options']! as List).cast<Map<String, Object?>>().every(
  (o) => !(o['body']! as String).contains(r'$'),
);

/// The first option that is not the answer.
String _wrong(Map<String, Object?> q) => _option(
  q,
  (q['options']! as List)
      .cast<Map<String, Object?>>()
      .map((o) => o['key']! as String)
      .firstWhere((k) => k != _key(q)),
);

/// Mock exam 1: two plain-text questions from each chapter, as exam questions 901–904.
final _mock = [
  for (final (i, q) in [
    ..._questions(21).where(_plain).take(2),
    ..._questions(22).where(_plain).take(2),
  ].indexed)
    {...q, 'id': 901 + i},
];

FakeServer _server() {
  final s = richServer()..unitTitle = _content['unit_title']! as String;
  for (final id in [21, 22]) {
    final body = Map<String, Object?>.of(_chapter(id));
    s.chapterTitles[id] = body.remove('title')! as String;
    s.chapterBodies[id] = body;
  }
  s.mockQuestions = _mock;
  // A Premium test student, like the store screenshots: no ads, no locks, no payment hints.
  s.premium = true;
  s.profile['premium'] = {'active': true, 'until': '2027-07-31T00:00:00Z'};
  return s;
}

/// Three days of practice ending today, so Home shows a streak, filled days and progress.
Future<void> _busyStudent(Repository repo) async {
  final qs = _questions(21);
  for (final (i, daysAgo) in [2, 1, 0].indexed) {
    final at = testNow.subtract(Duration(days: daysAgo));
    await repo.saveAttempt(
      clientUuid: '00000000-0000-4000-8000-00000000ad0$i',
      mode: 'quick10',
      chapterId: 21,
      startedAt: at,
      finishedAt: at,
      answers: [
        for (final (n, q) in qs.indexed)
          AttemptAnswer(
            questionId: q['id']! as int,
            chapterId: 21,
            version: 1,
            response: n < 7 ? _key(q) : _wrong(q),
            timeMs: 6000,
            correctLocally: n < 7,
          ),
      ],
    );
  }
}

typedef Scene = Future<void> Function(WidgetTester tester);

Future<void> _openMock(WidgetTester tester) async {
  await tapOn(tester, find.text('Exams').last);
  await tapOn(tester, find.widgetWithText(ChoiceChip, 'Physics'));
  await tapOn(tester, find.text('Physics mock exam 1'));
  await tapOn(tester, find.text('Start exam'));
}

void main() {
  setUpAll(loadFonts);

  final scenes = <String, (bool, Scene)>{
    'home': (false, (tester) async {}),
    'chapter': (false, (tester) async => tapOn(tester, find.text('1. Projectile motion'))),
    'chapter_dark': (true, (tester) async => tapOn(tester, find.text('1. Projectile motion'))),
    'question': (
      false,
      (tester) async {
        await tapOn(tester, find.text('Quick 10'));
        // Answer whichever question comes first, wrongly, to show the explanation. The question
        // is found by its options (stems with maths are drawn as formulas, not plain text).
        final q = [..._questions(21), ..._questions(22)]
            .where(_plain)
            .firstWhere(
              (q) => (q['options']! as List).cast<Map<String, Object?>>().every(
                (o) => find.text(o['body']! as String).evaluate().isNotEmpty,
              ),
            );
        await tapOn(tester, find.text(_wrong(q)));
        await tapOn(tester, find.byKey(const ValueKey('check')));
      },
    ),
    'exam': (
      false,
      (tester) async {
        await _openMock(tester);
        await tapOn(tester, find.text(_option(_mock[0], _key(_mock[0]))));
        await tapOn(tester, find.byTooltip('Flag for review'));
      },
    ),
    'result': (
      false,
      (tester) async {
        await _openMock(tester);
        for (final (i, q) in _mock.indexed) {
          await tapOn(tester, find.text(i == 1 ? _wrong(q) : _option(q, _key(q))));
          if (i < _mock.length - 1) await tapOn(tester, find.text('Next'));
        }
        await tapOn(tester, find.text('Submit exam'));
        await tapOn(
          tester,
          find.descendant(of: find.byType(AlertDialog), matching: find.text('Submit exam')),
        );
      },
    ),
    'progress': (false, (tester) async => tapOn(tester, find.text('Progress').last)),
    'plan': (false, (tester) async => tapOn(tester, find.text("Today's plan"))),
    'leaderboard': (
      false,
      (tester) async {
        await tapOn(tester, find.text('Progress').last);
        await tapOn(tester, find.text('See the weekly leaderboard'));
      },
    ),
  };

  for (final MapEntry(key: name, value: (dark, scene)) in scenes.entries) {
    final file = 'goldens/landing/$name.png';
    testWidgets(file, (tester) async {
      late Repository repository;
      await pumpGuul(
        tester,
        server: _server(),
        pixelRatio: 2,
        // The app shuffles practice questions with a new random order each time; a fixed order
        // keeps the picture the same on every run.
        overrides: [
          practiceSetProvider.overrideWith(
            (ref, request) =>
                buildPracticeSet(repository, request, random: Random(7), now: testNow),
          ),
        ],
        before: (repo) async {
          repository = repo;
          await prepared(repo);
          await repo.chapter(22);
          await repo.db.writeString('theme_mode', dark ? 'dark' : 'light');
          await _busyStudent(repo);
        },
      );
      await scene(tester);
      await expectLater(find.byType(MaterialApp), matchesGoldenFile(file));
    });
  }
}
