import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';

import '../support/app_harness.dart';
import '../support/branding_fixtures.dart';
import '../support/fake_server.dart';

void main() {
  testWidgets('a Nova branding colours the app and shows the logo and logo text', (tester) async {
    final server = FakeServer()
      ..branding = nileBranding()
      ..logos[nileLogoPath] = tinyPng;
    await pumpGuul(tester, server: server);

    expect(find.byKey(const ValueKey('brand-logo')), findsOneWidget);
    expect(find.text('Guul 12 Nile'), findsOneWidget);
    final theme = Theme.of(tester.element(find.text('Guul 12 Nile')));
    expect(theme.colorScheme.primary, const Color(0xFF1D4E89));
    expect(theme.textTheme.displaySmall?.fontFamily, 'Poppins');
  });

  testWidgets('without a branding the app keeps Sunrise, the app name and the icon', (
    tester,
  ) async {
    await pumpGuul(tester);

    expect(find.byKey(const ValueKey('brand-logo')), findsNothing);
    expect(find.byIcon(Icons.school), findsOneWidget);
    expect(find.text('Guul 12'), findsOneWidget);
    expect(
      Theme.of(tester.element(find.text('Guul 12'))).colorScheme.primary,
      const Color(0xFF0E5E6F),
    );
  });

  testWidgets('the home screen shows the logo text too', (tester) async {
    final server = FakeServer()..branding = nileBranding();
    onboardedProfile(server);
    await pumpGuul(tester, server: server);

    expect(
      find.descendant(of: find.byType(AppBar), matching: find.text('Guul 12 Nile')),
      findsOneWidget,
    );
  });
}
