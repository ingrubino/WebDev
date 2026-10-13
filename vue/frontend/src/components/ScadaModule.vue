<script setup>
// Un modulo della pagina SCADA (un canale): corrente, temperatura, codice errore con LED
// (verde/rosso secondo alarmAbove), selettore della sorgente e interruttore ON/OFF.
// Sorgente Manual: l'uscita segue l'ON/OFF del modulo; ON/OFF 1..4: segue quell'interruttore
// globale della pagina e l'ON/OFF del modulo è bloccato.
// Uscita accesa (power "on" riportato dal dispositivo = linea alimentata, possibile pericolo):
// spia LIVE rossa nell'intestazione e bordo rosso del riquadro.
import { computed } from 'vue'
import SlideSwitch from './SlideSwitch.vue'
import { display, ledColor, liveState, SWITCHES, sourceLabel } from '@/scada'

const props = defineProps({
  channel: { type: Number, required: true },
  device: { type: String, default: '' },
  acdc: { type: String, default: '' },
  state: { type: Object, default: null }, // null = nessun dato recente
  disabled: Boolean,                      // broker o gateway non raggiungibili
})
const emit = defineEmits(['command'])

const s = computed(() => props.state ?? {})
const led = computed(() => ledColor(s.value.error))
const live = computed(() => liveState(props.state))
const linked = computed(() => SWITCHES.includes(s.value.source))
const SOURCES = ['manual', ...SWITCHES]
const POWER = { left: { value: 'on', label: 'ON' }, right: { value: 'off', label: 'OFF' } }

// passando a Manual si invia anche il valore attuale dell'ON/OFF, così l'uscita non cambia
function setSource(event) {
  const v = event.target.value
  const source = v === 'manual' ? 'manual' : Number(v)
  emit('command', source === 'manual' ? { source, power: s.value.power ?? 'off' } : { source })
}
</script>

<template>
  <article class="scada-module" :class="{ 'no-data': !state, live: live === 'on' }" :aria-label="`Channel ${channel}`">
    <header>
      <strong>CH {{ channel }}</strong>
      <span class="device" :title="device">{{ device || 'not used' }}</span>
      <span v-if="acdc" class="tag">{{ acdc }}</span>
      <span class="live-badge" :class="live" role="status"
            :aria-label="`Output, channel ${channel}: ${live === 'on' ? 'live' : live === 'off' ? 'off' : 'unknown'}`">
        <span class="lamp"></span>{{ live === 'on' ? 'LIVE' : live === 'off' ? 'OFF' : '---' }}</span>
    </header>

    <div class="readouts">
      <output class="value" :aria-label="`Current, channel ${channel}`">{{ display(s.current) }}</output>
      <span class="unit">A</span>
      <span></span>

      <output class="value" :aria-label="`Temperature, channel ${channel}`">{{ display(s.temperature) }}</output>
      <span class="unit">°C</span>
      <span></span>

      <output class="value" :aria-label="`Error code, channel ${channel}`">{{ display(s.error) }}</output>
      <span class="unit">E</span>
      <span class="led" :class="led" role="img"
            :aria-label="led === 'red' ? 'Alarm' : led === 'green' ? 'No alarm' : 'No data'"></span>
    </div>

    <label class="source">
      <span>Source</span>
      <select :value="s.source ?? ''" :disabled="disabled || !state" :aria-label="`Source, channel ${channel}`"
              @change="setSource">
        <option v-if="s.source == null" value="" disabled>---</option>
        <option v-for="src in SOURCES" :key="src" :value="src">{{ sourceLabel(src) }}</option>
      </select>
    </label>
    <SlideSwitch name="Power" v-bind="POWER" :value="s.power ?? null" :disabled="disabled || !state || linked"
                 :title="linked ? `Controlled by ${sourceLabel(s.source)}` : ''"
                 @change="(v) => emit('command', { power: v })" />
  </article>
</template>
