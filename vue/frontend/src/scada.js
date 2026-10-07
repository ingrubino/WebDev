// Logica della pagina SCADA senza dipendenze da MQTT o dal DOM (testata in tests/scada.test.js).
// Formato dei messaggi: vue/canbus/TOPICS.md
import defaultConfig from '@config/scada.json'
import channelRules from '@config/channel-rules.json'

export const scadaConfig = defaultConfig

/** Colonne della griglia: intero tra 1 e il numero di moduli (valore non valido -> 3). */
export function gridColumns(config = defaultConfig, modules = channelRules.channels) {
  const n = Math.round(Number(config.columns))
  if (!Number.isFinite(n) || n < 1) return Math.min(3, modules)
  return Math.min(n, modules)
}

/** Numero del canale da un topic devices/<ch>/state; null se il topic non è di un canale. */
export function topicChannel(topic, kind = 'state', channels = channelRules.channels) {
  const m = /^devices\/(\d+)\/([a-z]+)$/.exec(topic)
  if (!m || m[2] !== kind) return null
  const ch = Number(m[1])
  return ch >= 1 && ch <= channels ? ch : null
}

/** Numero dentro i limiti di config.limits[key], altrimenti null (il codice errore deve essere intero). */
export function inRange(key, v, config = defaultConfig) {
  if (typeof v !== 'number' || !Number.isFinite(v)) return null
  const { min = -Infinity, max = Infinity } = config.limits?.[key] ?? {}
  if (v < min || v > max) return null
  if (key === 'error' && !Number.isInteger(v)) return null
  return v
}

/** Payload di devices/<ch>/state -> oggetto normalizzato; null se il JSON non è valido. */
export function parseState(payload, config = defaultConfig) {
  let s
  try { s = JSON.parse(typeof payload === 'string' ? payload : new TextDecoder().decode(payload)) } catch { return null }
  if (!s || typeof s !== 'object') return null
  return {
    current: inRange('current', s.current, config),
    temperature: inRange('temperature', s.temperature, config),
    error: inRange('error', s.error, config),
    control: s.control === 'manual' ? 'manual' : s.control === 'sync' ? 'sync' : null,
    power: s.power === 'on' ? 'on' : s.power === 'off' ? 'off' : null,
  }
}

/** Colore del LED: 'red' se il codice errore supera alarmAbove, 'green' altrimenti, null se manca. */
export function ledColor(error, config = defaultConfig) {
  if (error === null || error === undefined) return null
  return error > (config.alarmAbove ?? 15) ? 'red' : 'green'
}

/** Testo di un visualizzatore: numero intero, oppure --- se manca. */
export function display(value) {
  return value === null || value === undefined ? '---' : String(Math.round(value))
}

/** Stato vecchio: nessun messaggio da più di staleAfterSeconds (orario del browser, non del dispositivo). */
export function isStale(receivedAt, now, config = defaultConfig) {
  return !receivedAt || now - receivedAt > (config.staleAfterSeconds ?? 5) * 1000
}
