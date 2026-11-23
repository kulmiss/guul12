@extends('landing.layout')

@section('title', 'Delete your account – Guul 12')
@section('description', 'How to delete your Guul 12 account and data.')

@section('header')
    @include('landing._plain_header')
@endsection

@section('content')
    <h1>Delete your account</h1>
    <h2>In the app</h2>
    <ol>
        <li>Open Guul 12 and go to <strong>Me</strong>.</li>
        <li>Tap <strong>Delete my account</strong> and confirm.</li>
    </ol>
    <p>Your account, study profile, answers, results, bookmarks, nickname, battles and invites are deleted from our server straight away, and the app is reset on your phone.</p>

    <h2>Without the app</h2>
    @if ($email !== null)
        <p>Send your Student ID (Me → Your student ID) to <a href="mailto:{{ $email }}">{{ $email }}</a>. We delete the account within 7 days and reply when it is done.</p>
    @else
        <p>Our contact email is coming soon. Until then, please use the button in the app.</p>
    @endif

    <h2>What we keep</h2>
    <p>If you had Premium, our staff log keeps the student ID, plan, dates and payment reference of each Premium change, for accounting. Nothing else is kept.</p>
@endsection
