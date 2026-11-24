<?php

// Landing page texts (English). Somali and Amharic are drafts for native review.
// Pages linked from the app (/app and the legal pages) must never mention a price or how to pay.
return [
    'draft_notice' => '',
    'title' => 'Guul 12 – Ethiopia Grade 12 exam prep',
    'description' => 'Practise for the Ethiopian Grade 12 national exam: short lessons, original questions with explanations, mock exams and a daily study plan. Works offline.',
    'skip' => 'Skip to content',
    'nav_label' => 'Main menu',
    'nav_features' => 'Features',
    'nav_subjects' => 'Subjects',
    'nav_premium' => 'Premium',
    'nav_faq' => 'FAQ',
    'nav_privacy' => 'Privacy',
    'nav_terms' => 'Terms',
    'nav_delete' => 'Delete account',
    'get_app' => 'Get the app',
    'get_app_free' => 'Get Guul 12 free',

    // Hero
    'hero_eyebrow' => 'Grade 12 · National exam',
    'hero_eyebrow_year' => 'Grade 12 · National exam :year',
    'hero_title_a' => 'Pass the Grade 12 exam',
    'hero_title_b' => 'with confidence',
    'hero_body' => 'Short lessons, practice questions with clear explanations, real mock exams and a study plan for every day. Made in Ethiopia, with special care for the Somali Region.',
    'hero_more' => 'See how it works',
    'hero_chips' => ['Free to start', 'Works offline', 'Every answer checked twice'],
    'countdown' => '{1} <strong>1 day</strong> left until the national exam. Good luck!|[2,*] <strong>:count days</strong> left until the national exam. Start today.',
    'download' => 'Get it on Google Play',
    'download_small' => 'Get it on',
    'coming_soon' => 'Coming soon to Google Play',
    'coming_soon_small' => 'Coming soon to',
    'sticker_streak' => '🔥 3-day streak',
    'sticker_goal' => '✅ Goal done!',

    // Numbers (counted live from what students can open)
    'numbers_label' => 'Guul 12 in numbers',
    'numbers' => [
        'subjects' => 'subjects',
        'chapters' => 'chapters',
        'questions' => 'practice questions',
        'flashcards' => 'flashcards',
        'mocks' => 'mock exams',
    ],

    // How it works
    'steps_kicker' => 'How it works',
    'steps_title' => 'Start in three easy steps',
    'steps' => [
        ['Download the app', 'Free on Google Play. No long sign-up form: pick a nickname and an avatar.'],
        ['Choose your grade and stream', 'Natural or social science. The app sets your subjects and an exam countdown.'],
        ['Practise a little every day', 'Your plan tells you what to study today. Watch your weak chapters turn green.'],
    ],

    // Features (icon names are in the view)
    'features_kicker' => 'Features',
    'features_title' => 'Everything you need to get ready',
    'features' => [
        ['Short lessons', 'Key points, formulas and solved examples for every chapter, written for Grade 12.'],
        ['Questions with explanations', 'Original questions with clear explanations. Every answer is solved twice before you see it.'],
        ['Real exam mode', 'Full mock exams with a timer and a question map, like the national exam day.'],
        ['A plan for every day', 'Tell the app your minutes per day. It plans new chapters, weak topics and revision.'],
        ['Works without data', 'Chapters you open stay on your phone. Study at home, on the bus, anywhere.'],
        ['See your progress', 'Strong and weak chapters, streaks, badges and an estimate of your exam score.'],
        ['Friendly challenges', 'A daily challenge, quick battles and a weekly leaderboard. Nickname only.'],
        ['Private and safe', 'No chat, no contacts, no phone location. Only your nickname is ever shown.'],
    ],

    // App pictures
    'screens_kicker' => 'Inside the app',
    'screens_title' => 'See Guul 12 in action',
    'screens_label' => 'Pictures of the app (scroll sideways)',
    'screens_swipe' => 'Swipe to see more →',
    'screens_alt' => ':caption: a screen of the Guul 12 app',
    'screens' => [
        'home' => 'Your day at a glance',
        'chapter' => 'Short lessons and formulas',
        'question' => 'Clear explanations',
        'exam' => 'Real exam mode',
        'result' => 'Results by chapter',
        'progress' => 'Watch yourself grow',
        'plan' => 'Today\'s plan',
        'leaderboard' => 'Weekly leaderboard',
        'chapter_dark' => 'Day or night',
    ],

    // Subjects
    'subjects_kicker' => 'Subjects',
    'subjects_title' => 'All your Grade 12 subjects',
    'subjects_sub' => 'Natural and social science, organised by the 2023 Ministry of Education curriculum.',
    'stream_natural' => 'Natural',
    'stream_social' => 'Social',
    'stream_both' => 'Both streams',
    'subject_line' => ':stream · :chapters chapters · :questions questions',

    // Offline band
    'offline_title' => 'No data? No problem.',
    'offline_body' => 'Open a chapter once and it stays on your phone. Read lessons, practise questions and take exams anywhere: at home, on the bus, in the countryside. Your answers sync when you are online again.',
    'offline_points' => ['Study offline', 'No phone location', 'Light on data'],

    // Free and Premium
    'compare_kicker' => 'Free and Premium',
    'compare_title' => 'Free to start. Premium when you want more.',
    'compare_what' => 'What you get',
    'compare_free' => 'Free',
    'compare_premium' => 'Premium',
    'compare_yes' => 'Included',
    'compare_no' => 'Not included',
    'compare_rows' => [
        'Lessons, formulas and flashcards for every chapter',
        'Practice questions with explanations',
        'Daily study plan and exam countdown',
        'Mock exams 1 and 2 in every subject',
        'One random mock exam every day',
        'Chapters you open work offline',
        'Every mock exam (3 per subject)',
        'Exams from your mistakes and weak topics',
        'Whole subjects offline at once',
        '“Explain more” step-by-step help (AI)',
        'No ads',
    ],

    // Reviews (real ones only, added in Nova)
    'reviews_kicker' => 'Students',
    'reviews_title' => 'What students say',
    'review_grade' => 'Grade :grade',
    'review_stars' => ':count out of 5 stars',

    // FAQ (no prices, no payment: also shown on /app)
    'faq_kicker' => 'FAQ',
    'faq_title' => 'Questions students ask',
    'faq' => [
        ['Is Guul 12 free?', 'Yes. Lessons, practice questions, the study plan and two mock exams in every subject are free. Premium adds more exams, whole-subject downloads, “Explain more” and no ads.'],
        ['Does it work without internet?', 'Yes. Every chapter you open is saved on your phone, so you can study and practise without data. Your progress syncs when you are online again.'],
        ['Which students is it for?', 'Grade 12 students in Ethiopia preparing for the national exam, in natural and social science. It was made with special care for students in the Somali Region.'],
        ['Are the questions copied from textbooks?', 'No. Every lesson and question is our own writing, and every answer is solved a second time before students see it. We link to the official textbooks.'],
        ['Is my data safe?', 'We keep only what the app needs: your grade, stream, region or city and your target score. No contacts, no photos, no phone location. Others only see your nickname.'],
        ['Is this an official Ministry of Education app?', 'No. Guul 12 is an independent study app by Kulmiss.'],
    ],

    // Final call
    'final_title' => 'Your exam is coming. Start today.',
    'final_body' => 'Study a little every day with Guul 12. It is free to start.',

    'footer_tagline' => 'Exam prep for Grade 12 students in Ethiopia.',
    'footer_links' => 'More',
    'footer_langs' => 'Language',
    'footer' => '© :year Kulmiss. Guul 12 is an independent study app and is not an official Ministry of Education app.',
    'lang_name' => 'English',

    // /app (linked from the app and the summary PDFs: no prices, no payment)
    'app_title' => 'Guul 12 – the exam-prep app',
    'app_body' => 'Guul 12 helps Grade 12 students in Ethiopia prepare for the national exam with lessons, practice questions, mock exams and a daily plan.',

    // Premium part of the home page (prices and how to pay: never on /app or the legal pages)
    'plans_title' => 'Choose your plan',
    'plan_term' => 'One term',
    'plan_term_days' => ':days days',
    'plan_year' => 'One year',
    'plan_year_days' => ':days days',
    'plan_best' => 'Best value',
    'plan_includes' => 'Everything in Premium',
    'birr' => ':amount Birr',
    'how_title' => 'How to get Premium',
    'how_steps' => [
        'Open the app, go to Me and copy your Student ID (it looks like ST-7K3Q9).',
        'Pay the amount for your plan and keep the payment reference.',
        'Send us your Student ID and the payment reference.',
        'We turn on Premium. Open the app again: Premium is active.',
    ],
    'contact_title' => 'Send your Student ID',
    'contact_intro' => 'Message us with your Student ID. We answer every day.',
    'whatsapp' => 'WhatsApp: :number',
    'telegram' => 'Telegram: @:name',
    'email' => 'Email: :email',
    'contact_soon' => 'Contact details are coming soon.',
    'payment_note' => 'Payment is never made inside the app.',
];
