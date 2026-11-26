import 'package:flutter_math_fork/flutter_math.dart';
import 'package:material_ui/material_ui.dart';

/// A piece of question text: plain text, inline maths (`$…$`) or block maths (`$$…$$`).
sealed class MathSegment {
  const MathSegment(this.value);
  final String value;
}

class TextSegment extends MathSegment {
  const TextSegment(super.value);

  @override
  bool operator ==(Object other) => other is TextSegment && other.value == value;

  @override
  int get hashCode => Object.hash('t', value);

  @override
  String toString() => 'Text($value)';
}

class InlineMath extends MathSegment {
  const InlineMath(super.value);

  @override
  bool operator ==(Object other) => other is InlineMath && other.value == value;

  @override
  int get hashCode => Object.hash('i', value);

  @override
  String toString() => 'Inline($value)';
}

class BlockMath extends MathSegment {
  const BlockMath(super.value);

  @override
  bool operator ==(Object other) => other is BlockMath && other.value == value;

  @override
  int get hashCode => Object.hash('b', value);

  @override
  String toString() => 'Block($value)';
}

/// Splits text into plain and LaTeX parts. `\$` is a literal dollar sign.
/// An opening `$` with no closing `$` is kept as plain text.
List<MathSegment> parseMath(String input) {
  final out = <MathSegment>[];
  final buffer = StringBuffer();
  var i = 0;

  void flushText() {
    if (buffer.isNotEmpty) {
      out.add(TextSegment(buffer.toString()));
      buffer.clear();
    }
  }

  while (i < input.length) {
    final ch = input[i];

    if (ch == r'\' && i + 1 < input.length && input[i + 1] == r'$') {
      buffer.write(r'$');
      i += 2;
      continue;
    }

    if (ch == r'$') {
      final isBlock = i + 1 < input.length && input[i + 1] == r'$';
      final delimiter = isBlock ? r'$$' : r'$';
      final start = i + delimiter.length;
      final end = _findClosing(input, start, delimiter);
      if (end == -1) {
        buffer.write(input.substring(i));
        break;
      }
      final latex = input.substring(start, end).trim();
      if (latex.isEmpty) {
        buffer.write(input.substring(i, end + delimiter.length));
      } else {
        flushText();
        out.add(isBlock ? BlockMath(latex) : InlineMath(latex));
      }
      i = end + delimiter.length;
      continue;
    }

    buffer.write(ch);
    i++;
  }

  flushText();
  return out;
}

int _findClosing(String input, int from, String delimiter) {
  var i = from;
  while (i < input.length) {
    if (input[i] == r'\') {
      i += 2;
      continue;
    }
    if (input.startsWith(delimiter, i)) {
      // A single $ must not be the first half of $$.
      if (delimiter == r'$' && i + 1 < input.length && input[i + 1] == r'$') return -1;
      return i;
    }
    i++;
  }
  return -1;
}

/// Shows text with LaTeX formulas. Long formulas scroll sideways instead of overflowing small screens.
class MathText extends StatelessWidget {
  const MathText(
    this.text, {
    super.key,
    this.style,
    this.textAlign = TextAlign.start,
    this.breakLongMath = false,
  });

  final String text;
  final TextStyle? style;
  final TextAlign textAlign;

  /// Lets a long inline formula wrap onto the next line at `=`, `<`, `+`, `-` and similar signs,
  /// and lets a piece that is still too wide scroll sideways (for long worked solutions on a
  /// narrow phone). Off by default.
  final bool breakLongMath;

  @override
  Widget build(BuildContext context) {
    final effective = DefaultTextStyle.of(context).style.merge(style);
    final segments = parseMath(text);

    if (segments.every((s) => s is TextSegment)) {
      return Text(text.replaceAll(r'\$', r'$'), style: effective, textAlign: textAlign);
    }

    final blocks = <Widget>[];
    var inline = <InlineSpan>[];

    void flushInline() {
      if (inline.isEmpty) return;
      blocks.add(
        Text.rich(
          TextSpan(children: inline),
          style: effective,
          textAlign: textAlign,
        ),
      );
      inline = <InlineSpan>[];
    }

    for (final s in segments) {
      switch (s) {
        case TextSegment(:final value):
          inline.add(TextSpan(text: value));
        case InlineMath(:final value):
          final formula = _formula(value, effective, MathStyle.text);
          if (!breakLongMath) {
            inline.add(WidgetSpan(alignment: PlaceholderAlignment.middle, child: formula));
            continue;
          }
          // Each piece that still cannot wrap (one long fraction, a long sum) scrolls sideways
          // instead of running off the screen. flutter_math_fork 0.7.4 cannot lay out the pieces
          // of a formula with a style switch such as \displaystyle, so those stay whole.
          final pieces = value.contains(_styleSwitch) ? [formula] : formula.texBreak().parts;
          for (final piece in pieces) {
            inline.add(
              WidgetSpan(
                alignment: PlaceholderAlignment.middle,
                child: SingleChildScrollView(scrollDirection: Axis.horizontal, child: piece),
              ),
            );
          }
        case BlockMath(:final value):
          flushInline();
          blocks.add(
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: _formula(value, effective, MathStyle.display),
              ),
            ),
          );
      }
    }
    flushInline();

    if (blocks.length == 1) return blocks.single;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: blocks,
    );
  }

  static final _styleSwitch = RegExp(r'\\(display|text|script|scriptscript)style');

  Math _formula(String latex, TextStyle style, MathStyle mathStyle) => Math.tex(
    latex,
    mathStyle: mathStyle,
    textStyle: style,
    onErrorFallback: (error) => Text(latex, style: style),
  );
}
