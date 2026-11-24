<?php

namespace App\Nova;

/**
 * One line under Nova's "No … matched the given criteria" box on each list: what belongs there
 * and how it gets filled. Sent to the page as Nova.config('guulEmptyHelp') and shown by
 * resources/nova/guul-nova.js. Keys are the lists' addresses (resource uriKey).
 */
final class EmptyStates
{
    /** @return array<string, string> */
    public static function all(): array
    {
        return [
            'action-events' => 'Nova\'s own record of actions run here. It fills as staff use actions (super admins only).',
            'app-settings' => 'No setting has been changed yet, so the app uses the built-in defaults. Add one here to change a default.',
            'audit-logs' => 'Every staff action (Premium, publishing, settings …) is written here. Nothing has happened yet, or the filter hides it.',
            'battles' => 'Battles appear here when students challenge each other in the app.',
            'chapters' => 'Chapters come from the content files. Run the content import, or check the filter.',
            'feature-switches' => 'No switch has been changed yet, so every feature uses its built-in default. Add one here to turn a feature on or off.',
            'flashcards' => 'Flashcards come from the content files with each chapter.',
            'mocks' => 'Mock exams come from the content files. Run the content import, or check the filter.',
            'nickname-reports' => 'No nickname reports. Students can report a rude nickname on the leaderboard or in a battle.',
            'passages' => 'Reading passages come from the content files with their questions.',
            'payment-requests' => 'No payment requests. Use "Add payment request" when a student sends you a payment reference.',
            'premium-grants' => 'Premium given to students shows here. Give Premium from a student page or approve a payment request.',
            'question-options' => 'Answer options belong to questions. Open a question to see its options.',
            'question-reports' => 'No reports. Students can report a question from the app if they think something is wrong.',
            'questions' => 'Questions come from the content files, or use "Bulk import questions" (they start as Draft).',
            'referrals' => 'Referrals appear here when a student joins with a friend\'s invite code.',
            'solved-exercises' => 'Solved exercises come from the content files with each chapter.',
            'staff' => 'Only staff accounts are listed here. Use the create button to add one.',
            'students' => 'Students appear here after they open the app for the first time. Check the filter if you expected some.',
            'subjects' => 'Subjects come from the setup command ("php artisan db:seed") and the content import.',
            'summaries' => 'Chapter summaries come from the content files with each chapter.',
            'units' => 'Units come from the content files. Run the content import, or check the filter.',
            'website-reviews' => 'No reviews yet, so the website hides "What students say". Add only real words from a student who agreed.',
        ];
    }
}
