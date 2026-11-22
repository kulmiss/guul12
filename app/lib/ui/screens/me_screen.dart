import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/providers.dart';
import '../../data/api_client.dart';
import '../../data/models.dart';
import '../avatars.dart';
import '../regions.dart';
import '../widgets/common.dart';
import 'profile_fields.dart';

class MeScreen extends ConsumerStatefulWidget {
  const MeScreen({super.key});

  @override
  ConsumerState<MeScreen> createState() => _MeScreenState();
}

class _MeScreenState extends ConsumerState<MeScreen> {
  bool _signingIn = false;

  Future<void> _signIn() async {
    final l = context.l10n;
    final messenger = ScaffoldMessenger.of(context);
    final google = ref.read(googleAuthProvider);
    if (!google.isConfigured) {
      messenger.showSnackBar(SnackBar(content: Text(l.meSignInUnavailable)));
      return;
    }
    setState(() => _signingIn = true);
    try {
      final token = await google.idToken();
      if (token == null) return; // cancelled
      await ref.read(repositoryProvider).signInWithGoogle(token);
      await ref.read(profileProvider.notifier).reload();
      ref.read(syncGenerationProvider.notifier).bump();
    } on ApiException catch (e) {
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            e.isOffline
                ? l.commonOffline
                : e.code == 'account_conflict'
                ? l.meSignInConflict
                : l.meSignInFailed,
          ),
        ),
      );
    } on Exception {
      messenger.showSnackBar(SnackBar(content: Text(l.meSignInFailed)));
    } finally {
      if (mounted) setState(() => _signingIn = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final profile = ref.watch(profileProvider).value;
    final theme = ref.watch(themeModeProvider).value ?? ThemeMode.system;
    final googleOn = ref.watch(configProvider).value?.feature('google_sign_in') ?? true;
    final referralOn = ref.watch(configProvider).value?.feature('referral') ?? false;
    final version = ref.watch(appVersionProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l.meTitle)),
      body: profile == null
          ? const LoadingView()
          : ListView(
              padding: const EdgeInsets.only(bottom: 32),
              children: [
                ContentWidth(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Card(
                        margin: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                        child: ListTile(
                          leading: AvatarImage(
                            style: profile.avatarStyle,
                            seed: profile.avatarSeed,
                          ),
                          title: Text(profile.nickname ?? l.identityNone),
                          subtitle: Text(l.identityTitle),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () => context.push('/me/identity'),
                        ),
                      ),
                      _StudentIdCard(studentId: profile.studentId),
                      if (referralOn)
                        ListTile(
                          leading: const Icon(Icons.card_giftcard_outlined),
                          title: Text(l.inviteTitle),
                          subtitle: Text(l.inviteMeBody),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () => context.push('/me/invite'),
                        ),
                      _PlanTile(profile: profile),
                      if (googleOn)
                        profile.signedIn
                            ? ListTile(
                                leading: const Icon(Icons.verified_user_outlined),
                                title: Text(l.meSignedIn),
                              )
                            : ListTile(
                                leading: const Icon(Icons.account_circle_outlined),
                                title: Text(l.meSignIn),
                                subtitle: Text(l.meSignInWhy),
                                trailing: _signingIn
                                    ? const SizedBox.square(
                                        dimension: 24,
                                        child: CircularProgressIndicator(strokeWidth: 2),
                                      )
                                    : const Icon(Icons.chevron_right),
                                onTap: _signingIn ? null : _signIn,
                              ),
                      SectionTitle(l.meStudyProfile),
                      _InfoRow(
                        label: l.meGrade,
                        value: profile.grade == null ? l.meNotSet : l.studyGrade(profile.grade!),
                      ),
                      if ((profile.grade ?? 0) >= 11)
                        _InfoRow(label: l.meStream, value: streamLabel(context, profile.stream)),
                      _InfoRow(label: l.meRegion, value: regionLabel(l, profile.region)),
                      _InfoRow(
                        label: l.meTarget,
                        value: profile.targetScore == null
                            ? l.meNotSet
                            : l.onboardingTargetValue(profile.targetScore!),
                      ),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                        child: OutlinedButton.icon(
                          icon: const Icon(Icons.edit_outlined),
                          label: Text(l.meEditProfile),
                          onPressed: () => context.push('/me/profile'),
                        ),
                      ),
                      SectionTitle(l.meSettings),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(l.meTheme, style: Theme.of(context).textTheme.labelLarge),
                            const SizedBox(height: 8),
                            SegmentedButton<ThemeMode>(
                              segments: [
                                ButtonSegment(
                                  value: ThemeMode.system,
                                  label: Text(l.meThemeSystem),
                                ),
                                ButtonSegment(value: ThemeMode.light, label: Text(l.meThemeLight)),
                                ButtonSegment(value: ThemeMode.dark, label: Text(l.meThemeDark)),
                              ],
                              selected: {theme},
                              onSelectionChanged: (s) =>
                                  fireAndForget(ref.read(themeModeProvider.notifier).set(s.first)),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 24),
                      Center(
                        child: Text(
                          l.meVersion(version),
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
    );
  }
}

class _StudentIdCard extends StatelessWidget {
  const _StudentIdCard({required this.studentId});

  final String? studentId;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final id = studentId;
    return Card(
      margin: const EdgeInsets.fromLTRB(16, 16, 16, 8),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l.meStudentId, style: Theme.of(context).textTheme.labelLarge),
            const SizedBox(height: 4),
            if (id == null)
              Text(l.meStudentIdPending)
            else
              Row(
                children: [
                  Expanded(
                    child: SelectableText(
                      id,
                      style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        fontFamily: 'Nunito',
                        fontWeight: FontWeight.w900,
                        letterSpacing: 1.5,
                      ),
                    ),
                  ),
                  TextButton.icon(
                    icon: const Icon(Icons.copy),
                    label: Text(l.meCopy),
                    onPressed: () async {
                      await Clipboard.setData(ClipboardData(text: id));
                      if (context.mounted) {
                        ScaffoldMessenger.of(context)
                          ..hideCurrentSnackBar()
                          ..showSnackBar(SnackBar(content: Text(l.commonCopied)));
                      }
                    },
                  ),
                ],
              ),
            const SizedBox(height: 4),
            Text(l.meStudentIdHelp, style: Theme.of(context).textTheme.bodySmall),
          ],
        ),
      ),
    );
  }
}

