# Content (Grade 12)

All lessons and questions are **original writing** by Guul 12. Textbooks are linked, never copied.
Files are YAML. The backend checks them (`php artisan content:check`) and imports them (`php artisan content:import`).

```
content/
  grade12/
    curriculum.yaml               subjects → units → chapters (+ file of each written chapter)
    physics/u2-c1.yaml            one chapter
    physics/u2-c1.review.yaml     the independent second solve of that chapter
```

## A chapter file

```yaml
summary:
  key_points:
    - A projectile has constant horizontal velocity.
  formulas:
    - label: Range
      latex: 'R = \frac{v^2 \sin 2\theta}{g}'      # no $ here, it is already maths
solved_exercises:
  - uid: g12-physics-2-1-se01
    problem: A ball is thrown horizontally at 5 m/s from 20 m. How far does it land?
    steps:
      - 'Time: $t = \sqrt{2h/g} = 2$ s'
    answer: 10 m
flashcards:
  - {uid: g12-physics-2-1-fc01, term: Projectile, definition: An object moving only under gravity.}
passages:
  - {uid: g12-physics-2-1-ps01, title: Launch day, body: A student kicks a ball at 30 degrees.}
questions:
  - uid: g12-physics-2-1-q001
    type: single_choice
    difficulty: 2            # 1 easy … 5 hard
    stem: Which quantity stays constant for a projectile (no air)?
    options: {A: Vertical velocity, B: Horizontal velocity, C: Speed, D: Height}
    answer: B
    explanation: No horizontal force acts, so horizontal velocity does not change.
```

Every uid starts with the chapter code: `g<grade>-<subject>-<unit>-<chapter>-` (Maths units that split by stream add the stream).

| type | answer |
|---|---|
| `single_choice`, `passage_choice` (+ `passage: <uid>`), `image_choice` (+ `media:`) | a letter |
| `true_false` | `true` / `false` (no options) |
| `multiple_answers` | list of 2+ letters |
| `numeric` | `{value: 3, tolerance: 0, unit: s}` |
| `fill_in` | list of accepted answers |
| `matching` | `left: {L1: …}`, `right: {R1: …}`, `answer: {L1: R2, …}` |
| `ordering` | `items: {A: …, B: …}`, `answer: [B, A, C]` |

Maths uses `$…$` (inline) or `$$…$$` (block). Write `\$` for a real dollar sign.

## Quality gate

1. **Automatic checks** (`content:check`, also in CI): every field, option letters A–D in order, no two options alike,
   key matches an option, balanced maths, no duplicate question anywhere, right answers spread over the letters,
   explanation always present.
2. **Independent second solve**: `php artisan content:review-sheet grade12/physics/u2-c1.yaml --out=sheet.yaml`
   makes a sheet **without keys or explanations**. A separate reviewer solves it by hand (no calculator) and saves
   `u2-c1.review.yaml`. A question becomes visible (**Beta**) only when the solve of its exact current text matches the
   key. A disagreement keeps it hidden until it is fixed.
3. **Beta → Published** after enough students answer it with no open report (setting `beta_min_answers`).
   Questions almost nobody gets right are sent back to review.
4. **Wrong key found later**: fix the file, re-solve, import. Old answers are re-scored quietly.
