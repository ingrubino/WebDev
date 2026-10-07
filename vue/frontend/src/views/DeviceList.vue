<script setup>
// Elenco dispositivi (ex list_identifiers.php)
import { computed, onMounted, ref } from 'vue'
import { api } from '@/api/client'

const devices = ref([])
const loading = ref(true)
const error = ref('')
const filter = ref('')

const filtered = computed(() => {
  const f = filter.value.trim().toLowerCase()
  return f ? devices.value.filter((d) => d.identifier.toLowerCase().includes(f)) : devices.value
})

onMounted(async () => {
  try { devices.value = await api.listDevices() } catch (e) { error.value = e.message } finally { loading.value = false }
})
</script>

<template>
  <h1>List of devices</h1>
  <p>Select the device to view/modify or add a new device.</p>

  <section class="panel">
    <div class="toolbar">
      <input v-model="filter" type="search" placeholder="Filter by name…" aria-label="Filter devices" />
      <RouterLink to="/devices/new"><button>Add a new device</button></RouterLink>
      <RouterLink to="/channels"><button>Configure channels</button></RouterLink>
    </div>

    <p v-if="loading">Loading…</p>
    <p v-else-if="error" class="error">Cannot load devices: {{ error }}</p>
    <p v-else-if="devices.length === 0">No devices yet. Add one or import a file.</p>
    <table v-else class="list">
      <thead><tr><th>Part name</th><th>Points</th><th>Last saved</th></tr></thead>
      <tbody>
        <tr v-for="d in filtered" :key="d.identifier">
          <td><RouterLink :to="{ name: 'device-edit', params: { id: d.identifier } }">{{ d.identifier }}</RouterLink></td>
          <td>{{ d.points }}</td>
          <td>{{ d.updated_at }}</td>
        </tr>
      </tbody>
    </table>
  </section>
</template>
