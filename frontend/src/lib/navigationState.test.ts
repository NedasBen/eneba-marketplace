import { describe, expect, it } from 'vitest'
import { hasStateFlag } from './navigationState'

describe('hasStateFlag', () => {
  it('is true only for the flag set to exactly true', () => {
    expect(hasStateFlag({ justPaid: true }, 'justPaid')).toBe(true)
    expect(hasStateFlag({ justPaid: 'true' }, 'justPaid')).toBe(false)
    expect(hasStateFlag({ other: true }, 'justPaid')).toBe(false)
  })

  it('handles whatever history state holds', () => {
    for (const state of [null, undefined, 'justPaid', 1, ['justPaid']]) {
      expect(hasStateFlag(state, 'justPaid')).toBe(false)
    }
  })
})
