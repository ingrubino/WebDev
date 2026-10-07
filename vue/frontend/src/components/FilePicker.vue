<script setup>
// Selettore file con testi propri: l'<input type="file"> nativo mostra
// "Scegli file" / "Nessun file selezionato" nella lingua del browser.
import { ref } from 'vue'

defineProps({
  accept: { type: String, default: '' },
  label: { type: String, default: 'Choose file' },
})
const emit = defineEmits(['select'])

const input = ref(null)
const fileName = ref('')

function onChange(event) {
  const file = event.target.files[0]
  event.target.value = '' // permette di ricaricare lo stesso file
  if (!file) return
  fileName.value = file.name
  emit('select', file)
}
</script>

<template>
  <div class="file-picker">
    <input ref="input" type="file" :accept="accept" hidden @change="onChange" />
    <button type="button" @click="input.click()">{{ label }}</button>
    <span class="hint">{{ fileName || 'No file selected' }}</span>
  </div>
</template>
