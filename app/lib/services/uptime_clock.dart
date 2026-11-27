import 'dart:async';

import 'package:flutter/services.dart';

/// The phone's time since start-up in milliseconds, for the exam clock (the student cannot change
/// it, unlike the phone clock). It is read from Android once; after that a stopwatch adds to it,
/// so reading it is instant. Null where Android cannot be asked (tests, other platforms).
class UptimeClock {
  UptimeClock({this.channel = const MethodChannel('guul12/clock')});

  final MethodChannel channel;
  final _stopwatch = Stopwatch();
  int? _base;

  /// Asks Android once. Safe to call again.
  Future<void> start() async {
    if (_base != null) return;
    try {
      final value = await channel
          .invokeMethod<int>('elapsedRealtime')
          .timeout(const Duration(seconds: 2));
      if (value != null) {
        _base = value;
        _stopwatch
          ..reset()
          ..start();
      }
    } on MissingPluginException {
      _base = null;
    } on PlatformException {
      _base = null;
    } on TimeoutException {
      _base = null;
    }
  }

  int? nowMs() {
    final base = _base;
    return base == null ? null : base + _stopwatch.elapsedMilliseconds;
  }
}

/// For tests: a counter the test moves by hand (null = not available, like on other platforms).
class FakeUptimeClock extends UptimeClock {
  FakeUptimeClock([this.ms]);

  int? ms;

  @override
  Future<void> start() async {}

  @override
  int? nowMs() => ms;
}
