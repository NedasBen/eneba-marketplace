/** Mirrors the API's rule: Password::min(8)->letters()->numbers(). */
export const PASSWORD_RULES = [
  { label: 'At least 8 characters', test: (password: string) => password.length >= 8 },
  { label: 'A letter', test: (password: string) => /\p{L}/u.test(password) },
  { label: 'A number', test: (password: string) => /\p{N}/u.test(password) },
] as const
