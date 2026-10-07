// Unico punto di accesso all'API REST. Gli errori HTTP diventano ApiError
// con `status` e, per gli errori di validazione (422/409), `fields`.
export class ApiError extends Error {
  constructor(status, body) {
    super(body?.error || `HTTP ${status}`)
    this.status = status
    this.fields = body?.fields || {}
  }
}

async function request(method, path, body) {
  const res = await fetch(`/api${path}`, {
    method,
    headers: body ? { 'Content-Type': 'application/json' } : {},
    body: body ? JSON.stringify(body) : undefined,
  })
  const data = res.status === 204 ? null : await res.json().catch(() => null)
  if (!res.ok) throw new ApiError(res.status, data)
  return data
}

const enc = encodeURIComponent

export const api = {
  health: () => request('GET', '/health'),
  listDevices: () => request('GET', '/devices'),
  getDevice: (id) => request('GET', `/devices/${enc(id)}`),
  createDevice: (device) => request('POST', '/devices', device),
  updateDevice: (id, device) => request('PUT', `/devices/${enc(id)}`, device),
  deleteDevice: (id) => request('DELETE', `/devices/${enc(id)}`),
  getChannels: () => request('GET', '/channels'),
  saveChannels: (channels) => request('PUT', '/channels', { channels }),
  // "Set devices" non passa dall'API: va al gateway Python via MQTT (src/mqtt.js)
}
