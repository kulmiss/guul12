/*
 * Guul 12 additions to Nova, in plain JavaScript (no build step). Uses the Vue that Nova already
 * provides (window.Vue) and Nova's own CSS classes, so it follows light and dark mode.
 */
Nova.booting((app) => {
  const { h, ref } = window.Vue

  // "Find student by ID" card on the payments dashboard.
  app.component('guul-find-student', {
    props: ['card'],
    setup() {
      const id = ref('')
      const find = (event) => {
        event.preventDefault()
        const value = id.value.trim()
        if (value === '') return
        Nova.visit(`/resources/students?students_search=${encodeURIComponent(value)}`)
      }

      return () =>
        h('div', { class: 'relative overflow-hidden bg-white dark:bg-gray-800 rounded-lg shadow px-6 py-4 h-full flex flex-col justify-center' }, [
          h('h3', { class: 'text-base font-bold text-gray-500 dark:text-gray-400 mb-1' }, 'Find student by ID'),
          h('p', { class: 'text-xs text-gray-500 dark:text-gray-400 mb-3' }, 'Type the ID the student sent you, for example ST-7K3Q9.'),
          h('form', { class: 'flex gap-2', onSubmit: find }, [
            h('input', {
              class: 'w-full form-control form-input form-control-bordered',
              type: 'search',
              placeholder: 'ST-…',
              'aria-label': 'Student ID',
              autocomplete: 'off',
              value: id.value,
              onInput: (e) => { id.value = e.target.value },
            }),
            h('button', {
              type: 'submit',
              class: 'shadow relative bg-primary-500 hover:bg-primary-400 text-white dark:text-gray-900 cursor-pointer rounded text-sm font-bold focus:outline-none focus:ring ring-primary-200 dark:ring-gray-600 inline-flex items-center justify-center h-9 px-3 shrink-0',
            }, 'Find'),
          ]),
        ])
    },
  })
})

// Maths in question previews: <span class="guul-tex" data-display="0|1">tex</span> from App\Support\MathText.
// KaTeX is loaded by Nova before this script. Bad TeX shows in red instead of breaking the page.
;(() => {
  const draw = () => {
    if (!window.katex) return
    document.querySelectorAll('.guul-tex:not([data-done])').forEach((el) => {
      el.setAttribute('data-done', '1')
      window.katex.render(el.textContent, el, { displayMode: el.dataset.display === '1', throwOnError: false })
    })
  }
  // Nova changes the page often: look at most once per frame.
  let queued = false
  const queue = () => {
    if (queued) return
    queued = true
    window.requestAnimationFrame(() => { queued = false; draw() })
  }
  new MutationObserver(queue).observe(document.documentElement, { childList: true, subtree: true })
  document.addEventListener('DOMContentLoaded', draw)
  window.addEventListener('load', draw)
})()

// Each staff member's last filter on each list is remembered in this browser and brought back when
// they open the list again. "Reset filters" forgets it. Nothing is sent to the server.
;(() => {
  const base = () => String(Nova.config('base') || '/nova').replace(/\/$/, '')
  const storageKey = (list) => 'guul.filters.' + Nova.config('userId') + '.' + list
  const listOnScreen = () => {
    const match = window.location.pathname.slice(base().length).match(/^\/resources\/([a-z0-9-]+)\/?$/)
    return match ? match[1] : null
  }
  const store = (list, value) => {
    try {
      value ? window.localStorage.setItem(storageKey(list), value) : window.localStorage.removeItem(storageKey(list))
    } catch (e) {
      // Private mode or storage switched off: filters are simply not remembered.
    }
  }
  const saved = (list) => {
    try {
      return window.localStorage.getItem(storageKey(list))
    } catch (e) {
      return null
    }
  }

  Nova.$on('filter-changed', (args) => {
    const list = listOnScreen()
    if (list) store(list, Array.isArray(args) ? args[0] : args)
  })
  Nova.$on('filter-reset', () => {
    const list = listOnScreen()
    if (list) store(list, null)
  })

  const restore = () => {
    const list = listOnScreen()
    if (!list) return
    const params = new URLSearchParams(window.location.search)
    if (params.has(list + '_filter')) return
    const value = saved(list)
    if (!value) return
    params.set(list + '_filter', value)
    Nova.visit('/resources/' + list + '?' + params.toString(), { replace: true })
  }
  document.addEventListener('inertia:navigate', restore)
})()

