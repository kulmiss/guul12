import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../domain/premium_notice.dart';
import 'providers.dart';

/// "Your Premium ends on …" / "Your Premium ended on …" (neutral; null = nothing to say).
final premiumNoticeProvider = FutureProvider<PremiumNotice?>((ref) async {
  final profile = await ref.watch(profileProvider.future);
  final repo = ref.watch(repositoryProvider);
  return premiumNotice(
    now: ref.watch(clockProvider)(),
    active: profile.premiumActive,
    until: profile.premiumUntil,
    lastUntil: await repo.lastPremiumUntil(),
  );
});
