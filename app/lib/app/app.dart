import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

import '../l10n/generated/app_localizations.dart';
import '../ui/theme.dart';
import 'providers.dart';
import 'router.dart';

class GuulApp extends ConsumerStatefulWidget {
  const GuulApp({super.key});

  @override
  ConsumerState<GuulApp> createState() => _GuulAppState();
}

class _GuulAppState extends ConsumerState<GuulApp> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    // First sync in the background; the app works from the phone meanwhile.
    WidgetsBinding.instance.addPostFrameCallback((_) => fireAndForget(runSync(ref)));
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Coming back to the app is the cheapest moment to upload offline work.
    if (state == AppLifecycleState.resumed) fireAndForget(runSync(ref));
  }

  @override
  Widget build(BuildContext context) {
    final mode = ref.watch(themeModeProvider).value ?? ThemeMode.system;
    return MaterialApp.router(
      onGenerateTitle: (context) => AppLocalizations.of(context).appName,
      debugShowCheckedModeBanner: false,
      theme: sunriseTheme(Brightness.light),
      darkTheme: sunriseTheme(Brightness.dark),
      themeMode: mode,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      routerConfig: ref.watch(routerProvider),
    );
  }
}
