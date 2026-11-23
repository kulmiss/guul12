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
