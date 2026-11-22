import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/providers.dart';
import '../../data/api_client.dart';
import '../../data/models.dart';
import '../../l10n/generated/app_localizations.dart';
import '../widgets/common.dart';

/// The price-free landing page (DECISIONS #3), the same link as on the summary PDFs.
const landingAppUrl = 'https://guul12.kulmiss.com/app';

final referralProvider = FutureProvider.autoDispose<ReferralInfo>(
  (ref) => ref.watch(repositoryProvider).referral(),
);

String _errorText(AppLocalizations l, Object e) {
  if (e is! ApiException) return l.commonError;
  if (e.isOffline) return l.inviteOffline;
  return switch (e.code) {
    'not_found' => l.inviteErrorNotFound,
    'own_code' => l.inviteErrorOwn,
    'already_invited' => l.inviteErrorAlready,
    'too_late' => l.inviteErrorTooLate,
    _ => l.commonError,
  };
}

/// My invite code (share or copy), what my invites earned, and "did a friend invite you?".
class InviteScreen extends ConsumerWidget {
  const InviteScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final async = ref.watch(referralProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.inviteTitle)),
      body: async.when(
        loading: () => const LoadingView(),
        error: (e, _) => MessageView(
          message: _errorText(l, e),
          icon: Icons.cloud_off,
          onRetry: () => ref.invalidate(referralProvider),
        ),
        data: (info) => ListView(
          padding: const EdgeInsets.only(bottom: 32),
          children: [ContentWidth(child: _InviteBody(info: info))],
        ),
      ),
    );
  }
}

class _InviteBody extends ConsumerStatefulWidget {
  const _InviteBody({required this.info});

  final ReferralInfo info;

  @override
  ConsumerState<_InviteBody> createState() => _InviteBodyState();
}

class _InviteBodyState extends ConsumerState<_InviteBody> {
  final _code = TextEditingController();
  String? _error;
  var _saving = false;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _enter() async {
    final l = context.l10n;
    final code = _code.text.trim();
    if (code.isEmpty) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref.read(repositoryProvider).enterReferralCode(code);
      messenger.showSnackBar(SnackBar(content: Text(l.inviteEntered)));
      ref.invalidate(referralProvider);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = _errorText(l, e));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final info = widget.info;
    final sharingOn = ref.watch(configProvider).value?.feature('sharing') ?? false;
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  Text(l.inviteYourCode, style: theme.textTheme.labelLarge),
                  const SizedBox(height: 8),
                  SelectableText(
                    info.code,
                    style: theme.textTheme.displaySmall?.copyWith(letterSpacing: 4),
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 12,
                    alignment: WrapAlignment.center,
                    children: [
                      if (sharingOn)
                        FilledButton.icon(
                          icon: const Icon(Icons.share_outlined),
                          label: Text(l.inviteShare),
                          onPressed: () => ref
                              .read(sharingProvider)
                              .shareText(l.inviteShareText(info.code, landingAppUrl)),
                        ),
                      OutlinedButton.icon(
                        icon: const Icon(Icons.copy),
                        label: Text(l.inviteCopy),
                        onPressed: () async {
                          final messenger = ScaffoldMessenger.of(context);
                          await Clipboard.setData(ClipboardData(text: info.code));
                          messenger.showSnackBar(SnackBar(content: Text(l.inviteCopied)));
                        },
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            info.rewardDays > 0
                ? l.inviteRewardRule(info.minAnswers, info.rewardDays, info.maxPerMonth)
                : l.inviteNoRewardRule,
          ),
          const SizedBox(height: 8),
          Text(l.inviteStats(info.pending + info.valid, info.valid, info.rewardedDays)),
          if (info.canEnterCode) ...[
            const SizedBox(height: 24),
            Text(l.inviteEnterTitle, style: theme.textTheme.titleMedium),
            const SizedBox(height: 8),
            TextField(
              controller: _code,
              maxLength: 6,
              textCapitalization: TextCapitalization.characters,
              decoration: InputDecoration(labelText: l.inviteEnterLabel, errorText: _error),
            ),
            Align(
              alignment: AlignmentDirectional.centerEnd,
              child: FilledButton(
                onPressed: _saving ? null : _enter,
                child: Text(l.inviteEnterButton),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
