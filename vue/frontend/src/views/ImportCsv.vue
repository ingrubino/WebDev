<script setup>
// Import massivo da CSV (ex import.php, che non aveva una pagina dedicata).
// Il file viene letto e validato nel browser; si importano solo i dispositivi validi.
import { computed, ref } from 'vue'
import { api } from '@/api/client'
import { parseDevicesCsv } from '@/validation/parsers'
import { toFormModel, validateDevice } from '@/validation/device'
import FilePicker from '@/components/FilePicker.vue'

const items = ref([])
const fileName = ref('')
const error = ref('')
const running = ref(false)

const validItems = computed(() => items.value.filter((it) => it.valid && it.status !== 'done'))

async function onFile(file) {
  fileName.value = file.name
  error.value = ''
  try {
    const existing = new Set((await api.listDevices()).map((d) => d.identifier))
    items.value = parseDevicesCsv(await file.text()).map((raw) => {
      const { errors, clean, valid } = validateDevice(toFormModel(raw))
      return { clean, valid, errors: Object.entries(errors), exists: existing.has(clean.identifier), status: '' }
    })
    if (!items.value.length) error.value = 'No rows found in the file.'
  } catch (e) {
    items.value = []
    error.value = e.message
  }
}

async function importAll() {
  running.value = true
  for (const it of validItems.value) {
    try {
      if (it.exists) await api.updateDevice(it.clean.identifier, it.clean)
      else await api.createDevice(it.clean)
      it.status = 'done'
    } catch (e) {
      it.status = `error: ${e.message}`
    }
  }
  running.value = false
}
</script>

<template>
  <h1>Import devices from CSV</h1>
  <section class="panel">
    <p>One line per point: <code>identifier,time,current,v1,v2,…</code> (separator <code>,</code> or <code>;</code>). Lines with the same identifier form one device.</p>
    <FilePicker accept=".csv,.txt" @select="onFile" />
    <p v-if="error" class="error">{{ error }}</p>
  </section>

  <section v-if="items.length" class="panel">
    <h2>{{ fileName }}: {{ items.length }} device(s)</h2>
    <table class="list">
      <thead><tr><th>Part name</th><th>Points</th><th>Check</th><th>Result</th></tr></thead>
      <tbody>
        <tr v-for="it in items" :key="it.clean.identifier">
          <td>{{ it.clean.identifier || '(empty)' }}</td>
          <td>{{ it.clean.matrix.length }}</td>
          <td>
            <span v-if="it.valid">OK{{ it.exists ? ' (will overwrite)' : '' }}</span>
            <ul v-else class="error"><li v-for="[k, msg] in it.errors" :key="k">{{ k }}: {{ msg }}</li></ul>
          </td>
          <td>{{ it.status }}</td>
        </tr>
      </tbody>
    </table>
    <button :disabled="running || !validItems.length" @click="importAll">
      Import {{ validItems.length }} valid device(s)
    </button>
  </section>
</template>
