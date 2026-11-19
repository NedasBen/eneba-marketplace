/**
 * Whether router `location.state` carries `flag: true`. History state can be
 * anything (another page's state, an old entry, null), so it is checked, not cast.
 */
export function hasStateFlag(state: unknown, flag: string): boolean {
  return typeof state === 'object' && state !== null && (state as Record<string, unknown>)[flag] === true
}
