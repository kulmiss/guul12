import 'dart:math';

import 'package:flutter/services.dart';
import 'package:material_ui/material_ui.dart';

import '../../data/models.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/math_text.dart';

/// The answer area for one question. Reports the response in the shape the
/// scorer expects, or null while the answer is not complete yet.
/// Give it `key: ValueKey(question.id)` so each question starts fresh.
class QuestionInput extends StatefulWidget {
  const QuestionInput({
    required this.question,
    required this.onChanged,
    required this.locked,
    super.key,
  });

  final Question question;
  final ValueChanged<Object?> onChanged;

  /// True after "Check": no more changes; right and wrong are coloured.
  final bool locked;

  @override
  State<QuestionInput> createState() => _QuestionInputState();
}

/// Keys that are correct for choice-like questions (used for colouring).
Set<String> correctChoiceKeys(Question q) {
  final c = q.answerKey['correct'];
  if (c is String) return {c.toUpperCase()};
  if (c is List) return c.whereType<String>().map((e) => e.toUpperCase()).toSet();
  return const {};
}

/// Shuffles ordering items so they never start in the right order (when that is possible).
List<QuestionOption> startingOrder(Question q) {
  final items = [...q.options];
  if (items.length < 2) return items;
  final order = q.answerKey['order'];
  final correct = order is List ? order.whereType<String>().toList() : const <String>[];
  final random = Random(q.id);
  for (var attempt = 0; attempt < 8; attempt++) {
    items.shuffle(random);
    if (!_sameOrder(items, correct)) return items;
  }
  // Still in order after shuffling (tiny lists): move the first item to the end.
  if (_sameOrder(items, correct)) items.add(items.removeAt(0));
  return items;
}

bool _sameOrder(List<QuestionOption> items, List<String> keys) =>
    items.length == keys.length &&
    [for (var i = 0; i < items.length; i++) items[i].key == keys[i]].every((e) => e);

class _QuestionInputState extends State<QuestionInput> {
  String? _single;
  final Set<String> _multiple = {};
  final _text = TextEditingController();
  final Map<String, String> _pairs = {};
  String? _selectedLeft;
  late final List<QuestionOption> _order = startingOrder(widget.question);

  Question get q => widget.question;

  @override
  void initState() {
    super.initState();
    if (q.type == QuestionType.ordering) {
      // Ordering is always "complete": the student may agree with the order shown.
      WidgetsBinding.instance.addPostFrameCallback((_) => _emitOrder());
    }
  }

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  void _emitOrder() {
    if (mounted) widget.onChanged([for (final o in _order) o.key]);
  }

  @override
  Widget build(BuildContext context) {
    switch (q.type) {
      case QuestionType.singleChoice:
      case QuestionType.imageChoice:
      case QuestionType.passageChoice:
        return _choices(multiple: false);
      case QuestionType.multipleAnswers:
        return _choices(multiple: true);
      case QuestionType.trueFalse:
        return _trueFalse();
      case QuestionType.numeric:
        return _textField(numeric: true);
      case QuestionType.fillIn:
        return _textField(numeric: false);
      case QuestionType.matching:
        return _matching();
      case QuestionType.ordering:
        return _ordering();
    }
  }

  // ---- choices -------------------------------------------------------------------------------

