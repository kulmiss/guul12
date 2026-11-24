import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/providers.dart';
import '../../data/branding.dart';
import '../../l10n/generated/app_localizations.dart';

/// The logo from Nova → Settings → Branding, kept on the phone; the school icon when there is none.
class BrandLogo extends ConsumerWidget {
  const BrandLogo({super.key, this.size = 72});

  final double size;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    final bytes = ref.watch(brandLogoProvider(dark)).value;
    if (bytes == null) {
      return Icon(Icons.school, size: size, color: Theme.of(context).colorScheme.primary);
    }
    return Image.memory(
      bytes,
      key: const ValueKey('brand-logo'),
      height: size,
      fit: BoxFit.contain,
      // A broken picture shows the icon instead of an error box.
      errorBuilder: (context, error, stack) =>
          Icon(Icons.school, size: size, color: Theme.of(context).colorScheme.primary),
      semanticLabel: null,
      excludeFromSemantics: true,
    );
  }
}

/// The name shown at the top: the logo text from Nova once one is saved, the app name before that.
String brandName(AppLocalizations l, Branding brand) =>
    brand.version == 0 ? l.appName : brand.logoText;
