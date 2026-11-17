import type { ComponentType } from 'react'
import type { HomepageBlock } from '../../lib/api/types'

export interface BlockComponentProps {
  block: HomepageBlock
  /** Position on the page, used to alternate section backgrounds. */
  index: number
}

export type BlockComponent = ComponentType<BlockComponentProps>

/**
 * Homepage block registry, discovered at build time: every file in ./blocks
 * default-exports a component, and its file name is the layout it renders
 * (blocks/hero.tsx → "hero", blocks/product_rail.tsx → "product_rail").
 * Adding a layout means adding one file; nothing here changes.
 */
const modules = import.meta.glob<{ default: BlockComponent }>('./blocks/*.tsx', { eager: true })

export const blockComponents: Readonly<Record<string, BlockComponent>> = Object.fromEntries(
  Object.entries(modules).map(([path, module]) => [path.replace(/^\.\/blocks\/(.+)\.tsx$/, '$1'), module.default]),
)

/** The component for a layout, or undefined for layouts this build doesn't know. */
export function blockComponentFor(layout: string): BlockComponent | undefined {
  return Object.hasOwn(blockComponents, layout) ? blockComponents[layout] : undefined
}