  Widget _choices({required bool multiple}) {
    final correct = correctChoiceKeys(q);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (multiple)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Text(
              context.l10n.practiceChooseAll,
              style: Theme.of(context).textTheme.labelLarge,
            ),
          ),
        for (final o in q.options)
          _OptionTile(
            label: o.key,
            body: o.body,
            imageUrl: o.mediaUrl,
            multiple: multiple,
            selected: multiple ? _multiple.contains(o.key) : _single == o.key,
            state: !widget.locked
                ? _Mark.none
                : correct.contains(o.key.toUpperCase())
                ? _Mark.correct
                : (multiple ? _multiple.contains(o.key) : _single == o.key)
                ? _Mark.wrong
                : _Mark.none,
            onTap: widget.locked
                ? null
                : () => setState(() {
                    if (multiple) {
                      _multiple.contains(o.key) ? _multiple.remove(o.key) : _multiple.add(o.key);
                      final keys = _multiple.toList()..sort();
                      widget.onChanged(keys.isEmpty ? null : keys);
                    } else {
                      _single = o.key;
                      widget.onChanged(o.key);
                    }
                  }),
          ),
      ],
    );
  }

  Widget _trueFalse() {
    final correct = correctChoiceKeys(q);
    final l = context.l10n;
    Widget button(String key, String label) {
      final selected = _single == key;
      final mark = !widget.locked
          ? _Mark.none
          : correct.contains(key)
          ? _Mark.correct
          : selected
          ? _Mark.wrong
          : _Mark.none;
      return Expanded(
        child: _OptionTile(
          label: null,
          body: label,
          imageUrl: null,
          multiple: false,
          selected: selected,
          state: mark,
          centered: true,
          onTap: widget.locked
              ? null
              : () => setState(() {
                  _single = key;
                  widget.onChanged(key);
                }),
        ),
      );
    }

    return Row(
      children: [
        button('TRUE', l.practiceTrue),
        const SizedBox(width: 12),
        button('FALSE', l.practiceFalse),
      ],
    );
  }

  // ---- typed answers -------------------------------------------------------------------------

  Widget _textField({required bool numeric}) {
    final l = context.l10n;
    final unit = q.answerKey['unit'];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        TextField(
          controller: _text,
          enabled: !widget.locked,
          keyboardType: numeric
              ? const TextInputType.numberWithOptions(decimal: true, signed: true)
              : TextInputType.text,
          inputFormatters: numeric
              ? [FilteringTextInputFormatter.allow(RegExp(r'[0-9.,/eE+\-]'))]
              : null,
          maxLength: numeric ? 24 : 120,
          textInputAction: TextInputAction.done,
          decoration: InputDecoration(
            labelText: numeric ? l.practiceTypeNumber : l.practiceTypeAnswer,
            suffixText: numeric && unit is String && unit.isNotEmpty ? unit : null,
            counterText: '',
          ),
          onChanged: (value) {
            final trimmed = value.trim();
            widget.onChanged(trimmed.isEmpty ? null : trimmed);
          },
        ),
        if (numeric && unit is String && unit.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(l.practiceUnit(unit), style: Theme.of(context).textTheme.bodySmall),
          ),
      ],
    );
  }

  // ---- matching ------------------------------------------------------------------------------

  Widget _matching() {
    final l = context.l10n;
    final left = q.leftOptions;
    final right = q.rightOptions;
    final pairKeys = left.map((o) => o.key).toList();
    final pairsMade = _pairs.length;
    final correctPairs = q.answerKey['pairs'];

    int? numberFor(String rightKey) {
      for (final e in _pairs.entries) {
        if (e.value == rightKey) return pairKeys.indexOf(e.key) + 1;
      }
      return null;
    }

    _Mark markForLeft(String leftKey) {
      if (!widget.locked || correctPairs is! Map) return _Mark.none;
      return _pairs[leftKey] == correctPairs[leftKey] ? _Mark.correct : _Mark.wrong;
    }

    void emit() {
      widget.onChanged(_pairs.length == left.length ? Map<String, String>.of(_pairs) : null);
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(l.practiceMatchHint, style: Theme.of(context).textTheme.labelLarge),
        const SizedBox(height: 8),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                children: [
                  for (final (i, o) in left.indexed)
                    _OptionTile(
                      label: '${i + 1}',
                      body: o.body,
                      imageUrl: o.mediaUrl,
                      multiple: false,
                      selected: _selectedLeft == o.key || _pairs.containsKey(o.key),
                      state: markForLeft(o.key),
                      onTap: widget.locked ? null : () => setState(() => _selectedLeft = o.key),
                    ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                children: [
                  for (final o in right)
                    _OptionTile(
                      label: numberFor(o.key)?.toString() ?? '·',
                      body: o.body,
                      imageUrl: o.mediaUrl,
                      multiple: false,
                      selected: numberFor(o.key) != null,
                      state: _Mark.none,
                      onTap: widget.locked || _selectedLeft == null
                          ? null
                          : () => setState(() {
                              _pairs.removeWhere((_, v) => v == o.key);
                              _pairs[_selectedLeft!] = o.key;
                              _selectedLeft = null;
                              emit();
                            }),
                    ),
                ],
              ),
            ),
          ],
        ),
        Row(
          children: [
            Expanded(child: Text(l.practicePairs(pairsMade))),
            if (!widget.locked && pairsMade > 0)
              TextButton(
                onPressed: () => setState(() {
                  _pairs.clear();
                  _selectedLeft = null;
                  emit();
                }),
                child: Text(l.practiceClearPairs),
              ),
          ],
        ),
      ],
    );
  }

  // ---- ordering ------------------------------------------------------------------------------

  Widget _ordering() {
    final l = context.l10n;
    final order = q.answerKey['order'];
    final correct = order is List ? order.whereType<String>().toList() : const <String>[];

    void move(int from, int to) {
      setState(() => _order.insert(to, _order.removeAt(from)));
      _emitOrder();
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(l.practiceOrderHint, style: Theme.of(context).textTheme.labelLarge),
        const SizedBox(height: 8),
        ReorderableListView(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          buildDefaultDragHandles: !widget.locked,
          onReorderItem: (from, to) {
            if (!widget.locked) move(from, to);
          },
          children: [
            for (final (i, o) in _order.indexed)
              Padding(
                key: ValueKey(o.key),
                padding: const EdgeInsets.only(bottom: 8),
                child: _OptionTile(
                  label: '${i + 1}',
                  body: o.body,
                  imageUrl: o.mediaUrl,
                  multiple: false,
                  selected: false,
                  state: !widget.locked
                      ? _Mark.none
                      : (i < correct.length && correct[i] == o.key)
                      ? _Mark.correct
                      : _Mark.wrong,
                  onTap: null,
                  trailing: widget.locked
                      ? null
                      : Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            IconButton(
                              tooltip: l.practiceMoveUp,
                              icon: const Icon(Icons.arrow_upward),
                              onPressed: i == 0 ? null : () => move(i, i - 1),
                            ),
                            IconButton(
                              tooltip: l.practiceMoveDown,
                              icon: const Icon(Icons.arrow_downward),
                              onPressed: i == _order.length - 1 ? null : () => move(i, i + 1),
                            ),
                          ],
                        ),
                ),
              ),
          ],
        ),
      ],
    );
  }
}

