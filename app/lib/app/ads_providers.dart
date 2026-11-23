import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../services/ads.dart';
import 'providers.dart';

final adsProvider = Provider<Ads>((ref) => const NoAds());

/// Ads are allowed only when the server says so (ads switch on, not Premium) and the phone also
/// knows the student is not Premium. Unknown (still loading) = no ads.
final adsAllowedProvider = Provider<bool>((ref) {
  final config = ref.watch(configProvider).value;
  final profile = ref.watch(profileProvider).value;
  if (config == null || profile == null) return false;
  final until = profile.premiumUntil;
  final now = ref.watch(clockProvider)();
  final premium = profile.premiumActive && (until == null || until.isAfter(now));
  return config.showAds && !premium;
});

final rewardedAllowedProvider = Provider<bool>((ref) {
  final config = ref.watch(configProvider).value;
  return ref.watch(adsAllowedProvider) && (config?.feature('rewarded_ads') ?? false);
});
