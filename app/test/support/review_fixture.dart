/// A small solved review exercise for tests and goldens (made-up questions, not textbook text).
Map<String, Object?> sampleReview() => {
  'unit_id': 11,
  'book': 'Physics Student Textbook, Grade 12',
  'pages': '64–66',
  'intro': r'Use $g = 9.8$ m/s².',
  'items': [
    {
      'ref': '1',
      'question': r'A ball is thrown horizontally at $5$ m/s from $20$ m. How far does it land?',
      'note': null,
      'figure': 'Figure 2.9',
      'table': null,
      'book_error': false,
      'steps': [r'Time: $t = \sqrt{2h/g} = 2$ s', r'Distance: $x = 5 \times 2$'],
      'answer': r'$10$ m',
      'parts': <Object?>[],
    },
    {
      'ref': '2',
      'question': 'Use the table.',
      'note': null,
      'figure': null,
      'table': {
        't': [0, 1, 2],
        'v': [0, 10, 20],
      },
      'book_error': false,
      'steps': <Object?>[],
      'answer': null,
      'parts': [
        {
          'ref': 'a',
          'question': 'Find the acceleration.',
          'note': null,
          'steps': ['The speed grows by 10 each second.'],
          'answer': r'$10$ m/s²',
        },
      ],
    },
    {
      'ref': '3',
      'question': 'A question printed wrongly.',
      'note': 'Cannot be solved with the numbers in the book.',
      'figure': null,
      'table': null,
      'book_error': true,
      'steps': <Object?>[],
      'answer': null,
      'parts': <Object?>[],
    },
  ],
};
