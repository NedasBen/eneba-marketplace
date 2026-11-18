import { describe, expect, it } from 'vitest'
import { PASSWORD_RULES } from './passwordRules'

const passes = (password: string) => PASSWORD_RULES.every((rule) => rule.test(password))

describe('PASSWORD_RULES (mirrors the API)', () => {
  it.each(['secret12', 'Pässwörd9', 'abcdefg1'])('accepts %s', (password) => expect(passes(password)).toBe(true))
  it.each(['short1', 'lettersonly', '12345678', ''])('rejects %s', (password) => expect(passes(password)).toBe(false))
})
