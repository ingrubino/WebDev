// Validazione del dispositivo lato browser.
// Le soglie arrivano da config/device-rules.json, lo stesso file letto dall'API PHP
// (api/src/DeviceValidator.php): se si cambia una regola qui, va cambiata anche là.
import defaultRules from '@config/device-rules.json'

export { defaultRules as rules }

const NUMBER_RE = /^[-+]?(\d+\.?\d*|\.\d+)([eE][-+]?\d+)?$/

/** '' / null / spazi -> null; virgola decimale accettata ("1,5" -> "1.5"). */
export function normalizeNumber(value) {
  if (value === null || value === undefined) return null
  const s = String(value).trim().replace(',', '.')
  return s === '' ? null : s
}

function numberError(s, rule) {
  if (!NUMBER_RE.test(s)) return 'Must be a number'
  const n = Number(s)
  if (rule.min !== null && rule.min !== undefined && n < rule.min) return `Min ${rule.min}`
  if (rule.max !== null && rule.max !== undefined && n > rule.max) return `Max ${rule.max}`
  return null
}

/** Modello vuoto per il form: matrice rows x 2 e vettore di stringhe. */
export function emptyDevice(r = defaultRules) {
  return {
    identifier: '',
    matrix: Array.from({ length: r.rows }, () => ['', '']),
    vector: Array.from({ length: r.vectorLength }, () => ''),
  }
}

/** Porta un dispositivo arrivato da API/file nella forma del form (stringhe, lunghezze fisse). */
export function toFormModel(device, r = defaultRules) {
  const form = emptyDevice(r)
  form.identifier = device.identifier ?? ''
  ;(device.matrix || []).slice(0, r.rows).forEach((row, i) => {
    form.matrix[i] = [row?.[0] ?? '', row?.[1] ?? ''].map((v) => (v === null ? '' : String(v)))
  })
  ;(device.vector || []).slice(0, r.vectorLength).forEach((v, j) => {
    form.vector[j] = v === null || v === undefined ? '' : String(v)
  })
  return form
}

/**
 * Valida il modello del form.
 * Ritorna { errors, clean, valid }:
 *  - errors: mappa campo -> messaggio, chiavi 'identifier', 'matrix', 'matrix.3.0', 'vector.2' ...
 *  - clean:  payload pronto per l'API (righe vuote rimosse, numeri come Number)
 */
export function validateDevice(form, r = defaultRules) {
  const errors = {}

  const identifier = (form.identifier ?? '').trim()
  if (!identifier) errors.identifier = 'Required'
  else if (identifier.length > r.identifier.maxLength) errors.identifier = `Max ${r.identifier.maxLength} characters`
  else if (!new RegExp(r.identifier.pattern, 'u').test(identifier)) errors.identifier = `Allowed: ${r.identifier.patternHint}`

  const matrix = []
  let prevTime = null
  ;(form.matrix || []).slice(0, r.rows).forEach((row, i) => {
    const t = normalizeNumber(row?.[0])
    const c = normalizeNumber(row?.[1])
    if (t === null && c === null) return
    if (t === null) { errors[`matrix.${i}.0`] = 'Required when current is set'; return }
    if (c === null) { errors[`matrix.${i}.1`] = 'Required when time is set'; return }
    const te = numberError(t, r.time)
    const ce = numberError(c, r.current)
    if (te) errors[`matrix.${i}.0`] = te
    if (ce) errors[`matrix.${i}.1`] = ce
    if (!te) {
      if (r.time.strictlyIncreasing && prevTime !== null && Number(t) <= prevTime) {
        errors[`matrix.${i}.0`] = 'Must be greater than previous time'
      }
      prevTime = Number(t)
    }
    matrix.push([Number(t), Number(c)])
  })
  if ((form.matrix || []).length > r.rows) errors.matrix = `At most ${r.rows} rows`
  else if (matrix.length < r.minPoints) errors.matrix = `At least ${r.minPoints} complete rows`

  const vector = []
  for (let j = 0; j < r.vectorLength; j++) {
    const v = normalizeNumber(form.vector?.[j])
    if (v === null) {
      if (r.vector.required) errors[`vector.${j}`] = 'Required'
      vector.push(null)
      continue
    }
    const ve = numberError(v, r.vector)
    if (ve) errors[`vector.${j}`] = ve
    vector.push(Number(v))
  }
  if ((form.vector || []).length > r.vectorLength) errors.vector = `At most ${r.vectorLength} values`

  return { errors, clean: { identifier, matrix, vector }, valid: Object.keys(errors).length === 0 }
}

/** Punti numericamente validi, utili per disegnare il grafico anche con il form incompleto. */
export function plottablePoints(form) {
  return (form.matrix || [])
    .map(([t, c]) => [normalizeNumber(t), normalizeNumber(c)])
    .filter(([t, c]) => t !== null && c !== null && NUMBER_RE.test(t) && NUMBER_RE.test(c))
    .map(([t, c]) => [Number(t), Number(c)])
}
