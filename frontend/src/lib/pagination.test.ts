import { describe, expect, it } from 'vitest'
import { paginationRange } from './pagination'

describe('paginationRange', () => {
  it.each([
    [1, 1, [1]],
    [1, 3, [1, 2, 3]],
    [1, 30, [1, 2, 'gap', 30]],
    [5, 30, [1, 'gap', 4, 5, 6, 'gap', 30]],
    [30, 30, [1, 'gap', 29, 30]],
    // A single skipped page is shown instead of a gap.
    [3, 30, [1, 2, 3, 4, 'gap', 30]],
    [28, 30, [1, 'gap', 27, 28, 29, 30]],
  ] as const)('page %i of %i', (current, last, expected) => {
    expect(paginationRange(current, last)).toEqual(expected)
  })
})
