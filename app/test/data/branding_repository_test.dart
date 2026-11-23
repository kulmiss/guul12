import 'package:flutter_test/flutter_test.dart';
import 'package:guul12/data/branding.dart';

import '../support/branding_fixtures.dart';
import '../support/fake_server.dart';

void main() {
  test('the branding and its logo are kept on the phone and downloaded once', () async {
    final (repo, server, _) = await makeRepository();
    server.branding = nileBranding();
    server.logos[nileLogoPath] = tinyPng;

    final config = await repo.refreshConfig();
    expect(config.branding.primary, Branding.fromJson(nileBranding()).primary);
    expect(await repo.brandLogo(dark: false), tinyPng);
    expect(
      await repo.brandLogo(dark: true),
      tinyPng,
      reason: 'no dark logo: the light one is used',
    );

    // Offline later: the saved config and logo are still there.
    await repo.refreshConfig();
    expect(
      server.calls.where((c) => c.contains('/storage/branding/')).length,
      1,
      reason: 'not downloaded again',
    );
    server.online = false;
    expect((await repo.config()).branding.logoText, 'Guul 12 Nile');
    expect(await repo.brandLogo(dark: false), tinyPng);

    // The logo is removed in Nova: the phone forgets it.
    server.online = true;
    server.branding = nileBranding(logoUrl: null);
    await repo.refreshConfig();
    expect(await repo.brandLogo(dark: false), isNull);
  });

  test('a logo from another server, or one that is not a PNG, is not saved', () async {
    final (repo, server, _) = await makeRepository();
    server.logos[nileLogoPath] = [1, 2, 3, 4, 5, 6, 7, 8, 9];
    server.branding = nileBranding();
    await repo.refreshConfig();
    expect(await repo.brandLogo(dark: false), isNull);

    server.logos[nileLogoPath] = tinyPng;
    server.branding = nileBranding(logoUrl: 'https://evil.test$nileLogoPath');
    await repo.refreshConfig();
    expect(await repo.brandLogo(dark: false), isNull);
    expect(server.calls.where((c) => c.contains('evil')), isEmpty);
  });

  test('an older server without branding means Sunrise', () async {
    final (repo, _, _) = await makeRepository();
    expect((await repo.refreshConfig()).branding, Branding.sunrise);
    expect(await repo.brandLogo(dark: false), isNull);
  });
}
