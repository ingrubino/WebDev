// Connessione MQTT condivisa (via WebSocket su /mqtt, inoltrato da nginx al broker).
// Una sola connessione per tutta l'app: si apre con useLive() e si chiude quando
// nessuna pagina la usa più. Topic e payload: vue/canbus/TOPICS.md
import mqtt from 'mqtt'
import { reactive } from 'vue'
import { parseState, topicChannel } from './scada'

export const live = reactive({
  connected: false,
  gateway: null, // { status: 'online' | 'offline', bus } dal topic retained system/gateway/status
  states: {},    // canale -> { ...stato, receivedAt }
  syncPower: null, // 'on' | 'off' dal topic retained system/sync/state (pulsante sync unico)
})

let client = null
let users = 0
const pending = new Map() // id richiesta Set devices -> { resolve, timer }

export function brokerUrl(loc = window.location) {
  return `${loc.protocol === 'https:' ? 'wss' : 'ws'}://${loc.host}/mqtt`
}

function onMessage(topic, payload) {
  const ch = topicChannel(topic)
  if (ch) {
    const state = parseState(payload)
    if (state) live.states[ch] = { ...state, receivedAt: Date.now() }
    return
  }
  let body
  try { body = JSON.parse(payload.toString()) } catch { return }
  if (topic === 'system/gateway/status') live.gateway = body
  if (topic === 'system/sync/state') live.syncPower = ['on', 'off'].includes(body.power) ? body.power : null
  if (topic === 'system/set_devices/result' && pending.has(body.id)) {
    const { resolve, timer } = pending.get(body.id)
    clearTimeout(timer)
    pending.delete(body.id)
    resolve(body)
  }
}

/** Apre (o riusa) la connessione; restituisce la funzione per rilasciarla. */
export function useLive() {
  users++
  if (!client) {
    client = mqtt.connect(brokerUrl(), {
      clientId: `web-${Math.random().toString(16).slice(2, 10)}`,
      reconnectPeriod: 2000,
      connectTimeout: 5000,
    })
    client.on('connect', () => {
      live.connected = true
      client.subscribe(['devices/+/state', 'system/gateway/status', 'system/sync/state', 'system/set_devices/result'], { qos: 1 })
    })
    client.on('close', () => { live.connected = false })
    client.on('message', onMessage)
  }
  let released = false
  return () => {
    if (released) return
    released = true
    if (--users > 0) return
    client.end(true)
    client = null
    Object.assign(live, { connected: false, gateway: null, states: {}, syncPower: null })
  }
}

/** Comando a un modulo: { control: 'sync'|'manual' } oppure { power: 'on'|'off' }. */
export function sendCommand(channel, command) {
  if (!client || !live.connected) return false
  client.publish(`devices/${channel}/cmd`, JSON.stringify(command), { qos: 1 })
  return true
}

/** Pulsante sync unico: ON/OFF per tutti i moduli in modalità sync. */
export function sendSyncPower(power) {
  if (!client || !live.connected) return false
  client.publish('system/sync/cmd', JSON.stringify({ power }), { qos: 1 })
  return true
}

/** Set devices: start via MQTT al gateway; risolve con il risultato { ok, sent, log, error }. */
export function startSetDevices(timeoutMs = 60000) {
  if (!client || !live.connected) return Promise.reject(new Error('MQTT broker unreachable'))
  const id = Math.random().toString(16).slice(2, 14)
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => {
      pending.delete(id)
      reject(new Error('No answer from the device gateway'))
    }, timeoutMs)
    pending.set(id, { resolve, timer })
    client.publish('system/set_devices/start', JSON.stringify({ id }), { qos: 1 })
  })
}
