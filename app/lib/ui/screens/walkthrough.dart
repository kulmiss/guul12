import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/providers.dart';
import '../../data/branding.dart';
import '../widgets/accents.dart';
import '../widgets/brand_logo.dart';
import '../widgets/common.dart';

/// One welcome slide: a drawing made of the brand colours, a title and one short line.
class _Slide {
  const _Slide({
    required this.main,
    required this.bubbles,
    required this.title,
    required this.body,
  });

  final IconData main;

  /// Small round icons around the main one.
  final List<IconData> bubbles;
  final String title;
  final String body;
}

/// The three welcome slides shown before the warm-up. Skip goes straight to the warm-up.
class Walkthrough extends ConsumerStatefulWidget {
  const Walkthrough({required this.onDone, super.key});

  final VoidCallback onDone;

  @override
  ConsumerState<Walkthrough> createState() => _WalkthroughState();
}

class _WalkthroughState extends ConsumerState<Walkthrough> {
  final _pages = PageController();
  int _page = 0;

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  List<_Slide> _slides(BuildContext context) {
    final l = context.l10n;
    return [
      _Slide(
        main: Icons.menu_book_rounded,
        bubbles: const [Icons.lightbulb_rounded, Icons.style_rounded, Icons.download_done_rounded],
        title: l.walk1Title,
        body: l.walk1Body,
      ),
      _Slide(
        main: Icons.fact_check_rounded,
        bubbles: const [Icons.timer_rounded, Icons.bolt_rounded, Icons.flag_rounded],
        title: l.walk2Title,
        body: l.walk2Body,
      ),
      _Slide(
        main: Icons.local_fire_department_rounded,
        bubbles: const [Icons.trending_up_rounded, Icons.star_rounded, Icons.emoji_events_rounded],
        title: l.walk3Title,
        body: l.walk3Body,
      ),
    ];
  }

