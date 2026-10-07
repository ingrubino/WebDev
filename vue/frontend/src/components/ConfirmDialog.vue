<script setup>
// Finestra di conferma con testi propri: window.confirm() mostra i pulsanti
// nella lingua del browser ("Annulla"). Si usa con askConfirm() da confirm.js.
import { nextTick, ref, watch } from 'vue'
import { confirmState, answerConfirm } from '@/confirm'

const dialog = ref(null)
const okButton = ref(null)

watch(() => confirmState.open, async (open) => {
  if (!dialog.value) return
  if (open) {
    dialog.value.showModal()
    await nextTick()
    okButton.value?.focus()
  } else if (dialog.value.open) {
    dialog.value.close()
  }
})
</script>

<template>
  <dialog ref="dialog" class="confirm" @cancel.prevent="answerConfirm(false)">
    <p>{{ confirmState.message }}</p>
    <div class="actions">
      <button ref="okButton" type="button" :class="{ danger: confirmState.danger }" @click="answerConfirm(true)">
        {{ confirmState.okLabel }}
      </button>
      <button type="button" @click="answerConfirm(false)">Cancel</button>
    </div>
  </dialog>
</template>
