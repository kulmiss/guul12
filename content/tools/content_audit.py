"""Run from anywhere: python3 content/tools/content_audit.py (needs node; writes content_issues.json here).
Bug hunt: extra automatic checks on every Grade 12 question (beyond content:check)."""
import os, glob, json, re, subprocess, sys, difflib, collections, yaml

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'grade12')
files = sorted(f for f in glob.glob(f'{ROOT}/*/u*-c*.yaml'))
qs = []
for f in files:
    d = yaml.safe_load(open(f))
    subject = f.split('/')[-2]
    for q in d.get('questions', []):
        q['_file'] = f.split('grade12/')[1]; q['_subject'] = subject
        qs.append(q)
print('questions', len(qs), 'files', len(files))

issues = collections.defaultdict(list)

def texts(q):
    out = [('stem', q.get('stem', '')), ('explanation', q.get('explanation', ''))]
    opts = q.get('options') or {}
    if isinstance(opts, dict):
        out += [(f'option {k}', str(v)) for k, v in opts.items()]
    elif isinstance(opts, list):
        out += [(f'option {i}', json.dumps(v)) for i, v in enumerate(opts)]
    for k in ('left', 'right', 'items', 'pairs'):
        if k in q: out.append((k, json.dumps(q[k])))
    return out

# 1. LaTeX: every $...$ and $$...$$ must render in KaTeX.
math = []
for q in qs:
    for where, t in texts(q):
        for m in re.finditer(r'\$\$(.+?)\$\$|(?<!\\)\$(.+?)(?<!\\)\$', str(t), re.S):
            tex = m.group(1) or m.group(2)
            math.append(json.dumps({'id': f"{q['uid']} {where}", 'tex': tex, 'display': m.group(1) is not None}))
r = subprocess.run(['node', os.path.join(os.path.dirname(os.path.abspath(__file__)), 'katex_check.js')], input='\n'.join(math), capture_output=True, text=True)
for l in r.stdout.splitlines():
    issues['latex'].append(json.loads(l))
print(r.stderr.strip())

# 2. Near-duplicate questions (same subject, very similar stem).
norm = lambda s: re.sub(r'\s+', ' ', re.sub(r'[^a-z0-9$\\^_ ]', '', str(s).lower())).strip()
by_subject = collections.defaultdict(list)
for q in qs: by_subject[q['_subject']].append(q)
for subj, items in by_subject.items():
    stems = [(q['uid'], norm(q['stem'])) for q in items]
    for i in range(len(stems)):
        for j in range(i + 1, len(stems)):
            a, b = stems[i][1], stems[j][1]
            if abs(len(a) - len(b)) > 0.15 * max(len(a), len(b), 1): continue
            ratio = difflib.SequenceMatcher(None, a, b).ratio()
            if ratio >= 0.93:
                qa = next(q for q in items if q['uid'] == stems[i][0]); qb = next(q for q in items if q['uid'] == stems[j][0])
                same_answer = json.dumps(qa.get('answer'), sort_keys=True) == json.dumps(qb.get('answer'), sort_keys=True) and qa.get('options') == qb.get('options')
                issues['near_duplicate'].append({'a': stems[i][0], 'b': stems[j][0], 'ratio': round(ratio, 3), 'same_options_and_answer': same_answer})

# 3. Duplicate options inside a question (after normalising).
for q in qs:
    opts = q.get('options')
    if isinstance(opts, dict):
        vals = [re.sub(r'\s+', ' ', str(v).replace('−', '-')).strip() for v in opts.values()]
        if len(set(vals)) != len(vals): issues['duplicate_options'].append(q['uid'])

# 4. Numeric: the explanation should show the key's value.
def fmt_variants(v):
    out = {str(v)}
    try:
        f = float(v)
        out |= {f'{f:g}', f'{f:.1f}', f'{f:.2f}', f'{f:.3f}', f'{f:,.0f}', f'{f:,g}'}
        if f == int(f): out.add(str(int(f))); out.add(f'{int(f):,}')
    except Exception: pass
    return {x.replace('-', '−') for x in out} | out
for q in qs:
    if q['type'] == 'numeric':
        ans = q.get('answer') or {}
        val = ans.get('value') if isinstance(ans, dict) else ans
        expl = str(q.get('explanation', '')).replace('{,}', ',').replace('\\,', '').replace(' ', '')
        if not any(v.replace(' ', '') in expl for v in fmt_variants(val)):
            issues['numeric_value_not_in_explanation'].append({'uid': q['uid'], 'value': val})

# 5. True/false: the explanation must not start with the opposite word.
for q in qs:
    if q['type'] == 'true_false':
        key = str(q.get('answer')).lower()
        e = str(q.get('explanation', '')).lower()[:80]
        opposite = 'false' if key in ('true', 'a') else 'true'
        if e.startswith(opposite): issues['true_false_explanation_contradicts'].append(q['uid'])

# 6. Difficulty spread per chapter.
for f in files:
    d = yaml.safe_load(open(f)); c = collections.Counter(q.get('difficulty') for q in d.get('questions', []))
    if len(c) < 3 or max(c.values()) > 0.7 * sum(c.values()):
        issues['difficulty_spread'].append({'file': f.split('grade12/')[1], 'spread': dict(c)})

json.dump(issues, open('content_issues.json', 'w'), indent=1, ensure_ascii=False)
for k, v in issues.items(): print(f'{k}: {len(v)}')
