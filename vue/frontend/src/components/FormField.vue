<script setup>
// Campo di input con messaggio d'errore. L'errore compare solo dopo che
// l'utente ha lasciato il campo (blur) o ha tentato il salvataggio (`show`).
import { ref } from 'vue'

defineProps({
  label: { type: String, default: '' },
  error: { type: String, default: '' },
  show: { type: Boolean, default: false },
  numeric: { type: Boolean, default: false },
})
const model = defineModel({ type: String, default: '' })
const touched = ref(false)
</script>

<template>
  <label class="field" :class="{ invalid: error && (show || touched) }">
    <span v-if="label" class="field-label">{{ label }}</span>
    <input
      v-model="model"
      type="text"
      :inputmode="numeric ? 'decimal' : 'text'"
      :aria-invalid="!!error && (show || touched)"
      @blur="touched = true"
    />
    <small v-if="error && (show || touched)" class="error">{{ error }}</small>
  </label>
</template>
