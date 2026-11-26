import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/models.dart';
import 'package:guul12/ui/widgets/math_text.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/fake_server.dart';
import '../support/review_fixture.dart';

FakeServer onboardedServer() {
  final server = FakeServer();
  onboardedProfile(server);
  return server;
}

Future<void> openUnit(WidgetTester tester) async {
  await tapOn(tester, find.text('Study'));
  await tapOn(tester, find.text('Physics'));
  await tapOn(tester, find.text('Unit 2: Two-dimensional motion'));
}

void main() {
  test('the review reads every field, with safe defaults', () {
    final review = TextbookReview.fromJson(sampleReview());
    expect(review.pages, '64–66');
    expect(review.items.map((i) => i.ref), ['1', '2', '3']);
    expect(review.items[0].figure, 'Figure 2.9');
    expect(review.items[0].hasSolution, isTrue);
    expect(review.items[1].hasSolution, isFalse, reason: 'only its part has a solution');
    expect(review.items[1].table, {
      't': ['0', '1', '2'],
      'v': ['0', '10', '20'],
    });
    expect(review.items[1].parts.single.answer, r'$10$ m/s²');
    expect(review.items[2].bookError, isTrue);

    // A unit from a catalog saved before the field existed has no review.
    final unit = Unit.fromJson({
      'id': 1,
      'subject_id': 1,
      'grade': 12,
      'stream': null,
      'number': 1,
      'title': 'U',
      'sort': 1,
    });
    expect(unit.reviewQuestionCount, 0);
  });

  testWidgets('no review exercise: no row in the unit', (tester) async {
    await pumpGuul(tester, server: onboardedServer());
    await openUnit(tester);
    expect(find.text('Review exercise (textbook)'), findsNothing);
  });

  testWidgets('the review opens from the unit; solutions stay hidden until asked', (tester) async {
    final server = onboardedServer()..reviews[11] = sampleReview();
    await pumpGuul(tester, server: server);
    await openUnit(tester);

    expect(find.text('3 questions, solved step by step'), findsOneWidget);
    await tapOn(tester, find.text('Review exercise (textbook)'));

    expect(find.text('Unit 2 review exercise'), findsOneWidget);
    expect(find.text('Physics Student Textbook, Grade 12 · pages 64–66'), findsOneWidget);
    expect(find.text('Formulas used'), findsOneWidget);
    expect(find.text('See Figure 2.9 in your textbook.'), findsOneWidget);
    expect(find.textContaining('Time:'), findsNothing, reason: 'solutions start hidden');
    expect(find.text('Answer'), findsNothing);

    await tapOn(tester, find.text('Show solution').first);
    expect(find.textContaining('Time:'), findsOneWidget);
    expect(find.text('Answer'), findsOneWidget);
    expect(find.text('Hide solution'), findsOneWidget);

    // The table and the part of question 2 (scroll the list, not the table).
    final list = find
        .descendant(of: find.byType(ListView), matching: find.byType(Scrollable))
        .first;
    await tester.scrollUntilVisible(find.text('Find the acceleration.'), 200, scrollable: list);
    expect(find.text('v'), findsOneWidget);
    expect(find.text('20'), findsOneWidget);
    expect(find.text('The speed grows by 10 each second.'), findsNothing);
    await tapOn(tester, find.text('Show solution').last);
    expect(find.text('The speed grows by 10 each second.'), findsOneWidget);

    // The question the book printed wrongly says so and has no solution button.
    await tester.scrollUntilVisible(
      find.text('A question printed wrongly.'),
      200,
      scrollable: list,
    );
    expect(find.text('Error in the book'), findsOneWidget);
    expect(find.text('Cannot be solved with the numbers in the book.'), findsOneWidget);
    expect(
      find.descendant(
        of: find.byKey(const ValueKey('review-3')),
        matching: find.text('Show solution'),
      ),
      findsNothing,
    );
  });

  testWidgets('a review opened once still opens without internet', (tester) async {
    final server = onboardedServer()..reviews[11] = sampleReview();
    await pumpGuul(tester, server: server);
    await openUnit(tester);
    await tapOn(tester, find.text('Review exercise (textbook)'));
    expect(find.text('Unit 2 review exercise'), findsOneWidget);
    await tester.pageBack();
    await settle(tester);

    server.online = false;
    await tapOn(tester, find.text('Review exercise (textbook)'));
    expect(find.textContaining('A ball is thrown horizontally'), findsOneWidget);
  });

  testWidgets('a review that is gone from the server says so', (tester) async {
    final server = onboardedServer()..reviews[11] = sampleReview();
    await pumpGuul(tester, server: server);
    await openUnit(tester);
    server.reviews.clear();
    await tapOn(tester, find.text('Review exercise (textbook)'));
    expect(find.text('This review exercise is not available yet.'), findsOneWidget);
  });

  testWidgets('long formulas wrap on a narrow phone instead of running off the screen', (
    tester,
  ) async {
    const long =
        r'$\dfrac{f(3 + \Delta x) - f(3)}{\Delta x} = \dfrac{3 - (3 + \Delta x)}{3(3 + \Delta x)\Delta x} = -\dfrac{1}{3(3 + \Delta x)} = -\dfrac{1}{9 + 3\Delta x}$';
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: Center(child: SizedBox(width: 280, child: MathText(long, breakLongMath: true))),
        ),
      ),
    );
    expect(tester.takeException(), isNull);
    expect(tester.getSize(find.byType(MathText)).width, lessThanOrEqualTo(280));

    // A big sum (\displaystyle) cannot be split by the maths library; it scrolls instead of
    // crashing the layout.
    const sum =
        r'$\displaystyle\sum_{k=1}^{11}3^k = \frac{3(3^{11} - 1)}{3 - 1} = \frac{3(177147 - 1)}{2} = 265\,719$';
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: Center(child: SizedBox(width: 280, child: MathText(sum, breakLongMath: true))),
        ),
      ),
    );
    expect(tester.takeException(), isNull);
  });
}
