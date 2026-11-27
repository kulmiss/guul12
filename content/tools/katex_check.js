// Reads JSON lines {id, tex, display} from stdin; prints the ones KaTeX cannot draw.
const katex = require(require('path').join(__dirname, '..', '..', 'backend', 'public', 'katex', 'katex.min.js'))
const lines = require('fs').readFileSync(0, 'utf8').split('\n').filter(Boolean)
let bad = 0
for (const l of lines) {
  const { id, tex, display } = JSON.parse(l)
  try { katex.renderToString(tex, { throwOnError: true, displayMode: display, strict: 'ignore' }) }
  catch (e) { bad++; console.log(JSON.stringify({ id, tex, error: String(e.message).slice(0, 140) })) }
}
console.error(`checked ${lines.length}, failed ${bad}`)
