<script setup>
// Pagina SCADA: un modulo per canale in una griglia (colonne in config/scada.json).
// Dati in tempo reale e comandi via MQTT (src/mqtt.js); dispositivo e AC/DC dalla
// configurazione canali (GET /api/channels). I 4 interruttori ON/OFF 1..4 comandano i moduli
// che li hanno scelti come sorgente (la logica è nel gateway Python, vedi vue/canbus/TOPICS.md).
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { api } from '@/api/client'
import { live, sendCommand, sendSwitchPower, useLive } from '@/mqtt'
import { gridColumns, isStale, SWITCHES } from '@/scada'
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
const linkedCount = (n) => channels.value.filter((r) => stateOf(r.channel)?.source === n).length
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
    <div class="switch-bank">
      <div v-for="n in SWITCHES" :key="n" class="switch-control"
           :class="{ live: gatewayOnline && live.switches[n] === 'on' }">
        <span>ON/OFF {{ n }}</span>
        <SlideSwitch :name="`ON/OFF ${n}`" v-bind="POWER" :value="gatewayOnline ? live.switches[n] ?? null : null"
                     :disabled="!gatewayOnline || !live.switches[n]" @change="(v) => sendSwitchPower(n, v)" />
        <span v-if="gatewayOnline && live.switches[n] === 'on'" class="live-badge on"><span class="lamp"></span>LIVE</span>
        <small class="hint">{{ linkedCount(n) }} module(s)</small>
      </div>
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
