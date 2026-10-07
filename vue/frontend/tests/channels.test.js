// Test della configurazione canali: eseguire con `npm test`.
import { describe, expect, it } from 'vitest'
import { channelRules, toChannelForm, validateChannels } from '../src/validation/channels'

const devices = ['InterruttoreXX2', 'Rele A']

describe('toChannelForm', () => {
  it('always returns one row per channel with the default mode', () => {
    const rows = toChannelForm([{ channel: 3, device: 'Rele A', mode: 'DC' }])
    expect(rows).toHaveLength(channelRules.channels)
    expect(rows[0]).toEqual({ channel: 1, device: '', mode: channelRules.defaultMode })
    expect(rows[2]).toEqual({ channel: 3, device: 'Rele A', mode: 'DC' })
  })
})

describe('validateChannels', () => {
  it('accepts unused channels and known devices', () => {
    const rows = toChannelForm()
    rows[0].device = 'InterruttoreXX2'
    rows[1].device = 'InterruttoreXX2' // lo stesso dispositivo può servire più canali
    rows[1].mode = 'DC'
    const { valid, clean } = validateChannels(rows, devices)
    expect(valid).toBe(true)
    expect(clean[0]).toEqual({ device: 'InterruttoreXX2', mode: 'AC' })
    expect(clean[1]).toEqual({ device: 'InterruttoreXX2', mode: 'DC' })
    expect(clean[5]).toEqual({ device: null, mode: 'AC' })
  })

  it('rejects unknown devices and modes', () => {
    const rows = toChannelForm()
    rows[4].device = 'Removed'
    rows[7].mode = 'XX'
    const { errors } = validateChannels(rows, devices)
    expect(errors['channels.4.device']).toBe('Unknown device')
    expect(errors['channels.7.mode']).toMatch(/AC or DC/)
  })

  it('requires exactly the configured number of channels', () => {
    expect(validateChannels(toChannelForm().slice(1), devices).errors.channels).toMatch(/Exactly 12/)
  })
})
