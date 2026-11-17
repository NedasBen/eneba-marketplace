import { cn } from '../../lib/cn'

/** Loading placeholder on Eneba's missing-cover gradient (#2C0E6C → #3C1395). */
export function Skeleton({ className }: { className?: string }) {
  return <div aria-hidden className={cn('animate-shimmer bg-linear-to-b from-placeholder-from to-placeholder-to', className)} />
}

/** A ProductCard-shaped placeholder, so the grid doesn't jump when data arrives. */
export function ProductCardSkeleton() {
  return (
    <div aria-hidden className="flex flex-col bg-card">
      <Skeleton className="aspect-[1/1.4] w-full" />
      <div className="flex flex-col gap-2 p-3">
        <Skeleton className="h-[14px] w-11/12" />
        <Skeleton className="h-[14px] w-2/3" />
        <Skeleton className="h-[12px] w-1/4" />
        <Skeleton className="mt-1 h-[24px] w-1/2" />
      </div>
    </div>
  )
}
