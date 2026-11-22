import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../data/models.dart';
import '../domain/practice.dart';
import '../ui/exams/exam_result_screen.dart';
import '../ui/exams/exam_screen.dart';
import '../ui/exams/exams_screen.dart';
import '../ui/plan/plan_screen.dart';
import '../ui/practice/player_screen.dart';
import '../ui/practice/practice_screen.dart';
import '../ui/progress/progress_screen.dart';
import '../ui/review/mistakes_screen.dart';
import '../ui/screens/chapter_screen.dart';
import '../ui/screens/home_screen.dart';
import '../ui/screens/identity_screen.dart';
import '../ui/screens/me_screen.dart';
import '../ui/screens/onboarding_screen.dart';
import '../ui/screens/study_screens.dart';
import '../ui/social/leaderboard_screen.dart';
import '../ui/widgets/common.dart';
import 'practice_set.dart';
import 'providers.dart';

/// Build number from a version like "1.0.0+7" (7). Missing → 0.
int buildNumber(String version) {
  final plus = version.indexOf('+');
  return plus < 0 ? 0 : int.tryParse(version.substring(plus + 1)) ?? 0;
}

/// Where a student must be, given what we know. Null = stay.
String? redirectFor({
  required String location,
  required AsyncValue<Profile> profile,
  required AppConfig? config,
  required int build,
}) {
  if (config != null && build > 0 && build < config.minSupportedBuild) {
    return location == '/update' ? null : '/update';
  }
  if (location == '/update') return '/loading';
  final p = profile.value;
  if (p == null) {
    // Still reading the phone's database (fast). Errors also wait here with a retry.
    return location == '/loading' ? null : '/loading';
  }
  if (!p.onboarded) return location == '/welcome' ? null : '/welcome';
  if (location == '/welcome' || location == '/loading') return '/home';
  return null;
}

int? _intParam(GoRouterState state, String name) =>
    int.tryParse(state.uri.queryParameters[name] ?? '');

int _pathId(GoRouterState state) => int.tryParse(state.pathParameters['id'] ?? '') ?? 0;

final _rootKey = GlobalKey<NavigatorState>();

final routerProvider = Provider<GoRouter>((ref) {
  // Re-check redirects only when something they depend on changes. Refreshing on every
  // profile edit would race with a screen that pops right after saving.
  final refresh = ValueNotifier<int>(0);
  ref
    ..listen(
      profileProvider.select((p) => (p.hasValue, p.hasError, p.value?.onboarded)),
      (_, _) => refresh.value++,
    )
    ..listen(configProvider.select((c) => c.value?.minSupportedBuild), (_, _) => refresh.value++)
    ..onDispose(refresh.dispose);

  final router = GoRouter(
    navigatorKey: _rootKey,
    initialLocation: '/loading',
    refreshListenable: refresh,
    redirect: (context, state) => redirectFor(
      location: state.matchedLocation,
      profile: ref.read(profileProvider),
      config: ref.read(configProvider).value,
      build: buildNumber(ref.read(appVersionProvider)),
    ),
    routes: [
      GoRoute(path: '/loading', builder: (_, _) => const _LoadingScreen()),
      GoRoute(path: '/update', builder: (_, _) => const _UpdateScreen()),
      GoRoute(path: '/welcome', builder: (_, _) => const OnboardingScreen()),
      GoRoute(
        path: '/practice',
        parentNavigatorKey: _rootKey,
        builder: (_, state) => PracticeScreen(
          chapterId: _intParam(state, 'chapter'),
          unitId: _intParam(state, 'unit'),
        ),
        routes: [
          GoRoute(
            path: 'play',
            parentNavigatorKey: _rootKey,
            builder: (_, state) => PlayerScreen(
              request: PracticeRequest(
                mode: PracticeMode.fromWire(state.uri.queryParameters['mode'] ?? ''),
                chapterId: _intParam(state, 'chapter'),
                unitId: _intParam(state, 'unit'),
              ),
            ),
          ),
        ],
      ),
      GoRoute(
        path: '/mistakes',
        parentNavigatorKey: _rootKey,
        builder: (_, _) => const MistakesScreen(),
      ),
      GoRoute(
        path: '/exam',
        parentNavigatorKey: _rootKey,
        builder: (_, _) => const ExamScreen(),
        routes: [
          GoRoute(
            path: 'result/:uuid',
            parentNavigatorKey: _rootKey,
            builder: (_, state) => ExamResultScreen(clientUuid: state.pathParameters['uuid'] ?? ''),
          ),
        ],
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => AppShell(shell: shell),
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/home',
                builder: (_, _) => const HomeScreen(),
                // Inside the Home tab, so a task can open a chapter in the Study tab.
                routes: [GoRoute(path: 'plan', builder: (_, _) => const PlanScreen())],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/study',
                builder: (_, _) => const StudyScreen(),
                routes: [
                  GoRoute(
                    path: 'subject/:id',
                    builder: (_, state) => SubjectScreen(subjectId: _pathId(state)),
                  ),
                  GoRoute(
                    path: 'chapter/:id',
                    builder: (_, state) => ChapterScreen(chapterId: _pathId(state)),
                    routes: [
                      GoRoute(
                        path: 'flashcards',
                        builder: (_, state) => FlashcardsScreen(chapterId: _pathId(state)),
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: '/exams', builder: (_, _) => const ExamsScreen())],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/progress',
                builder: (_, _) => const ProgressScreen(),
                routes: [
                  GoRoute(path: 'leaderboard', builder: (_, _) => const LeaderboardScreen()),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/me',
                builder: (_, _) => const MeScreen(),
                routes: [
                  GoRoute(path: 'profile', builder: (_, _) => const EditProfileScreen()),
                  GoRoute(path: 'identity', builder: (_, _) => const IdentityScreen()),
                ],
              ),
            ],
          ),
        ],
      ),
    ],
  );
  ref.onDispose(router.dispose);
  return router;
});

