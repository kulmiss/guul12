import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/providers.dart';
import '../../data/hook_questions.dart';
import '../../data/models.dart';
import '../widgets/accents.dart';
import '../widgets/common.dart';
import 'profile_fields.dart';
import 'walkthrough.dart';
import 'warm_up.dart';

final hookQuestionsProvider = FutureProvider<List<Question>>(
  (ref) async => parseHookQuestions(await rootBundle.loadString(hookQuestionsAsset)),
);

enum OnboardingStep {
  welcome,
  grade,
  hook,
  hookResult,
  stream,
  retaker,
  place,
  gender,
  target,
  reminder,
}

/// Which steps a student sees: the welcome slides and the easy warm-up first (the same for every
/// grade), then the profile; stream only from Grade 11, retaker only in Grade 12.
List<OnboardingStep> onboardingSteps(int? grade) => [
  OnboardingStep.welcome,
  OnboardingStep.hook,
  OnboardingStep.hookResult,
  OnboardingStep.grade,
  if (grade != null && grade >= 11) OnboardingStep.stream,
  if (grade == 12) OnboardingStep.retaker,
  OnboardingStep.place,
  OnboardingStep.gender,
  OnboardingStep.target,
  OnboardingStep.reminder,
];

class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  int _index = 0;
  int? _grade;
  String? _stream;
  bool? _retaker;
  String? _region;
  final _city = TextEditingController();
  String? _gender;
  int _target = defaultTarget;
  TimeOfDay? _reminder;
  (int correct, int total)? _hookScore;
  bool _saving = false;

  List<OnboardingStep> get _steps => onboardingSteps(_grade);
  int get _setupStart => _steps.indexOf(OnboardingStep.grade);
  OnboardingStep get _step => _steps[_index];

  @override
  void dispose() {
    _city.dispose();
    super.dispose();
  }

  bool get _canContinue => switch (_step) {
    OnboardingStep.hookResult => true,
    OnboardingStep.grade => _grade != null,
    OnboardingStep.welcome || OnboardingStep.hook => false, // they have their own buttons
    OnboardingStep.stream => _stream != null,
    OnboardingStep.retaker => _retaker != null,
    OnboardingStep.place => _region != null,
    OnboardingStep.gender || OnboardingStep.target || OnboardingStep.reminder => true,
  };

  void _back() {
    if (_index == 0) return;
    setState(() {
      _index--;
      // Never land back inside the warm-up; it was already played.
      if (_step == OnboardingStep.hook) _index--;
    });
  }

  Future<void> _continue() async {
    if (_index < _steps.length - 1) {
      setState(() {
        _index++;
        if (_step == OnboardingStep.hook && _hookScore != null) _index++;
      });
      return;
    }
    setState(() => _saving = true);
    final grade = _grade!;
    final city = _city.text.trim();
    final sync = syncTask(ref);
    final messenger = ScaffoldMessenger.of(context);
    final errorText = context.l10n.commonError;
    try {
      await ref.read(profileProvider.notifier).save({
        'grade': grade,
        'stream': grade >= 11 ? _stream : null,
        'is_retaker': grade == 12 && (_retaker ?? false),
        'region': _region,
        'city': city.isEmpty ? null : city,
        'gender': _gender,
        'target_score': _target,
        'reminder_time': timeToWire(_reminder),
        'onboarded': true,
      });
    } on Exception {
      if (mounted) setState(() => _saving = false);
      messenger.showSnackBar(SnackBar(content: Text(errorText)));
      return;
    }
    // The router moves to Home once the profile says onboarded; sync runs in the background.
    fireAndForget(sync());
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final step = _step;
    final ownButtons = step == OnboardingStep.welcome || step == OnboardingStep.hook;
    return PopScope(
      canPop: _index == 0,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _back();
      },
      child: Scaffold(
        appBar: ownButtons
            ? null
            : AppBar(
                leading: _index > 0 ? BackButton(onPressed: _back) : null,
                automaticallyImplyLeading: false,
                // Only the setup questions are numbered (the grade question is step 1).
                title: step == OnboardingStep.hookResult
                    ? null
                    : Text(l.onboardingStep(_index - _setupStart + 1, _steps.length - _setupStart)),
              ),
        body: SafeArea(
          child: step == OnboardingStep.welcome
              ? Walkthrough(onDone: () => fireAndForget(_continue()))
              : step == OnboardingStep.hook
              ? _hook()
              : Column(
                  children: [
                    Expanded(
                      child: ListView(
                        padding: const EdgeInsets.all(20),
                        children: [ContentWidth(maxWidth: 560, child: _stepBody(step))],
                      ),
                    ),
                    if (!ownButtons)
                      Padding(
                        padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                        child: ContentWidth(
                          maxWidth: 560,
                          child: Row(
                            children: [
                              if (step == OnboardingStep.gender ||
                                  step == OnboardingStep.reminder) ...[
                                Expanded(
                                  child: TextButton(
                                    onPressed: _saving
                                        ? null
                                        : () {
                                            setState(() {
                                              if (step == OnboardingStep.gender) _gender = null;
                                              if (step == OnboardingStep.reminder) _reminder = null;
                                            });
                                            fireAndForget(_continue());
                                          },
                                    child: Text(l.commonSkip),
                                  ),
                                ),
                                const SizedBox(width: 12),
                              ],
                              Expanded(
                                flex: 2,
                                child: FilledButton(
                                  key: const ValueKey('onboarding-continue'),
                                  onPressed: _canContinue && !_saving
                                      ? () => fireAndForget(_continue())
                                      : null,
                                  child: Text(
                                    _index == _steps.length - 1
                                        ? l.onboardingFinish
                                        : l.commonContinue,
                                    textAlign: TextAlign.center,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                  ],
                ),
        ),
      ),
    );
  }

  Widget _title(String text) => Padding(
    padding: const EdgeInsets.only(bottom: 16),
    child: Semantics(
      header: true,
      child: Text(text, style: Theme.of(context).textTheme.headlineSmall),
    ),
  );

  Widget _stepBody(OnboardingStep step) {
    final l = context.l10n;
    switch (step) {
      case OnboardingStep.grade:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _title(l.onboardingGradeTitle),
            ChoiceList<int>(
              values: const [9, 10, 11, 12],
              labelOf: l.onboardingGradeOption,
              selected: _grade,
              onSelected: (g) => setState(() => _grade = g),
            ),
          ],
        );
      case OnboardingStep.welcome:
      case OnboardingStep.hook:
        return const SizedBox.shrink(); // full-screen steps, built in build()
      case OnboardingStep.hookResult:
        final (correct, total) = _hookScore ?? (0, 0);
        return _WarmUpResult(correct: correct, total: total);
      case OnboardingStep.stream:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _title(l.onboardingStreamTitle),
            ChoiceList<String>(
              values: streamCodes,
              labelOf: (c) => streamLabel(context, c),
              selected: _stream,
              onSelected: (s) => setState(() => _stream = s),
            ),
          ],
        );
      case OnboardingStep.retaker:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _title(l.onboardingRetakerTitle),
            ChoiceList<bool>(
              values: const [false, true],
              labelOf: (v) => v ? l.onboardingRetakerYes : l.onboardingRetakerNo,
              selected: _retaker,
              onSelected: (v) => setState(() => _retaker = v),
            ),
          ],
        );
      case OnboardingStep.place:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _title(l.onboardingRegionTitle),
            RegionField(value: _region, onChanged: (v) => setState(() => _region = v)),
            const SizedBox(height: 16),
            CityField(controller: _city),
            const SizedBox(height: 12),
            Text(l.onboardingPrivacyNote, style: Theme.of(context).textTheme.bodySmall),
          ],
        );
      case OnboardingStep.gender:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _title(l.onboardingGenderTitle),
            ChoiceList<String>(
              values: genderCodes,
              labelOf: (c) => genderLabel(context, c),
              selected: _gender,
              onSelected: (g) => setState(() => _gender = g),
            ),
            Text(l.onboardingPrivacyNote, style: Theme.of(context).textTheme.bodySmall),
          ],
        );
      case OnboardingStep.target:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _title(l.onboardingTargetTitle),
            TargetSlider(value: _target, onChanged: (v) => setState(() => _target = v)),
          ],
        );
      case OnboardingStep.reminder:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _title(l.onboardingReminderTitle),
            ReminderField(value: _reminder, onChanged: (t) => setState(() => _reminder = t)),
          ],
        );
    }
  }

  Widget _hook() {
    final l = context.l10n;
    return ref
        .watch(hookQuestionsProvider)
        .when(
          loading: () => const LoadingView(),
          error: (_, _) => MessageView(message: l.commonError),
          data: (list) {
            if (list.isEmpty) {
              WidgetsBinding.instance.addPostFrameCallback((_) {
                if (!mounted) return;
                setState(() => _hookScore = (0, 0));
                fireAndForget(_continue());
              });
              return const SizedBox.shrink();
            }
            return WarmUpQuiz(
              questions: list,
              onFinished: (correct, total) {
                setState(() => _hookScore = (correct, total));
                fireAndForget(_continue());
              },
            );
          },
        );
  }
}