  void _next(int count) {
    if (_page == count - 1) {
      widget.onDone();
      return;
    }
    final reduceMotion = MediaQuery.disableAnimationsOf(context);
    if (reduceMotion) {
      _pages.jumpToPage(_page + 1);
    } else {
      _pages.nextPage(duration: const Duration(milliseconds: 350), curve: Curves.easeOutCubic);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final slides = _slides(context);
    final last = _page == slides.length - 1;
    final brand = ref.watch(configProvider).value?.branding ?? Branding.sunrise;
    // The phone's Back button goes to the previous slide first.
    return PopScope(
      canPop: _page == 0,
      onPopInvokedWithResult: (didPop, _) {
        if (didPop || _page == 0) return;
        if (MediaQuery.disableAnimationsOf(context)) {
          _pages.jumpToPage(_page - 1);
        } else {
          _pages.previousPage(duration: const Duration(milliseconds: 300), curve: Curves.easeOut);
        }
      },
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 8, 8, 0),
            child: Row(
              children: [
                const BrandLogo(size: 32),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    brandName(l, brand),
                    style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
                  ),
                ),
                // Keeps the row height when Skip is hidden on the last slide.
                SizedBox(
                  height: 48,
                  child: last
                      ? null
                      : TextButton(onPressed: widget.onDone, child: Text(l.walkSkip)),
                ),
              ],
            ),
          ),
          Expanded(
            child: PageView.builder(
              controller: _pages,
              itemCount: slides.length,
              onPageChanged: (p) => setState(() => _page = p),
              itemBuilder: (_, i) => _SlideView(slide: slides[i], index: i),
            ),
          ),
          Semantics(
            label: l.walkPage(_page + 1, slides.length),
            child: ExcludeSemantics(
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  for (var i = 0; i < slides.length; i++)
                    AnimatedContainer(
                      duration: const Duration(milliseconds: 250),
                      margin: const EdgeInsets.symmetric(horizontal: 4),
                      width: i == _page ? 24 : 8,
                      height: 8,
                      decoration: BoxDecoration(
                        color: i == _page
                            ? theme.colorScheme.primary
                            : theme.colorScheme.outlineVariant,
                        borderRadius: BorderRadius.circular(4),
                      ),
                    ),
                ],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 16),
            child: ContentWidth(
              maxWidth: 560,
              child: SizedBox(
                width: double.infinity,
                child: FilledButton(
                  key: const ValueKey('walkthrough-next'),
                  onPressed: () => _next(slides.length),
                  child: Text(last ? l.walkStart : l.walkNext),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SlideView extends StatelessWidget {
  const _SlideView({required this.slide, required this.index});

  final _Slide slide;
  final int index;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return LayoutBuilder(
      builder: (context, box) {
        // Smaller drawing on short screens so the text always fits.
        final art = (box.maxHeight * 0.48).clamp(160.0, 260.0);
        return SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: ContentWidth(
            maxWidth: 560,
            child: Column(
              children: [
                const SizedBox(height: 16),
                _Drawing(slide: slide, size: art, index: index),
                const SizedBox(height: 24),
                Semantics(
                  header: true,
                  child: Text(
                    slide.title,
                    textAlign: TextAlign.center,
                    style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
                  ),
                ),
                const SizedBox(height: 12),
                Text(slide.body, textAlign: TextAlign.center, style: theme.textTheme.bodyLarge),
              ],
            ),
          ),
        );
      },
    );
  }
}

/// A round "sun" in the brand colours with a big icon and three small icons around it. The small
/// ones pop in once when the slide is first shown (no looping animation).
class _Drawing extends StatelessWidget {
  const _Drawing({required this.slide, required this.size, required this.index});

  final _Slide slide;
  final double size;
  final int index;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final reduceMotion = MediaQuery.disableAnimationsOf(context);
    // Each slide leads with a different colour, so the three feel different.
    final colours = accents(context);
    final lead = colours[index % 3].strong;
    final onLead = colours[index % 3].onStrong;
    // Where the small icons sit, as a fraction of the drawing (left, top).
    const spots = [(0.02, 0.10), (0.74, 0.02), (0.70, 0.74)];
    final bubble = size * 0.24;
    return ExcludeSemantics(
      child: SizedBox(
        width: size,
        height: size,
        child: Stack(
          children: [
            Positioned.fill(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: lead.withValues(alpha: 0.12),
                ),
              ),
            ),
            Positioned.fill(
              child: Padding(
                padding: EdgeInsets.all(size * 0.12),
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: lead.withValues(alpha: 0.18),
                  ),
                ),
              ),
            ),
            Center(
              child: Container(
                width: size * 0.42,
                height: size * 0.42,
                decoration: BoxDecoration(
                  color: lead,
                  borderRadius: BorderRadius.circular(size * 0.12),
                  boxShadow: [
                    BoxShadow(
                      color: lead.withValues(alpha: 0.35),
                      blurRadius: 24,
                      offset: const Offset(0, 10),
                    ),
                  ],
                ),
                child: Icon(slide.main, size: size * 0.24, color: onLead),
              ),
            ),
            for (var i = 0; i < slide.bubbles.length; i++)
              Positioned(
                left: size * spots[i].$1,
                top: size * spots[i].$2,
                child: TweenAnimationBuilder<double>(
                  tween: Tween(begin: reduceMotion ? 1 : 0, end: 1),
                  duration: Duration(milliseconds: reduceMotion ? 0 : 450 + i * 150),
                  curve: Curves.easeOutBack,
                  builder: (_, t, child) => Transform.scale(scale: t, child: child),
                  child: Container(
                    width: bubble,
                    height: bubble,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: colours[(index + i + 1) % 3].strong,
                      border: Border.all(color: scheme.surface, width: 3),
                    ),
                    child: Icon(
                      slide.bubbles[i],
                      size: bubble * 0.5,
                      color: colours[(index + i + 1) % 3].onStrong,
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