/// Bottom navigation on phones, a side rail on tablets.
class AppShell extends StatelessWidget {
  const AppShell({required this.shell, super.key});

  final StatefulNavigationShell shell;

  void _go(int index) => shell.goBranch(index, initialLocation: index == shell.currentIndex);

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final items = [
      (Icons.home_outlined, Icons.home, l.navHome),
      (Icons.menu_book_outlined, Icons.menu_book, l.navStudy),
      (Icons.assignment_outlined, Icons.assignment, l.navExams),
      (Icons.insights_outlined, Icons.insights, l.navProgress),
      (Icons.person_outline, Icons.person, l.navMe),
    ];
    if (isWide(context)) {
      return Scaffold(
        body: Row(
          children: [
            NavigationRail(
              selectedIndex: shell.currentIndex,
              onDestinationSelected: _go,
              labelType: NavigationRailLabelType.all,
              destinations: [
                for (final (icon, selected, label) in items)
                  NavigationRailDestination(
                    icon: Icon(icon),
                    selectedIcon: Icon(selected),
                    label: Text(label),
                  ),
              ],
            ),
            const VerticalDivider(width: 1),
            Expanded(child: shell),
          ],
        ),
      );
    }
    return Scaffold(
      body: shell,
      bottomNavigationBar: NavigationBar(
        selectedIndex: shell.currentIndex,
        onDestinationSelected: _go,
        destinations: [
          for (final (icon, selected, label) in items)
            NavigationDestination(icon: Icon(icon), selectedIcon: Icon(selected), label: label),
        ],
      ),
    );
  }
}

class _LoadingScreen extends ConsumerWidget {
  const _LoadingScreen();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final profile = ref.watch(profileProvider);
    return Scaffold(
      body: profile.hasError
          ? MessageView(
              message: context.l10n.commonError,
              onRetry: () => ref.invalidate(profileProvider),
            )
          : const LoadingView(),
    );
  }
}

class _UpdateScreen extends StatelessWidget {
  const _UpdateScreen();

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.system_update, size: 56, color: Theme.of(context).colorScheme.primary),
                const SizedBox(height: 16),
                Text(
                  l.updateRequiredTitle,
                  style: Theme.of(context).textTheme.headlineSmall,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 8),
                Text(l.updateRequiredBody, textAlign: TextAlign.center),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
