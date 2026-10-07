// Test della logica SCADA (src/scada.js): eseguire con `npm test`.
import { describe, expect, it } from 'vitest'
import { display, gridColumns, isStale, ledColor, parseState, topicChannel } from '../src/scada'

describe('gridColumns', () => {
  it('uses the configured number of columns, limited to the modules', () => {
    expect(gridColumns({ columns: 4 }, 12)).toBe(4)
    expect(gridColumns({ columns: 20 }, 12)).toBe(12)
  })
  it('falls back to 3 for invalid values', () => {
    expect(gridColumns({ columns: 0 }, 12)).toBe(3)
    expect(gridColumns({ columns: 'x' }, 12)).toBe(3)
  })
})

describe('topicChannel', () => {
  it('reads the channel from a state topic', () => {
    expect(topicChannel('devices/7/state')).toBe(7)
    expect(topicChannel('devices/7/cmd')).toBeNull()
    expect(topicChannel('devices/13/state')).toBeNull()
    expect(topicChannel('system/gateway/status')).toBeNull()
  })
})

describe('parseState', () => {
  it('normalizes a gateway message', () => {
    const s = parseState('{"current":-145.4,"temperature":80,"error":20,"control":"sync","power":"on"}')
    expect(s).toEqual({ current: -145.4, temperature: 80, error: 20, control: 'sync', power: 'on' })
  })
  it('turns missing or wrong fields into null and rejects invalid JSON', () => {
    expect(parseState('{"current":"x","error":"A1","control":"auto"}'))
      .toEqual({ current: null, temperature: null, error: null, control: null, power: null })
    expect(parseState('not json')).toBeNull()
  })
  it('discards values outside the limits', () => {
    expect(parseState('{"current":10001}').current).toBeNull()
    expect(parseState('{"current":-10000}').current).toBe(-10000)
    expect(parseState('{"temperature":150}').temperature).toBe(150)
    expect(parseState('{"temperature":150.5}').temperature).toBeNull()
    expect(parseState('{"temperature":-1}').temperature).toBeNull()
    expect(parseState('{"error":255}').error).toBe(255)
    expect(parseState('{"error":256}').error).toBeNull()
    expect(parseState('{"error":3.5}').error).toBeNull()
  })
})

describe('ledColor', () => {
  it('is red above 15, green otherwise, none without data', () => {
    expect(ledColor(0)).toBe('green')
    expect(ledColor(15)).toBe('green')
    expect(ledColor(16)).toBe('red')
    expect(ledColor(null)).toBeNull()
  })
})

describe('display and isStale', () => {
  it('shows --- when there is no value', () => {
    expect(display(null)).toBe('---')
    expect(display(79.6)).toBe('80')
  })
  it('marks data older than staleAfterSeconds', () => {
    expect(isStale(1000, 4000, { staleAfterSeconds: 5 })).toBe(false)
    expect(isStale(1000, 7000, { staleAfterSeconds: 5 })).toBe(true)
    expect(isStale(null, 7000)).toBe(true)
  })
})
