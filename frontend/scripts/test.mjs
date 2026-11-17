// Runs Vitest from a normalized working directory.
//
// On Windows, a shell opened in "c:\…" (lowercase drive letter, as VS Code's
// terminal does for this workspace) makes Node load Vitest's runner twice,
// once per drive-letter spelling, and every suite fails before running with
// "Cannot read properties of undefined (reading 'config')". Starting Vitest
// from the uppercase spelling of the same directory avoids the duplicate.
import { spawnSync } from 'node:child_process'
import { join } from 'node:path'

const cwd = process.cwd().replace(/^[a-z]:/, (drive) => drive.toUpperCase())
const vitest = join(cwd, 'node_modules', 'vitest', 'vitest.mjs')

const result = spawnSync(process.execPath, [vitest, 'run', ...process.argv.slice(2)], { cwd, stdio: 'inherit' })

process.exit(result.status ?? 1)