/// Shows the plan only. No price, no buy button, no payment link (Google Play rule).
class _PlanTile extends StatelessWidget {
  const _PlanTile({required this.profile});

  final Profile profile;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final until = profile.premiumUntil;
    return ListTile(
      leading: Icon(profile.premiumActive ? Icons.workspace_premium : Icons.person_outline),
      title: Text(
        profile.premiumActive && until != null
            ? l.mePremiumUntil(DateFormat.yMMMd(l.localeName).format(until.toLocal()))
            : profile.premiumActive
            ? l.premiumLabel
            : l.meFree,
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => ListTile(
    dense: true,
    title: Text(label),
    trailing: Text(value, style: Theme.of(context).textTheme.bodyLarge),
  );
}

/// Edit grade, stream, region, city, gender, target and reminder.
class EditProfileScreen extends ConsumerStatefulWidget {
  const EditProfileScreen({super.key});

  @override
  ConsumerState<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends ConsumerState<EditProfileScreen> {
  bool _loaded = false;
  int? _grade;
  String? _stream;
  bool _retaker = false;
  String? _region;
  final _city = TextEditingController();
  String? _gender;
  int _target = defaultTarget;
  TimeOfDay? _reminder;
  bool _saving = false;

  @override
  void dispose() {
    _city.dispose();
    super.dispose();
  }

  void _load(Profile p) {
    _loaded = true;
    _grade = p.grade;
    _stream = p.stream;
    _retaker = p.isRetaker;
    _region = p.region;
    _city.text = p.city ?? '';
    _gender = p.gender;
    _target = p.targetScore ?? defaultTarget;
    _reminder = timeFromWire(p.reminderTime);
  }

  Future<void> _save() async {
    final grade = _grade;
    if (grade == null) return;
    setState(() => _saving = true);
    final city = _city.text.trim();
    final messenger = ScaffoldMessenger.of(context);
    final errorText = context.l10n.commonError;
    try {
      await ref.read(profileProvider.notifier).save({
        'grade': grade,
        'stream': grade >= 11 ? _stream : null,
        'is_retaker': grade == 12 && _retaker,
        'region': _region,
        'city': city.isEmpty ? null : city,
        'gender': _gender,
        'target_score': _target,
        'reminder_time': timeToWire(_reminder),
      });
    } on Exception {
      if (mounted) setState(() => _saving = false);
      messenger.showSnackBar(SnackBar(content: Text(errorText)));
      return;
    }
    if (!mounted) return;
    fireAndForget(runSync(ref));
    context.pop();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final profile = ref.watch(profileProvider).value;
    if (profile == null) return Scaffold(appBar: AppBar(), body: const LoadingView());
    if (!_loaded) _load(profile);
    final grade = _grade;
    final needsStream = grade != null && grade >= 11;
    final valid = grade != null && (!needsStream || _stream != null);

    return Scaffold(
      appBar: AppBar(title: Text(l.meEditProfile)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          ContentWidth(
            maxWidth: 560,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(l.meGrade, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                SegmentedButton<int>(
                  segments: [
                    for (final g in const [9, 10, 11, 12])
                      ButtonSegment(value: g, label: Text('$g')),
                  ],
                  selected: {?grade},
                  emptySelectionAllowed: grade == null,
                  onSelectionChanged: (s) => setState(() => _grade = s.first),
                ),
                if (needsStream) ...[
                  const SizedBox(height: 20),
                  Text(l.meStream, style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  ChoiceList<String>(
                    values: streamCodes,
                    labelOf: (c) => streamLabel(context, c),
                    selected: _stream,
                    onSelected: (s) => setState(() => _stream = s),
                  ),
                ],
                if (grade == 12)
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(l.meRetaker),
                    value: _retaker,
                    onChanged: (v) => setState(() => _retaker = v),
                  ),
                const SizedBox(height: 20),
                RegionField(value: _region, onChanged: (v) => setState(() => _region = v)),
                const SizedBox(height: 12),
                CityField(controller: _city),
                const SizedBox(height: 20),
                Text(l.meGender, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                ChoiceList<String?>(
                  values: const [null, ...genderCodes],
                  labelOf: (c) => genderLabel(context, c),
                  selected: _gender,
                  onSelected: (g) => setState(() => _gender = g),
                ),
                Text(l.onboardingPrivacyNote, style: Theme.of(context).textTheme.bodySmall),
                const SizedBox(height: 20),
                Text(l.meTarget, style: Theme.of(context).textTheme.titleMedium),
                TargetSlider(value: _target, onChanged: (v) => setState(() => _target = v)),
                const SizedBox(height: 12),
                Text(l.meReminder, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                ReminderField(value: _reminder, onChanged: (t) => setState(() => _reminder = t)),
                const SizedBox(height: 24),
                FilledButton(onPressed: valid && !_saving ? _save : null, child: Text(l.meSave)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
