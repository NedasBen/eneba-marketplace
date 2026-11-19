import { describe, expect, it, vi } from 'vitest'
import { copyText } from './clipboard'

describe('copyText', () => {
  it('writes to the clipboard and reports success', async () => {
    const writeText = vi.fn(async () => {})
    const select = vi.fn()

    await expect(copyText('ABCDE-12345', select, { writeText })).resolves.toBe(true)
    expect(writeText).toHaveBeenCalledWith('ABCDE-12345')
    expect(select).not.toHaveBeenCalled()
  })

  it('selects the text for a manual copy when the clipboard refuses or is missing', async () => {
    const select = vi.fn()
    const refusing = { writeText: vi.fn(async () => Promise.reject(new DOMException('Denied', 'NotAllowedError'))) }

    await expect(copyText('ABCDE-12345', select, refusing)).resolves.toBe(false)
    await expect(copyText('ABCDE-12345', select, undefined)).resolves.toBe(false)
    expect(select).toHaveBeenCalledTimes(2)
  })
})
