// askConfirm(message, { okLabel, danger }) -> Promise<boolean>
// Sostituisce window.confirm(), i cui pulsanti seguono la lingua del browser.
// La finestra è components/ConfirmDialog.vue, montata una volta in App.vue.
import { reactive } from 'vue'

export const confirmState = reactive({ open: false, message: '', okLabel: 'OK', danger: false })
let pending = null

export function askConfirm(message, { okLabel = 'OK', danger = false } = {}) {
  if (pending) pending(false) // una sola domanda alla volta
  Object.assign(confirmState, { open: true, message, okLabel, danger })
  return new Promise((resolve) => { pending = resolve })
}

export function answerConfirm(value) {
  confirmState.open = false
  const resolve = pending
  pending = null
  resolve?.(value)
}
