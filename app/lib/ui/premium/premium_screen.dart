import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/providers.dart';
import '../../domain/premium_notice.dart';
import '../screens/chapter_screen.dart';
import '../widgets/accents.dart';
import '../widgets/common.dart';

/// The WhatsApp chat link with the message already typed (owner decision #40).
String whatsAppLink(String digits, String message) =>
    'https://wa.me/$digits?text=${Uri.encodeComponent(message)}';

/// The Telegram chat link (owner decision #40).
String telegramLink(String name) => 'https://t.me/$name';

/// What Premium gives, and (for free students, when set in Nova) how to ask for it on WhatsApp or
/// Telegram. Never a price (owner decision #40).
class PremiumScreen extends ConsumerWidget {
  const PremiumScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final config = ref.watch(configProvider).value;
    final profile = ref.watch(profileProvider).value;
    final now = ref.watch(clockProvider)();
    final isPremium =
        profile != null &&
        premiumNow(active: profile.premiumActive, until: profile.premiumUntil, now: now);
    final whatsapp = config?.contactWhatsapp;
    final telegram = config?.contactTelegram;
    final studentId = profile?.studentId;
    final colours = accents(context);

    final benefits = [
      (Icons.assignment_rounded, l.premiumBenefitMocks, l.premiumBenefitMocksBody),
      (Icons.download_for_offline_rounded, l.premiumBenefitOffline, l.premiumBenefitOfflineBody),
      (Icons.auto_awesome_rounded, l.premiumBenefitExplain, l.premiumBenefitExplainBody),
      (Icons.block_rounded, l.premiumBenefitNoAds, l.premiumBenefitNoAdsBody),
    ];

    return Scaffold(
      appBar: AppBar(title: Text(l.premiumPageTitle)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
        children: [
          ContentWidth(
            maxWidth: 640,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Center(
                  child: Container(
                    width: 88,
                    height: 88,
                    decoration: BoxDecoration(color: colours[1].strong, shape: BoxShape.circle),
                    child: Icon(
                      Icons.workspace_premium_rounded,
                      size: 48,
                      color: colours[1].onStrong,
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                if (isPremium)
                  _Note(text: l.premiumActiveNote, colour: colours[2])
                else
                  Text(
                    l.premiumPageIntro,
                    textAlign: TextAlign.center,
                    style: theme.textTheme.titleMedium,
                  ),
                const SizedBox(height: 16),
                for (final (i, (icon, title, body)) in benefits.indexed)
                  Card(
                    margin: const EdgeInsets.only(bottom: 10),
                    child: ListTile(
                      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                      leading: CircleAvatar(
                        backgroundColor: colours[i % colours.length].soft,
                        child: Icon(icon, color: colours[i % colours.length].onSoft),
                      ),
                      title: Text(
                        title,
                        style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      subtitle: Text(body),
                    ),
                  ),
                if (!isPremium && (whatsapp != null || telegram != null)) ...[
                  const SizedBox(height: 12),
                  Semantics(
                    header: true,
                    child: Text(
                      l.premiumContactTitle,
                      style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                    ),
                  ),
                  const SizedBox(height: 12),
                  _Step(number: 1, text: l.premiumContactStep1),
                  _Step(number: 2, text: l.premiumContactStep2),
                  if (studentId != null)
                    Padding(
                      padding: const EdgeInsets.only(left: 44, bottom: 8),
                      child: Row(
                        children: [
                          SelectableText(
                            studentId,
                            style: theme.textTheme.titleLarge?.copyWith(
                              fontWeight: FontWeight.w900,
                              letterSpacing: 1,
                            ),
                          ),
                          const SizedBox(width: 4),
                          IconButton(
                            tooltip: l.premiumContactCopy,
                            icon: const Icon(Icons.copy_rounded),
                            onPressed: () async {
                              await Clipboard.setData(ClipboardData(text: studentId));
                              if (!context.mounted) return;
                              ScaffoldMessenger.of(context)
                                  .showSnackBar(SnackBar(content: Text(l.premiumContactCopied)));
                            },
                          ),
                        ],
                      ),
                    ),
                  _Step(number: 3, text: l.premiumContactStep3),
                  const SizedBox(height: 16),
                  if (whatsapp != null)
                    FilledButton.icon(
                      key: const ValueKey('premium-whatsapp'),
                      icon: const Icon(Icons.chat_rounded),
                      label: Text(l.premiumContactWhatsApp),
                      onPressed: () => openExternal(
                        whatsAppLink(
                          whatsapp,
                          studentId == null
                              ? l.premiumContactMessageNoId
                              : l.premiumContactMessage(studentId),
                        ),
                      ),
                    ),
                  if (whatsapp != null && telegram != null) const SizedBox(height: 10),
                  if (telegram != null)
                    OutlinedButton.icon(
                      key: const ValueKey('premium-telegram'),
                      icon: const Icon(Icons.send_rounded),
                      label: Text(l.premiumContactTelegram),
                      onPressed: () => openExternal(telegramLink(telegram)),
                    ),
                ],
                if (isPremium && profile.premiumUntil != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    l.homePremiumUntil(
                      DateFormat.yMMMd(l.localeName).format(profile.premiumUntil!.toLocal()),
                    ),
                    textAlign: TextAlign.center,
                    style: theme.textTheme.bodyMedium,
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Step extends StatelessWidget {
  const _Step({required this.number, required this.text});

  final int number;
  final String text;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CircleAvatar(
            radius: 16,
            backgroundColor: scheme.primary,
            child: Text(
              '$number',
              style: TextStyle(color: scheme.onPrimary, fontWeight: FontWeight.w900),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(text, style: Theme.of(context).textTheme.bodyLarge),
            ),
          ),
        ],
      ),
    );
  }
}

class _Note extends StatelessWidget {
  const _Note({required this.text, required this.colour});

  final String text;
  final Accent colour;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(color: colour.soft, borderRadius: BorderRadius.circular(16)),
    child: Text(
      text,
      textAlign: TextAlign.center,
      style: Theme.of(context).textTheme.titleMedium
          ?.copyWith(color: colour.onSoft, fontWeight: FontWeight.w800),
    ),
  );
}
