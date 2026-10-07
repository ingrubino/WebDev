<script setup>
// Un modulo della pagina SCADA (un canale): corrente, temperatura, codice errore con LED
// (verde/rosso secondo alarmAbove), interruttori sync/manual e ON/OFF.
// In sync l'ON/OFF del modulo è bloccato: comanda il pulsante sync unico della pagina.
import { computed } from 'vue'
import SlideSwitch from './SlideSwitch.vue'
import { display, ledColor } from '@/scada'

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
const inSync = computed(() => s.value.control === 'sync')
const CONTROL = { left: { value: 'sync', label: 'sync' }, right: { value: 'manual', label: 'manual' } }
const POWER = { left: { value: 'on', label: 'ON' }, right: { value: 'off', label: 'OFF' } }

// passando a manual si invia anche il valore attuale del pulsante ON/OFF del modulo
function setControl(control) {
  emit('command', control === 'manual' ? { control, power: s.value.power ?? 'off' } : { control })
}
</script>

<template>
  <article class="scada-module" :class="{ 'no-data': !state }" :aria-label="`Channel ${channel}`">
    <header>
      <strong>CH {{ channel }}</strong>
      <span class="device" :title="device">{{ device || 'not used' }}</span>
      <span v-if="acdc" class="tag">{{ acdc }}</span>
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

    <SlideSwitch name="Control" v-bind="CONTROL" :value="s.control ?? null" :disabled="disabled || !state"
                 @change="setControl" />
    <SlideSwitch name="Power" v-bind="POWER" :value="s.power ?? null" :disabled="disabled || !state || inSync"
                 :title="inSync ? 'Controlled by the sync switch' : ''"
                 @change="(v) => emit('command', { power: v })" />
  </article>
</template>
