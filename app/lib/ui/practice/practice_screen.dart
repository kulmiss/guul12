import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../domain/practice.dart';
import '../widgets/common.dart';
import 'player_screen.dart';

/// Lets the student choose how to practise. Chapter and unit modes appear
/// only when opened from a chapter.
class PracticeScreen extends StatelessWidget {
  const PracticeScreen({super.key, this.chapterId, this.unitId});

  final int? chapterId;
  final int? unitId;

  static String playPath(PracticeMode mode, {int? chapterId, int? unitId}) => Uri(
    path: '/practice/play',
    queryParameters: {
      'mode': mode.wire,
      'chapter': ?chapterId?.toString(),
      'unit': ?unitId?.toString(),
    },
  ).toString();

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final modes = <(PracticeMode, IconData)>[
      if (chapterId != null) (PracticeMode.chapter, Icons.menu_book_outlined),
      if (unitId != null) (PracticeMode.unit, Icons.library_books_outlined),
      (PracticeMode.quick10, Icons.bolt),
      (PracticeMode.mixed, Icons.shuffle),
      (PracticeMode.timed, Icons.timer_outlined),
      (PracticeMode.review, Icons.event_repeat),
      (PracticeMode.wrongOnly, Icons.replay),
      (PracticeMode.quickRevise, Icons.flash_on_outlined),
    ];
    return Scaffold(
      appBar: AppBar(title: Text(l.practiceTitle)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          ContentWidth(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(l.practiceChooseMode, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 12),
                for (final (mode, icon) in modes)
                  Card(
                    child: ListTile(
                      leading: Icon(icon),
                      title: Text(practiceModeLabel(l, mode)),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => context.push(
                        playPath(
                          mode,
                          chapterId: mode == PracticeMode.chapter ? chapterId : null,
                          unitId: mode == PracticeMode.unit ? unitId : null,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
