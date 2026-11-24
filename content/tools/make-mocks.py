#!/usr/bin/env python3
"""Writes the fixed mock exams: content/grade12/mocks/<subject>-<n>.yaml.

Each mock takes about 50 choice questions (the real paper's type) from the bank:
  - every chapter of the subject is covered, in proportion to its questions;
  - no question is in two mocks of the same subject;
  - the questions of one reading passage stay together;
  - questions follow the chapter order (or, if that cannot avoid it, a mixed order),
    and the key never shows a pattern (same letter 4 times in a row, or A, B, C, D, A… 5 in a row).
The choice is random but repeatable (fixed seed per subject), so running this
again on the same bank gives the same files. php artisan content:check checks the result.

Usage: python3 content/tools/make-mocks.py [--size 50] [--mocks 3] [--free 2] [--minutes-per-question 1.5]
"""
import argparse
import math
import random
import sys
from pathlib import Path

import yaml

ROOT = Path(__file__).resolve().parents[1] / 'grade12'
NAMES = {
    'english': 'English', 'mathematics': 'Mathematics', 'aptitude': 'Scholastic Aptitude',
    'physics': 'Physics', 'chemistry': 'Chemistry', 'biology': 'Biology',
    'geography': 'Geography', 'history': 'History', 'economics': 'Economics',
}
CHOICE = {'single_choice', 'passage_choice'}
NEXT = {'A': 'B', 'B': 'C', 'C': 'D', 'D': 'A'}


def bank(subject):
    """Units in curriculum order: a unit is one question or one passage's questions."""
    units = []
    for u in subject['units']:
        for c in u['chapters']:
            if not c.get('file'):
                continue
            chapter = yaml.safe_load((ROOT / c['file']).read_text())
            groups = {}
            for q in chapter['questions']:
                if q['type'] not in CHOICE:
                    continue
                key = q.get('passage') or q['uid']
                if key not in groups:
                    groups[key] = []
                    units.append({'chapter': c['file'], 'questions': groups[key]})
                groups[key].append((q['uid'], q['answer']))
    return units


def key_problem(letters):
    same = cycle = 0
    prev = None
    for letter in letters:
        same = same + 1 if letter == prev else 1
        cycle = cycle + 1 if prev is not None and NEXT.get(prev) == letter else 1
        if same >= 4 or cycle >= 5:
            return True
        prev = letter
    return False


def order(units, rng):
    """Chapter order; inside a chapter, shuffle until the key shows no pattern."""
    chapters = []
    for u in units:
        if not chapters or chapters[-1][0] != u['chapter']:
            chapters.append((u['chapter'], []))
        chapters[-1][1].append(u)
    for _ in range(2000):
        for _, items in chapters:
            rng.shuffle(items)
        flat = [q for _, items in chapters for u in items for q in u['questions']]
        if not key_problem([a for _, a in flat]):
            return [uid for uid, _ in flat]
    # A chapter's few questions may share a letter: mix the whole mock instead (passages stay whole).
    mixed = list(units)
    for _ in range(5000):
        rng.shuffle(mixed)
        flat = [q for u in mixed for q in u['questions']]
        if not key_problem([a for _, a in flat]):
            return [uid for uid, _ in flat]
    sys.exit('Could not order a mock without a key pattern; add or change questions.')


def make(code, subject, args):
    rng = random.Random(f'guul12-mocks-{code}')
    units = bank(subject)
    total = sum(len(u['questions']) for u in units)
    count = min(args.mocks, total // args.size)
    if count == 0:
        return []

    # Deal the units to the mocks chapter by chapter, so each mock gets every chapter.
    by_chapter = {}
    for u in units:
        by_chapter.setdefault(u['chapter'], []).append(u)
    piles = [[] for _ in range(count)]
    turn = 0
    for items in by_chapter.values():
        rng.shuffle(items)
        for u in items:
            piles[turn % count].append(u)
            turn += 1

    mocks = []
    for n, pile in enumerate(piles, start=1):
        # Keep passages; then take evenly spaced units (in chapter order) up to the size.
        pile.sort(key=lambda u: list(by_chapter).index(u['chapter']))
        chosen = [u for u in pile if len(u['questions']) > 1]
        singles = [u for u in pile if len(u['questions']) == 1]
        need = args.size - sum(len(u['questions']) for u in chosen)
        if need > 0:
            step = len(singles) / need
            chosen += [singles[math.floor(i * step)] for i in range(min(need, len(singles)))]
        chosen.sort(key=lambda u: (list(by_chapter).index(u['chapter']), units.index(u)))
        uids = order(chosen, rng)
        mocks.append({
            'uid': f'g12-mock-{code}-{n}',
            'subject': code,
            'number': n,
            'title': f'{NAMES[code]} mock exam {n}',
            'minutes': round(len(uids) * args.minutes_per_question),
            'access': 'free' if n <= args.free else 'premium',
            'questions': uids,
        })
    return mocks


def main():
    p = argparse.ArgumentParser()
    p.add_argument('--size', type=int, default=50)
    p.add_argument('--mocks', type=int, default=3)
    p.add_argument('--free', type=int, default=2)
    p.add_argument('--minutes-per-question', type=float, default=1.5)
    args = p.parse_args()

    curriculum = yaml.safe_load((ROOT / 'curriculum.yaml').read_text())
    out = ROOT / 'mocks'
    out.mkdir(exist_ok=True)
    for old in out.glob('*.yaml'):
        old.unlink()
    for subject in curriculum['subjects']:
        for mock in make(subject['code'], subject, args):
            path = out / f"{mock['subject']}-{mock['number']}.yaml"
            header = '# Fixed mock exam. Made by content/tools/make-mocks.py; checked by content:check.\n'
            path.write_text(header + yaml.safe_dump(mock, sort_keys=False, allow_unicode=True, width=120))
            print(f"{path.relative_to(ROOT.parent)}: {len(mock['questions'])} questions, {mock['minutes']} min, {mock['access']}")


if __name__ == '__main__':
    main()
