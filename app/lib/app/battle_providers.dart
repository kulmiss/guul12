import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/models.dart';
import 'providers.dart';

/// The student's battles (online). Uploads offline work first so results are current.
final battlesProvider = FutureProvider.autoDispose<List<BattleInfo>>((ref) async {
  final repo = ref.watch(repositoryProvider);
  await repo.tryUploadAttempts(appVersion: ref.watch(appVersionProvider));
  return repo.battles();
});
