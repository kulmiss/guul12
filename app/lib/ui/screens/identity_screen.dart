import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/providers.dart';
import '../../data/api_client.dart';
import '../avatars.dart';
import '../widgets/common.dart';

/// Same rule as the backend: 3–24 letters, numbers, spaces, dots, dashes, underscores.
final _nicknamePattern = RegExp(r'^[\p{L}\p{N} _.-]{3,24}$', unicode: true);

/// Spaces squeezed and trimmed, as the server does.
String cleanNickname(String raw) => raw.replaceAll(RegExp(r'\s+'), ' ').trim();

bool nicknameLooksValid(String raw) => _nicknamePattern.hasMatch(cleanNickname(raw));

/// Choose a nickname and a built-in avatar. Saved online only (the server checks the nickname).
class IdentityScreen extends ConsumerStatefulWidget {
  const IdentityScreen({super.key});

  @override
  ConsumerState<IdentityScreen> createState() => _IdentityScreenState();
}

class _IdentityScreenState extends ConsumerState<IdentityScreen> {
  final _nickname = TextEditingController();
  late String _style;
  late String _seed;
  String? _error;
  var _saving = false;

  @override
  void initState() {
    super.initState();
    final p = ref.read(profileProvider).value;
    _nickname.text = p?.nickname ?? '';
    final known = isBuiltInAvatar(p?.avatarStyle, p?.avatarSeed);
    _style = known ? p!.avatarStyle! : avatarStyles.first;
    _seed = known ? p!.avatarSeed! : avatarSeeds.first;
  }

  @override
  void dispose() {
    _nickname.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final l = context.l10n;
    if (!nicknameLooksValid(_nickname.text)) {
      setState(() => _error = l.identityNicknameInvalid);
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref
          .read(profileProvider.notifier)
          .saveIdentity(
            nickname: cleanNickname(_nickname.text),
            avatarStyle: _style,
            avatarSeed: _seed,
          );
      if (!mounted) return;
      messenger.showSnackBar(SnackBar(content: Text(l.identitySaved)));
      context.pop();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = e.isOffline
            ? l.identityNeedsInternet
            : e.status == 422
            ? l.identityNicknameRefused
            : l.commonError;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(l.identityTitle),
        actions: [
          TextButton(onPressed: _saving ? null : _save, child: Text(l.identitySave)),
          const SizedBox(width: 8),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.only(bottom: 32),
        children: [
          ContentWidth(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Center(
                    child: AvatarImage(style: _style, seed: _seed, size: 96),
                  ),
                  const SizedBox(height: 16),
                  Text(l.identityWhy, style: theme.textTheme.bodyMedium),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _nickname,
                    maxLength: 24,
                    textCapitalization: TextCapitalization.words,
                    decoration: InputDecoration(
                      labelText: l.identityNickname,
                      helperText: l.identityNicknameHelp,
                      helperMaxLines: 3,
                      errorText: _error,
                      errorMaxLines: 3,
                    ),
                  ),
                  const SizedBox(height: 8),
                  for (final style in avatarStyles) ...[
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        for (final (i, seed) in avatarSeeds.indexed)
                          Semantics(
                            button: true,
                            selected: style == _style && seed == _seed,
                            label: l.identityAvatarOption(
                              avatarStyles.indexOf(style) * avatarSeeds.length + i + 1,
                            ),
                            child: InkWell(
                              customBorder: const CircleBorder(),
                              onTap: () => setState(() {
                                _style = style;
                                _seed = seed;
                              }),
                              child: Container(
                                padding: const EdgeInsets.all(2),
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  border: Border.all(
                                    width: 3,
                                    color: style == _style && seed == _seed
                                        ? theme.colorScheme.primary
                                        : Colors.transparent,
                                  ),
                                ),
                                child: AvatarImage(style: style, seed: seed, size: 48),
                              ),
                            ),
                          ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
