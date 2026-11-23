@extends('landing.layout')

{{-- Linked from the app and the Play listing: no prices and no link to the price page. --}}
@section('title', 'Privacy policy – Guul 12')
@section('description', 'What Guul 12 stores about students, why, and how to delete it.')

@section('header')
    @include('landing._plain_header')
@endsection

@section('content')
    <h1>Privacy policy</h1>
    <p class="note">Guul 12 is an exam-prep app by Kulmiss for secondary school students in Ethiopia. This page explains what we store and why. We keep as little as possible.</p>

    <h2>What we store</h2>
    <ul>
        <li><strong>Study profile</strong> you choose: grade, stream, region and city, gender (optional), target score and reminder time.</li>
        <li><strong>A random student ID</strong> (like ST-7K3Q9) and a scrambled code for each phone you use (made from a random install ID; it is not your phone number or IMEI).</li>
        <li><strong>Your study activity</strong>: answers, practice and exam results, bookmarks, reports about questions, battles, invites and “Explain more” votes.</li>
        <li><strong>Nickname and avatar</strong>, only if you choose them. They are the only things other students can ever see (on the leaderboard and in battles).</li>
        <li><strong>Google account</strong> (only if you sign in with Google): your Google account ID and email, so you can keep your progress on a new phone.</li>
        <li><strong>Premium</strong>: the plan, its dates and the payment reference you sent us.</li>
    </ul>

    <h2>What we never collect</h2>
    <p>No contacts, no location, no photos, no camera, no microphone. There is no chat between students and no public profile.</p>

    <h2>Who else receives data</h2>
    <ul>
        <li><strong>Google</strong>: Google Play (app delivery), Google sign-in (only if you use it) and Google AdMob for ads to students without Premium. Ads are non-personalised and use teen settings; AdMob may use the advertising ID of the phone. Premium students see no ads.</li>
        <li><strong>Anthropic</strong> (the AI for “Explain more”, Premium): we send the text of the question and its answer only, never your name, ID or profile.</li>
    </ul>
    <p>We never sell personal data.</p>

    <h2>On your phone</h2>
    <p>Chapters, answers and settings are kept on your phone so the app works offline. They are removed when you uninstall the app. The daily reminder is a notification made on your phone.</p>

    <h2>Security</h2>
    <p>Data travels encrypted (HTTPS). Staff can see only what their job needs, and every Premium change is logged.</p>

    <h2>Deleting your data</h2>
    <p>You can delete your account and all your study data at any time: see <a href="/delete-account">Delete your account</a>.</p>

    <h2>Age</h2>
    <p>Guul 12 is made for students aged 13 and older.</p>

    <h2>Contact</h2>
    @if ($email !== null)
        <p>Email: <a href="mailto:{{ $email }}">{{ $email }}</a></p>
    @else
        <p>Our contact email is coming soon.</p>
    @endif
    <p class="note">Last updated: 23 November 2026.</p>
@endsection
