import { Link } from 'react-router'

/** Our own text wordmark (not Eneba's logo artwork), set in Metropolis ExtraBold. */
export function Logo() {
  return (
    // No aria-label: the accessible name is the visible text plus a hidden "home", so it matches what voice-control users see.
    <Link to="/" className="flex shrink-0 items-center gap-2 text-white">
      <svg aria-hidden viewBox="0 0 32 32" className="size-[30px]">
        <rect x="4" y="4" width="24" height="24" rx="6" transform="rotate(45 16 16)" className="fill-accent" />
        <rect x="11" y="11" width="10" height="10" rx="2" transform="rotate(45 16 16)" className="fill-page" />
      </svg>
      <span className="text-[28px] leading-none font-extrabold tracking-tight">eneba</span>
      <span className="mt-1 hidden self-start rounded-xs bg-white/10 px-1 text-[10px] font-semibold uppercase text-soft xs:inline">demo</span>
      <span className="sr-only">Marketplace home</span>
    </Link>
  )
}
