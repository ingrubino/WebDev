import { createRouter, createWebHistory } from 'vue-router'
import DeviceList from './views/DeviceList.vue'
import DeviceEdit from './views/DeviceEdit.vue'
import ImportCsv from './views/ImportCsv.vue'

// Una riga per schermata: per aggiungerne una nuova vedi WebDev/docs/02-manutenzione.md
const routes = [
  { path: '/', name: 'devices', component: DeviceList },
  { path: '/devices/new', name: 'device-new', component: DeviceEdit },
  { path: '/devices/:id', name: 'device-edit', component: DeviceEdit, props: true },
  { path: '/import', name: 'import', component: ImportCsv },
  { path: '/channels', name: 'channels', component: () => import('./views/ChannelConfig.vue') },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

export default createRouter({ history: createWebHistory(), routes })
