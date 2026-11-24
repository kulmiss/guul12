import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/app/providers.dart';
import 'package:guul12/data/models.dart';
import 'package:guul12/domain/session.dart';
import 'package:guul12/l10n/generated/app_localizations.dart';
import 'package:guul12/ui/practice/practice_runner.dart';
import 'package:guul12/ui/practice/question_input.dart';
import 'package:guul12/ui/theme.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';

QuestionOption opt(String key, String body, {String? side}) =>
    QuestionOption(key: key, side: side, body: body, mediaUrl: null);

Question question(
  int id,
  QuestionType type,
  Map<String, Object?> key, {
  List<QuestionOption> options = const [],
  int? passageId,
  String stem = 'Stem',
}) => Question(
  id: id,
  chapterId: 1,
  passageId: passageId,
  type: type,
  stem: stem,
  mediaUrl: null,
  difficulty: 2,
  isBeta: id == 99,
  answerKey: key,
  explanation: 'Because.',
  version: 1,
  options: options,
);

/// Plays [questions] alone (no repository needed) and returns the finished session.
Future<PracticeSession? Function()> pumpRunner(
  WidgetTester tester,
  List<Question> questions, {
  Map<int, Passage> passages = const {},
  int? secondsPerQuestion,
  RecordingSpeech? speech,
}) async {
  tester.view.physicalSize = phoneSize;
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  PracticeSession? finished;
  await tester.pumpWidget(
    ProviderScope(
      retry: (_, _) => null,
      overrides: [
        clockProvider.overrideWithValue(() => testNow),
        configProvider.overrideWith((ref) => AppConfig.fallback),
        speechProvider.overrideWithValue(speech ?? RecordingSpeech()),
      ],
      child: MaterialApp(
        theme: sunriseTheme(Brightness.light),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: AppLocalizations.supportedLocales,
        home: Scaffold(
          body: PracticeRunner(
            questions: questions,
            passages: passages,
            secondsPerQuestion: secondsPerQuestion,
            allowBookmarks: false,
            onFinished: (s) => finished = s,
          ),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
  return () => finished;
}

Future<void> check(WidgetTester tester) => tapOn(tester, find.byKey(const ValueKey('check')));
Future<void> next(WidgetTester tester) => tapOn(tester, find.byKey(const ValueKey('next')));

void main() {
  testWidgets('multiple answers: all and only the right ones', (tester) async {
    final q = question(
      1,
      QuestionType.multipleAnswers,
      {
        'correct': ['A', 'C'],
      },
      options: [opt('A', 'two'), opt('B', 'three'), opt('C', 'five'), opt('D', 'nine')],
    );
    final result = await pumpRunner(tester, [q, q]);
    expect(find.text('Choose all that apply'), findsOneWidget);

    await tapOn(tester, find.text('five'));
    await tapOn(tester, find.text('two'));
    await tapOn(tester, find.text('nine'));
    await tapOn(tester, find.text('nine')); // untick
    await check(tester);
    expect(find.text('Correct!'), findsOneWidget);
    await next(tester);

    await tapOn(tester, find.text('two'));
    await check(tester);
    expect(find.text('Not quite'), findsOneWidget);
    expect(find.text('Correct answer: A. two · C. five'), findsOneWidget);
    await next(tester);

    final s = result()!;
    expect(s.answerFor(0)!.response, ['A', 'C']);
    expect(s.answerFor(1)!.response, ['A']);
    expect(s.correctCount, 1);
  });

  testWidgets('numeric: comma decimals and fractions are accepted; unit shown', (tester) async {
    final q = question(2, QuestionType.numeric, {'value': 2.5, 'tolerance': 0, 'unit': 'm/s'});
    final result = await pumpRunner(tester, [q, q]);
    expect(find.text('Unit: m/s'), findsOneWidget);

    await tester.enterText(find.byType(TextField), '2,5');
    await check(tester);
    expect(find.text('Correct!'), findsOneWidget);
    await next(tester);

    await tester.enterText(find.byType(TextField), '5/2');
    await check(tester);
    expect(find.text('Correct!'), findsOneWidget);
    await next(tester);
    expect(result()!.correctCount, 2);
  });

  testWidgets('numeric field drops letters; spaces only means no answer', (tester) async {
    final q = question(3, QuestionType.numeric, {'value': 7});
    await pumpRunner(tester, [q]);
    await tester.enterText(find.byType(TextField), 'abc7');
    expect(tester.widget<TextField>(find.byType(TextField)).controller!.text, '7');
    await tester.enterText(find.byType(TextField), '   ');
    await check(tester);
    expect(find.text('Choose or type an answer first.'), findsOneWidget);
  });

  testWidgets('fill in: case and extra spaces do not matter', (tester) async {
    final q = question(4, QuestionType.fillIn, {
      'accepted': ['photosynthesis'],
    });
    final result = await pumpRunner(tester, [q]);
    await tester.enterText(find.byType(TextField), '  Photosynthesis ');
    await check(tester);
    expect(find.text('Correct!'), findsOneWidget);
    await next(tester);
    expect(result()!.answerFor(0)!.response, 'Photosynthesis');
  });

  testWidgets('matching: tap left then right; re-pairing moves the match', (tester) async {
    final q = question(
      5,
      QuestionType.matching,
      {
        'pairs': {'L1': 'R2', 'L2': 'R1'},
      },
      options: [
        opt('L1', 'Velocity', side: 'left'),
        opt('L2', 'Force', side: 'left'),
        opt('R1', 'newton', side: 'right'),
        opt('R2', 'm/s', side: 'right'),
      ],
    );
    final result = await pumpRunner(tester, [q]);

    // Tapping a right item first does nothing.
    await tapOn(tester, find.text('newton'));
    expect(find.text('0 pairs made'), findsOneWidget);

    await tapOn(tester, find.text('Velocity'));
    await tapOn(tester, find.text('newton')); // wrong on purpose
    await tapOn(tester, find.text('Force'));
    await tapOn(tester, find.text('newton')); // takes "newton" away from Velocity
    expect(find.text('1 pair made'), findsOneWidget);
    await check(tester);
    expect(find.text('Choose or type an answer first.'), findsOneWidget);

    await tapOn(tester, find.text('Velocity'));
    await tapOn(tester, find.text('m/s'));
    expect(find.text('2 pairs made'), findsOneWidget);
    await check(tester);
    expect(find.text('Correct!'), findsOneWidget);
    await next(tester);
    expect(result()!.answerFor(0)!.response, {'L1': 'R2', 'L2': 'R1'});
  });

  testWidgets('ordering: never starts solved; up/down buttons reorder', (tester) async {
    final q = question(
      6,
      QuestionType.ordering,
      {
        'order': ['A', 'B', 'C'],
      },
      options: [opt('A', 'first'), opt('B', 'second'), opt('C', 'third')],
    );
    expect(startingOrder(q).map((o) => o.key).toList(), isNot(['A', 'B', 'C']));
    final result = await pumpRunner(tester, [q]);

    // Put the items in order using only the buttons (works without dragging).
    for (var round = 0; round < 3; round++) {
      final order = _visibleOrder(tester, ['first', 'second', 'third']);
      for (var i = 1; i < order.length; i++) {
        final ranks = {'first': 0, 'second': 1, 'third': 2};
        if (ranks[order[i]]! < ranks[order[i - 1]]!) {
          await tapOn(tester, find.widgetWithIcon(IconButton, Icons.arrow_upward).at(i));
          break;
        }
      }
    }
    expect(_visibleOrder(tester, ['first', 'second', 'third']), ['first', 'second', 'third']);
    await check(tester);
    expect(find.text('Correct!'), findsOneWidget);
    await next(tester);
    expect(result()!.answerFor(0)!.response, ['A', 'B', 'C']);
  });

  test('ordering start is never already solved, for any question id', () {
    for (var id = 1; id <= 300; id++) {
      final two = question(
        id,
        QuestionType.ordering,
        {
          'order': ['A', 'B'],
        },
        options: [opt('A', 'a'), opt('B', 'b')],
      );
      expect(startingOrder(two).map((o) => o.key).toList(), ['B', 'A'], reason: 'id $id');
      final one = question(
        id,
        QuestionType.ordering,
        {
          'order': ['A'],
        },
        options: [opt('A', 'a')],
      );
      expect(startingOrder(one).single.key, 'A');
    }
  });

  testWidgets('passage question shows the passage; beta label shows', (tester) async {
    final q = question(
      99,
      QuestionType.passageChoice,
      {'correct': 'A'},
      passageId: 7,
      options: [opt('A', 'yes'), opt('B', 'no')],
    );
    await pumpRunner(
      tester,
      [q],
      passages: {7: const Passage(id: 7, title: 'The river', body: 'The Wabi Shebelle flows.')},
    );
    expect(find.text('The river'), findsOneWidget);
    expect(find.text('The Wabi Shebelle flows.'), findsOneWidget);
    expect(find.text('Beta'), findsOneWidget);
  });

  testWidgets('timed mode: when time runs out the question counts as unanswered', (tester) async {
    final q = question(8, QuestionType.trueFalse, {'correct': 'TRUE'});
    final result = await pumpRunner(tester, [q], secondsPerQuestion: 60);
    expect(find.text('60s'), findsOneWidget);
    await tester.pump(const Duration(seconds: 50));
    expect(find.text('10s'), findsOneWidget);
    await tester.pump(const Duration(seconds: 10));
    await tester.pumpAndSettle();
    expect(find.text('Time is up'), findsOneWidget);
    expect(find.text('Correct answer: True'), findsOneWidget);
    // Answering after time is up is not possible.
    await tester.tap(find.text('True'), warnIfMissed: false);
    await next(tester);
    expect(result()!.answerFor(0)!.response, isNull);
    expect(result()!.correctCount, 0);
  });

  testWidgets('an empty set shows a message instead of crashing', (tester) async {
    await pumpRunner(tester, const []);
    expect(find.text('No questions here yet. Study a chapter first.'), findsOneWidget);
  });

  testWidgets('read aloud speaks the question without LaTeX symbols', (tester) async {
    final speech = RecordingSpeech();
    final q = question(
      9,
      QuestionType.singleChoice,
      {'correct': 'A'},
      stem: r'What is $\frac{1}{2}$ of 8?',
      options: [opt('A', '4'), opt('B', '2')],
    );
    await pumpRunner(tester, [q], speech: speech);
    await tapOn(tester, find.byTooltip('Read aloud'));
    expect(speech.spoken.single, r'What is $\frac{1}{2}$ of 8?. A. 4. B. 2');
  });
}

List<String> _visibleOrder(WidgetTester tester, List<String> labels) {
  final positions = {for (final l in labels) l: tester.getTopLeft(find.text(l)).dy};
  return labels.toList()..sort((a, b) => positions[a]!.compareTo(positions[b]!));
}