// Empty lists: one line under Nova's "No … matched" box saying what belongs there and how it gets
// filled (App\Nova\EmptyStates). Text only (textContent), never HTML.
;(() => {
  const addHelp = () => {
    const help = Nova.config('guulEmptyHelp') || {}
    document.querySelectorAll('[dusk$="-empty-dialog"]:not([data-guul-help])').forEach((box) => {
      box.setAttribute('data-guul-help', '1')
      const list = (box.getAttribute('dusk') || '').replace(/-empty-dialog$/, '')
      if (!Object.prototype.hasOwnProperty.call(help, list)) return
      const line = document.createElement('p')
      line.className = 'guul-empty-help'
      line.textContent = help[list]
      box.appendChild(line)
    })
  }
  let queued = false
  new MutationObserver(() => {
    if (queued) return
    queued = true
    window.requestAnimationFrame(() => { queued = false; addHelp() })
  }).observe(document.documentElement, { childList: true, subtree: true })
})()

// Settings → Branding (App\Nova\Branding\BrandingController). Colours are checked here as you type;
// the server checks them again and refuses to save anything below WCAG AA (4.5:1).
Nova.booting(() => {
  const { h, reactive, computed, ref } = window.Vue

  const channel = (c) => {
    const s = c / 255
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
  }
  const luminance = (hex) => {
    const n = parseInt(String(hex).slice(1), 16)
    return 0.2126 * channel((n >> 16) & 255) + 0.7152 * channel((n >> 8) & 255) + 0.0722 * channel(n & 255)
  }
  const isHex = (v) => /^#[0-9A-Fa-f]{6}$/.test(String(v))
  const ratio = (a, b) => {
    if (!isHex(a) || !isHex(b)) return 0
    const [x, y] = [luminance(a), luminance(b)]
    return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05)
  }
  const bestOn = (bg, dark) => (ratio('#FFFFFF', bg) >= ratio(dark, bg) ? '#FFFFFF' : dark)
  const clone = (v) => JSON.parse(JSON.stringify(v))

  const card = (title, children, help) =>
    h('div', { class: 'bg-white dark:bg-gray-800 rounded-lg shadow p-5 mb-5' }, [
      h('h2', { class: 'text-lg font-bold mb-1' }, title),
      help ? h('p', { class: 'text-sm text-gray-500 dark:text-gray-400 mb-3' }, help) : null,
      ...children,
    ])

  Nova.inertia('GuulBranding', {
    props: ['current', 'palettes', 'fonts', 'roundness', 'night', 'needed', 'history', 'notice'],
    setup(props) {
      const form = reactive(clone(props.current))
      const history = ref(props.history)
      const problems = ref([])
      const busy = ref(false)
      const fontCss = (key) => (props.fonts.find((f) => f.key === key) || props.fonts[0]).css

      const report = computed(() => {
        const l = form.light
        const d = form.dark
        return [
          ['Text on the background', l.text, l.background],
          ['Main colour on the background (links, titles)', l.primary, l.background],
          ['Button text on the main colour', bestOn(l.primary, l.text), l.primary],
          ['Text on the accent colour', bestOn(l.accent, l.text), l.accent],
          ['Main colour on the dark background', d.primary, props.night],
          ['Accent colour on the dark background', d.accent, props.night],
        ].map(([label, fg, bg]) => {
          const r = ratio(fg, bg)
          return { label, ratio: Math.floor(r * 100) / 100, ok: r >= props.needed }
        })
      })
      const allOk = computed(() => report.value.every((r) => r.ok))

      const apply = (data) => {
        Object.assign(form, clone(data))
        problems.value = []
      }
      const usePalette = (key) => {
        const p = props.palettes[key].colors
        form.palette = key
        form.light = clone(p.light)
        form.dark = clone(p.dark)
      }
      const done = (response) => {
        apply(response.data.current)
        history.value = response.data.history
        Nova.success(response.data.message)
      }
      const failed = (error) => {
        problems.value = error.response?.data?.problems || ['Something went wrong. Try again.']
        Nova.error(problems.value[0])
      }
      const call = (url, body) => {
        busy.value = true
        return Nova.request().post(Nova.url(url), body).then(done).catch(failed).finally(() => { busy.value = false })
      }
      const save = () => call('/branding/api/save', { data: form })
      const reset = () => { if (window.confirm('Go back to the Sunrise branding? You can restore this one from the history.')) call('/branding/api/reset', {}) }
      const restore = (id) => call(`/branding/api/restore/${id}`, {})
      const upload = (variant, event) => {
        const file = event.target.files[0]
        if (!file) return
        const data = new FormData()
        data.append('variant', variant)
        data.append('file', file)
        busy.value = true
        Nova.request().post(Nova.url('/branding/api/logo'), data)
          .then((r) => { form[`logo_${variant}`] = r.data.path; form[`logo_${variant}_url`] = r.data.url; Nova.success('Logo ready. Save to use it.') })
          .catch(failed)
          .finally(() => { busy.value = false; event.target.value = '' })
      }

      const colour = (group, key, label) =>
        h('label', { class: 'flex items-center gap-2 mb-2 text-sm' }, [
          h('input', { type: 'color', value: form[group][key], 'aria-label': label, class: 'h-8 w-10 rounded border',
            onInput: (e) => { form[group][key] = e.target.value.toUpperCase(); form.palette = 'custom' } }),
          h('input', { type: 'text', value: form[group][key], maxlength: 7, class: 'w-24 form-control form-input form-control-bordered',
            'aria-label': label + ' (code)', onInput: (e) => { form[group][key] = e.target.value.toUpperCase(); form.palette = 'custom' } }),
          h('span', label),
        ])

      const phone = (dark) => {
        const l = form.light
        const primary = dark ? form.dark.primary : l.primary
        const accent = dark ? form.dark.accent : l.accent
        const bg = dark ? props.night : l.background
        const text = dark ? '#E6EEF0' : l.text
        const onPrimary = dark ? props.night : bestOn(l.primary, l.text)
        const onAccent = dark ? props.night : bestOn(l.accent, l.text)
        const r = (props.roundness.find((x) => x.key === form.roundness) || props.roundness[1])
        const logo = dark ? (form.logo_dark_url || form.logo_light_url) : form.logo_light_url
        return h('div', { class: 'guul-phone', style: { background: bg, color: text, fontFamily: `'${fontCss(form.body_font)}', sans-serif` }, 'aria-label': dark ? 'Dark mode preview' : 'Light mode preview' }, [
          h('div', { class: 'guul-phone-bar', style: { fontFamily: `'${fontCss(form.heading_font)}', sans-serif` } }, [
            logo ? h('img', { src: logo, alt: '', class: 'guul-phone-logo' }) : null,
            form.logo_text,
          ]),
          h('div', { class: 'guul-phone-card', style: { background: primary, color: onPrimary, borderRadius: r.card + 'px' } }, [
            h('div', { style: { fontFamily: `'${fontCss(form.heading_font)}', sans-serif`, fontWeight: 900, fontSize: '20px' } }, '120 days to the exam'),
            h('div', { style: { fontSize: '12px' } }, 'Keep going, a little every day.'),
          ]),
          h('div', { class: 'guul-phone-option', style: { borderRadius: r.button + 'px', borderColor: dark ? '#2E474E' : '#E2E2E2', background: dark ? '#172A30' : '#FFFFFF' } }, 'A  Photosynthesis'),
          h('span', { class: 'guul-phone-chip', style: { background: accent, color: onAccent } }, 'Premium'),
          h('button', { type: 'button', tabindex: -1, class: 'guul-phone-button', style: { background: primary, color: onPrimary, borderRadius: r.button + 'px' } }, 'Start practice'),
          h('a', { class: 'guul-phone-link', style: { color: primary } }, 'See all chapters'),
        ])
      }

      return () =>
        h('div', { class: 'guul-branding' }, [
          h('h1', { class: 'text-2xl font-bold mb-2' }, 'Branding'),
          h('p', { class: 'text-sm text-gray-600 dark:text-gray-400 mb-5' }, props.notice),
          h('div', { class: 'guul-branding-grid' }, [
            h('div', [
              card('Palette', [h('div', { class: 'flex flex-wrap gap-2' }, Object.entries(props.palettes).map(([key, p]) =>
                h('button', { type: 'button', class: 'guul-palette' + (form.palette === key ? ' is-active' : ''), onClick: () => usePalette(key), 'aria-pressed': form.palette === key }, [
                  h('span', { class: 'guul-swatch', style: { background: p.colors.light.primary } }),
                  h('span', { class: 'guul-swatch', style: { background: p.colors.light.accent } }),
                  h('span', { class: 'guul-swatch', style: { background: p.colors.light.background } }),
                  ' ' + p.name,
                ])))], 'Start from a palette, then change any colour.'),
              card('Colours', [
                h('div', { class: 'font-bold text-sm mb-1' }, 'Light mode'),
                colour('light', 'primary', 'Main colour'), colour('light', 'accent', 'Accent colour'),
                colour('light', 'background', 'Background'), colour('light', 'text', 'Text colour'),
                h('div', { class: 'font-bold text-sm mt-3 mb-1' }, 'Dark mode'),
                colour('dark', 'primary', 'Main colour (dark mode)'), colour('dark', 'accent', 'Accent colour (dark mode)'),
                h('table', { class: 'guul-contrast mt-3' }, [
                  h('caption', { class: 'text-left text-sm font-bold mb-1' }, 'Contrast (WCAG AA needs 4.5:1)'),
                  ...report.value.map((r) => h('tr', [
                    h('td', r.label),
                    h('td', { class: r.ok ? 'is-ok' : 'is-bad' }, (r.ok ? '✓ ' : '✗ ') + r.ratio.toFixed(2) + ':1'),
                  ])),
                ]),
              ]),
              card('Fonts and shape', [
                h('label', { class: 'block text-sm mb-2' }, ['Heading font ',
                  h('select', { class: 'form-control form-select form-control-bordered ml-2', value: form.heading_font, onChange: (e) => { form.heading_font = e.target.value } },
                    props.fonts.map((f) => h('option', { value: f.key }, f.label)))]),
                h('label', { class: 'block text-sm mb-2' }, ['Text font ',
                  h('select', { class: 'form-control form-select form-control-bordered ml-2', value: form.body_font, onChange: (e) => { form.body_font = e.target.value } },
                    props.fonts.map((f) => h('option', { value: f.key }, f.label)))]),
                h('div', { class: 'text-sm', role: 'radiogroup', 'aria-label': 'Roundness' }, ['Roundness: ',
                  ...props.roundness.map((r) => h('label', { class: 'mr-3' }, [
                    h('input', { type: 'radio', name: 'roundness', value: r.key, checked: form.roundness === r.key, onChange: () => { form.roundness = r.key } }),
                    ' ' + r.key,
                  ]))]),
              ]),
              card('Logo', [
                h('label', { class: 'block text-sm mb-2' }, ['Logo text ',
                  h('input', { type: 'text', maxlength: 30, value: form.logo_text, class: 'form-control form-input form-control-bordered ml-2', onInput: (e) => { form.logo_text = e.target.value } })]),
                ...['light', 'dark'].map((v) => h('div', { class: 'flex items-center gap-3 mb-2 text-sm' }, [
                  h('span', { style: { minWidth: '7rem' } }, v === 'light' ? 'Logo (light)' : 'Logo (dark)'),
                  form[`logo_${v}_url`] ? h('img', { src: form[`logo_${v}_url`], alt: '', class: 'guul-logo-thumb' + (v === 'dark' ? ' is-dark' : '') }) : h('span', { class: 'text-gray-500' }, 'none'),
                  h('input', { type: 'file', accept: 'image/png,image/jpeg,image/webp', 'aria-label': `Upload logo for ${v} mode`, onChange: (e) => upload(v, e) }),
                  form[`logo_${v}`] ? h('button', { type: 'button', class: 'text-red-500', onClick: () => { form[`logo_${v}`] = null; form[`logo_${v}_url`] = null } }, 'Remove') : null,
                ])),
              ], 'PNG, JPG or WebP, at most 2 MB. It is made at most 512 px wide or high.'),
            ]),
            h('div', [
              card('Live preview', [h('div', { class: 'flex flex-wrap gap-4' }, [phone(false), phone(true)])],
                'What students see in the app (light and dark mode).'),
              problems.value.length ? h('div', { class: 'guul-problems', role: 'alert' }, problems.value.map((p) => h('p', p))) : null,
              h('div', { class: 'flex flex-wrap gap-3 mb-5' }, [
                h('button', { type: 'button', disabled: busy.value || !allOk.value, onClick: save,
                  class: 'shadow rounded bg-primary-500 hover:bg-primary-400 text-white dark:text-gray-900 font-bold h-9 px-4 disabled:opacity-50' },
                  allOk.value ? 'Save branding' : 'Fix the contrast to save'),
                h('button', { type: 'button', disabled: busy.value, onClick: reset, class: 'rounded border border-gray-300 dark:border-gray-600 font-bold h-9 px-4' }, 'Reset to Sunrise'),
              ]),
              card('History', [history.value.length === 0 ? h('p', { class: 'text-sm text-gray-500' }, 'No saved branding yet: Sunrise is in use.')
                : h('table', { class: 'guul-history' }, history.value.map((v) => h('tr', [
                  h('td', '#' + v.id), h('td', v.at), h('td', v.by), h('td', v.palette + ' · ' + v.logo_text + (v.note ? ' · ' + v.note : '')),
                  h('td', v.current ? h('strong', 'In use') : h('button', { type: 'button', class: 'text-primary-500 font-bold', disabled: busy.value, onClick: () => restore(v.id) }, 'Restore')),
                ])))], 'The last 10 saves. Restoring adds it again as the newest.'),
            ]),
          ]),
        ])
    },
  })
})