/// The warm-up score as a small celebration: a trophy and one star per right answer.
class _WarmUpResult extends StatelessWidget {
  const _WarmUpResult({required this.correct, required this.total});

  final int correct;
  final int total;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final accent = accents(context)[1];
    return Column(
      children: [
        const SizedBox(height: 24),
        Container(
          width: 112,
          height: 112,
          decoration: BoxDecoration(color: accent.soft, shape: BoxShape.circle),
          child: Center(
            child: Container(
              width: 80,
              height: 80,
              decoration: BoxDecoration(color: accent.strong, shape: BoxShape.circle),
              child: Icon(Icons.emoji_events_rounded, size: 44, color: accent.onStrong),
            ),
          ),
        ),
        const SizedBox(height: 16),
        ExcludeSemantics(
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              for (var i = 0; i < total; i++)
                Icon(
                  i < correct ? Icons.star_rounded : Icons.star_outline_rounded,
                  size: 36,
                  color: i < correct ? accent.strong : theme.colorScheme.outline,
                ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Text(
          l.onboardingHookResult(correct, total),
          style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 8),
        Text(
          correct * 5 >= total * 4 ? l.onboardingHookResultGreat : l.onboardingHookResultGood,
          textAlign: TextAlign.center,
          style: theme.textTheme.bodyLarge,
        ),
      ],
    );
  }
}
