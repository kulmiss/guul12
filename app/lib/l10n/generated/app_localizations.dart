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
