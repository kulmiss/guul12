import '../l10n/generated/app_localizations.dart';

/// Ethiopia's 12 regional states and 2 chartered cities (2023 onwards).
/// Somali comes first because it is our focus region. Stored as these codes;
/// the label comes from the translations.
const ethiopianRegions = <String>[
  'somali',
  'addis_ababa',
  'afar',
  'amhara',
  'benishangul_gumuz',
  'central_ethiopia',
  'dire_dawa',
  'gambela',
  'harari',
  'oromia',
  'sidama',
  'south_ethiopia',
  'south_west_ethiopia',
  'tigray',
];

String regionLabel(AppLocalizations l, String? code) =>
    code == null ? l.meNotSet : l.regionName(code);
