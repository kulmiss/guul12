@extends('landing.layout')

@section('title', 'Terms of use – Guul 12')
@section('description', 'The rules for using Guul 12.')

@section('header')
    @include('landing._plain_header')
@endsection

@section('content')
    <article class="wrap narrow doc">
    <h1>Terms of use</h1>
    <ol>
        <li>Guul 12 helps you practise for the Ethiopian Grade 12 national exam. It is an independent study app and not an official Ministry of Education or exam-service app. We cannot promise any exam result.</li>
        <li>Lessons and questions are written by us. We check every answer twice, but mistakes can happen: please report them in the app and we will fix them.</li>
        <li>“Explain more” answers are written by AI and may contain mistakes.</li>
        <li>Use a respectful nickname. Nicknames that insult or show personal details can be removed.</li>
        <li>Do not try to cheat the leaderboard, battles or invites. Unfair results can be removed.</li>
        <li>Premium is given for the plan and dates you paid for. Payment is never made inside the app.</li>
        <li>You may delete your account at any time (see <a href="/delete-account">Delete your account</a>).</li>
        <li>We may update these terms; the date below shows the last change.</li>
    </ol>
    @if ($email !== null)
        <p>Questions: <a href="mailto:{{ $email }}">{{ $email }}</a></p>
    @endif
    <p class="note">Last updated: 23 November 2026.</p>
    </article>
@endsection
