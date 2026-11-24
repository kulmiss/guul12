import 'package:material_ui/material_ui.dart';

import '../regions.dart';
import '../widgets/common.dart';

/// Pickers shared by onboarding and "Edit study profile".
/// Values use the same codes as the API.

const streamCodes = ['natural', 'social'];
const genderCodes = ['female', 'male'];
const minTarget = 300;
const maxTarget = 600;
const defaultTarget = 400;

String streamLabel(BuildContext context, String? code) => switch (code) {
  'natural' => context.l10n.streamNatural,
  'social' => context.l10n.streamSocial,
  _ => context.l10n.meNotSet,
};

String genderLabel(BuildContext context, String? code) => switch (code) {
  'female' => context.l10n.genderFemale,
  'male' => context.l10n.genderMale,
  _ => context.l10n.meNotSet,
};

/// A list of big tappable choices (one answer).
class ChoiceList<T> extends StatelessWidget {
  const ChoiceList({
    required this.values,
    required this.labelOf,
    required this.selected,
    required this.onSelected,
    super.key,
  });

  final List<T> values;
  final String Function(T) labelOf;
  final T? selected;
  final ValueChanged<T> onSelected;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final v in values)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Semantics(
              selected: v == selected,
              inMutuallyExclusiveGroup: true,
              button: true,
              child: Material(
                color: v == selected ? scheme.primaryContainer : scheme.surface,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                  side: BorderSide(
                    color: v == selected ? scheme.primary : scheme.outlineVariant,
                    width: v == selected ? 2 : 1,
                  ),
                ),
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  onTap: () => onSelected(v),
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(minHeight: 56),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                      child: Row(
                        children: [
                          Expanded(
                            child: Text(labelOf(v), style: Theme.of(context).textTheme.titleMedium),
                          ),
                          if (v == selected) Icon(Icons.check_circle, color: scheme.primary),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class RegionField extends StatelessWidget {
  const RegionField({required this.value, required this.onChanged, super.key});

  final String? value;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return DropdownButtonFormField<String>(
      initialValue: ethiopianRegions.contains(value) ? value : null,
      isExpanded: true,
      decoration: InputDecoration(labelText: l.onboardingRegionLabel),
      items: [
        for (final code in ethiopianRegions)
          DropdownMenuItem(value: code, child: Text(l.regionName(code))),
      ],
      onChanged: onChanged,
    );
  }
}

class CityField extends StatelessWidget {
  const CityField({required this.controller, super.key, this.onChanged});

  final TextEditingController controller;
  final ValueChanged<String>? onChanged;

  @override
  Widget build(BuildContext context) => TextField(
    controller: controller,
    maxLength: 64,
    textCapitalization: TextCapitalization.words,
    decoration: InputDecoration(
      labelText: context.l10n.onboardingCityLabel,
      hintText: context.l10n.onboardingCityHint,
      counterText: '',
    ),
    onChanged: onChanged,
  );
}

class TargetSlider extends StatelessWidget {
  const TargetSlider({required this.value, required this.onChanged, super.key});

  final int value;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final clamped = value.clamp(minTarget, maxTarget);
    final label = context.l10n.onboardingTargetValue(clamped);
    return Column(
      children: [
        Text(
          label,
          style: Theme.of(context).textTheme.displaySmall
              ?.copyWith(color: Theme.of(context).colorScheme.primary),
        ),
        Slider(
          value: clamped.toDouble(),
          min: minTarget.toDouble(),
          max: maxTarget.toDouble(),
          divisions: (maxTarget - minTarget) ~/ 10,
          label: label,
          onChanged: (v) => onChanged(v.round()),
        ),
      ],
    );
  }
}

/// "HH:mm" (24 h, as the API wants) ↔ TimeOfDay.
String? timeToWire(TimeOfDay? t) => t == null
    ? null
    : '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

TimeOfDay? timeFromWire(String? value) {
  if (value == null) return null;
  final match = RegExp(r'^([01]\d|2[0-3]):([0-5]\d)$').firstMatch(value);
  if (match == null) return null;
  return TimeOfDay(hour: int.parse(match.group(1)!), minute: int.parse(match.group(2)!));
}

class ReminderField extends StatelessWidget {
  const ReminderField({required this.value, required this.onChanged, super.key});

  final TimeOfDay? value;
  final ValueChanged<TimeOfDay?> onChanged;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final v = value;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        OutlinedButton.icon(
          icon: const Icon(Icons.alarm),
          label: Text(
            v == null ? l.onboardingReminderPick : l.onboardingReminderAt(v.format(context)),
          ),
          onPressed: () async {
            final picked = await showTimePicker(
              context: context,
              initialTime: v ?? const TimeOfDay(hour: 19, minute: 0),
            );
            if (picked != null) onChanged(picked);
          },
        ),
        if (v != null)
          TextButton(onPressed: () => onChanged(null), child: Text(l.onboardingReminderNone)),
      ],
    );
  }
}
