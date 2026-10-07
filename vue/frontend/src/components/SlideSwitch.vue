<script setup>
// Interruttore a due posizioni con etichetta a sinistra e a destra (es. sync | manual).
// value = valore attuale (dal dispositivo); click -> emette il valore dell'altra posizione.
const props = defineProps({
  name: { type: String, required: true },      // per l'accessibilità, es. "Control"
  left: { type: Object, required: true },      // { value: 'sync', label: 'sync' }
  right: { type: Object, required: true },
  value: { type: String, default: null },      // null = sconosciuto
  disabled: Boolean,
})
const emit = defineEmits(['change'])

function toggle() {
  emit('change', props.value === props.right.value ? props.left.value : props.right.value)
}
</script>

<template>
  <div class="slide-switch" :class="{ unknown: value === null }">
    <span :class="{ active: value === left.value }">{{ left.label }}</span>
    <button
      type="button"
      role="switch"
      :aria-checked="value === right.value"
      :aria-label="`${name}: ${value ?? 'unknown'}`"
      :class="{ right: value === right.value }"
      :disabled="disabled"
      @click="toggle"
    ><span class="knob"></span></button>
    <span :class="{ active: value === right.value }">{{ right.label }}</span>
  </div>
</template>
