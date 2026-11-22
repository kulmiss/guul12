// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appName => 'Guul 12';

  @override
  String get commonContinue => 'Continue';

  @override
  String get commonSkip => 'Skip';

  @override
  String get commonRetry => 'Try again';

  @override
  String get commonDone => 'Done';

  @override
  String get commonNext => 'Next';

  @override
  String get commonPrevious => 'Previous';

  @override
  String get commonCheck => 'Check';

  @override
  String get commonCopied => 'Copied';

  @override
  String get commonOffline => 'You are offline. Your progress is saved and will sync later.';

  @override
  String get commonError => 'Something went wrong. Please try again.';

  @override
  String get commonLoading => 'Loading…';

  @override
  String get premiumLabel => 'Premium';

  @override
  String get betaLabel => 'Beta';

  @override
  String get onboardingGradeTitle => 'Which grade are you in?';

  @override
  String onboardingGradeOption(int grade) {
    return 'Grade $grade';
  }

  @override
  String get onboardingHookTitle => 'Quick warm-up: 5 questions';

  @override
  String onboardingHookResult(int correct, int total) {
    return 'You got $correct of $total!';
  }

  @override
  String get onboardingHookResultBody => 'Let\'s set up your plan so you can do even better.';

  @override
  String get onboardingStreamTitle => 'Which stream are you in?';

  @override
  String get streamNatural => 'Natural Science';

  @override
  String get streamSocial => 'Social Science';

  @override
  String get onboardingRetakerTitle => 'Are you taking the national exam again?';

  @override
  String get onboardingRetakerYes => 'Yes, I am retaking it';

  @override
  String get onboardingRetakerNo => 'No, first time';

  @override
  String get onboardingRegionTitle => 'Where do you study?';

  @override
  String get onboardingRegionLabel => 'Region';

  @override
  String get onboardingCityLabel => 'City or town';

  @override
  String get onboardingGenderTitle => 'Gender (optional)';

  @override
  String get genderFemale => 'Female';

  @override
  String get genderMale => 'Male';

  @override
  String get onboardingPrivacyNote =>
      'Used only for anonymous statistics. Never shown to other students.';

  @override
  String get onboardingTargetTitle => 'What is your target score?';

  @override
  String onboardingTargetValue(int score) {
    return '$score / 600';
  }

  @override
  String get onboardingReminderTitle => 'When should we remind you to study?';

  @override
  String get onboardingReminderPick => 'Pick a time';

  @override
  String get onboardingFinish => 'Start studying';

  @override
  String get navHome => 'Home';

  @override
  String get navStudy => 'Study';

  @override
  String get navMe => 'Me';

  @override
  String get homeGreeting => 'Welcome back';

  @override
  String homeExamCountdown(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: '$days days to the exam',
      one: '1 day to the exam',
      zero: 'Exam is today',
    );
    return '$_temp0';
  }

  @override
  String homeExamDates(String gregorian, String ethiopian) {
    return '$gregorian · $ethiopian E.C.';
  }

  @override
  String get homeExamDateUnknown => 'Exam date not announced yet';

  @override
  String get homeQuick10 => 'Quick 10';

  @override
  String get homeQuick10Body => '10 mixed questions from what you have studied';

  @override
  String get homeContinueStudying => 'Continue studying';

  @override
  String get homeNoContentYet => 'Open a subject in Study to download it for offline use.';

  @override
  String get studyTitle => 'Study';

  @override
  String studyGrade(int grade) {
    return 'Grade $grade';
  }

  @override
  String studyUnit(int number, String title) {
    return 'Unit $number: $title';
  }

  @override
  String studyChapter(int number, String title) {
    return '$number. $title';
  }

  @override
  String get studyEmpty => 'Content for this part is coming soon.';

  @override
  String get studyCatalogEmpty => 'Connect to the internet once to download the list of subjects.';

  @override
  String get studyDownloadedOffline => 'Saved for offline use';

  @override
  String get chapterSummary => 'Summary';

  @override
  String get chapterKeyPoints => 'Key points';

  @override
  String get chapterFormulas => 'Formulas';

  @override
  String get chapterSolvedExercises => 'Solved exercises';

  @override
  String chapterSolvedStep(int number) {
    return 'Step $number';
  }

  @override
  String get chapterShowSolution => 'Show solution';

  @override
  String get chapterAnswer => 'Answer';

  @override
  String get chapterFlashcards => 'Flashcards';

  @override
  String get chapterFlashcardHint => 'Tap to flip · swipe for the next card';

  @override
  String chapterFlashcardCount(int current, int total) {
    return '$current / $total';
  }

  @override
  String get chapterQuiz => 'Chapter quiz';

  @override
  String chapterQuizCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count questions',
      one: '1 question',
    );
    return '$_temp0';
  }

  @override
  String get chapterSummaryPdf => 'Chapter summary (PDF)';

  @override
  String get chapterTextbook => 'Official textbook (PDF)';

  @override
  String get chapterVideo => 'Video lesson';

  @override
  String get practiceTitle => 'Practice';

  @override
  String get practiceModeQuick10 => 'Quick 10';

  @override
  String get practiceModeChapter => 'This chapter';

  @override
  String get practiceModeUnit => 'Whole unit';

  @override
  String get practiceModeMixed => 'Mixed';

  @override
  String get practiceModeTimed => 'Timed (1 minute each)';

  @override
  String get practiceModeWrongOnly => 'Only my wrong answers';

  @override
  String get practiceModeQuickRevise => 'Quick revise (5 minutes)';

  @override
  String get practiceNoQuestions => 'No questions here yet. Study a chapter first.';

  @override
  String get practiceNoWrongAnswers => 'No wrong answers to practise. Well done!';

  @override
  String practiceQuestionOf(int current, int total) {
    return 'Question $current of $total';
  }

  @override
  String get practiceCorrect => 'Correct!';

  @override
  String get practiceWrong => 'Not quite';

  @override
  String practiceCorrectAnswer(String answer) {
    return 'Correct answer: $answer';
  }

  @override
  String get practiceExplanation => 'Explanation';

  @override
  String practiceTimeLeft(int seconds) {
    return '${seconds}s';
  }

  @override
  String get practiceTimeUp => 'Time is up';

  @override
  String get practiceReadAloud => 'Read aloud';

  @override
  String get practiceBookmark => 'Bookmark';

  @override
  String get practiceBookmarked => 'Bookmarked';

  @override
  String get practiceTrue => 'True';

  @override
  String get practiceFalse => 'False';

  @override
  String get practiceTypeAnswer => 'Type your answer';

  @override
  String get practiceTypeNumber => 'Type a number';

  @override
  String practiceUnit(String unit) {
    return 'Unit: $unit';
  }

  @override
  String get practiceChooseAll => 'Choose all that apply';

  @override
  String get practiceMatchHint => 'Tap an item on the left, then its match on the right';

  @override
  String get practiceOrderHint => 'Drag to put the items in the right order';

  @override
  String get practiceMoveUp => 'Move up';

  @override
  String get practiceMoveDown => 'Move down';

  @override
  String get practiceReadPassage => 'Read the passage';

  @override
  String get practiceResultTitle => 'Practice finished';

  @override
  String practiceResultScore(int correct, int total) {
    return '$correct / $total correct';
  }

  @override
  String get practiceResultSaved => 'Saved. It will sync when you are online.';

  @override
  String get practiceResultSynced => 'Saved and synced.';

  @override
  String get practiceAgain => 'Practise again';

  @override
  String get meTitle => 'Me';

  @override
  String get meStudentId => 'Your student ID';

  @override
  String get meStudentIdHelp => 'Private. Keep it for support.';

  @override
  String get meCopy => 'Copy';

  @override
  String mePremiumUntil(String date) {
    return 'Premium until $date';
  }

  @override
  String get meFree => 'Free plan';

  @override
  String get meSignIn => 'Sign in with Google';

  @override
  String get meSignInWhy => 'Keep your progress if you change phones.';

  @override
  String get meSignedIn => 'Signed in';

  @override
  String get meSignInFailed => 'Sign-in did not work. Please try again.';

  @override
  String get meSignInConflict => 'This phone is already signed in with another Google account.';

  @override
  String get meSettings => 'Settings';

  @override
  String get meTheme => 'Theme';

  @override
  String get meThemeSystem => 'Same as phone';

  @override
  String get meThemeLight => 'Light';

  @override
  String get meThemeDark => 'Dark';

  @override
  String get meGrade => 'Grade';

  @override
  String get meStream => 'Stream';

  @override
  String get meEditProfile => 'Edit study profile';

  @override
  String meVersion(String version) {
    return 'Version $version';
  }

  @override
  String ethiopianMonth(String month) {
    String _temp0 = intl.Intl.selectLogic(month, {
      'm1': 'Meskerem',
      'm2': 'Tikimt',
      'm3': 'Hidar',
      'm4': 'Tahsas',
      'm5': 'Tir',
      'm6': 'Yekatit',
      'm7': 'Megabit',
      'm8': 'Miyazya',
      'm9': 'Ginbot',
      'm10': 'Sene',
      'm11': 'Hamle',
      'm12': 'Nehase',
      'm13': 'Pagume',
      'other': '?',
    });
    return '$_temp0';
  }

  @override
  String ethiopianDate(String month, int day, int year) {
    return '$month $day, $year';
  }

  @override
  String onboardingStep(int current, int total) {
    return 'Step $current of $total';
  }

  @override
  String get onboardingCityHint => 'For example: Jijiga';

  @override
  String get onboardingReminderNone => 'No reminder';

  @override
  String onboardingReminderAt(String time) {
    return 'Every day at $time';
  }

  @override
  String get onboardingWelcomeTitle => 'Pass the national exam with Guul 12';

  @override
  String get onboardingWelcomeBody =>
      'Short lessons, smart practice and mock exams. Works offline.';

  @override
  String get onboardingStart => 'Get started';

  @override
  String regionName(String code) {
    String _temp0 = intl.Intl.selectLogic(code, {
      'somali': 'Somali',
      'addis_ababa': 'Addis Ababa',
      'afar': 'Afar',
      'amhara': 'Amhara',
      'benishangul_gumuz': 'Benishangul-Gumuz',
      'central_ethiopia': 'Central Ethiopia',
      'dire_dawa': 'Dire Dawa',
      'gambela': 'Gambela',
      'harari': 'Harari',
      'oromia': 'Oromia',
      'sidama': 'Sidama',
      'south_ethiopia': 'South Ethiopia',
      'south_west_ethiopia': 'South West Ethiopia',
      'tigray': 'Tigray',
      'other': 'Other',
    });
    return '$_temp0';
  }

  @override
  String get updateRequiredTitle => 'Please update Guul 12';

  @override
  String get updateRequiredBody =>
      'This version is too old. Update the app from Google Play to continue.';

  @override
  String get studyNoChapters => 'No chapters yet.';

  @override
  String get studyPremiumLocked => '🔒 Premium';

  @override
  String get chapterPractice => 'Practise this chapter';

  @override
  String get chapterNoSummary => 'The summary for this chapter is coming soon.';

  @override
  String get chapterOffline => 'Connect to the internet once to download this chapter.';

  @override
  String get practiceChooseMode => 'How do you want to practise?';

  @override
  String get practiceFinish => 'Finish';

  @override
  String get practiceReview => 'Review your answers';

  @override
  String practiceYourAnswer(String answer) {
    return 'Your answer: $answer';
  }

  @override
  String get practiceNoAnswer => 'No answer';

  @override
  String get practiceLeaveTitle => 'Stop practising?';

  @override
  String get practiceLeaveBody => 'Answers so far will not be saved.';

  @override
  String get practiceLeave => 'Stop';

  @override
  String get practiceStay => 'Keep going';

  @override
  String practicePairs(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count pairs made',
      one: '1 pair made',
    );
    return '$_temp0';
  }

  @override
  String get practiceClearPairs => 'Clear pairs';

  @override
  String get meSignInUnavailable => 'Google sign-in is not available yet.';

  @override
  String get meRegion => 'Region';

  @override
  String get meTarget => 'Target score';

  @override
  String get meReminder => 'Study reminder';

  @override
  String get meRetaker => 'Retaking the exam';

  @override
  String get meGender => 'Gender';

  @override
  String get meNotSet => 'Not set';

  @override
  String get meStudentIdPending => 'Connect to the internet once to get your student ID.';

  @override
  String get meSave => 'Save';

  @override
  String get practicePickAnswer => 'Choose or type an answer first.';

  @override
  String get meStudyProfile => 'Study profile';

  @override
  String get practiceReport => 'Report a problem';

  @override
  String get reportTitle => 'What is wrong with this question?';

  @override
  String get reportWrongAnswer => 'The answer is wrong';

  @override
  String get reportUnclear => 'The question is unclear';

  @override
  String get reportTypo => 'There is a typo';

  @override
  String get reportOther => 'Something else';

  @override
  String get reportNote => 'Tell us more (optional)';

  @override
  String get reportSend => 'Send';

  @override
  String get reportThanks => 'Thank you! We will check this question.';

  @override
  String get navExams => 'Exams';

  @override
  String get examsTitle => 'Exams';

  @override
  String get examsMocks => 'Mock exams';

  @override
  String get examsMore => 'More exams';

  @override
  String get examsPast => 'Your results';

  @override
  String examsMockInfo(int questions, int minutes) {
    String _temp0 = intl.Intl.pluralLogic(
      questions,
      locale: localeName,
      other: '$questions questions',
      one: '1 question',
    );
    return '$_temp0 · $minutes min';
  }

  @override
  String get examsRandom => 'Random mock';

  @override
  String get examsRandomBody => '50 questions from the whole subject. One free each day.';

  @override
  String get examsWeak => 'My weak topics';

  @override
  String get examsWeakBody => 'Questions from the chapters where you score lowest.';

  @override
  String get examsMistakes => 'My mistakes';

  @override
  String get examsMistakesBody => 'Questions you got wrong before.';

  @override
  String get examsNoMocks => 'Mock exams appear here when you are online.';

  @override
  String get examsContinue => 'Continue your exam';

  @override
  String examsContinueBody(String title, String time) {
    return '$title · $time left';
  }

  @override
  String examsResultLine(int correct, int total, String date) {
    return '$correct / $total · $date';
  }

  @override
  String get examsFinishFirst => 'Finish or submit your current exam first.';

  @override
  String get examsDailyLimit => 'Today\'s free random mock is used. A new one is ready tomorrow.';

  @override
  String get examsNeedPractice => 'Practise some questions in this subject first.';

  @override
  String get examsNoQuestions => 'There are no questions for this exam yet.';

  @override
  String get examsOffline => 'No internet. Connect and try again.';

  @override
  String get examIntroRules => 'Real Exam Mode';

  @override
  String get examIntroTimer => 'The timer starts now and does not stop, even if you leave the app.';

  @override
  String get examIntroNoFeedback => 'You see right and wrong answers only at the end.';

  @override
  String get examIntroChange => 'You can change answers and flag questions to check again.';

  @override
  String get examIntroAuto => 'When the time is up, the exam is submitted for you.';

  @override
  String get examStart => 'Start exam';

  @override
  String examQuestionOf(int current, int total) {
    return 'Question $current of $total';
  }

  @override
  String examTimeLeft(String time) {
    return 'Time left: $time';
  }

  @override
  String get examFlag => 'Flag for review';

  @override
  String get examUnflag => 'Remove flag';

  @override
  String get examMap => 'All questions';

  @override
  String get examClear => 'Clear answer';

  @override
  String get examPrevious => 'Previous';

  @override
  String get examSubmit => 'Submit exam';

  @override
  String get examSubmitTitle => 'Submit the exam?';

  @override
  String examSubmitBody(int answered, int total, int unanswered, int flagged) {
    return 'Answered: $answered of $total\nNot answered: $unanswered\nFlagged: $flagged';
  }

  @override
  String get examKeepGoing => 'Keep going';

  @override
  String get examLeaveTitle => 'Leave the exam?';

  @override
  String get examLeaveBody => 'The timer keeps running. You can come back from the Exams tab.';

  @override
  String get examLeave => 'Leave';

  @override
  String get examTimeUp => 'Time is up. Your exam was submitted.';

  @override
  String get examMapAnswered => 'Answered';

  @override
  String get examMapFlagged => 'Flagged';

  @override
  String get examMapEmpty => 'Not answered';

  @override
  String get examResultTitle => 'Exam result';

  @override
  String examResultPercent(int percent) {
    return '$percent% right';
  }

  @override
  String examResultEstimate(int low, int high, int max) {
    return 'Estimated exam score: $low–$high out of $max';
  }

  @override
  String get examResultEstimateNote => 'An estimate from this practice, not a promise.';

  @override
  String examResultTime(String time) {
    return 'Time used: $time';
  }

  @override
  String examResultBetterThan(int percent) {
    return 'Better than $percent% of Guul 12 students';
  }

  @override
  String get examResultNotEnough =>
      'Not enough students have taken this mock yet. Check again later.';

  @override
  String get examResultConnect =>
      'Saved on your phone. Connect to the internet to compare with other students.';

  @override
  String get examResultRefresh => 'Check again';

  @override
  String get examResultChapters => 'By chapter';

  @override
  String examResultChapterScore(int correct, int total) {
    return '$correct / $total';
  }

  @override
  String get examResultReview => 'Review answers';

  @override
  String get examResultHideReview => 'Hide answers';

  @override
  String get examResultPractiseWeakest => 'Practise your weakest chapter';

  @override
  String get examResultNotFound => 'This result is not on this phone.';

  @override
  String examUnknownChapter(int id) {
    return 'Chapter $id';
  }

  @override
  String get practiceModeReview => 'Review due questions';

  @override
  String get practiceNothingToReview => 'Nothing to review today. Well done!';

  @override
  String homeReviewDue(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count questions to review today',
      one: '1 question to review today',
    );
    return '$_temp0';
  }

  @override
  String get homeReviewBody => 'Mistakes come back after 1, 3 and 7 days until you know them.';

  @override
  String get homeMistakes => 'My mistakes';

  @override
  String homeMistakesBody(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count questions to learn',
      one: '1 question to learn',
    );
    return '$_temp0';
  }

  @override
  String get mistakesTitle => 'My mistakes';

  @override
  String get mistakesEmpty => 'No mistakes yet. Questions you get wrong appear here.';

  @override
  String get mistakesPractise => 'Practise my mistakes';

  @override
  String get mistakesReviewToday => 'Review today';

  @override
  String mistakesNextReview(String date) {
    return 'Next review: $date';
  }

  @override
  String get practiceModeSmart => 'Smart practice';

  @override
  String get homeSmartBody =>
      '10 questions picked for you: due reviews and your weak chapters first.';

  @override
  String get navProgress => 'Progress';

  @override
  String get progressTitle => 'Progress';

  @override
  String progressAnswered(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count questions answered',
      one: '1 question answered',
    );
    return '$_temp0';
  }

  @override
  String progressAccuracy(int percent) {
    return '$percent% right (last answer to each question)';
  }

  @override
  String get progressEmpty =>
      'Study a chapter and answer some questions to see your progress here.';

  @override
  String get progressWeak => 'Weak topics';

  @override
  String get progressWeakNone => 'No weak topics right now. Keep practising!';

  @override
  String get progressStrength => 'Chapter strength';

  @override
  String progressChapterInfo(int score, int answered) {
    return '$score/100 · $answered answered';
  }

  @override
  String progressSubjectStarted(int started, int total) {
    String _temp0 = intl.Intl.pluralLogic(
      total,
      locale: localeName,
      other: '$total chapters',
      one: '1 chapter',
    );
    return '$started of $_temp0 started';
  }

  @override
  String get progressPractise => 'Practise';

  @override
  String get masteryNotStarted => 'Not started';

  @override
  String get masteryLearning => 'Learning';

  @override
  String get masteryGood => 'Good';

  @override
  String get masteryStrong => 'Strong';

  @override
  String get planTitle => 'Today\'s plan';

  @override
  String planSummary(int count, int minutes) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count tasks',
      one: '1 task',
    );
    return '$_temp0 · $minutes min';
  }

  @override
  String get planAllDone => 'All done for today. Great work!';

  @override
  String get planMinutesLabel => 'Minutes a day';

  @override
  String planMinutesValue(int minutes) {
    return '$minutes min';
  }

  @override
  String planTaskReview(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Review $count due questions',
      one: 'Review 1 due question',
    );
    return '$_temp0';
  }

  @override
  String planTaskLearn(String chapter) {
    return 'Study $chapter';
  }

  @override
  String planTaskPractise(String chapter) {
    return 'Practise $chapter';
  }

  @override
  String get planTaskSmart => 'Smart practice';

  @override
  String get planTaskMock => 'Full mock exam';

  @override
  String get planTaskDone => 'Done';

  @override
  String get planStageNoDate => 'No exam date yet. Learn a little every day.';

  @override
  String planStageLearn(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: 'Learning time. Revision starts in $days days.',
      one: 'Learning time. Revision starts tomorrow.',
    );
    return '$_temp0';
  }

  @override
  String get planStageRevision => 'Revision time: weak chapters first and a mock exam every week.';

  @override
  String get planStageLastDay => 'The exam is tomorrow. Do a short review, then rest well.';

  @override
  String get planStageExamDay => 'Exam day. Good luck!';

  @override
  String get planStageOver => 'The exam date has passed.';

  @override
  String planPace(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Study about $count new chapters a week to finish before revision.',
      one: 'Study about 1 new chapter a week to finish before revision.',
    );
    return '$_temp0';
  }

  @override
  String planAtTarget(int done, int total) {
    return '$done of $total chapters at your target';
  }

  @override
  String get planEmpty => 'Nothing to plan yet. Open a subject in Study to begin.';

  @override
  String streakDays(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: '$days-day streak',
      one: '1-day streak',
      zero: 'No streak yet',
    );
    return '$_temp0';
  }

  @override
  String streakToday(int count, int goal) {
    return '$count / $goal questions today';
  }

  @override
  String get streakGoalDone => 'Daily goal reached';

  @override
  String engagementBest(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: 'Best: $days days',
      one: 'Best: 1 day',
    );
    return '$_temp0';
  }

  @override
  String engagementXp(int xp) {
    return '$xp XP';
  }

  @override
  String engagementWeekXp(int xp) {
    return '$xp XP this week';
  }

  @override
  String get badgesTitle => 'Badges';

  @override
  String badgesEarned(int count, int total) {
    return '$count of $total earned';
  }

  @override
  String badgeProgress(int value, int goal) {
    return '$value / $goal';
  }

  @override
  String get badgeEarned => 'Earned';

  @override
  String get badgeFirstSteps => 'First steps';

  @override
  String get badgeFirstStepsBody => 'Answer 10 questions';

  @override
  String get badgeHundred => 'Hundred';

  @override
  String get badgeHundredBody => 'Answer 100 questions';

  @override
  String get badgeThousand => 'Thousand';

  @override
  String get badgeThousandBody => 'Answer 1,000 questions';

  @override
  String get badgeStreak3 => '3 days in a row';

  @override
  String get badgeStreak3Body => 'Reach the daily goal 3 days in a row';

  @override
  String get badgeStreak7 => 'One week';

  @override
  String get badgeStreak7Body => 'Reach the daily goal 7 days in a row';

  @override
  String get badgeStreak30 => 'One month';

  @override
  String get badgeStreak30Body => 'Reach the daily goal 30 days in a row';

  @override
  String get badgeFirstExam => 'First exam';

  @override
  String get badgeFirstExamBody => 'Finish a mock exam';

  @override
  String get badgeExamStar => 'Exam star';

  @override
  String get badgeExamStarBody => 'Score 80% or more in an exam';

  @override
  String get badgeStrongChapter => 'Strong chapter';

  @override
  String get badgeStrongChapterBody => 'Make a chapter Strong';

  @override
  String get badgeStrongTen => 'Ten strong';

  @override
  String get badgeStrongTenBody => 'Make 10 chapters Strong';

  @override
  String get reminderTitle => 'Time to practise';

  @override
  String reminderKeepStreak(int streak, int remaining) {
    String _temp0 = intl.Intl.pluralLogic(
      remaining,
      locale: localeName,
      other: 'Keep your $streak-day streak: $remaining more questions today.',
      one: 'Keep your $streak-day streak: 1 more question today.',
    );
    return '$_temp0';
  }

  @override
  String reminderStart(int remaining) {
    String _temp0 = intl.Intl.pluralLogic(
      remaining,
      locale: localeName,
      other: '$remaining questions today to reach your daily goal.',
      one: '1 more question today to reach your daily goal.',
    );
    return '$_temp0';
  }

  @override
  String get reminderChannel => 'Daily reminder';

  @override
  String get identityTitle => 'Nickname and avatar';

  @override
  String get identityWhy =>
      'Only your nickname and avatar are shown on the leaderboard and in battles. Your grade, region and other details are never shown.';

  @override
  String get identityNickname => 'Nickname';

  @override
  String get identityNicknameHelp =>
      '3 to 24 letters, numbers, spaces, dots, dashes or underscores. Don\'t use your real full name.';

  @override
  String get identityNicknameInvalid =>
      'Use 3 to 24 letters, numbers, spaces, dots, dashes or underscores.';

  @override
  String get identityNicknameRefused => 'Please choose another nickname.';

  @override
  String get identityAvatar => 'Avatar';

  @override
  String identityAvatarOption(int number) {
    return 'Avatar $number';
  }

  @override
  String get identitySave => 'Save';

  @override
  String get identityNeedsInternet =>
      'Saving your nickname needs the internet. Please try again when you are online.';

  @override
  String get identitySaved => 'Saved';

  @override
  String get identityNone => 'Choose a nickname';

  @override
  String get leaderboardTitle => 'Weekly leaderboard';

  @override
  String leaderboardWeek(String date) {
    return 'Week of $date · resets every Monday';
  }

  @override
  String leaderboardMyRank(int rank) {
    return 'You are number $rank this week';
  }

  @override
  String get leaderboardNoPoints => 'Answer questions this week to get on the board.';

  @override
  String get leaderboardNeedNickname =>
      'Choose a nickname to appear on the board. Only your nickname and avatar are shown.';

  @override
  String get leaderboardEmpty => 'Nobody has points this week yet. Be the first!';

  @override
  String get leaderboardOffline => 'The leaderboard needs the internet.';

  @override
  String leaderboardRank(int rank) {
    return '#$rank';
  }

  @override
  String get leaderboardYou => 'You';

  @override
  String get leaderboardOpen => 'See the weekly leaderboard';
}
