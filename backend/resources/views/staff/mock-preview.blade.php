<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $mock->title }} · student preview</title>
    <link rel="stylesheet" href="{{ asset('katex/katex.min.css?v=0.19.1') }}">
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; color: #1f2937; background: #f8fafc; line-height: 1.5; }
        main { max-width: 40rem; margin: 0 auto; padding: 1rem; }
        .bar { position: sticky; top: 0; background: #0f766e; color: #fff; padding: .75rem 1rem; font-size: .875rem; }
        h1 { font-size: 1.25rem; margin: 1rem 0 .25rem; }
        .meta { color: #64748b; font-size: .875rem; margin-bottom: 1rem; }
        .card { background: #fff; border-radius: .5rem; padding: 1rem; margin-bottom: 1rem; box-shadow: 0 1px 2px rgb(0 0 0 / .08); }
        .num { color: #64748b; font-size: .75rem; font-weight: 700; margin-bottom: .5rem; }
        .guul-preview .gp-passage { margin-bottom: .75rem; padding: .75rem; border-radius: .375rem; font-size: .875rem; background: #f1f5f9; }
        .guul-preview .gp-title, .guul-preview .gp-key { font-weight: 700; }
        .guul-preview .gp-stem { margin-bottom: .75rem; }
        .guul-preview .gp-list { list-style: none; padding: 0; margin: 0; }
        .guul-preview .gp-option { display: flex; gap: .5rem; margin-bottom: .25rem; padding: .5rem; border: 1px solid #e2e8f0; border-radius: .375rem; font-size: .875rem; }
        .guul-preview .gp-body { flex: 1; }
        .guul-preview .gp-muted { color: #64748b; }
        .guul-preview .gp-image { display: block; max-width: 100%; margin: .5rem 0; }
    </style>
</head>
<body>
<div class="bar">Student preview — answers are hidden, nothing is saved.</div>
<main>
    <h1>{{ $mock->title }}</h1>
    <div class="meta">{{ $mock->subject->name }} · Grade {{ $mock->grade }} · {{ $mock->minutes }} minutes · {{ $questions->count() }} questions</div>
    @forelse ($questions as $i => $question)
        <div class="card">
            <div class="num">Question {{ $i + 1 }}</div>
            {!! \App\Support\QuestionPreview::html($question, withAnswers: false) !!}
        </div>
    @empty
        <div class="card">No question of this mock is shown to students yet.</div>
    @endforelse
</main>
<script src="{{ asset('katex/katex.min.js?v=0.19.1') }}"></script>
<script>
    document.querySelectorAll('.guul-tex').forEach(function (el) {
        window.katex.render(el.textContent, el, { displayMode: el.dataset.display === '1', throwOnError: false })
    })
</script>
</body>
</html>
