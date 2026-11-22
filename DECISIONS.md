# DECISIONS

Big decisions. Small technical choices are in [QUESTIONS.md](QUESTIONS.md).

| # | Date | Decision | Why |
|---|---|---|---|
| 1 | 2026-11-22 | App name **Guul 12**. Store title idea: "Guul 12 – Ethiopia Grade 12 Exam Prep" | No clash on Play (Arday, Imtixaan, Waaberi, Sahan, Kaabe, Fasal are taken) |
| 2 | 2026-11-22 | Design **A · Sunrise**: teal #0E5E6F, orange #F28C28, cream #FFF8EE; Nunito + Noto Sans; calmer exam screens | Friendly for teens; Noto has Ethiopic/Arabic sister fonts |
| 3 | 2026-11-22 | Summary PDFs link to a landing page **without prices**; prices only on a separate page never linked from the app or PDFs | Google Play payments policy (consumption-only app) |
| 4 | 2026-11-22 | "Explain more" uses **Claude Haiku 5.5** behind a provider interface | ~$0.0003/answer, under $20/month |
| 5 | 2026-11-22 | Aptitude is its own subject; paper score scale editable (`paper_max_score`) | Aptitude still examined; scale not confirmed |
| 6 | 2026-11-22 | Avatars: DiceBear Notionists Neutral, Lorelei Neutral, Thumbs (CC0, bundled offline) | No attribution needed |
| 7 | 2026-11-22 | Past national exam papers: owner has permission. "Past papers" section, source + year on each item, keys re-solved through the quality gate. PDFs and the permission letter stay out of the public repo | Permission confirmed by owner |
| 8 | 2026-11-22 | AdMob: age treatment TEEN, max content rating G, non-personalised ads; Play audience 13–15, 16–17, 18+ | Teen audience |
| 9 | 2026-11-22 | **No calculator** in the exam: every maths/science question must be solvable by hand | Owner confirmed |
| 10 | 2026-11-22 | Repo `kulmiss/guul12` (public for now), domain `guul12.kulmiss.com` | Owner choice |
| 11 | 2026-11-22 | Offline content: every chapter a student opens is downloaded and kept offline (free). **Whole subject packs at once = Premium** (`/sync/pack`). Changed content syncs by a change cursor, only changed chapters are re-downloaded | Fits "everything studied works offline" + "all offline packs Premium" — **confirmed by owner after Phase 1** |
| 12 | 2026-11-22 | Mathematics is one subject; Grade 11–12 units carry a stream (natural/social), Grades 9–10 units are shared | Maths splits by stream only in Grades 11–12 |
