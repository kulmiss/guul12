import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../app/plan_providers.dart';
import '../../app/providers.dart';
import '../../data/models.dart';
import '../../data/repository.dart';
import '../../domain/practice.dart';
import '../../domain/study_plan.dart';
import '../../l10n/generated/app_localizations.dart';
import '../practice/practice_screen.dart';
import '../widgets/common.dart';

/// The line that says where the student is on the road to the exam.
String planStageText(AppLocalizations l, StudyPlan plan) => switch (plan.stage) {
  PlanStage.noDate => l.planStageNoDate,
  PlanStage.learn => l.planStageLearn(plan.daysLeft! - StudyPlanner.revisionDays),
  PlanStage.revision => l.planStageRevision,
  PlanStage.lastDay => l.planStageLastDay,
  PlanStage.done => plan.daysLeft == 0 ? l.planStageExamDay : l.planStageOver,
};

String planTaskTitle(AppLocalizations l, PlanTask task, Catalog? catalog) {
  String chapter() {
    final id = task.chapterId ?? 0;
    final c = catalog?.chapter(id);
    return c == null ? l.examUnknownChapter(id) : l.studyChapter(c.number, c.title);
  }

  return switch (task.kind) {
    PlanTaskKind.review => l.planTaskReview(task.count ?? 0),
    PlanTaskKind.learn => l.planTaskLearn(chapter()),
    PlanTaskKind.practise => l.planTaskPractise(chapter()),
    PlanTaskKind.smart => l.planTaskSmart,
    PlanTaskKind.mock => l.planTaskMock,
  };
}

IconData planTaskIcon(PlanTaskKind kind) => switch (kind) {
  PlanTaskKind.review => Icons.event_repeat,
  PlanTaskKind.learn => Icons.menu_book_outlined,
  PlanTaskKind.practise => Icons.fitness_center,
  PlanTaskKind.smart => Icons.auto_awesome_outlined,
  PlanTaskKind.mock => Icons.assignment_outlined,
};

/// Opens the screen where the task is done.
void openPlanTask(BuildContext context, PlanTask task) {
  switch (task.kind) {
    case PlanTaskKind.review:
      context.push(PracticeScreen.playPath(PracticeMode.review));
    case PlanTaskKind.learn:
      context.push('/study/chapter/${task.chapterId}');
    case PlanTaskKind.practise:
      context.push(PracticeScreen.playPath(PracticeMode.chapter, chapterId: task.chapterId));
    case PlanTaskKind.smart:
      context.push(PracticeScreen.playPath(PracticeMode.smart));
    case PlanTaskKind.mock:
      context.go('/exams');
  }
}

/// Today's plan: the tasks with tick boxes, the daily minutes, and the pace to the exam.
class PlanScreen extends ConsumerWidget {
  const PlanScreen({super.key});

  Future<void> _toggle(WidgetRef ref, TodayPlan today, int index, bool done) async {
    final saved = today.saved;
    await ref
        .read(repositoryProvider)
        .savePlan(
          SavedPlan(
            day: saved.day,
            minutes: saved.minutes,
            tasks: saved.tasks,
            done: done ? {...saved.done, index} : ({...saved.done}..remove(index)),
          ),
        );
    ref.invalidate(todayPlanProvider);
  }

  Future<void> _setMinutes(WidgetRef ref, int minutes) async {
    await ref.read(repositoryProvider).setPlanMinutes(minutes);
    ref.invalidate(planMinutesProvider);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = context.l10n;
    final theme = Theme.of(context);
    final async = ref.watch(todayPlanProvider);
    final catalog = ref.watch(catalogProvider).value;

    return Scaffold(
      appBar: AppBar(title: Text(l.planTitle)),
      body: async.when(
        loading: () => const LoadingView(),
        error: (_, _) => MessageView(
          message: l.commonError,
          icon: Icons.error_outline,
          onRetry: () => ref.invalidate(todayPlanProvider),
        ),
        data: (today) {
          final plan = today.plan;
          final saved = today.saved;
          return ListView(
            padding: const EdgeInsets.only(bottom: 24),
            children: [
              ContentWidth(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Card(
                      margin: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(planStageText(l, plan), style: theme.textTheme.titleMedium),
                            if (plan.chaptersPerWeek != null) ...[
                              const SizedBox(height: 6),
                              Text(l.planPace(plan.chaptersPerWeek!)),
                            ],
                            if (plan.chaptersTotal > 0) ...[
                              const SizedBox(height: 6),
                              Text(l.planAtTarget(plan.chaptersAtTarget, plan.chaptersTotal)),
                            ],
                          ],
                        ),
                      ),
                    ),
                    SectionTitle(l.planMinutesLabel),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          for (final m in StudyPlanner.minutesChoices)
                            ChoiceChip(
                              label: Text(l.planMinutesValue(m)),
                              selected: saved.minutes == m,
                              onSelected: (_) => _setMinutes(ref, m),
                            ),
                        ],
                      ),
                    ),
                    SectionTitle(
                      saved.tasks.isEmpty
                          ? l.planTitle
                          : '${l.planTitle} · ${l.planSummary(saved.tasks.length, today.minutes)}',
                    ),
                    if (saved.tasks.isEmpty)
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: Text(
                          plan.stage == PlanStage.done || plan.stage == PlanStage.lastDay
                              ? planStageText(l, plan)
                              : l.planEmpty,
                        ),
                      ),
                    if (today.allDone)
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                        child: Text(
                          l.planAllDone,
                          style: theme.textTheme.titleSmall?.copyWith(
                            color: theme.colorScheme.primary,
                          ),
                        ),
                      ),
                    for (final (i, task) in saved.tasks.indexed)
                      Card(
                        margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                        child: ListTile(
                          leading: Checkbox(
                            value: saved.done.contains(i),
                            semanticLabel: l.planTaskDone,
                            onChanged: (v) => _toggle(ref, today, i, v ?? false),
                          ),
                          title: Text(
                            planTaskTitle(l, task, catalog),
                            style: saved.done.contains(i)
                                ? const TextStyle(decoration: TextDecoration.lineThrough)
                                : null,
                          ),
                          subtitle: Text(l.planMinutesValue(task.minutes)),
                          trailing: Icon(planTaskIcon(task.kind)),
                          onTap: () => openPlanTask(context, task),
                        ),
                      ),
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
