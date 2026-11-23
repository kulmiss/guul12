import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/providers.dart';
import '../../data/branding.dart';
import '../../data/hook_questions.dart';
import '../../data/models.dart';
import '../practice/practice_runner.dart';
import '../widgets/brand_logo.dart';
import '../widgets/common.dart';
import 'profile_fields.dart';

final hookQuestionsProvider = FutureProvider<Map<int, List<Question>>>(
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

/// Which steps a student sees: stream only from Grade 11, retaker only in Grade 12.
List<OnboardingStep> onboardingSteps(int? grade) => [
  OnboardingStep.welcome,
  OnboardingStep.grade,
  OnboardingStep.hook,
  OnboardingStep.hookResult,
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
  OnboardingStep get _step => _steps[_index];

  @override
  void dispose() {
    _city.dispose();
    super.dispose();
  }

  bool get _canContinue => switch (_step) {
    OnboardingStep.welcome || OnboardingStep.hookResult => true,
    OnboardingStep.grade => _grade != null,
    OnboardingStep.hook => false,
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
    final showNav = step != OnboardingStep.hook;
    return PopScope(
      canPop: _index == 0,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _back();
      },
      child: Scaffold(
        appBar: AppBar(
          leading: _index > 0 && step != OnboardingStep.hook ? BackButton(onPressed: _back) : null,
          automaticallyImplyLeading: false,
          title: step == OnboardingStep.welcome
              ? null
              : Text(l.onboardingStep(_index, _steps.length - 1)),
        ),
        body: SafeArea(
          child: step == OnboardingStep.hook
              ? _hook()
              : Column(
                  children: [
                    Expanded(
                      child: ListView(
                        padding: const EdgeInsets.all(20),
                        children: [ContentWidth(maxWidth: 560, child: _stepBody(step))],
                      ),
                    ),
                    if (showNav)
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
                                    step == OnboardingStep.welcome
                                        ? l.onboardingStart
                                        : _index == _steps.length - 1
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
      case OnboardingStep.welcome:
        final brand = ref.watch(configProvider).value?.branding ?? Branding.sunrise;
        return Column(
          children: [
            const SizedBox(height: 32),
            const BrandLogo(),
            const SizedBox(height: 16),
            Text(
              brandName(l, brand),
              style: Theme.of(context).textTheme.displaySmall
                  ?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 12),
            Text(
              l.onboardingWelcomeTitle,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 8),
            Text(l.onboardingWelcomeBody, textAlign: TextAlign.center),
          ],
        );
      case OnboardingStep.grade:
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _title(l.onboardingGradeTitle),
            ChoiceList<int>(
              values: const [9, 10, 11, 12],
              labelOf: l.onboardingGradeOption,
              selected: _grade,
              onSelected: (g) => setState(() {
                if (g != _grade) _hookScore = null;
                _grade = g;
              }),
            ),
          ],
        );
      case OnboardingStep.hook:
        return const SizedBox.shrink();
      case OnboardingStep.hookResult:
        final (correct, total) = _hookScore ?? (0, 0);
        return Column(
          children: [
            const SizedBox(height: 24),
            Icon(
              Icons.emoji_events_outlined,
              size: 64,
              color: Theme.of(context).colorScheme.secondary,
            ),
            const SizedBox(height: 12),
            Text(
              l.onboardingHookResult(correct, total),
              style: Theme.of(context).textTheme.headlineSmall,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            Text(l.onboardingHookResultBody, textAlign: TextAlign.center),
          ],
        );
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
    final questions = ref.watch(hookQuestionsProvider);
    return questions.when(
      loading: () => const LoadingView(),
      error: (_, _) => MessageView(message: l.commonError),
      data: (all) {
        final list = all[_grade] ?? const <Question>[];
        if (list.isEmpty) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted) setState(() => _hookScore = (0, 0));
            if (mounted) fireAndForget(_continue());
          });
          return const SizedBox.shrink();
        }
        return Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 4),
              child: Text(l.onboardingHookTitle, style: Theme.of(context).textTheme.titleMedium),
            ),
            Expanded(
              child: PracticeRunner(
                key: ValueKey('hook-$_grade'),
                questions: list,
                allowBookmarks: false,
                allowReports: false,
                onFinished: (session) {
                  setState(() => _hookScore = (session.correctCount, session.total));
                  fireAndForget(_continue());
                },
              ),
            ),
          ],
        );
      },
    );
  }
}
