import 'dart:async';

import 'package:google_mobile_ads/google_mobile_ads.dart';
import 'package:material_ui/material_ui.dart';

/// Ads: small banners and rewarded videos only (no full-screen interstitials), teen settings,
/// non-personalised. Never started for Premium students; never shown during a quiz or exam
/// (those screens open outside the tab shell that holds the banner).
abstract class Ads {
  /// Starts the ad SDK once. Called only when ads are allowed.
  Future<void> start();

  /// A small banner (320×50). Takes no space until an ad has loaded.
  Widget banner();

  /// Loads and shows a rewarded video. True when the student watched it to the end.
  Future<bool> showRewarded();
}

class NoAds implements Ads {
  const NoAds();

  @override
  Future<void> start() async {}

  @override
  Widget banner() => const SizedBox.shrink();

  @override
  Future<bool> showRewarded() async => false;
}

/// Ad unit IDs come in at build time. The defaults are Google's public TEST units, which never
/// pay and are safe to keep in a public repo; real IDs must never be committed.
const _bannerUnit = String.fromEnvironment(
  'ADMOB_BANNER_ID',
  defaultValue: 'ca-app-pub-3940256099942544/6300978111',
);
const _rewardedUnit = String.fromEnvironment(
  'ADMOB_REWARDED_ID',
  defaultValue: 'ca-app-pub-3940256099942544/5224354917',
);

const _request = AdRequest(nonPersonalizedAds: true);

class PhoneAds implements Ads {
  Future<void>? _started;

  @override
  Future<void> start() => _started ??= () async {
    await MobileAds.instance.updateRequestConfiguration(
      RequestConfiguration(
        maxAdContentRating: MaxAdContentRating.g,
        ageRestrictedTreatment: AgeRestrictedTreatment.teen,
      ),
    );
    await MobileAds.instance.initialize();
  }();

  @override
  Widget banner() => const _PhoneBanner();

  @override
  Future<bool> showRewarded() async {
    await start();
    final done = Completer<bool>();
    var earned = false;
    await RewardedAd.load(
      adUnitId: _rewardedUnit,
      request: _request,
      rewardedAdLoadCallback: RewardedAdLoadCallback(
        onAdLoaded: (ad) {
          ad.fullScreenContentCallback = FullScreenContentCallback(
            onAdDismissedFullScreenContent: (ad) {
              ad.dispose();
              if (!done.isCompleted) done.complete(earned);
            },
            onAdFailedToShowFullScreenContent: (ad, _) {
              ad.dispose();
              if (!done.isCompleted) done.complete(false);
            },
          );
          ad.show(onUserEarnedReward: (_, _) => earned = true);
        },
        onAdFailedToLoad: (_) {
          if (!done.isCompleted) done.complete(false);
        },
      ),
    );
    return done.future;
  }
}

class _PhoneBanner extends StatefulWidget {
  const _PhoneBanner();

  @override
  State<_PhoneBanner> createState() => _PhoneBannerState();
}

class _PhoneBannerState extends State<_PhoneBanner> {
  BannerAd? _ad;
  var _loaded = false;

  @override
  void initState() {
    super.initState();
    _ad = BannerAd(
      adUnitId: _bannerUnit,
      size: AdSize.banner,
      request: _request,
      listener: BannerAdListener(
        onAdLoaded: (_) {
          if (mounted) setState(() => _loaded = true);
        },
        onAdFailedToLoad: (ad, _) {
          ad.dispose();
          _ad = null;
        },
      ),
    )..load();
  }

  @override
  void dispose() {
    _ad?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ad = _ad;
    if (!_loaded || ad == null) return const SizedBox.shrink();
    return SafeArea(
      top: false,
      bottom: false,
      child: SizedBox(
        width: AdSize.banner.width.toDouble(),
        height: AdSize.banner.height.toDouble(),
        child: AdWidget(ad: ad),
      ),
    );
  }
}
