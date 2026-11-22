import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_en.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'generated/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale) : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate = _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[Locale('en')];

  /// App name. Do not translate.
  ///
  /// In en, this message translates to:
  /// **'Guul 12'**
  String get appName;

  /// Primary button to go to the next step
  ///
  /// In en, this message translates to:
  /// **'Continue'**
  String get commonContinue;

  /// Button to skip an optional step
  ///
  /// In en, this message translates to:
  /// **'Skip'**
  String get commonSkip;

  /// Retry after an error
  ///
  /// In en, this message translates to:
  /// **'Try again'**
  String get commonRetry;

  /// Finish a step
  ///
  /// In en, this message translates to:
  /// **'Done'**
  String get commonDone;

  /// Go to the next question or card
  ///
  /// In en, this message translates to:
  /// **'Next'**
  String get commonNext;

  /// Go to the previous question or card
  ///
  /// In en, this message translates to:
  /// **'Previous'**
  String get commonPrevious;

  /// Check my answer
  ///
  /// In en, this message translates to:
  /// **'Check'**
  String get commonCheck;

  /// Snackbar after copying text
  ///
  /// In en, this message translates to:
  /// **'Copied'**
  String get commonCopied;

  /// Banner when there is no internet
  ///
  /// In en, this message translates to:
  /// **'You are offline. Your progress is saved and will sync later.'**
  String get commonOffline;

  /// Generic error
  ///
  /// In en, this message translates to:
  /// **'Something went wrong. Please try again.'**
  String get commonError;

  /// Loading indicator label
  ///
  /// In en, this message translates to:
  /// **'Loading…'**
  String get commonLoading;

  /// Small label on locked items. Must not mention price or how to buy.
  ///
  /// In en, this message translates to:
  /// **'Premium'**
  String get premiumLabel;

  /// Label on new questions that are still being checked
  ///
  /// In en, this message translates to:
  /// **'Beta'**
  String get betaLabel;

  /// First onboarding screen
  ///
  /// In en, this message translates to:
  /// **'Which grade are you in?'**
  String get onboardingGradeTitle;

  /// Grade choice button
  ///
  /// In en, this message translates to:
  /// **'Grade {grade}'**
  String onboardingGradeOption(int grade);

  /// Title of the 30-second hook quiz
  ///
  /// In en, this message translates to:
  /// **'Quick warm-up: 5 questions'**
  String get onboardingHookTitle;

  /// Hook quiz result
  ///
  /// In en, this message translates to:
  /// **'You got {correct} of {total}!'**
  String onboardingHookResult(int correct, int total);

  /// Text under hook quiz result
  ///
  /// In en, this message translates to:
  /// **'Let\'s set up your plan so you can do even better.'**
  String get onboardingHookResultBody;

  /// Stream choice screen (Grades 11–12)
  ///
  /// In en, this message translates to:
  /// **'Which stream are you in?'**
  String get onboardingStreamTitle;

  /// Natural science stream
  ///
  /// In en, this message translates to:
  /// **'Natural Science'**
  String get streamNatural;

  /// Social science stream
  ///
  /// In en, this message translates to:
  /// **'Social Science'**
  String get streamSocial;

  /// Retaker question
  ///
  /// In en, this message translates to:
  /// **'Are you taking the national exam again?'**
  String get onboardingRetakerTitle;

  /// Retaker answer yes
  ///
  /// In en, this message translates to:
  /// **'Yes, I am retaking it'**
  String get onboardingRetakerYes;

  /// Retaker answer no
  ///
  /// In en, this message translates to:
  /// **'No, first time'**
  String get onboardingRetakerNo;

  /// Region and city screen
  ///
  /// In en, this message translates to:
  /// **'Where do you study?'**
  String get onboardingRegionTitle;

  /// Region field label
  ///
  /// In en, this message translates to:
  /// **'Region'**
  String get onboardingRegionLabel;

  /// City field label
  ///
  /// In en, this message translates to:
  /// **'City or town'**
  String get onboardingCityLabel;

  /// Optional gender screen
  ///
  /// In en, this message translates to:
  /// **'Gender (optional)'**
  String get onboardingGenderTitle;

  /// Gender option
  ///
  /// In en, this message translates to:
  /// **'Female'**
  String get genderFemale;

  /// Gender option
  ///
  /// In en, this message translates to:
  /// **'Male'**
  String get genderMale;

  /// Privacy note under personal questions
  ///
  /// In en, this message translates to:
  /// **'Used only for anonymous statistics. Never shown to other students.'**
  String get onboardingPrivacyNote;

  /// Target score screen
  ///
  /// In en, this message translates to:
  /// **'What is your target score?'**
  String get onboardingTargetTitle;

  /// Target score value
  ///
  /// In en, this message translates to:
  /// **'{score} / 600'**
  String onboardingTargetValue(int score);

  /// Reminder time screen
  ///
  /// In en, this message translates to:
  /// **'When should we remind you to study?'**
  String get onboardingReminderTitle;

  /// Button to open time picker
  ///
  /// In en, this message translates to:
  /// **'Pick a time'**
  String get onboardingReminderPick;

  /// Last onboarding button
  ///
  /// In en, this message translates to:
  /// **'Start studying'**
  String get onboardingFinish;

  /// Bottom navigation
  ///
  /// In en, this message translates to:
  /// **'Home'**
  String get navHome;

  /// Bottom navigation
  ///
  /// In en, this message translates to:
  /// **'Study'**
  String get navStudy;

  /// Bottom navigation
  ///
  /// In en, this message translates to:
  /// **'Me'**
  String get navMe;

  /// Home screen greeting
  ///
  /// In en, this message translates to:
  /// **'Welcome back'**
  String get homeGreeting;

  /// Exam countdown
  ///
  /// In en, this message translates to:
  /// **'{days, plural, =0{Exam is today} =1{1 day to the exam} other{{days} days to the exam}}'**
  String homeExamCountdown(int days);

  /// Exam date in Gregorian and Ethiopian calendars
  ///
  /// In en, this message translates to:
  /// **'{gregorian} · {ethiopian} E.C.'**
  String homeExamDates(String gregorian, String ethiopian);

  /// When admin has not set the exam date
  ///
  /// In en, this message translates to:
  /// **'Exam date not announced yet'**
  String get homeExamDateUnknown;

  /// Start a quick 10-question practice
  ///
  /// In en, this message translates to:
  /// **'Quick 10'**
  String get homeQuick10;

  /// Subtitle for Quick 10
  ///
  /// In en, this message translates to:
  /// **'10 mixed questions from what you have studied'**
  String get homeQuick10Body;

  /// Card to open the study section
  ///
  /// In en, this message translates to:
  /// **'Continue studying'**
  String get homeContinueStudying;

  /// Home when nothing is downloaded yet
  ///
  /// In en, this message translates to:
  /// **'Open a subject in Study to download it for offline use.'**
  String get homeNoContentYet;

  /// Study tab title
  ///
  /// In en, this message translates to:
  /// **'Study'**
  String get studyTitle;

  /// Grade heading
  ///
  /// In en, this message translates to:
  /// **'Grade {grade}'**
  String studyGrade(int grade);

  /// Unit heading
  ///
  /// In en, this message translates to:
  /// **'Unit {number}: {title}'**
  String studyUnit(int number, String title);

  /// Chapter row
  ///
  /// In en, this message translates to:
  /// **'{number}. {title}'**
  String studyChapter(int number, String title);

  /// Nothing in this list yet
  ///
  /// In en, this message translates to:
  /// **'Content for this part is coming soon.'**
  String get studyEmpty;

  /// No catalog downloaded yet
  ///
  /// In en, this message translates to:
  /// **'Connect to the internet once to download the list of subjects.'**
  String get studyCatalogEmpty;

  /// Chapter is stored on the phone
  ///
  /// In en, this message translates to:
  /// **'Saved for offline use'**
  String get studyDownloadedOffline;

  /// Chapter section
  ///
  /// In en, this message translates to:
  /// **'Summary'**
  String get chapterSummary;

  /// Summary heading
  ///
  /// In en, this message translates to:
  /// **'Key points'**
  String get chapterKeyPoints;

  /// Summary heading
  ///
  /// In en, this message translates to:
  /// **'Formulas'**
  String get chapterFormulas;

  /// Chapter section
  ///
  /// In en, this message translates to:
  /// **'Solved exercises'**
  String get chapterSolvedExercises;

  /// Solved exercise step
  ///
  /// In en, this message translates to:
  /// **'Step {number}'**
  String chapterSolvedStep(int number);

  /// Reveal the steps of a solved exercise
  ///
  /// In en, this message translates to:
  /// **'Show solution'**
  String get chapterShowSolution;

  /// Final answer label
  ///
  /// In en, this message translates to:
  /// **'Answer'**
  String get chapterAnswer;

  /// Chapter section
  ///
  /// In en, this message translates to:
  /// **'Flashcards'**
  String get chapterFlashcards;

  /// Hint on flashcards
  ///
  /// In en, this message translates to:
  /// **'Tap to flip · swipe for the next card'**
  String get chapterFlashcardHint;

  /// Flashcard position
  ///
  /// In en, this message translates to:
  /// **'{current} / {total}'**
  String chapterFlashcardCount(int current, int total);

  /// Chapter section
  ///
  /// In en, this message translates to:
  /// **'Chapter quiz'**
  String get chapterQuiz;

  /// Number of questions
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{1 question} other{{count} questions}}'**
  String chapterQuizCount(int count);

  /// Opens the printable Guul 12 summary of this chapter
  ///
  /// In en, this message translates to:
  /// **'Chapter summary (PDF)'**
  String get chapterSummaryPdf;

  /// Link to the Ministry of Education textbook
  ///
  /// In en, this message translates to:
  /// **'Official textbook (PDF)'**
  String get chapterTextbook;

  /// Video slot (shown only when a video exists)
  ///
  /// In en, this message translates to:
  /// **'Video lesson'**
  String get chapterVideo;

  /// Practice mode picker title
  ///
  /// In en, this message translates to:
  /// **'Practice'**
  String get practiceTitle;

  /// Practice mode
  ///
  /// In en, this message translates to:
  /// **'Quick 10'**
  String get practiceModeQuick10;

  /// Practice mode
  ///
  /// In en, this message translates to:
  /// **'This chapter'**
  String get practiceModeChapter;

  /// Practice mode
  ///
  /// In en, this message translates to:
  /// **'Whole unit'**
  String get practiceModeUnit;

  /// Practice mode
  ///
  /// In en, this message translates to:
  /// **'Mixed'**
  String get practiceModeMixed;

  /// Practice mode
  ///
  /// In en, this message translates to:
  /// **'Timed (1 minute each)'**
  String get practiceModeTimed;

  /// Practice mode
  ///
  /// In en, this message translates to:
  /// **'Only my wrong answers'**
  String get practiceModeWrongOnly;

  /// Practice mode: key points then 5 questions
  ///
  /// In en, this message translates to:
  /// **'Quick revise (5 minutes)'**
  String get practiceModeQuickRevise;

  /// Empty practice set
  ///
  /// In en, this message translates to:
  /// **'No questions here yet. Study a chapter first.'**
  String get practiceNoQuestions;

  /// Wrong-only mode with nothing to show
  ///
  /// In en, this message translates to:
  /// **'No wrong answers to practise. Well done!'**
  String get practiceNoWrongAnswers;

  /// Progress label
  ///
  /// In en, this message translates to:
  /// **'Question {current} of {total}'**
  String practiceQuestionOf(int current, int total);

  /// Feedback after a correct answer
  ///
  /// In en, this message translates to:
  /// **'Correct!'**
  String get practiceCorrect;

  /// Feedback after a wrong answer
  ///
  /// In en, this message translates to:
  /// **'Not quite'**
  String get practiceWrong;

  /// Shows the right answer
  ///
  /// In en, this message translates to:
  /// **'Correct answer: {answer}'**
  String practiceCorrectAnswer(String answer);

  /// Explanation heading
  ///
  /// In en, this message translates to:
  /// **'Explanation'**
  String get practiceExplanation;

  /// Seconds left in timed mode
  ///
  /// In en, this message translates to:
  /// **'{seconds}s'**
  String practiceTimeLeft(int seconds);

  /// Timed mode ran out
  ///
  /// In en, this message translates to:
  /// **'Time is up'**
  String get practiceTimeUp;

  /// Read the question with text-to-speech
  ///
  /// In en, this message translates to:
  /// **'Read aloud'**
  String get practiceReadAloud;

  /// Bookmark the question
  ///
  /// In en, this message translates to:
  /// **'Bookmark'**
  String get practiceBookmark;

  /// Question is bookmarked
  ///
  /// In en, this message translates to:
  /// **'Bookmarked'**
  String get practiceBookmarked;

  /// True/false option
  ///
  /// In en, this message translates to:
  /// **'True'**
  String get practiceTrue;

  /// True/false option
  ///
  /// In en, this message translates to:
  /// **'False'**
  String get practiceFalse;

  /// Fill-in field hint
  ///
  /// In en, this message translates to:
  /// **'Type your answer'**
  String get practiceTypeAnswer;

  /// Numeric field hint
  ///
  /// In en, this message translates to:
  /// **'Type a number'**
  String get practiceTypeNumber;

  /// Expected unit for numeric answers
  ///
  /// In en, this message translates to:
  /// **'Unit: {unit}'**
  String practiceUnit(String unit);

  /// Multiple answers hint
  ///
  /// In en, this message translates to:
  /// **'Choose all that apply'**
  String get practiceChooseAll;

  /// Matching hint
  ///
  /// In en, this message translates to:
  /// **'Tap an item on the left, then its match on the right'**
  String get practiceMatchHint;

  /// Ordering hint
  ///
  /// In en, this message translates to:
  /// **'Drag to put the items in the right order'**
  String get practiceOrderHint;

  /// Accessibility: move item up
  ///
  /// In en, this message translates to:
  /// **'Move up'**
  String get practiceMoveUp;

  /// Accessibility: move item down
  ///
  /// In en, this message translates to:
  /// **'Move down'**
  String get practiceMoveDown;

  /// Passage heading
  ///
  /// In en, this message translates to:
  /// **'Read the passage'**
  String get practiceReadPassage;

  /// Result screen title
  ///
  /// In en, this message translates to:
  /// **'Practice finished'**
  String get practiceResultTitle;

  /// Result score
  ///
  /// In en, this message translates to:
  /// **'{correct} / {total} correct'**
  String practiceResultScore(int correct, int total);

  /// Attempt stored for upload
  ///
  /// In en, this message translates to:
  /// **'Saved. It will sync when you are online.'**
  String get practiceResultSaved;

  /// Attempt uploaded
  ///
  /// In en, this message translates to:
  /// **'Saved and synced.'**
  String get practiceResultSynced;

  /// Restart practice
  ///
  /// In en, this message translates to:
  /// **'Practise again'**
  String get practiceAgain;

  /// Profile tab title
  ///
  /// In en, this message translates to:
  /// **'Me'**
  String get meTitle;

  /// Private student ID label
  ///
  /// In en, this message translates to:
  /// **'Your student ID'**
  String get meStudentId;

  /// Help under student ID. Must not mention payment.
  ///
  /// In en, this message translates to:
  /// **'Private. Keep it for support.'**
  String get meStudentIdHelp;

  /// Copy button
  ///
  /// In en, this message translates to:
  /// **'Copy'**
  String get meCopy;

  /// Premium status
  ///
  /// In en, this message translates to:
  /// **'Premium until {date}'**
  String mePremiumUntil(String date);

  /// Not Premium. Must not mention price or buying.
  ///
  /// In en, this message translates to:
  /// **'Free plan'**
  String get meFree;

  /// Google sign-in button
  ///
  /// In en, this message translates to:
  /// **'Sign in with Google'**
  String get meSignIn;

  /// Why sign in
  ///
  /// In en, this message translates to:
  /// **'Keep your progress if you change phones.'**
  String get meSignInWhy;

  /// Shown when signed in with Google
  ///
  /// In en, this message translates to:
  /// **'Signed in'**
  String get meSignedIn;

  /// Sign-in error
  ///
  /// In en, this message translates to:
  /// **'Sign-in did not work. Please try again.'**
  String get meSignInFailed;

  /// Account conflict
  ///
  /// In en, this message translates to:
  /// **'This phone is already signed in with another Google account.'**
  String get meSignInConflict;

  /// Settings heading
  ///
  /// In en, this message translates to:
  /// **'Settings'**
  String get meSettings;

  /// Theme setting
  ///
  /// In en, this message translates to:
  /// **'Theme'**
  String get meTheme;

  /// Theme option
  ///
  /// In en, this message translates to:
  /// **'Same as phone'**
  String get meThemeSystem;

  /// Theme option
  ///
  /// In en, this message translates to:
  /// **'Light'**
  String get meThemeLight;

  /// Theme option
  ///
  /// In en, this message translates to:
  /// **'Dark'**
  String get meThemeDark;

  /// Profile field
  ///
  /// In en, this message translates to:
  /// **'Grade'**
  String get meGrade;

  /// Profile field
  ///
  /// In en, this message translates to:
  /// **'Stream'**
  String get meStream;

  /// Open onboarding answers again
  ///
  /// In en, this message translates to:
  /// **'Edit study profile'**
  String get meEditProfile;

  /// App version
  ///
  /// In en, this message translates to:
  /// **'Version {version}'**
  String meVersion(String version);

  /// Ethiopian month name; month is m1…m13
  ///
  /// In en, this message translates to:
  /// **'{month, select, m1{Meskerem} m2{Tikimt} m3{Hidar} m4{Tahsas} m5{Tir} m6{Yekatit} m7{Megabit} m8{Miyazya} m9{Ginbot} m10{Sene} m11{Hamle} m12{Nehase} m13{Pagume} other{?}}'**
  String ethiopianMonth(String month);

  /// Ethiopian date, e.g. Sene 23, 2018
  ///
  /// In en, this message translates to:
  /// **'{month} {day}, {year}'**
  String ethiopianDate(String month, int day, int year);

  /// No description provided for @onboardingStep.
  ///
  /// In en, this message translates to:
  /// **'Step {current} of {total}'**
  String onboardingStep(int current, int total);

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'For example: Jijiga'**
  String get onboardingCityHint;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'No reminder'**
  String get onboardingReminderNone;

  /// No description provided for @onboardingReminderAt.
  ///
  /// In en, this message translates to:
  /// **'Every day at {time}'**
  String onboardingReminderAt(String time);

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Pass the national exam with Guul 12'**
  String get onboardingWelcomeTitle;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Short lessons, smart practice and mock exams. Works offline.'**
  String get onboardingWelcomeBody;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Get started'**
  String get onboardingStart;

  /// No description provided for @regionName.
  ///
  /// In en, this message translates to:
  /// **'{code, select, somali{Somali} addis_ababa{Addis Ababa} afar{Afar} amhara{Amhara} benishangul_gumuz{Benishangul-Gumuz} central_ethiopia{Central Ethiopia} dire_dawa{Dire Dawa} gambela{Gambela} harari{Harari} oromia{Oromia} sidama{Sidama} south_ethiopia{South Ethiopia} south_west_ethiopia{South West Ethiopia} tigray{Tigray} other{Other}}'**
  String regionName(String code);

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Please update Guul 12'**
  String get updateRequiredTitle;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'This version is too old. Update the app from Google Play to continue.'**
  String get updateRequiredBody;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'No chapters yet.'**
  String get studyNoChapters;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'🔒 Premium'**
  String get studyPremiumLocked;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Practise this chapter'**
  String get chapterPractice;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'The summary for this chapter is coming soon.'**
  String get chapterNoSummary;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Connect to the internet once to download this chapter.'**
  String get chapterOffline;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'How do you want to practise?'**
  String get practiceChooseMode;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Finish'**
  String get practiceFinish;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Review your answers'**
  String get practiceReview;

  /// No description provided for @practiceYourAnswer.
  ///
  /// In en, this message translates to:
  /// **'Your answer: {answer}'**
  String practiceYourAnswer(String answer);

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'No answer'**
  String get practiceNoAnswer;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Stop practising?'**
  String get practiceLeaveTitle;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Answers so far will not be saved.'**
  String get practiceLeaveBody;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Stop'**
  String get practiceLeave;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Keep going'**
  String get practiceStay;

  /// No description provided for @practicePairs.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{1 pair made} other{{count} pairs made}}'**
  String practicePairs(int count);

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Clear pairs'**
  String get practiceClearPairs;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Google sign-in is not available yet.'**
  String get meSignInUnavailable;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Region'**
  String get meRegion;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Target score'**
  String get meTarget;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Study reminder'**
  String get meReminder;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Retaking the exam'**
  String get meRetaker;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Gender'**
  String get meGender;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Not set'**
  String get meNotSet;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Connect to the internet once to get your student ID.'**
  String get meStudentIdPending;

  /// UI text.
  ///
  /// In en, this message translates to:
  /// **'Save'**
  String get meSave;

  /// Shown when Check is pressed with no answer.
  ///
  /// In en, this message translates to:
  /// **'Choose or type an answer first.'**
  String get practicePickAnswer;

  /// Section title on Me.
  ///
  /// In en, this message translates to:
  /// **'Study profile'**
  String get meStudyProfile;

  /// Report a problem sheet.
  ///
  /// In en, this message translates to:
  /// **'Report a problem'**
  String get practiceReport;

  /// Report a problem sheet.
  ///
  /// In en, this message translates to:
  /// **'What is wrong with this question?'**
  String get reportTitle;

  /// Report a problem sheet.
  ///
  /// In en, this message translates to:
  /// **'The answer is wrong'**
  String get reportWrongAnswer;

  /// Report a problem sheet.
  ///
  /// In en, this message translates to:
  /// **'The question is unclear'**
  String get reportUnclear;

  /// Report a problem sheet.
  ///
  /// In en, this message translates to:
  /// **'There is a typo'**
  String get reportTypo;

  /// Report a problem sheet.
  ///
  /// In en, this message translates to:
  /// **'Something else'**
  String get reportOther;

  /// Report a problem sheet.
  ///
  /// In en, this message translates to:
  /// **'Tell us more (optional)'**
  String get reportNote;

  /// Report a problem sheet.
  ///
  /// In en, this message translates to:
  /// **'Send'**
  String get reportSend;

  /// Report a problem sheet.
  ///
  /// In en, this message translates to:
  /// **'Thank you! We will check this question.'**
  String get reportThanks;

  /// Bottom navigation
  ///
  /// In en, this message translates to:
  /// **'Exams'**
  String get navExams;

  /// Exams tab title
  ///
  /// In en, this message translates to:
  /// **'Exams'**
  String get examsTitle;

  /// Section: fixed mock exams of a subject
  ///
  /// In en, this message translates to:
  /// **'Mock exams'**
  String get examsMocks;

  /// Section: random, weak-topic and mistakes exams
  ///
  /// In en, this message translates to:
  /// **'More exams'**
  String get examsMore;

  /// Section: finished exams
  ///
  /// In en, this message translates to:
  /// **'Your results'**
  String get examsPast;

  /// Mock list subtitle
  ///
  /// In en, this message translates to:
  /// **'{questions, plural, =1{1 question} other{{questions} questions}} · {minutes} min'**
  String examsMockInfo(int questions, int minutes);

  /// A random exam from the whole subject
  ///
  /// In en, this message translates to:
  /// **'Random mock'**
  String get examsRandom;

  /// Random mock subtitle
  ///
  /// In en, this message translates to:
  /// **'50 questions from the whole subject. One free each day.'**
  String get examsRandomBody;

  /// Exam from the student's weakest chapters
  ///
  /// In en, this message translates to:
  /// **'My weak topics'**
  String get examsWeak;

  /// Weak topics subtitle
  ///
  /// In en, this message translates to:
  /// **'Questions from the chapters where you score lowest.'**
  String get examsWeakBody;

  /// Exam from questions answered wrong
  ///
  /// In en, this message translates to:
  /// **'My mistakes'**
  String get examsMistakes;

  /// Mistakes subtitle
  ///
  /// In en, this message translates to:
  /// **'Questions you got wrong before.'**
  String get examsMistakesBody;

  /// Empty mock list
  ///
  /// In en, this message translates to:
  /// **'Mock exams appear here when you are online.'**
  String get examsNoMocks;

  /// Banner for an exam in progress
  ///
  /// In en, this message translates to:
  /// **'Continue your exam'**
  String get examsContinue;

  /// Exam in progress subtitle
  ///
  /// In en, this message translates to:
  /// **'{title} · {time} left'**
  String examsContinueBody(String title, String time);

  /// Past result subtitle
  ///
  /// In en, this message translates to:
  /// **'{correct} / {total} · {date}'**
  String examsResultLine(int correct, int total, String date);

  /// Starting a second exam
  ///
  /// In en, this message translates to:
  /// **'Finish or submit your current exam first.'**
  String get examsFinishFirst;

  /// Random mock daily limit reached
  ///
  /// In en, this message translates to:
  /// **'Today\'s free random mock is used. A new one is ready tomorrow.'**
  String get examsDailyLimit;

  /// No data yet for a personal exam
  ///
  /// In en, this message translates to:
  /// **'Practise some questions in this subject first.'**
  String get examsNeedPractice;

  /// Server has no questions
  ///
  /// In en, this message translates to:
  /// **'There are no questions for this exam yet.'**
  String get examsNoQuestions;

  /// Exam download failed offline
  ///
  /// In en, this message translates to:
  /// **'No internet. Connect and try again.'**
  String get examsOffline;

  /// Intro dialog heading
  ///
  /// In en, this message translates to:
  /// **'Real Exam Mode'**
  String get examIntroRules;

  /// Rule 1
  ///
  /// In en, this message translates to:
  /// **'The timer starts now and does not stop, even if you leave the app.'**
  String get examIntroTimer;

  /// Rule 2
  ///
  /// In en, this message translates to:
  /// **'You see right and wrong answers only at the end.'**
  String get examIntroNoFeedback;

  /// Rule 3
  ///
  /// In en, this message translates to:
  /// **'You can change answers and flag questions to check again.'**
  String get examIntroChange;

  /// Rule 4
  ///
  /// In en, this message translates to:
  /// **'When the time is up, the exam is submitted for you.'**
  String get examIntroAuto;

  /// Intro dialog button
  ///
  /// In en, this message translates to:
  /// **'Start exam'**
  String get examStart;

  /// Exam app bar
  ///
  /// In en, this message translates to:
  /// **'Question {current} of {total}'**
  String examQuestionOf(int current, int total);

  /// Exam timer for screen readers
  ///
  /// In en, this message translates to:
  /// **'Time left: {time}'**
  String examTimeLeft(String time);

  /// Exam flag button
  ///
  /// In en, this message translates to:
  /// **'Flag for review'**
  String get examFlag;

  /// Exam flag button when flagged
  ///
  /// In en, this message translates to:
  /// **'Remove flag'**
  String get examUnflag;

  /// Opens the question map
  ///
  /// In en, this message translates to:
  /// **'All questions'**
  String get examMap;

  /// Removes the chosen answer
  ///
  /// In en, this message translates to:
  /// **'Clear answer'**
  String get examClear;

  /// Exam navigation
  ///
  /// In en, this message translates to:
  /// **'Previous'**
  String get examPrevious;

  /// Exam submit button
  ///
  /// In en, this message translates to:
  /// **'Submit exam'**
  String get examSubmit;

  /// Submit confirm title
  ///
  /// In en, this message translates to:
  /// **'Submit the exam?'**
  String get examSubmitTitle;

  /// Submit confirm body
  ///
  /// In en, this message translates to:
  /// **'Answered: {answered} of {total}\nNot answered: {unanswered}\nFlagged: {flagged}'**
  String examSubmitBody(int answered, int total, int unanswered, int flagged);

  /// Submit confirm: cancel
  ///
  /// In en, this message translates to:
  /// **'Keep going'**
  String get examKeepGoing;

  /// Back button during an exam
  ///
  /// In en, this message translates to:
  /// **'Leave the exam?'**
  String get examLeaveTitle;

  /// Back button during an exam
  ///
  /// In en, this message translates to:
  /// **'The timer keeps running. You can come back from the Exams tab.'**
  String get examLeaveBody;

  /// Leave the exam
  ///
  /// In en, this message translates to:
  /// **'Leave'**
  String get examLeave;

  /// Shown when the exam auto-submits
  ///
  /// In en, this message translates to:
  /// **'Time is up. Your exam was submitted.'**
  String get examTimeUp;

  /// Question map legend
  ///
  /// In en, this message translates to:
  /// **'Answered'**
  String get examMapAnswered;

  /// Question map legend
  ///
  /// In en, this message translates to:
  /// **'Flagged'**
  String get examMapFlagged;

  /// Question map legend
  ///
  /// In en, this message translates to:
  /// **'Not answered'**
  String get examMapEmpty;

  /// Result screen title
  ///
  /// In en, this message translates to:
  /// **'Exam result'**
  String get examResultTitle;

  /// Result percent
  ///
  /// In en, this message translates to:
  /// **'{percent}% right'**
  String examResultPercent(int percent);

  /// Likely real score range
  ///
  /// In en, this message translates to:
  /// **'Estimated exam score: {low}–{high} out of {max}'**
  String examResultEstimate(int low, int high, int max);

  /// Note under the estimate
  ///
  /// In en, this message translates to:
  /// **'An estimate from this practice, not a promise.'**
  String get examResultEstimateNote;

  /// Result time
  ///
  /// In en, this message translates to:
  /// **'Time used: {time}'**
  String examResultTime(String time);

  /// Comparison with other students
  ///
  /// In en, this message translates to:
  /// **'Better than {percent}% of Guul 12 students'**
  String examResultBetterThan(int percent);

  /// Comparison not available yet
  ///
  /// In en, this message translates to:
  /// **'Not enough students have taken this mock yet. Check again later.'**
  String get examResultNotEnough;

  /// Comparison needs upload
  ///
  /// In en, this message translates to:
  /// **'Saved on your phone. Connect to the internet to compare with other students.'**
  String get examResultConnect;

  /// Refresh comparison
  ///
  /// In en, this message translates to:
  /// **'Check again'**
  String get examResultRefresh;

  /// Section title
  ///
  /// In en, this message translates to:
  /// **'By chapter'**
  String get examResultChapters;

  /// Chapter score
  ///
  /// In en, this message translates to:
  /// **'{correct} / {total}'**
  String examResultChapterScore(int correct, int total);

  /// Toggle the answer review
  ///
  /// In en, this message translates to:
  /// **'Review answers'**
  String get examResultReview;

  /// Toggle the answer review
  ///
  /// In en, this message translates to:
  /// **'Hide answers'**
  String get examResultHideReview;

  /// Button to practise the weakest chapter
  ///
  /// In en, this message translates to:
  /// **'Practise your weakest chapter'**
  String get examResultPractiseWeakest;

  /// Unknown result id
  ///
  /// In en, this message translates to:
  /// **'This result is not on this phone.'**
  String get examResultNotFound;

  /// Chapter not in the catalog
  ///
  /// In en, this message translates to:
  /// **'Chapter {id}'**
  String examUnknownChapter(int id);

  /// Spaced repetition practice mode
  ///
  /// In en, this message translates to:
  /// **'Review due questions'**
  String get practiceModeReview;

  /// Review mode with nothing due
  ///
  /// In en, this message translates to:
  /// **'Nothing to review today. Well done!'**
  String get practiceNothingToReview;

  /// Home card: spaced repetition
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{1 question to review today} other{{count} questions to review today}}'**
  String homeReviewDue(int count);

  /// Home card subtitle
  ///
  /// In en, this message translates to:
  /// **'Mistakes come back after 1, 3 and 7 days until you know them.'**
  String get homeReviewBody;

  /// Home: mistakes notebook
  ///
  /// In en, this message translates to:
  /// **'My mistakes'**
  String get homeMistakes;

  /// Home: mistakes count
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{1 question to learn} other{{count} questions to learn}}'**
  String homeMistakesBody(int count);

  /// Mistakes notebook title
  ///
  /// In en, this message translates to:
  /// **'My mistakes'**
  String get mistakesTitle;

  /// Empty notebook
  ///
  /// In en, this message translates to:
  /// **'No mistakes yet. Questions you get wrong appear here.'**
  String get mistakesEmpty;

  /// Starts wrong-only practice
  ///
  /// In en, this message translates to:
  /// **'Practise my mistakes'**
  String get mistakesPractise;

  /// SRS state
  ///
  /// In en, this message translates to:
  /// **'Review today'**
  String get mistakesReviewToday;

  /// SRS state
  ///
  /// In en, this message translates to:
  /// **'Next review: {date}'**
  String mistakesNextReview(String date);

  /// Adaptive practice mode
  ///
  /// In en, this message translates to:
  /// **'Smart practice'**
  String get practiceModeSmart;

  /// Home smart practice subtitle
  ///
  /// In en, this message translates to:
  /// **'10 questions picked for you: due reviews and your weak chapters first.'**
  String get homeSmartBody;

  /// Bottom navigation
  ///
  /// In en, this message translates to:
  /// **'Progress'**
  String get navProgress;

  /// Progress tab title
  ///
  /// In en, this message translates to:
  /// **'Progress'**
  String get progressTitle;

  /// Progress summary
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{1 question answered} other{{count} questions answered}}'**
  String progressAnswered(int count);

  /// Progress summary
  ///
  /// In en, this message translates to:
  /// **'{percent}% right (last answer to each question)'**
  String progressAccuracy(int percent);

  /// Progress empty state
  ///
  /// In en, this message translates to:
  /// **'Study a chapter and answer some questions to see your progress here.'**
  String get progressEmpty;

  /// Progress section
  ///
  /// In en, this message translates to:
  /// **'Weak topics'**
  String get progressWeak;

  /// No weak chapters
  ///
  /// In en, this message translates to:
  /// **'No weak topics right now. Keep practising!'**
  String get progressWeakNone;

  /// Progress section: mastery by subject
  ///
  /// In en, this message translates to:
  /// **'Chapter strength'**
  String get progressStrength;

  /// Chapter mastery line
  ///
  /// In en, this message translates to:
  /// **'{score}/100 · {answered} answered'**
  String progressChapterInfo(int score, int answered);

  /// Under a subject on the Progress screen
  ///
  /// In en, this message translates to:
  /// **'{started} of {total, plural, =1{1 chapter} other{{total} chapters}} started'**
  String progressSubjectStarted(int started, int total);

  /// Practise a weak chapter
  ///
  /// In en, this message translates to:
  /// **'Practise'**
  String get progressPractise;

  /// Mastery level
  ///
  /// In en, this message translates to:
  /// **'Not started'**
  String get masteryNotStarted;

  /// Mastery level
  ///
  /// In en, this message translates to:
  /// **'Learning'**
  String get masteryLearning;

  /// Mastery level
  ///
  /// In en, this message translates to:
  /// **'Good'**
  String get masteryGood;

  /// Mastery level
  ///
  /// In en, this message translates to:
  /// **'Strong'**
  String get masteryStrong;
}

class _AppLocalizationsDelegate extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) => <String>['en'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'en':
      return AppLocalizationsEn();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
