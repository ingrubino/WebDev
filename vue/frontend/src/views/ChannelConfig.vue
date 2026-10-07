<script setup>
// Configurazione canali: per ogni canale un dispositivo dell'elenco e la modalità AC/DC.
// Salvata nella tabella channel_config, letta dal programma di backend.
import { computed, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import { api } from '@/api/client'
import { channelRules, toChannelForm, validateChannels } from '@/validation/channels'

const rows = ref(toChannelForm())
const devices = ref([])
const snapshot = ref('')
const loading = ref(true)
const saving = ref(false)
const submitted = ref(false)
const message = ref({ type: '', text: '' })
const serverErrors = ref({})
const applying = ref(false)
const applyLog = ref([])

const deviceIds = computed(() => devices.value.map((d) => d.identifier))
const validation = computed(() => validateChannels(rows.value, deviceIds.value))
const errors = computed(() => ({ ...validation.value.errors, ...serverErrors.value }))
const errorCount = computed(() => Object.keys(errors.value).length)
const dirty = computed(() => JSON.stringify(rows.value) !== snapshot.value)
const usedCount = computed(() => rows.value.filter((r) => r.device).length)

watch(rows, () => { serverErrors.value = {} }, { deep: true })

function setRows(saved) {
  rows.value = toChannelForm(saved)
  snapshot.value = JSON.stringify(rows.value)
}

onMounted(async () => {
  try {
    const [list, config] = await Promise.all([api.listDevices(), api.getChannels()])
    devices.value = list
    setRows(config.channels)
  } catch (e) {
    message.value = { type: 'error', text: `Cannot load configuration: ${e.message}` }
  } finally {
    loading.value = false
  }
})

async function save() {
  submitted.value = true
  message.value = { type: '', text: '' }
  if (!validation.value.valid) return
  saving.value = true
  try {
    const saved = await api.saveChannels(validation.value.clean)
    setRows(saved.channels)
    submitted.value = false
    message.value = { type: 'ok', text: 'Channel configuration saved.' }
  } catch (e) {
    serverErrors.value = e.fields || {}
    message.value = { type: 'error', text: e.message }
  } finally {
    saving.value = false
  }
}

function reset() {
  rows.value = JSON.parse(snapshot.value)
  submitted.value = false
  message.value = { type: '', text: '' }
}

// "Set devices": il programma esterno legge la configurazione SALVATA, quindi prima va salvata
async function setDevices() {
  applying.value = true
  message.value = { type: '', text: '' }
  try {
    const result = await api.setDevices()
    applyLog.value = result.log
    message.value = { type: 'ok', text: `Devices set: ${result.sent} channel(s) sent.` }
  } catch (e) {
    applyLog.value = []
    message.value = { type: 'error', text: e.message }
  } finally {
    applying.value = false
  }
}

onBeforeRouteLeave(() => {
  if (dirty.value && !confirm('You have unsaved changes. Leave anyway?')) return false
})
</script>

<template>
  <h1>Configure channels</h1>
  <p>Assign a device and the AC/DC mode to each channel, then save.</p>
  <p v-if="message.text" class="message" :class="message.type" role="status">{{ message.text }}</p>
  <p v-if="loading">Loading…</p>

  <form v-else class="panel" novalidate @submit.prevent="save">
    <p v-if="!devices.length" class="message info">No devices yet: add one from the list of devices first.</p>
    <p v-if="errors.channels" class="error">{{ errors.channels }}</p>

    <table class="list channels">
      <thead><tr><th>Channel</th><th>Device</th><th>Mode</th></tr></thead>
      <tbody>
        <tr v-for="(row, i) in rows" :key="row.channel">
          <td>{{ row.channel }}</td>
          <td>
            <select v-model="row.device" :aria-label="`Device for channel ${row.channel}`">
              <option value="">— not used —</option>
              <option v-if="row.device && !deviceIds.includes(row.device)" :value="row.device">{{ row.device }} (missing)</option>
              <option v-for="id in deviceIds" :key="id" :value="id">{{ id }}</option>
            </select>
            <small v-if="(submitted || serverErrors[`channels.${i}.device`]) && errors[`channels.${i}.device`]" class="error">
              {{ errors[`channels.${i}.device`] }}
            </small>
          </td>
          <td>
            <div class="segmented" role="radiogroup" :aria-label="`Mode for channel ${row.channel}`">
              <label v-for="m in channelRules.modes" :key="m" :class="{ active: row.mode === m }">
                <input v-model="row.mode" type="radio" :name="`mode-${row.channel}`" :value="m" />{{ m }}
              </label>
            </div>
            <small v-if="(submitted || serverErrors[`channels.${i}.mode`]) && errors[`channels.${i}.mode`]" class="error">
              {{ errors[`channels.${i}.mode`] }}
            </small>
          </td>
        </tr>
      </tbody>
    </table>

    <div class="actions">
      <button type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save configuration' }}</button>
      <button type="button" :disabled="!dirty || saving" @click="reset">Discard changes</button>
      <button
        type="button"
        :disabled="dirty || saving || applying || !usedCount"
        :title="dirty ? 'Save the configuration first' : !usedCount ? 'No channel in use' : 'Send the saved configuration to the devices'"
        @click="setDevices"
      >{{ applying ? 'Setting…' : 'Set devices' }}</button>
      <span v-if="submitted && errorCount" class="error">{{ errorCount }} field(s) to fix</span>
      <span v-else-if="dirty" class="hint">Unsaved changes</span>
      <span v-else class="hint">{{ usedCount }} of {{ rows.length }} channels in use</span>
    </div>
  </form>

  <section v-if="applyLog.length" class="panel">
    <h2>Set devices output</h2>
    <pre class="log">{{ applyLog.join('\n') }}</pre>
  </section>

  <p><RouterLink to="/">← Return to the main page</RouterLink></p>
</template>
