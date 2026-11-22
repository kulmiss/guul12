import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/models.dart';
import 'providers.dart';

/// Today's challenge (online). Errors (offline, no questions, switched off) hide the Home card.
final dailyChallengeProvider = FutureProvider<DailyChallengeInfo>((ref) {
  ref.watch(syncGenerationProvider);
  return ref.watch(repositoryProvider).dailyChallenge();
});
