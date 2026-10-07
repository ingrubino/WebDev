<script setup>
// Dettaglio / inserimento dispositivo (ex storeTable2.php + save.php + save_xml.php + delete.php)
import { computed, ref, watch } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRouter } from 'vue-router'
import { api } from '@/api/client'
import { emptyDevice, plottablePoints, rules, toFormModel, validateDevice } from '@/validation/device'
import { parseDeviceXml } from '@/validation/parsers'
import FormField from '@/components/FormField.vue'
import FilePicker from '@/components/FilePicker.vue'
import { askConfirm } from '@/confirm'
import CartesianChart from '@/components/CartesianChart.vue'

const props = defineProps({ id: { type: String, default: null } })
const router = useRouter()

const form = ref(emptyDevice())
const originalId = ref(null) // identificativo salvato nel DB; null = nuovo dispositivo
const snapshot = ref('')
const loading = ref(false)
const saving = ref(false)
const submitted = ref(false)
const message = ref({ type: '', text: '' })
const serverErrors = ref({})

const validation = computed(() => validateDevice(form.value))
const errors = computed(() => ({ ...validation.value.errors, ...serverErrors.value }))
const errorCount = computed(() => Object.keys(errors.value).length)
const points = computed(() => plottablePoints(form.value))
const dirty = computed(() => JSON.stringify(form.value) !== snapshot.value)

// Un errore dal server vale finché l'utente non modifica i dati
watch(form, () => { serverErrors.value = {} }, { deep: true })

function setForm(model, id) {
  form.value = model
  originalId.value = id
  snapshot.value = JSON.stringify(model)
  submitted.value = false
}

async function load() {
  if (props.id && props.id === originalId.value) return // appena salvato: dati già aggiornati
  message.value = { type: '', text: '' }
  if (!props.id) { setForm(emptyDevice(), null); return }
  loading.value = true
  try {
    setForm(toFormModel(await api.getDevice(props.id)), props.id)
  } catch (e) {
    message.value = { type: 'error', text: e.status === 404 ? `Device "${props.id}" not found` : e.message }
  } finally {
    loading.value = false
  }
}
watch(() => props.id, load, { immediate: true })

async function onXmlFile(file) {
  try {
    const model = toFormModel(parseDeviceXml(await file.text()))
    form.value = model
    submitted.value = true // mostra subito eventuali errori del file
    if (originalId.value && model.identifier !== originalId.value) {
      originalId.value = null
      message.value = { type: 'info', text: `File loaded for "${model.identifier}": it will be saved as a separate device. Review and save.` }
    } else {
      message.value = { type: 'info', text: 'File loaded. Review the data and save.' }
    }
  } catch (e) {
    message.value = { type: 'error', text: `Cannot read ${file.name}: ${e.message}` }
  }
}

async function save() {
  submitted.value = true
  if (!validation.value.valid) {
    message.value = { type: 'error', text: 'Please fix the highlighted fields.' }
    return
  }
  const device = validation.value.clean
  saving.value = true
  try {
    let saved
    try {
      saved = originalId.value
        ? await api.updateDevice(originalId.value, device)
        : await api.createDevice(device)
    } catch (e) {
      if (e.status === 409 && !originalId.value
          && await askConfirm(`Device "${device.identifier}" already exists. Overwrite it?`, { okLabel: 'Overwrite', danger: true })) {
        saved = await api.updateDevice(device.identifier, device)
      } else {
        throw e
      }
    }
    setForm(toFormModel(saved), saved.identifier)
    message.value = { type: 'ok', text: 'Saved.' }
    if (props.id !== saved.identifier) {
      router.replace({ name: 'device-edit', params: { id: saved.identifier } })
    }
  } catch (e) {
    serverErrors.value = e.fields || {}
    message.value = { type: 'error', text: e.message }
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!await askConfirm(`Remove device "${originalId.value}"?`, { okLabel: 'Remove', danger: true })) return
  try {
    await api.deleteDevice(originalId.value)
    snapshot.value = JSON.stringify(form.value)
    router.push({ name: 'devices' })
  } catch (e) {
    message.value = { type: 'error', text: e.message }
  }
}

async function confirmLeave() {
  if (dirty.value && !await askConfirm('You have unsaved changes. Leave anyway?', { okLabel: 'Leave' })) return false
}
onBeforeRouteLeave(confirmLeave)
onBeforeRouteUpdate(confirmLeave)
</script>

<template>
  <h1>{{ originalId ? `Device ${originalId}` : 'New device' }}</h1>
  <p v-if="message.text" class="message" :class="message.type" role="status">{{ message.text }}</p>
  <p v-if="loading">Loading…</p>

  <template v-else>
    <section class="panel">
      <h2>Load data from XML file</h2>
      <FilePicker accept=".xml,.txt" @select="onXmlFile" />
    </section>

    <form class="panel" novalidate @submit.prevent="save">
      <h2>Insert or modify data</h2>
      <FormField v-model="form.identifier" label="Part name" :error="errors.identifier" :show="submitted" />

      <div class="split">
        <div>
          <h3>Time - Current table</h3>
          <p v-if="errors.matrix && submitted" class="error">{{ errors.matrix }}</p>
          <table class="grid-table">
            <thead><tr><th>line</th><th>{{ rules.time.label }}</th><th>{{ rules.current.label }}</th></tr></thead>
            <tbody>
              <tr v-for="(row, i) in form.matrix" :key="i">
                <td>{{ i + 1 }}</td>
                <td><FormField v-model="row[0]" numeric :error="errors[`matrix.${i}.0`]" :show="submitted" /></td>
                <td><FormField v-model="row[1]" numeric :error="errors[`matrix.${i}.1`]" :show="submitted" /></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div>
          <h3>Protection curve</h3>
          <CartesianChart
            :points="points"
            :title="`${form.identifier || 'new'} device`"
            :x-label="rules.time.label"
            :y-label="rules.current.label"
          />
        </div>
      </div>

      <h3>Vector ({{ rules.vectorLength }} values)</h3>
      <div class="vector">
        <FormField
          v-for="(_, j) in form.vector"
          :key="j"
          v-model="form.vector[j]"
          :label="String(j + 1)"
          numeric
          :error="errors[`vector.${j}`]"
          :show="submitted"
        />
      </div>

      <div class="actions">
        <button type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Store into the database' }}</button>
        <span v-if="submitted && errorCount" class="error">{{ errorCount }} field(s) to fix</span>
        <span v-else-if="dirty" class="hint">Unsaved changes</span>
      </div>
    </form>

    <section class="panel">
      <button v-if="originalId" type="button" class="danger" @click="remove">Remove: {{ originalId }}</button>
      <p><RouterLink to="/">← Return to the main page</RouterLink></p>
    </section>
  </template>
</template>
