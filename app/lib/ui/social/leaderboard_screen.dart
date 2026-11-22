import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/providers.dart';
import '../../data/api_client.dart';
import '../../data/models.dart';
import '../avatars.dart';
import '../widgets/common.dart';

final leaderboardProvider = FutureProvider.autoDispose<WeeklyLeaderboard>((ref) {
  final repo = ref.watch(repositoryProvider);
  return repo.leaderboard(appVersion: ref.watch(appVersionProvider));
});

/// This week's top students: rank, nickname, avatar and XP only.
class LeaderboardScreen extends ConsumerWidget {
  const LeaderboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final async = ref.watch(leaderboardProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.leaderboardTitle)),
      body: async.when(
        loading: () => const LoadingView(),
        error: (e, _) => MessageView(
          message: e is ApiException && e.isOffline ? l.leaderboardOffline : l.commonError,
          icon: Icons.cloud_off,
          onRetry: () => ref.invalidate(leaderboardProvider),
        ),
        data: (board) => RefreshIndicator(
          onRefresh: () => ref.refresh(leaderboardProvider.future),
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
                      child: Text(
                        l.leaderboardWeek(DateFormat.MMMd(l.localeName).format(board.weekStart)),
                      ),
                    ),
                    _MyPlace(board: board),
                    const SizedBox(height: 8),
                    if (board.top.isEmpty)
                      Padding(padding: const EdgeInsets.all(16), child: Text(l.leaderboardEmpty)),
                    for (final row in board.top) _Row(row: row),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MyPlace extends StatelessWidget {
  const _MyPlace({required this.board});

  final WeeklyLeaderboard board;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final rank = board.myRank;
    return Card(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      color: theme.colorScheme.primaryContainer,
      child: ListTile(
        leading: Icon(Icons.emoji_events_outlined, color: theme.colorScheme.onPrimaryContainer),
        title: Text(
          !board.hasNickname
              ? l.leaderboardNeedNickname
              : rank == null
              ? l.leaderboardNoPoints
              : l.leaderboardMyRank(rank),
          style: TextStyle(color: theme.colorScheme.onPrimaryContainer),
        ),
        subtitle: board.myXp > 0
            ? Text(
                l.engagementWeekXp(board.myXp),
                style: TextStyle(color: theme.colorScheme.onPrimaryContainer),
              )
            : null,
        trailing: board.hasNickname
            ? null
            : Icon(Icons.chevron_right, color: theme.colorScheme.onPrimaryContainer),
        onTap: board.hasNickname ? null : () => context.push('/me/identity'),
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.row});

  final LeaderboardEntry row;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    return ListTile(
      selected: row.isMe,
      selectedTileColor: theme.colorScheme.secondaryContainer.withValues(alpha: 0.5),
      leading: SizedBox(
        width: 96,
        child: Row(
          children: [
            SizedBox(
              width: 44,
              child: Text(l.leaderboardRank(row.rank), style: theme.textTheme.titleMedium),
            ),
            AvatarImage(style: row.avatarStyle, seed: row.avatarSeed),
          ],
        ),
      ),
      title: Text(row.isMe ? '${row.nickname} · ${l.leaderboardYou}' : row.nickname),
      trailing: Text(l.engagementXp(row.xp), style: theme.textTheme.titleSmall),
    );
  }
}
