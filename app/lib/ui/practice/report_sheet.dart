import 'package:material_ui/material_ui.dart';

import '../widgets/common.dart';

/// The reasons the backend accepts (`ReportReason`).
const reportReasons = ['wrong_answer', 'unclear', 'typo', 'other'];

/// What the student chose in the sheet.
typedef ReportChoice = ({String reason, String? note});

/// Asks what is wrong with a question. Returns null when the student closes the sheet.
Future<ReportChoice?> showReportSheet(BuildContext context) => showModalBottomSheet<ReportChoice>(
  context: context,
  isScrollControlled: true,
  showDragHandle: true,
  builder: (_) => const _ReportSheet(),
);

class _ReportSheet extends StatefulWidget {
  const _ReportSheet();

  @override
  State<_ReportSheet> createState() => _ReportSheetState();
}

class _ReportSheetState extends State<_ReportSheet> {
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
      'wrong_answer' => l.reportWrongAnswer,
      'unclear' => l.reportUnclear,
      'typo' => l.reportTypo,
      _ => l.reportOther,
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
              Text(l.reportTitle, style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              RadioGroup<String>(
                groupValue: _reason,
                onChanged: (v) => setState(() => _reason = v),
                child: Column(
                  children: [
                    for (final r in reportReasons)
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
                maxLength: 500,
                maxLines: 3,
                minLines: 1,
                decoration: InputDecoration(labelText: l.reportNote),
              ),
              const SizedBox(height: 8),
              FilledButton(
                key: const ValueKey('report-send'),
                onPressed: _reason == null
                    ? null
                    : () => Navigator.of(context).pop((reason: _reason!, note: _note.text)),
                child: Text(l.reportSend),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
