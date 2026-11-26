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
  String get onboardingHookTitle => 'Warm-up: 5 easy questions';

  @override
  String get warmUpHint => 'Just for fun. Tap an answer.';

  @override
  String warmUpProgress(int current, int total) {
    return 'Question $current of $total';
  }

  @override
  String get warmUpPraise1 => 'Great job!';

  @override
  String get warmUpPraise2 => 'You got it!';

  @override
  String get warmUpPraise3 => 'Nice one!';

  @override
  String warmUpAlmost(String answer) {
    return 'Almost! The answer is $answer.';
  }

  @override
  String get warmUpNext => 'Next';

  @override
  String get warmUpSeeResult => 'See my result';

  @override
  String onboardingHookResult(int correct, int total) {
    return 'You got $correct of $total!';
  }

  @override
  String get onboardingHookResultGreat => 'Great start! Now let\'s set up your plan.';

  @override
  String get onboardingHookResultGood => 'Good start! Guul 12 will help you get even better.';

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

  @override
  String get nicknameReportAction => 'Report nickname';

  @override
  String nicknameReportTitle(String nickname) {
    return 'Report \"$nickname\"';
  }

  @override
  String get nicknameReportIntro =>
      'Staff will check this nickname. The student is not told who reported it.';

  @override
  String get nicknameReportRude => 'Rude or hurtful';

  @override
  String get nicknameReportImpersonation => 'Pretends to be someone else';

  @override
  String get nicknameReportOther => 'Other';

  @override
  String get nicknameReportNote => 'Anything else? (optional)';

  @override
  String get nicknameReportSend => 'Send report';

  @override
  String get nicknameReportCancel => 'Cancel';

  @override
  String get nicknameReportThanks => 'Thanks. Staff will check this nickname.';

  @override
  String get nicknameReportOffline => 'You need the internet to send this report.';

  @override
  String get nicknameReportLimit => 'You have sent many reports today. Please try again tomorrow.';

  @override
  String get challengeTitle => 'Daily challenge';

  @override
  String challengeDone(int correct, int total) {
    return 'Today: $correct / $total right';
  }

  @override
  String challengeBetterThan(int percent) {
    return 'Better than $percent% of today\'s players';
  }

  @override
  String get challengeCompareLater => 'Come back later to compare with other players';

  @override
  String get battleTitle => 'Battles';

  @override
  String get battleQuick => 'Quick battle';

  @override
  String get battleQuickBody => 'Play against a student of your grade';

  @override
  String get battleFriend => 'Battle a friend';

  @override
  String get battleFriendBody => 'Get a code to give your friend';

  @override
  String get battleJoin => 'Join with a code';

  @override
  String get battleCodeLabel => 'Battle code';

  @override
  String get battleCodeTitle => 'Your battle code';

  @override
  String get battleCodeBody =>
      'Tell your friend this code. They open Battles, tap \"Join with a code\" and enter it. The battle is open for 48 hours.';

  @override
  String get battlePlayNow => 'Play now';

  @override
  String get battleCancel => 'Cancel';

  @override
  String get battleMine => 'My battles';

  @override
  String get battleNone => 'No battles yet.';

  @override
  String get battleWaitingOpponent => 'Waiting for an opponent';

  @override
  String get battleYourTurn => 'Your turn';

  @override
  String battleTheirTurn(String name) {
    return 'Waiting for $name';
  }

  @override
  String get battleWon => 'You won';

  @override
  String get battleLost => 'You lost';

  @override
  String get battleDraw => 'Draw';

  @override
  String get battleExpired => 'Ended without a result';

  @override
  String battleScore(String mine, String name, String theirs) {
    return 'You $mine · $name $theirs';
  }

  @override
  String get battleNeedNickname =>
      'Choose a nickname to start battles. Only your nickname and avatar are shown.';

  @override
  String get battleErrorNotFound => 'No battle has this code.';

  @override
  String get battleErrorFull => 'Another student already joined this battle.';

  @override
  String get battleErrorOwn => 'This is your own battle. Give the code to a friend.';

  @override
  String get battleErrorExpired => 'This battle has ended.';

  @override
  String get battleErrorLimit => 'That\'s enough battles for today. Come back tomorrow!';

  @override
  String get battleOffline => 'Battles need the internet.';

  @override
  String get battleRule =>
      '10 questions · 60 seconds each · more right answers wins; on a tie, the faster one';

  @override
  String get battleClose => 'Close';

  @override
  String get inviteTitle => 'Invite friends';

  @override
  String get inviteMeBody => 'Study together: share your invite code';

  @override
  String get inviteYourCode => 'Your invite code';

  @override
  String inviteRewardRule(int answers, int days, int max) {
    return 'When a friend you invite answers $answers questions, you get $days days of Premium (up to $max friends a month).';
  }

  @override
  String get inviteNoRewardRule => 'Invite friends to study for the exam with you.';

  @override
  String get inviteShare => 'Share';

  @override
  String get inviteCopy => 'Copy';

  @override
  String get inviteCopied => 'Code copied';

  @override
  String inviteShareText(String code, String link) {
    return 'Study for the Grade 12 national exam with Guul 12. My invite code: $code. $link';
  }

  @override
  String inviteStats(int joined, int valid, int days) {
    return 'Friends joined: $joined · Counted: $valid · Premium days earned: $days';
  }

  @override
  String get inviteEnterTitle => 'Did a friend invite you?';

  @override
  String get inviteEnterLabel => 'Friend\'s invite code';

  @override
  String get inviteEnterButton => 'Use code';

  @override
  String get inviteEntered => 'Thanks! Your friend\'s code is saved.';

  @override
  String get inviteErrorNotFound => 'No student has this code.';

  @override
  String get inviteErrorOwn => 'That\'s your own code. Share it with friends instead.';

  @override
  String get inviteErrorAlready => 'You already used a friend\'s code.';

  @override
  String get inviteErrorTooLate => 'Codes can only be used in your first days with the app.';

  @override
  String get inviteOffline => 'Invites need the internet.';

  @override
  String get examsRewardTitle => 'One more random exam?';

  @override
  String get examsRewardBody =>
      'You used today\'s free random exam. Watch a short video to unlock one more today.';

  @override
  String get examsRewardWatch => 'Watch video';

  @override
  String get examsRewardNotNow => 'Not now';

  @override
  String get examsRewardNotFinished => 'The video was not finished, so nothing was unlocked.';

  @override
  String get examsRewardLimit => 'That\'s all the extra exams for today. Come back tomorrow!';

  @override
  String get explainMore => 'Explain more';

  @override
  String get explainLocked => 'Explain more · 🔒 Premium';

  @override
  String get explainTitle => 'More explanation';

  @override
  String get explainAiNote =>
      'Written by AI. It may contain mistakes. If something looks wrong, report the question.';

  @override
  String get explainHelpful => 'Helpful';

  @override
  String get explainNotHelpful => 'Not helpful';

  @override
  String get explainThanks => 'Thanks for telling us!';

  @override
  String get explainLimit => 'That\'s all the extra explanations for today. Come back tomorrow!';

  @override
  String get explainUnavailable =>
      'Explain more is not available right now. Please try again later.';

  @override
  String get explainOffline => 'Explain more needs the internet.';

  @override
  String premiumEnding(String date) {
    return 'Your Premium ends on $date.';
  }

  @override
  String premiumEnded(String date) {
    return 'Your Premium ended on $date.';
  }

  @override
  String get meDelete => 'Delete my account';

  @override
  String get meDeleteTitle => 'Delete your account?';

  @override
  String get meDeleteBody =>
      'This deletes your study profile, answers, results, bookmarks, nickname, battles and invites from our server, and resets the app on this phone. Any Premium time is lost. This cannot be undone.';

  @override
  String get meDeleteConfirm => 'Delete';

  @override
  String get meDeleteCancel => 'Cancel';

  @override
  String get meDeleteOffline => 'Deleting your account needs the internet.';

  @override
  String get meDeleteDone => 'Your account was deleted.';

  @override
  String get walkSkip => 'Skip';

  @override
  String get walkNext => 'Next';

  @override
  String get walkStart => 'Let\'s start';

  @override
  String walkPage(int page, int total) {
    return 'Page $page of $total';
  }

  @override
  String get walk1Title => 'Learn in short, clear lessons';

  @override
  String get walk1Body =>
      'Every chapter has a short summary, solved examples and flashcards. They work offline too.';

  @override
  String get walk2Title => 'Practise like the real exam';

  @override
  String get walk2Body =>
      'Quick practice, a daily challenge and full mock exams with a timer show you where you stand.';

  @override
  String get walk3Title => 'A little every day adds up';

  @override
  String get walk3Body => 'Keep your streak, watch your progress grow and reach your target score.';

  @override
  String homeGreeting(String part) {
    String _temp0 = intl.Intl.selectLogic(part, {
      'morning': 'Good morning!',
      'afternoon': 'Good afternoon!',
      'other': 'Good evening!',
    });
    return '$_temp0';
  }

  @override
  String homeGreetingName(String part, String name) {
    String _temp0 = intl.Intl.selectLogic(part, {
      'morning': 'Good morning, $name!',
      'afternoon': 'Good afternoon, $name!',
      'other': 'Good evening, $name!',
    });
    return '$_temp0';
  }

  @override
  String get homeMoodStart => 'Ready to learn something new today?';

  @override
  String homeMoodGoing(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count more questions to reach today\'s goal',
      one: '1 more question to reach today\'s goal',
    );
    return '$_temp0';
  }

  @override
  String get homeMoodDone => 'Today\'s goal is done. Amazing!';

  @override
  String get homeGoalLabel => 'Today\'s goal';

  @override
  String homeGoalRing(int done, int goal) {
    return '$done of $goal questions today';
  }

  @override
  String get homeContinueTitle => 'Continue where you left off';

  @override
  String get homeStartTitle => 'Start your first lesson';

  @override
  String get homeStartBody => 'Pick a subject and open a chapter. It takes about 5 minutes.';

  @override
  String get homeForYou => 'For you today';

  @override
  String get homeSubjects => 'Your subjects';

  @override
  String get homeSeeAll => 'See all';

  @override
  String get homeTipTitle => 'Tip of the day';

  @override
  String homeTip(String n) {
    String _temp0 = intl.Intl.selectLogic(n, {
      '0': 'Study for 25 minutes, then rest for 5. Your brain remembers more.',
      '1': 'Got a question wrong? Read the explanation. Mistakes are how you learn.',
      '2': 'Explain a topic out loud as if you were the teacher.',
      '3': 'Sleep well before an exam. A rested brain thinks faster.',
      '4': 'Do a little every day. Ten questions a day is better than a hundred once a week.',
      '5': 'Read the whole question twice before you choose an answer.',
      '6': 'Start with the subject you like least while your mind is fresh.',
      '7': 'Write short notes in your own words after each chapter.',
      '8': 'In a mock exam, skip a hard question and come back to it later.',
      'other': 'Drink water and take short walks. Your body helps your brain.',
    });
    return '$_temp0';
  }

  @override
  String homeTileChallenge(int count) {
    return '$count questions';
  }

  @override
  String get homeTileBattle => 'Play another student';

  @override
  String get homeTileQuick => '10 mixed questions';

  @override
  String get homeTileSmart => 'Your weak spots';

  @override
  String get homeTileMore => 'More practice';

  @override
  String get homeTileMoreLine => 'Pick a mode';

  @override
  String get homeWeekTitle => 'This week';

  @override
  String homeWeekCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Goal reached on $count days this week',
      one: 'Goal reached on 1 day this week',
      zero: 'Reach the daily goal to fill a day',
    );
    return '$_temp0';
  }

  @override
  String homeWeekDayReached(String day) {
    return '$day: goal reached';
  }

  @override
  String homeWeekDayCount(String day, int count, int goal) {
    return '$day: $count of $goal questions';
  }

  @override
  String get homeWeakTitle => 'Your weakest chapter';

  @override
  String homeWeakLine(String subject, int percent) {
    return '$subject · $percent% right so far';
  }

  @override
  String homeWeakPercent(int percent) {
    return '$percent% right so far';
  }

  @override
  String get homeWeakAction => 'Practise it';

  @override
  String homeBadgeNext(String name) {
    return 'Next badge: $name';
  }

  @override
  String get homeBecomePremium => 'Become Premium';

  @override
  String get homeBecomePremiumLine => 'All mock exams, offline subjects, AI help and no ads';

  @override
  String get homePremiumOn => 'Premium is on';

  @override
  String homePremiumUntil(String date) {
    return 'Until $date';
  }

  @override
  String get premiumPageTitle => 'Guul 12 Premium';

  @override
  String get premiumPageIntro => 'Everything in the free app, plus:';

  @override
  String get premiumBenefitMocks => 'All mock exams';

  @override
  String get premiumBenefitMocksBody =>
      'Every subject\'s third full mock exam, plus the \"My weak topics\" and \"My mistakes\" exams.';

  @override
  String get premiumBenefitOffline => 'Whole subjects offline';

  @override
  String get premiumBenefitOfflineBody =>
      'Download a whole subject at once and study without internet.';

  @override
  String get premiumBenefitExplain => 'Explain more (AI)';

  @override
  String get premiumBenefitExplainBody =>
      'Ask for a simpler, step-by-step explanation of a question.';

  @override
  String get premiumBenefitNoAds => 'No ads';

  @override
  String get premiumBenefitNoAdsBody => 'Study without any ads.';

  @override
  String get premiumActiveNote => 'Your Premium is on. Thank you!';

  @override
  String get premiumContactTitle => 'How to get Premium';

  @override
  String get premiumContactStep1 => 'Message us on WhatsApp or Telegram.';

  @override
  String get premiumContactStep2 => 'Send us your student ID:';

  @override
  String get premiumContactStep3 => 'We turn on your Premium. Pull down on Home to see it.';

  @override
  String get premiumContactCopy => 'Copy student ID';

  @override
  String get premiumContactCopied => 'Student ID copied';

  @override
  String get premiumContactWhatsApp => 'Message us on WhatsApp';

  @override
  String get premiumContactTelegram => 'Message us on Telegram';

  @override
  String premiumContactMessage(String id) {
    return 'Hello! I want Guul 12 Premium. My student ID: $id';
  }

  @override
  String get premiumContactMessageNoId => 'Hello! I want Guul 12 Premium.';

  @override
  String get homeReviewCardTitle => 'Review time';

  @override
  String get homeReviewCardLine => 'A few minutes now keeps them in your memory.';

  @override
  String get homeReviewStart => 'Start review';

  @override
  String get homeReadinessTitle => 'Exam readiness';

  @override
  String readinessRange(int low, int high) {
    return 'Likely score: $low–$high%';
  }

  @override
  String readinessUp(int points) {
    return 'Up $points this week';
  }

  @override
  String readinessDown(int points) {
    return 'Down $points this week';
  }

  @override
  String get readinessSame => 'Same as last week';

  @override
  String readinessBasedOn(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'From your last $count answers',
      one: 'From your last answer',
    );
    return '$_temp0';
  }

  @override
  String readinessNeedMore(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Answer $count more questions to see your likely exam score.',
      one: 'Answer 1 more question to see your likely exam score.',
    );
    return '$_temp0';
  }

  @override
  String get readinessNote =>
      'An estimate from your recent answers, not a promise. Practise more chapters to make it surer.';

  @override
  String get homeRankTitle => 'Your rank this week';

  @override
  String homeRankPlace(int rank) {
    return 'Place $rank';
  }

  @override
  String homeRankGap(int xp) {
    return '$xp XP to move up one place';
  }

  @override
  String get homeRankFirst => 'You are first. Keep it up!';

  @override
  String get homeRankNone => 'Answer questions to get on the board.';

  @override
  String homeRankXp(int xp) {
    return '$xp XP this week';
  }

  @override
  String get homeCardOfDayTitle => 'Card of the day';

  @override
  String get homeCardOfDayTap => 'Tap to see the answer';

  @override
  String get homeCardOfDayBack => 'Tap to see the term again';

  @override
  String get homeCardOfDayMore => 'More cards';

  @override
  String get homeTileStats => 'Statistics';

  @override
  String get homeTileStatsLine => 'Charts of your progress';

  @override
  String get statsTitle => 'Statistics';

  @override
  String get statsOpen => 'See your statistics';

  @override
  String get statsEmpty => 'Answer some questions and your charts will appear here.';

  @override
  String get statsAnswered => 'Answered';

  @override
  String get statsRight => 'Right';

  @override
  String get statsWrong => 'Wrong';

  @override
  String get statsDaysStudied => 'Days studied';

  @override
  String get statsBestStreak => 'Best streak';

  @override
  String get statsExams => 'Exams taken';

  @override
  String get statsBestExam => 'Best exam';

  @override
  String get statsStudyTime => 'Study time';

  @override
  String get statsPerQuestion => 'Time per question';

  @override
  String get statsNoValue => '–';

  @override
  String statsPercent(int percent) {
    return '$percent%';
  }

  @override
  String statsDays(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: '$days days',
      one: '1 day',
    );
    return '$_temp0';
  }

  @override
  String statsHours(int hours, int minutes) {
    return '$hours h $minutes min';
  }

  @override
  String statsMinutes(int minutes) {
    return '$minutes min';
  }

  @override
  String statsSeconds(int seconds) {
    return '$seconds s';
  }

  @override
  String get statsStudyTimeNote =>
      'Study time counts from this app version on, at most 5 minutes a question.';

  @override
  String get statsTapHint => 'Tap a bar, point or square to see its numbers.';

  @override
  String get statsDaysTitle => 'Last 14 days';

  @override
  String get statsDaysSubtitle => 'Questions answered each day';

  @override
  String statsDayDetail(String date, int right, int wrong) {
    return '$date: $right right, $wrong wrong';
  }

  @override
  String get statsWeeksTitle => 'Accuracy by week';

  @override
  String get statsWeeksSubtitle => 'Share of answers right, last 8 weeks';

  @override
  String statsWeekDetail(String date, int percent, int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Week of $date: $percent% right of $count answers',
      one: 'Week of $date: $percent% right of 1 answer',
    );
    return '$_temp0';
  }

  @override
  String statsWeekNone(String date) {
    return 'Week of $date: no answers';
  }

  @override
  String get statsSubjectsTitle => 'Accuracy by subject';

  @override
  String statsShareLine(int percent, int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$percent% · $count answers',
      one: '$percent% · 1 answer',
    );
    return '$_temp0';
  }

  @override
  String get statsShareNone => 'No answers';

  @override
  String get statsExamsTitle => 'Exam scores';

  @override
  String get statsExamsSubtitle => 'Your last 10 exams';

  @override
  String statsExamDetail(String date, String title, int percent) {
    return '$date · $title: $percent%';
  }

  @override
  String get statsExamsNone => 'Finish an exam to see your scores here.';

  @override
  String get statsMasteryTitle => 'Chapters by strength';

  @override
  String statsMasteryItem(String level, int count) {
    return '$level: $count';
  }

  @override
  String get statsHeatmapTitle => 'Activity';

  @override
  String get statsHeatmapSubtitle => 'Last 12 weeks, one square a day';

  @override
  String statsHeatmapDetail(String date, int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$date: $count questions',
      one: '$date: 1 question',
      zero: '$date: no questions',
    );
    return '$_temp0';
  }

  @override
  String get statsLess => 'Less';

  @override
  String get statsMore => 'More';

  @override
  String get statsPartsTitle => 'When you study';

  @override
  String statsPart(String part) {
    String _temp0 = intl.Intl.selectLogic(part, {
      'morning': 'Morning',
      'afternoon': 'Afternoon',
      'other': 'Evening',
    });
    return '$_temp0';
  }
}
