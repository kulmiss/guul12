import 'package:flutter_math_fork/flutter_math.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/ui/widgets/math_text.dart';
import 'package:material_ui/material_ui.dart';

void main() {
  group('parseMath', () {
    test('plain text', () {
      expect(parseMath('What is 2 + 2?'), [const TextSegment('What is 2 + 2?')]);
    });

    test('inline and block maths', () {
      expect(parseMath(r'Solve $3x - 5 = 10$ now. $$x = \frac{15}{3}$$ Done'), [
        const TextSegment('Solve '),
        const InlineMath('3x - 5 = 10'),
        const TextSegment(' now. '),
        const BlockMath(r'x = \frac{15}{3}'),
        const TextSegment(' Done'),
      ]);
    });

    test('escaped dollar is literal', () {
      expect(parseMath(r'It costs \$5'), [const TextSegment(r'It costs $5')]);
    });

    test('unclosed dollar stays text', () {
      expect(parseMath(r'Price $5 only'), [const TextSegment(r'Price $5 only')]);
    });

    test('empty maths stays text', () {
      expect(parseMath(r'a $$ $$ b'), [const TextSegment(r'a $$ $$ b')]);
    });

    test('escaped dollar inside maths does not close it', () {
      expect(parseMath(r'$a \$ b$'), [const InlineMath(r'a \$ b')]);
    });

    test('maths at start and end', () {
      expect(parseMath(r'$x$'), [const InlineMath('x')]);
    });
  });

  testWidgets('renders formulas with flutter_math', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: MathText(r'Fall time $t = \sqrt{\frac{2h}{g}}$ seconds')),
      ),
    );
    expect(find.byType(Math), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('bad LaTeX falls back to the raw text instead of crashing', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(home: Scaffold(body: MathText(r'Broken $\frac{1$ here'))),
    );
    expect(tester.takeException(), isNull);
    expect(find.textContaining(r'\frac{1', findRichText: true), findsWidgets);
  });

  testWidgets('wide block formula does not overflow a 360 px screen', (tester) async {
    tester.view.physicalSize = const Size(360, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: MathText(
            r'$$a_1 + a_2 + a_3 + a_4 + a_5 + a_6 + a_7 + a_8 + a_9 + a_{10} + a_{11} + a_{12} + a_{13} = S$$',
          ),
        ),
      ),
    );
    expect(tester.takeException(), isNull);
  });
}
