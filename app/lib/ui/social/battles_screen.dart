import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/battle_providers.dart';
import '../../app/providers.dart';
import '../../data/api_client.dart';
import '../../data/models.dart';
import '../../domain/practice.dart';
import '../../l10n/generated/app_localizations.dart';
import '../avatars.dart';
import '../practice/practice_screen.dart';
import '../widgets/common.dart';

String battleStatusText(AppLocalizations l, BattleInfo b) => switch (b.status) {
  BattleStatus.waiting => l.battleWaitingOpponent,
  BattleStatus.yourTurn => l.battleYourTurn,
  BattleStatus.theirTurn => l.battleTheirTurn(b.opponent?.nickname ?? ''),
  BattleStatus.won => l.battleWon,
  BattleStatus.lost => l.battleLost,
  BattleStatus.draw => l.battleDraw,
  BattleStatus.expired => l.battleExpired,
};

String _errorText(AppLocalizations l, ApiException e) {
  if (e.isOffline) return l.battleOffline;
  return switch (e.code) {
    'not_found' => l.battleErrorNotFound,
    'battle_full' => l.battleErrorFull,
    'own_battle' => l.battleErrorOwn,
    'expired' => l.battleErrorExpired,
    'battle_limit' => l.battleErrorLimit,
    'nickname_required' => l.battleNeedNickname,
    _ => l.commonError,
  };
}

/// Battles: quick battle, battle a friend with a code, join with a code, and the list.
class BattlesScreen extends ConsumerStatefulWidget {
  const BattlesScreen({super.key});

  @override
  ConsumerState<BattlesScreen> createState() => _BattlesScreenState();
}

class _BattlesScreenState extends ConsumerState<BattlesScreen> {
  var _busy = false;

  void _play(int battleId) =>
      context.push(PracticeScreen.playPath(PracticeMode.battle, battleId: battleId));

  Future<void> _run(Future<void> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    final l = context.l10n;
    final messenger = ScaffoldMessenger.of(context)
      // An old error message must not cover the next screen's buttons.
      ..hideCurrentSnackBar();
    try {
      await action();
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(_errorText(l, e))));
    } finally {
      if (mounted) setState(() => _busy = false);
      ref.invalidate(battlesProvider);
    }
  }

  Future<void> _quick() => _run(() async {
    final b = await ref.read(repositoryProvider).startBattle(forFriend: false);
    if (mounted) _play(b.battle.id);
  });

  Future<void> _friend() => _run(() async {
    final b = await ref.read(repositoryProvider).startBattle(forFriend: true);
    if (!mounted) return;
    final l = context.l10n;
    final play = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l.battleCodeTitle),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: SelectableText(
                b.battle.code ?? '',
                style: Theme.of(context).textTheme.displaySmall?.copyWith(letterSpacing: 4),
              ),
            ),
            const SizedBox(height: 12),
            Text(l.battleCodeBody),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: Text(l.battleClose)),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(l.battlePlayNow),
          ),
        ],
      ),
    );
    if (play == true && mounted) _play(b.battle.id);
  });

  Future<void> _join() async {
    final code = await showDialog<String>(context: context, builder: (_) => const _JoinDialog());
    if (code == null || code.isEmpty) return;
    await _run(() async {
      final b = await ref.read(repositoryProvider).joinBattle(code);
      if (mounted) _play(b.battle.id);
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final profile = ref.watch(profileProvider).value;
    final hasNickname = profile?.nickname != null;
    final list = ref.watch(battlesProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l.battleTitle)),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(battlesProvider.future),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.only(bottom: 24),
          children: [
            ContentWidth(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                    child: Text(l.battleRule),
                  ),
                  if (!hasNickname)
                    Card(
                      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                      child: ListTile(
                        leading: const Icon(Icons.badge_outlined),
                        title: Text(l.battleNeedNickname),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () => context.push('/me/identity'),
                      ),
                    )
                  else ...[
                    Card(
                      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                      child: ListTile(
                        leading: const CircleAvatar(child: Icon(Icons.bolt)),
                        title: Text(l.battleQuick),
                        subtitle: Text(l.battleQuickBody),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: _busy ? null : _quick,
                      ),
                    ),
                    Card(
                      margin: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                      child: ListTile(
                        leading: const CircleAvatar(child: Icon(Icons.group_outlined)),
                        title: Text(l.battleFriend),
                        subtitle: Text(l.battleFriendBody),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: _busy ? null : _friend,
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                      child: OutlinedButton.icon(
                        icon: const Icon(Icons.keyboard_outlined),
                        label: Text(l.battleJoin),
                        onPressed: _busy ? null : _join,
                      ),
                    ),
                  ],
                  SectionTitle(l.battleMine),
                  ...list.when(
                    loading: () => [
                      const Padding(padding: EdgeInsets.all(24), child: LoadingView()),
                    ],
                    error: (e, _) => [
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: Text(e is ApiException ? _errorText(l, e) : l.commonError),
                      ),
                    ],
                    data: (battles) => battles.isEmpty
                        ? [
                            Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 16),
                              child: Text(l.battleNone),
                            ),
                          ]
                        : [for (final b in battles) _BattleTile(battle: b, onPlay: _play)],
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

class _BattleTile extends StatelessWidget {
  const _BattleTile({required this.battle, required this.onPlay});

  final BattleInfo battle;
  final void Function(int battleId) onPlay;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final other = battle.opponent;
    String score(BattlePlayer? p) => p != null && p.played ? '${p.correct}/${p.total}' : '–';
    final bothPlayed = battle.me.played && (other?.played ?? false);
    return ListTile(
      leading: AvatarImage(style: other?.avatarStyle, seed: other?.avatarSeed),
      title: Text(other?.nickname ?? l.battleWaitingOpponent),
      subtitle: Text(
        bothPlayed
            ? '${battleStatusText(l, battle)} · ${l.battleScore(score(battle.me), other!.nickname ?? '', score(other))}'
            : [
                battleStatusText(l, battle),
                if (battle.code != null) '${l.battleCodeLabel}: ${battle.code}',
              ].join(' · '),
      ),
      trailing: battle.status == BattleStatus.yourTurn
          ? FilledButton(onPressed: () => onPlay(battle.id), child: Text(l.battlePlayNow))
          : Icon(switch (battle.status) {
              BattleStatus.won => Icons.emoji_events,
              BattleStatus.lost => Icons.sentiment_neutral,
              BattleStatus.draw => Icons.handshake_outlined,
              BattleStatus.expired => Icons.timer_off_outlined,
              _ => Icons.hourglass_empty,
            }, color: battle.status == BattleStatus.won ? theme.colorScheme.secondary : null),
    );
  }
}

/// Asks for a friend's battle code. Owns its text controller, so it is cleaned up only after
/// the dialog has fully closed.
class _JoinDialog extends StatefulWidget {
  const _JoinDialog();

  @override
  State<_JoinDialog> createState() => _JoinDialogState();
}

class _JoinDialogState extends State<_JoinDialog> {
  final _code = TextEditingController();

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return AlertDialog(
      title: Text(l.battleJoin),
      content: TextField(
        controller: _code,
        autofocus: true,
        maxLength: 6,
        textCapitalization: TextCapitalization.characters,
        decoration: InputDecoration(labelText: l.battleCodeLabel),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: Text(l.battleCancel)),
        FilledButton(
          onPressed: () => Navigator.of(context).pop(_code.text.trim()),
          child: Text(l.battlePlayNow),
        ),
      ],
    );
  }
}
