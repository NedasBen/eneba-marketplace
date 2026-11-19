/**
 * Copy text with the Clipboard API. Where it is missing or refused (an old
 * browser, a denied permission), select the text so the user can copy it
 * themselves, and return false so the UI can say so.
 */
export async function copyText(text: string, selectFallback: () => void, clipboard: Pick<Clipboard, 'writeText'> | undefined = globalThis.navigator?.clipboard): Promise<boolean> {
  try {
    if (clipboard === undefined) throw new Error('Clipboard API unavailable')
    await clipboard.writeText(text)
    return true
  } catch {
    selectFallback()
    return false
  }
}
