# Textbook review exercises

The solved review exercise at the end of each textbook unit (decision #44, QUESTIONS #78).

## Where the files go

```
content/textbook/grade12/mathematics/review-u1.yaml   (Unit 1)
content/textbook/grade12/mathematics/review-u2.yaml   (Unit 2)
...
```

`content/textbook/` is **ignored by git** while the repository is public: the files hold the
real textbook questions. Keep them on your PC and on the server, and back them up.

## Import

Run the normal content import first (so the units exist), then:

```bash
cd backend
php artisan content:import-textbook
```

Every file is checked first. If any file has a problem, nothing is imported and every problem
is listed. Running it again changes only what changed; a removed file removes that unit's review.

## File format

```yaml
source:
  book: Mathematics Student Textbook, Grade 12 (Ministry of Education, 2023)
  unit: 1                       # must match the number in the file name
  pages: 57–60
  conventions: '...'            # optional: formulas the solutions use (shown in the app)
items:
  - ref: "1"
    question: 'Question text, word for word, maths in $...$'
    table: {Class: [15-20, 20-25], f: [8, 21]}   # optional, rows the same length
    figure: Figure 2.40          # optional
    note: '...'                  # optional
    book_error: true             # optional: cannot be solved as printed (explain in note)
    steps: ['step 1', 'step 2']
    answer: 'final answer'
    parts:                       # optional: a, b, c ...
      - ref: a
        question: '...'
        steps: ['...']
        answer: '...'
```

## In the app

Study → subject → unit → **Review exercise (textbook)**. Solutions stay hidden until the student
taps **Show solution**. The review works offline after it was opened once.
