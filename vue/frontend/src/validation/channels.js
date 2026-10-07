// Validazione della configurazione canali lato browser.
// Regole in config/channel-rules.json, lette anche dall'API (api/src/ChannelValidator.php):
// se si cambia una regola qui, va cambiata anche là.
import defaultRules from '@config/channel-rules.json'

export { defaultRules as channelRules }

/** Righe del form: sempre N, una per canale; `saved` = risposta di GET /api/channels. */
export function toChannelForm(saved = [], rules = defaultRules) {
  return Array.from({ length: rules.channels }, (_, i) => {
    const row = saved.find((r) => r.channel === i + 1)
    return { channel: i + 1, device: row?.device ?? '', mode: row?.mode ?? rules.defaultMode }
  })
}

/**
 * Controlla le righe del form contro l'elenco dei dispositivi esistenti.
 * Chiavi d'errore uguali all'API: channels, channels.3.device, channels.3.mode.
 */
export function validateChannels(rows, deviceIds, rules = defaultRules) {
  const errors = {}
  if (!Array.isArray(rows) || rows.length !== rules.channels) {
    errors.channels = `Exactly ${rules.channels} channels expected`
    return { valid: false, errors, clean: [] }
  }
  const known = new Set(deviceIds)
  const clean = rows.map((r, i) => {
    const device = String(r.device ?? '').trim() || null
    if (device !== null && !known.has(device)) errors[`channels.${i}.device`] = 'Unknown device'
    if (!rules.modes.includes(r.mode)) errors[`channels.${i}.mode`] = `Choose ${rules.modes.join(' or ')}`
    return { device, mode: r.mode }
  })
  return { valid: Object.keys(errors).length === 0, errors, clean }
}
