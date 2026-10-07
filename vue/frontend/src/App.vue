<script setup>
// Layout comune: barra di navigazione, indicatore stato API/DB, footer
// (sostituisce header.php e footer.php).
import { onMounted, onUnmounted, ref } from 'vue'
import { api } from './api/client'

const online = ref(null)
let timer
async function ping() {
  try { await api.health(); online.value = true } catch { online.value = false }
}
onMounted(() => { ping(); timer = setInterval(ping, 15000) })
onUnmounted(() => clearInterval(timer))
</script>

<template>
  <header class="topbar">
    <RouterLink to="/" class="brand">Device Console</RouterLink>
    <nav>
      <RouterLink to="/">Devices</RouterLink>
      <RouterLink to="/devices/new">New device</RouterLink>
      <RouterLink to="/import">Import CSV</RouterLink>
      <RouterLink to="/channels">Channels</RouterLink>
    </nav>
    <span class="status" :title="online ? 'API and database online' : 'API or database unreachable'">
      <span class="indicator" :class="{ alert: online === false }"></span>
      {{ online === null ? '…' : online ? 'online' : 'offline' }}
    </span>
  </header>
  <main>
    <RouterView />
  </main>
  <footer>© {{ new Date().getFullYear() }} Device Console</footer>
</template>
