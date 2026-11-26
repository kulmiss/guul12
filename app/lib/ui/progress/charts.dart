import 'dart:math' as math;

import 'package:material_ui/material_ui.dart';

/// Small charts drawn by the app itself (no chart package). Every chart reads its whole data out
/// to screen readers (the [Semantics] label), so nothing is shown by colour alone.

/// The line under a chart that shows the numbers of the tapped bar, point or square.
class _Readout extends StatelessWidget {
  const _Readout({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Text(
      text,
      key: const ValueKey('chart-readout'),
      maxLines: 2,
      style: Theme.of(context).textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w700),
    ),
  );
}

/// One legend entry: a colour square and its name.
class ChartLegendItem extends StatelessWidget {
  const ChartLegendItem({required this.color, required this.label, super.key});

  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Container(
        width: 12,
        height: 12,
        decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(3)),
      ),
      const SizedBox(width: 6),
      Text(label, style: Theme.of(context).textTheme.bodySmall),
    ],
  );
}

/// Light grid and axis colours that stay in the background.
Color _gridColor(BuildContext context) => Theme.of(context).colorScheme.outlineVariant;

/// One bar in [StackedBarChart]: a bottom part and a top part.
typedef StackedBar = ({int bottom, int top});

/// Bars of two stacked parts (for example right answers below, wrong ones on top). Tap a bar to
/// see its numbers in the line above the chart; the last bar is shown first.
class StackedBarChart extends StatefulWidget {
  const StackedBarChart({
    required this.bars,
    required this.labels,
    required this.describe,
    required this.bottomColor,
    required this.topColor,
    this.height = 140,
    super.key,
  });

  final List<StackedBar> bars;

  /// One per bar; an empty label is left out.
  final List<String> labels;

  /// The readout text for a bar.
  final String Function(int index) describe;
  final Color bottomColor;
  final Color topColor;
  final double height;

  @override
  State<StackedBarChart> createState() => _StackedBarChartState();
}

class _StackedBarChartState extends State<StackedBarChart> {
  late int _selected = widget.bars.length - 1;

  @override
  void didUpdateWidget(StackedBarChart old) {
    super.didUpdateWidget(old);
    if (_selected >= widget.bars.length) _selected = widget.bars.length - 1;
  }

