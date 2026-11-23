import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

import '../data/branding.dart';
import '../l10n/generated/app_localizations.dart';
import '../ui/theme.dart';
import 'providers.dart';
import 'reminder_job.dart';
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
    ref.listenManual<(bool, String?)>(
      profileProvider.select((p) => (p.hasValue, p.value?.reminderTime)),
      (previous, next) => fireAndForget(_onReminderTime(previous, next)),
      fireImmediately: true,
    );
  }

  /// Keeps the phone's reminder in step with the profile. The notification permission is asked
  /// only when the student sets a new reminder time, not on every start.
  Future<void> _onReminderTime((bool, String?)? previous, (bool, String?) next) async {
    final (loaded, time) = next;
    if (!loaded) return;
    final reminders = ref.read(remindersProvider);
    final changed = previous != null && previous.$1 && previous.$2 != time;
    if (changed && time != null) await reminders.askPermission();
    await syncReminder(
      db: ref.read(repositoryProvider).db,
      reminders: reminders,
      now: ref.read(clockProvider)(),
    );
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
    // Nova branding, kept on the phone with the config; Sunrise until the first sync.
    final brand = ref.watch(configProvider).value?.branding ?? Branding.sunrise;
    return MaterialApp.router(
      onGenerateTitle: (context) => AppLocalizations.of(context).appName,
      debugShowCheckedModeBanner: false,
      theme: brandTheme(brand, Brightness.light),
      darkTheme: brandTheme(brand, Brightness.dark),
      themeMode: mode,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      routerConfig: ref.watch(routerProvider),
    );
  }
}
