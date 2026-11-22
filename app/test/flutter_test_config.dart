import 'dart:async';

import 'package:flutter_test/flutter_test.dart';

/// Runs before every test file: a tap that would miss its widget fails the test.
Future<void> testExecutable(FutureOr<void> Function() testMain) async {
  WidgetController.hitTestWarningShouldBeFatal = true;
  await testMain();
}
