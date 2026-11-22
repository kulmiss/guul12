import 'package:flutter_svg/flutter_svg.dart';
import 'package:material_ui/material_ui.dart';

/// Built-in avatars (DiceBear, CC0 1.0), bundled in `assets/avatars/<style>/<seed>.svg` by
/// `tool/avatars`. Styles match the backend's `MeController::AVATAR_STYLES`.
const avatarStyles = ['notionists-neutral', 'lorelei-neutral', 'thumbs'];
final avatarSeeds = [for (var i = 1; i <= 12; i++) 'a${i.toString().padLeft(2, '0')}'];

bool isBuiltInAvatar(String? style, String? seed) =>
    style != null && seed != null && avatarStyles.contains(style) && avatarSeeds.contains(seed);

/// A round avatar. Unknown or missing avatars show a plain person icon.
class AvatarImage extends StatelessWidget {
  const AvatarImage({required this.style, required this.seed, this.size = 40, super.key});

  final String? style;
  final String? seed;
  final double size;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return ExcludeSemantics(
      child: Container(
        width: size,
        height: size,
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(color: scheme.secondaryContainer, shape: BoxShape.circle),
        child: isBuiltInAvatar(style, seed)
            ? SvgPicture.asset('assets/avatars/$style/$seed.svg', fit: BoxFit.cover)
            : Icon(Icons.person, size: size * 0.6, color: scheme.onSecondaryContainer),
      ),
    );
  }
}