  @override
  Widget build(BuildContext context) {
    final bars = widget.bars;
    if (bars.isEmpty) return const SizedBox.shrink();
    final theme = Theme.of(context);
    return Semantics(
      label: [for (var i = 0; i < bars.length; i++) widget.describe(i)].join('. '),
      child: ExcludeSemantics(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _Readout(text: widget.describe(_selected)),
            LayoutBuilder(
              builder: (context, box) => GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTapDown: (d) => setState(
                  () => _selected = (d.localPosition.dx / box.maxWidth * bars.length).floor().clamp(
                    0,
                    bars.length - 1,
                  ),
                ),
                child: CustomPaint(
                  size: Size(box.maxWidth, widget.height),
                  painter: _StackedBarPainter(
                    bars: bars,
                    selected: _selected,
                    bottomColor: widget.bottomColor,
                    topColor: widget.topColor,
                    grid: _gridColor(context),
                    surface: theme.colorScheme.surfaceContainerLow,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 4),
            Row(
              children: [
                for (final label in widget.labels)
                  Expanded(
                    child: Text(
                      label,
                      textAlign: TextAlign.center,
                      maxLines: 1,
                      overflow: TextOverflow.clip,
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _StackedBarPainter extends CustomPainter {
  _StackedBarPainter({
    required this.bars,
    required this.selected,
    required this.bottomColor,
    required this.topColor,
    required this.grid,
    required this.surface,
  });

  final List<StackedBar> bars;
  final int selected;
  final Color bottomColor;
  final Color topColor;
  final Color grid;
  final Color surface;

  @override
  void paint(Canvas canvas, Size size) {
    final gridPaint = Paint()
      ..color = grid
      ..strokeWidth = 1;
    // Baseline and a half-way line.
    canvas
      ..drawLine(Offset(0, size.height), Offset(size.width, size.height), gridPaint)
      ..drawLine(Offset(0, size.height / 2), Offset(size.width, size.height / 2), gridPaint);

    final most = bars.fold(0, (m, b) => math.max(m, b.bottom + b.top));
    if (most == 0) return;
    final slot = size.width / bars.length;
    final width = math.min(slot * 0.6, 28.0);
    const gap = 2.0;
    const radius = Radius.circular(4);

    for (var i = 0; i < bars.length; i++) {
      final b = bars[i];
      final left = slot * i + (slot - width) / 2;
      final bottomHeight = size.height * b.bottom / most;
      final topHeight = size.height * b.top / most;
      if (i == selected) {
        canvas.drawRRect(
          RRect.fromRectAndRadius(
            Rect.fromLTWH(slot * i + 1, 0, slot - 2, size.height),
            const Radius.circular(6),
          ),
          Paint()..color = surface,
        );
      }
      if (b.bottom > 0) {
        canvas.drawRRect(
          RRect.fromRectAndCorners(
            Rect.fromLTWH(left, size.height - bottomHeight, width, bottomHeight),
            topLeft: b.top == 0 ? radius : Radius.zero,
            topRight: b.top == 0 ? radius : Radius.zero,
          ),
          Paint()..color = bottomColor,
        );
      }
      if (b.top > 0) {
        // A 2px gap between the parts when both are there.
        final bottomEdge = size.height - bottomHeight - (b.bottom > 0 ? gap : 0);
        final h = math.max(topHeight - (b.bottom > 0 ? gap : 0), 1.0);
        canvas.drawRRect(
          RRect.fromRectAndCorners(
            Rect.fromLTWH(left, bottomEdge - h, width, h),
            topLeft: radius,
            topRight: radius,
          ),
          Paint()..color = topColor,
        );
      }
    }
  }

  @override
  bool shouldRepaint(_StackedBarPainter old) =>
      old.bars != bars ||
      old.selected != selected ||
      old.bottomColor != bottomColor ||
      old.topColor != topColor ||
      old.grid != grid ||
      old.surface != surface;
}

/// A 0–100 line with a point per value; a missing value (null) breaks the line. Tap a point to
/// see its numbers; the last point is shown first.
class PercentLineChart extends StatefulWidget {
  const PercentLineChart({
    required this.values,
    required this.labels,
    required this.describe,
    required this.color,
    this.height = 140,
    super.key,
  });

  final List<int?> values;
  final List<String> labels;
  final String Function(int index) describe;
  final Color color;
  final double height;

  @override
  State<PercentLineChart> createState() => _PercentLineChartState();
}

class _PercentLineChartState extends State<PercentLineChart> {
  late int _selected = widget.values.length - 1;

  static const _gutter = 32.0;

  @override
  void didUpdateWidget(PercentLineChart old) {
    super.didUpdateWidget(old);
    if (_selected >= widget.values.length) _selected = widget.values.length - 1;
  }

  @override
  Widget build(BuildContext context) {
    final values = widget.values;
    if (values.isEmpty) return const SizedBox.shrink();
    final theme = Theme.of(context);
    final axisStyle = theme.textTheme.labelSmall?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );
    return Semantics(
      label: [for (var i = 0; i < values.length; i++) widget.describe(i)].join('. '),
      child: ExcludeSemantics(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _Readout(text: widget.describe(_selected)),
            LayoutBuilder(
              builder: (context, box) {
                final plot = box.maxWidth - _gutter;
                return GestureDetector(
                  behavior: HitTestBehavior.opaque,
                  onTapDown: (d) => setState(
                    () => _selected = ((d.localPosition.dx - _gutter) / plot * values.length)
                        .floor()
                        .clamp(0, values.length - 1),
                  ),
                  child: CustomPaint(
                    size: Size(box.maxWidth, widget.height),
                    painter: _LinePainter(
                      values: values,
                      selected: _selected,
                      color: widget.color,
                      grid: _gridColor(context),
                      surface: theme.colorScheme.surface,
                      highlight: theme.colorScheme.surfaceContainerLow,
                      axisStyle: axisStyle ?? const TextStyle(fontSize: 11),
                      textDirection: Directionality.of(context),
                      gutter: _gutter,
                    ),
                  ),
                );
              },
            ),
            const SizedBox(height: 4),
            Padding(
              padding: const EdgeInsetsDirectional.only(start: _gutter),
              child: Row(
                children: [
                  for (final label in widget.labels)
                    Expanded(
                      child: Text(
                        label,
                        textAlign: TextAlign.center,
                        maxLines: 1,
                        overflow: TextOverflow.clip,
                        style: axisStyle,
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LinePainter extends CustomPainter {
  _LinePainter({
    required this.values,
    required this.selected,
    required this.color,
    required this.grid,
    required this.surface,
    required this.highlight,
    required this.axisStyle,
    required this.textDirection,
    required this.gutter,
  });

  final List<int?> values;
  final int selected;
  final Color color;
  final Color grid;
  final Color surface;
  final Color highlight;
  final TextStyle axisStyle;
  final TextDirection textDirection;
  final double gutter;

  @override
  void paint(Canvas canvas, Size size) {
    // Room at the top and bottom so a point at 0 or 100 is not cut.
    const pad = 6.0;
    final plotHeight = size.height - 2 * pad;
    double y(int v) => pad + plotHeight * (1 - v / 100);
    final slot = (size.width - gutter) / values.length;
    double x(int i) => gutter + slot * i + slot / 2;

    if (selected >= 0 && selected < values.length) {
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromLTWH(gutter + slot * selected + 1, 0, slot - 2, size.height),
          const Radius.circular(6),
        ),
        Paint()..color = highlight,
      );
    }

    final gridPaint = Paint()
      ..color = grid
      ..strokeWidth = 1;
    for (final v in const [0, 50, 100]) {
      canvas.drawLine(Offset(gutter, y(v)), Offset(size.width, y(v)), gridPaint);
      final text = TextPainter(
        text: TextSpan(text: v == 100 ? '100%' : '$v', style: axisStyle),
        textDirection: textDirection,
      )..layout();
      text.paint(canvas, Offset(gutter - 6 - text.width, y(v) - text.height / 2));
      text.dispose();
    }

    final line = Paint()
      ..color = color
      ..strokeWidth = 2
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    final path = Path();
    var drawing = false;
    for (var i = 0; i < values.length; i++) {
      final v = values[i];
      if (v == null) {
        drawing = false;
        continue;
      }
      final point = Offset(x(i), y(v.clamp(0, 100)));
      if (drawing) {
        path.lineTo(point.dx, point.dy);
      } else {
        path.moveTo(point.dx, point.dy);
        drawing = true;
      }
    }
    canvas.drawPath(path, line);

    for (var i = 0; i < values.length; i++) {
      final v = values[i];
      if (v == null) continue;
      final centre = Offset(x(i), y(v.clamp(0, 100)));
      final r = i == selected ? 6.0 : 4.0;
      // A 2px ring in the surface colour keeps a point clear of the line.
      canvas
        ..drawCircle(centre, r + 2, Paint()..color = surface)
        ..drawCircle(centre, r, Paint()..color = color);
    }
  }

  @override
  bool shouldRepaint(_LinePainter old) =>
      old.values != values ||
      old.selected != selected ||
      old.color != color ||
      old.grid != grid ||
      old.surface != surface ||
      old.highlight != highlight ||
      old.axisStyle != axisStyle ||
      old.textDirection != textDirection;
}

/// One row of [ShareBars].
typedef ShareBar = ({String label, int? percent, String value});

/// Horizontal 0–100 bars with a name and a value above each: for accuracy by subject or by part
/// of the day.
class ShareBars extends StatelessWidget {
  const ShareBars({required this.rows, required this.color, super.key});

  final List<ShareBar> rows;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final r in rows)
          MergeSemantics(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          r.label,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(r.value, style: theme.textTheme.bodySmall),
                    ],
                  ),
                  const SizedBox(height: 6),
                  ExcludeSemantics(
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: SizedBox(
                        height: 8,
                        child: Stack(
                          children: [
                            Positioned.fill(
                              child: ColoredBox(color: theme.colorScheme.surfaceContainerHighest),
                            ),
                            FractionallySizedBox(
                              widthFactor: ((r.percent ?? 0) / 100).clamp(0.0, 1.0),
                              child: DecoratedBox(
                                decoration: BoxDecoration(
                                  color: color,
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: const SizedBox.expand(),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

/// One part of [SegmentBar].
typedef Segment = ({int value, Color color, String label});

/// One bar split into parts by size (with 2px gaps), and a legend that names every part.
class SegmentBar extends StatelessWidget {
  const SegmentBar({required this.segments, super.key});

  final List<Segment> segments;

  @override
  Widget build(BuildContext context) {
    final shown = segments.where((s) => s.value > 0).toList();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (shown.isNotEmpty)
          ExcludeSemantics(
            child: SizedBox(
              height: 16,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  for (var i = 0; i < shown.length; i++) ...[
                    if (i > 0) const SizedBox(width: 2),
                    Expanded(
                      flex: shown[i].value,
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          color: shown[i].color,
                          borderRadius: BorderRadius.circular(4),
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 16,
          runSpacing: 8,
          children: [for (final s in segments) ChartLegendItem(color: s.color, label: s.label)],
        ),
      ],
    );
  }
}

/// One square of [ActivityHeatmap].
typedef HeatDay = ({int count, bool isFuture});

/// Squares in columns of 7 (one column a week, Monday on top), darker for more answers. Tap a
/// square to see its numbers; today is shown first.
class ActivityHeatmap extends StatefulWidget {
  const ActivityHeatmap({
    required this.days,
    required this.initial,
    required this.describe,
    required this.color,
    required this.dayLabels,
    required this.lessLabel,
    required this.moreLabel,
    super.key,
  });

  /// Day by day, a multiple of 7, Monday first.
  final List<HeatDay> days;

  /// The square shown first (today).
  final int initial;
  final String Function(int index) describe;
  final Color color;

  /// Monday to Sunday, short; shown next to the rows (only Mon, Wed and Fri).
  final List<String> dayLabels;
  final String lessLabel;
  final String moreLabel;

  /// How dark a square is: 0 for none, then 1–4 by the share of the busiest day.
  static int level(int count, int most) {
    if (count <= 0 || most <= 0) return 0;
    return (4 * count / most).ceil().clamp(1, 4);
  }

  @override
  State<ActivityHeatmap> createState() => _ActivityHeatmapState();
}

class _ActivityHeatmapState extends State<ActivityHeatmap> {
  late int _selected = widget.initial;

  static const _labelWidth = 32.0;
  static const _gap = 3.0;

  Color _shade(BuildContext context, int level) {
    final empty = Theme.of(context).colorScheme.surfaceContainerHighest;
    if (level == 0) return empty;
    // One hue, light to dark.
    return Color.lerp(empty, widget.color, const [0.0, 0.3, 0.55, 0.8, 1.0][level])!;
  }

  @override
  Widget build(BuildContext context) {
    final days = widget.days;
    if (days.isEmpty) return const SizedBox.shrink();
    final theme = Theme.of(context);
    final weeks = days.length ~/ 7;
    final most = days.fold(0, (m, d) => math.max(m, d.count));
    final labelStyle = theme.textTheme.labelSmall?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );
    final selected = _selected.clamp(0, days.length - 1);
    return Semantics(
      label: [
        for (var i = 0; i < days.length; i++)
          if (!days[i].isFuture) widget.describe(i),
      ].join('. '),
      child: ExcludeSemantics(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _Readout(text: widget.describe(selected)),
            LayoutBuilder(
              builder: (context, box) {
                final cell = math.min(
                  (box.maxWidth - _labelWidth - _gap * (weeks - 1)) / weeks,
                  22.0,
                );
                return Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      width: _labelWidth,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          for (var r = 0; r < 7; r++)
                            SizedBox(
                              height: cell + (r < 6 ? _gap : 0),
                              child: r.isEven && r < 6
                                  ? Text(widget.dayLabels[r], style: labelStyle, maxLines: 1)
                                  : null,
                            ),
                        ],
                      ),
                    ),
                    for (var w = 0; w < weeks; w++) ...[
                      if (w > 0) const SizedBox(width: _gap),
                      Column(
                        children: [
                          for (var r = 0; r < 7; r++) ...[
                            if (r > 0) const SizedBox(height: _gap),
                            _square(context, w * 7 + r, cell, most, selected),
                          ],
                        ],
                      ),
                    ],
                  ],
                );
              },
            ),
            const SizedBox(height: 10),
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                Text(widget.lessLabel, style: labelStyle),
                const SizedBox(width: 6),
                for (var level = 0; level <= 4; level++)
                  Padding(
                    padding: const EdgeInsets.only(right: 3),
                    child: Container(
                      width: 12,
                      height: 12,
                      decoration: BoxDecoration(
                        color: _shade(context, level),
                        borderRadius: BorderRadius.circular(3),
                      ),
                    ),
                  ),
                const SizedBox(width: 3),
                Text(widget.moreLabel, style: labelStyle),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _square(BuildContext context, int index, double cell, int most, int selected) {
    final day = widget.days[index];
    if (day.isFuture) return SizedBox(width: cell, height: cell);
    final isSelected = index == selected;
    return GestureDetector(
      onTap: () => setState(() => _selected = index),
      child: Container(
        width: cell,
        height: cell,
        decoration: BoxDecoration(
          color: _shade(context, ActivityHeatmap.level(day.count, most)),
          borderRadius: BorderRadius.circular(3),
          border: isSelected
              ? Border.all(color: Theme.of(context).colorScheme.onSurface, width: 2)
              : null,
        ),
      ),
    );
  }
}

/// A half-circle gauge: the likely range as a band and the middle as a dot, on 0–100.
class RangeGauge extends StatelessWidget {
  const RangeGauge({
    required this.low,
    required this.high,
    required this.color,
    required this.track,
    required this.centre,
    this.width = 160,
    super.key,
  });

  final int low;
  final int high;
  final Color color;
  final Color track;

  /// Shown in the middle (for example "58–72%").
  final Widget centre;
  final double width;

  @override
  Widget build(BuildContext context) {
    final reduceMotion = MediaQuery.disableAnimationsOf(context);
    return SizedBox(
      width: width,
      height: width / 2 + 12,
      child: TweenAnimationBuilder<double>(
        tween: Tween(begin: reduceMotion ? 1 : 0, end: 1),
        duration: Duration(milliseconds: reduceMotion ? 0 : 800),
        curve: Curves.easeOutCubic,
        builder: (context, t, child) => CustomPaint(
          painter: _GaugePainter(
            low: low * t,
            high: high * t,
            color: color,
            track: track,
            surface: Theme.of(context).colorScheme.surface,
          ),
          child: child,
        ),
        child: Align(alignment: const Alignment(0, 0.85), child: centre),
      ),
    );
  }
}

class _GaugePainter extends CustomPainter {
  _GaugePainter({
    required this.low,
    required this.high,
    required this.color,
    required this.track,
    required this.surface,
  });

  final double low;
  final double high;
  final Color color;
  final Color track;
  final Color surface;

  @override
  void paint(Canvas canvas, Size size) {
    const stroke = 14.0;
    final radius = size.width / 2 - stroke / 2;
    final centre = Offset(size.width / 2, radius + stroke / 2);
    final rect = Rect.fromCircle(center: centre, radius: radius);
    Paint arc(Color c) => Paint()
      ..color = c
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.round;

    // From the left (0) over the top to the right (100).
    canvas.drawArc(rect, math.pi, math.pi, false, arc(track));
    final from = low.clamp(0, 100) / 100;
    final to = high.clamp(0, 100) / 100;
    if (to > from) {
      canvas.drawArc(
        rect,
        math.pi + math.pi * from,
        math.pi * (to - from),
        false,
        arc(color.withValues(alpha: 0.45)),
      );
    }
    final middle = (from + to) / 2;
    final angle = math.pi + math.pi * middle;
    final dot = centre + Offset(math.cos(angle), math.sin(angle)) * radius;
    canvas
      ..drawCircle(dot, stroke / 2 + 2, Paint()..color = surface)
      ..drawCircle(dot, stroke / 2, Paint()..color = color);
  }

  @override
  bool shouldRepaint(_GaugePainter old) =>
      old.low != low ||
      old.high != high ||
      old.color != color ||
      old.track != track ||
      old.surface != surface;
}