enum _Mark { none, correct, wrong }

class _OptionTile extends StatelessWidget {
  const _OptionTile({
    required this.label,
    required this.body,
    required this.imageUrl,
    required this.multiple,
    required this.selected,
    required this.state,
    required this.onTap,
    this.centered = false,
    this.trailing,
  });

  final String? label;
  final String body;
  final String? imageUrl;
  final bool multiple;
  final bool selected;
  final _Mark state;
  final VoidCallback? onTap;
  final bool centered;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final feedback = context.feedback;
    final (border, fill, icon) = switch (state) {
      _Mark.correct => (feedback.correct, feedback.correctContainer, Icons.check_circle),
      _Mark.wrong => (feedback.wrong, feedback.wrongContainer, Icons.cancel),
      _Mark.none when selected => (scheme.primary, scheme.primaryContainer, null),
      _Mark.none => (scheme.outlineVariant, scheme.surface, null),
    };

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Semantics(
        button: onTap != null,
        selected: selected,
        inMutuallyExclusiveGroup: !multiple,
        child: Material(
          color: fill,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
            side: BorderSide(color: border, width: selected || state != _Mark.none ? 2 : 1),
          ),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: onTap,
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 52),
              child: Padding(
                padding: const EdgeInsetsDirectional.fromSTEB(12, 8, 8, 8),
                child: Row(
                  mainAxisAlignment: centered ? MainAxisAlignment.center : MainAxisAlignment.start,
                  children: [
                    if (label != null) ...[
                      CircleAvatar(
                        radius: 14,
                        backgroundColor: selected ? scheme.primary : scheme.surfaceContainerHighest,
                        foregroundColor: selected ? scheme.onPrimary : scheme.onSurface,
                        child: Text(label!, style: const TextStyle(fontWeight: FontWeight.w700)),
                      ),
                      const SizedBox(width: 12),
                    ],
                    Flexible(
                      fit: centered ? FlexFit.loose : FlexFit.tight,
                      child: Column(
                        crossAxisAlignment: centered
                            ? CrossAxisAlignment.center
                            : CrossAxisAlignment.start,
                        children: [
                          if (imageUrl != null)
                            Padding(
                              padding: const EdgeInsets.only(bottom: 6),
                              child: Image.network(
                                imageUrl!,
                                height: 120,
                                fit: BoxFit.contain,
                                errorBuilder: (_, _, _) => const Icon(Icons.image_not_supported),
                              ),
                            ),
                          MathText(body),
                        ],
                      ),
                    ),
                    if (icon != null)
                      Padding(
                        padding: const EdgeInsetsDirectional.only(start: 8),
                        child: Icon(icon, color: border),
                      ),
                    ?trailing,
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
