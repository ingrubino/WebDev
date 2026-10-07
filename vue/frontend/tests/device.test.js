// Test delle regole di validazione: eseguire con `npm test`.
// Aggiungere un caso qui ogni volta che si introduce o modifica una regola.
import { describe, expect, it } from 'vitest'
import { emptyDevice, validateDevice } from '../src/validation/device'
import { parseDevicesCsv } from '../src/validation/parsers'

function device(overrides = {}) {
  const d = emptyDevice()
  d.identifier = 'InterruttoreXX2'
  d.matrix[0] = ['0', '100']
  d.matrix[1] = ['1', '90']
  return Object.assign(d, overrides)
}

describe('validateDevice', () => {
  it('accepts a minimal valid device', () => {
    const { valid, clean } = validateDevice(device())
    expect(valid).toBe(true)
    expect(clean.matrix).toEqual([[0, 100], [1, 90]])
    expect(clean.vector).toHaveLength(10)
  })

  it('requires the identifier and checks allowed characters', () => {
    expect(validateDevice(device({ identifier: '  ' })).errors.identifier).toBe('Required')
    expect(validateDevice(device({ identifier: 'a<b>' })).errors.identifier).toMatch(/Allowed/)
  })

  it('requires both columns on a row', () => {
    const d = device()
    d.matrix[2] = ['3', '']
    expect(validateDevice(d).errors['matrix.2.1']).toBeDefined()
  })

  it('rejects non numbers and negative current', () => {
    const d = device()
    d.matrix[0] = ['abc', '-1']
    const { errors } = validateDevice(d)
    expect(errors['matrix.0.0']).toBe('Must be a number')
    expect(errors['matrix.0.1']).toBe('Min 0')
  })

  it('requires strictly increasing time', () => {
    const d = device()
    d.matrix[1] = ['0', '90']
    expect(validateDevice(d).errors['matrix.1.0']).toMatch(/greater/)
  })

  it('needs at least two points', () => {
    const d = device()
    d.matrix[1] = ['', '']
    expect(validateDevice(d).errors.matrix).toMatch(/At least 2/)
  })

  it('accepts decimal comma', () => {
    const d = device()
    d.vector[0] = '1,5'
    expect(validateDevice(d).clean.vector[0]).toBe(1.5)
  })
})

describe('parseDevicesCsv', () => {
  it('groups lines by identifier and skips the header', () => {
    const csv = 'identifier,time,current,v1,v2\nA,0,10,1,2\nA,1,5,1,2\nB;x'.replace('B;x', 'B,0,3')
    const out = parseDevicesCsv(csv)
    expect(out.map((d) => d.identifier)).toEqual(['A', 'B'])
    expect(out[0].matrix).toEqual([['0', '10'], ['1', '5']])
    expect(out[0].vector).toEqual(['1', '2'])
  })

  it('supports semicolon separator with decimal comma', () => {
    const out = parseDevicesCsv('A;0;1,5\nA;1;2,5')
    expect(out[0].matrix).toEqual([['0', '1,5'], ['1', '2,5']])
  })
})
