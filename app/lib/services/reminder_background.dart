import 'package:material_ui/material_ui.dart';
import 'package:workmanager/workmanager.dart';

import '../app/reminder_job.dart';
import '../data/local_db.dart';
import '../l10n/generated/app_localizations.dart';
import 'reminders.dart';

/// Background entry point for WorkManager. Runs in its own isolate, without the app's UI.
@pragma('vm:entry-point')
void reminderDispatcher() {
  Workmanager().executeTask((task, _) async {
    if (task != reminderTaskName) return true;
    final db = await LocalDb.open();
    try {
      await runReminder(
        db: db,
        reminders: await PhoneReminders.start(),
        now: DateTime.now(),
        l: lookupAppLocalizations(const Locale('en')),
      );
    } finally {
      await db.close();
    }
    return true;
  });
}
