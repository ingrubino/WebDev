<script setup>
// Pagina SCADA: un modulo per canale in una griglia (colonne in config/scada.json).
// Dati in tempo reale e comandi via MQTT (src/mqtt.js); dispositivo e AC/DC dalla
// configurazione canali (GET /api/channels). Il pulsante sync comanda l'ON/OFF di tutti i
// moduli in modalità sync (la logica è nel gateway Python, vedi vue/canbus/TOPICS.md).
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { api } from '@/api/client'
import { live, sendCommand, sendSyncPower, useLive } from '@/mqtt'
import { gridColumns, isStale } from '@/scada'
import { toChannelForm } from '@/validation/channels'
import ScadaModule from '@/components/ScadaModule.vue'
import SlideSwitch from '@/components/SlideSwitch.vue'

const channels = ref(toChannelForm())
const message = ref('')
const now = ref(Date.now())
const columns = gridColumns()
let release, timer

onMounted(async () => {
  release = useLive()
  timer = setInterval(() => { now.value = Date.now() }, 1000)
  try {
    channels.value = toChannelForm((await api.getChannels()).channels)
  } catch (e) {
    message.value = `Cannot load channel configuration: ${e.message}`
  }
})
onUnmounted(() => { release?.(); clearInterval(timer) })

const POWER = { left: { value: 'on', label: 'ON' }, right: { value: 'off', label: 'OFF' } }
const syncCount = computed(() => channels.value.filter((r) => stateOf(r.channel)?.control === 'sync').length)
const gatewayOnline = computed(() => live.connected && live.gateway?.status === 'online')
const stateOf = (ch) => {
  const s = live.states[ch]
  return s && gatewayOnline.value && !isStale(s.receivedAt, now.value) ? s : null
}
</script>

<template>
  <div class="scada-head">
    <h1>SCADA</h1>
    <span class="status"><span class="indicator" :class="{ alert: !live.connected }"></span>
      Broker {{ live.connected ? 'connected' : 'disconnected' }}</span>
    <span class="status"><span class="indicator" :class="{ alert: !gatewayOnline }"></span>
      Gateway {{ gatewayOnline ? `online (${live.gateway.bus ?? '?'})` : 'offline' }}</span>
    <div class="sync-control">
      <span>Sync</span>
      <SlideSwitch name="Sync power" v-bind="POWER" :value="gatewayOnline ? live.syncPower : null"
                   :disabled="!gatewayOnline || !live.syncPower" @change="sendSyncPower" />
      <small class="hint">{{ syncCount }} module(s) in sync</small>
    </div>
  </div>
  <p v-if="message" class="message error" role="status">{{ message }}</p>

  <div class="scada-grid" :style="{ '--cols': columns }">
    <ScadaModule
      v-for="row in channels"
      :key="row.channel"
      :channel="row.channel"
      :device="row.device"
      :acdc="row.device ? row.mode : ''"
      :state="stateOf(row.channel)"
      :disabled="!gatewayOnline"
      @command="(cmd) => sendCommand(row.channel, cmd)"
    />
  </div>
</template>
