import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/providers.dart';
import '../../data/api_client.dart';
import '../widgets/common.dart';

/// The reasons the backend accepts (`NicknameReportReason`).
const nicknameReportReasons = ['rude', 'impersonation', 'other'];

/// The small flag button on another student's leaderboard or battle row.
class ReportNicknameButton extends ConsumerWidget {
  const ReportNicknameButton({required this.reportRef, required this.nickname, super.key});

  final String reportRef;
  final String nickname;

  @override
  Widget build(BuildContext context, WidgetRef ref) => IconButton(
    tooltip: context.l10n.nicknameReportAction,
    icon: const Icon(Icons.outlined_flag),
    visualDensity: VisualDensity.compact,
    onPressed: () => reportNickname(context, ref, reportRef: reportRef, nickname: nickname),
  );
}

/// Asks why the nickname is a problem, sends the report (online only) and says how it went.
Future<void> reportNickname(
  BuildContext context,
  WidgetRef ref, {
  required String reportRef,
  required String nickname,
}) async {
  final l = context.l10n;
  final messenger = ScaffoldMessenger.of(context);
  final repo = ref.read(repositoryProvider);
  final choice = await showModalBottomSheet<({String reason, String note})>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (_) => _ReportNicknameSheet(nickname: nickname),
  );
  if (choice == null) return;

  String message;
  try {
    await repo.reportNickname(reportRef, reason: choice.reason, note: choice.note);
    message = l.nicknameReportThanks;
  } on ApiException catch (e) {
    message = e.isOffline
        ? l.nicknameReportOffline
        : e.code == 'report_limit'
        ? l.nicknameReportLimit
        : l.commonError;
  }
  messenger
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));
}

class _ReportNicknameSheet extends StatefulWidget {
  const _ReportNicknameSheet({required this.nickname});

  final String nickname;

  @override
  State<_ReportNicknameSheet> createState() => _ReportNicknameSheetState();
}

class _ReportNicknameSheetState extends State<_ReportNicknameSheet> {
  String? _reason;
  final _note = TextEditingController();

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  String _label(String reason) {
    final l = context.l10n;
    return switch (reason) {
      'rude' => l.nicknameReportRude,
      'impersonation' => l.nicknameReportImpersonation,
      _ => l.nicknameReportOther,
    };
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                l.nicknameReportTitle(widget.nickname),
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 4),
              Text(l.nicknameReportIntro),
              const SizedBox(height: 8),
              RadioGroup<String>(
                groupValue: _reason,
                onChanged: (v) => setState(() => _reason = v),
                child: Column(
                  children: [
                    for (final r in nicknameReportReasons)
                      RadioListTile<String>(
                        value: r,
                        title: Text(_label(r)),
                        contentPadding: EdgeInsets.zero,
                      ),
                  ],
                ),
              ),
              TextField(
                controller: _note,
                maxLength: 255,
                maxLines: 2,
                minLines: 1,
                decoration: InputDecoration(labelText: l.nicknameReportNote),
              ),
              const SizedBox(height: 8),
              // Side by side when they fit; stacked on narrow phones.
              OverflowBar(
                alignment: MainAxisAlignment.end,
                spacing: 8,
                overflowAlignment: OverflowBarAlignment.end,
                children: [
                  TextButton(
                    onPressed: () => Navigator.of(context).pop(),
                    child: Text(l.nicknameReportCancel),
                  ),
                  FilledButton(
                    onPressed: _reason == null
                        ? null
                        : () => Navigator.of(context).pop((reason: _reason!, note: _note.text)),
                    child: Text(l.nicknameReportSend),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
