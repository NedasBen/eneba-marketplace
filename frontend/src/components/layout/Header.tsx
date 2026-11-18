import { Link, NavLink } from 'react-router'
import { useCatalogFilters } from '../../lib/api/catalog'
import { cartItemCount, useCart } from '../../lib/cart'
import { cn } from '../../lib/cn'
import { SearchBox } from '../search/SearchBox'
import { Logo } from './Logo'

function CartIcon() {
  return (
    <svg aria-hidden viewBox="0 0 24 24" className="size-[24px]" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
      <path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 8H6.2" />
      <circle cx="9" cy="20" r="1.3" />
      <circle cx="17" cy="20" r="1.3" />
    </svg>
  )
}

function UserIcon() {
  return (
    <svg aria-hidden viewBox="0 0 24 24" className="size-[22px]" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round">
      <circle cx="12" cy="8" r="4" />
      <path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6" />
    </svg>
  )
}

/** Cart icon with the number of keys in the guest cart. */
function CartLink() {
  const count = cartItemCount(useCart())

  return (
    <Link to="/cart" className="relative text-white transition-colors hover:text-accent" aria-label={count > 0 ? `Cart, ${count} ${count === 1 ? 'item' : 'items'}` : 'Cart'}>
      <CartIcon />
      {count > 0 && (
        <span aria-hidden className="absolute -top-2 -right-2 grid h-[17px] min-w-[17px] place-items-center rounded-full bg-accent px-1 text-[10px] leading-none font-extrabold text-badge-ink">
          {count > 99 ? '99+' : count}
        </span>
      )}
    </Link>
  )
}

const navLinkClasses = ({ isActive }: { isActive: boolean }) =>
  cn('whitespace-nowrap py-2 text-sm font-semibold transition-colors hover:text-accent', isActive ? 'text-accent' : 'text-white')

/** Store shortcuts; platform links come from /catalog/filters, not hardcoded. */
function CategoryNav() {
  const filters = useCatalogFilters()

  return (
    <nav aria-label="Categories" className="bg-nav">
      <div className="mx-auto flex max-w-page items-center gap-5 overflow-x-auto px-4">
        <NavLink to="/store" end className={navLinkClasses}>
          All games
        </NavLink>
        <Link to="/store?sort=popularity" className="whitespace-nowrap py-2 text-sm font-semibold hover:text-accent">
          Best sellers
        </Link>
        <Link to="/store?sort=release_date" className="whitespace-nowrap py-2 text-sm font-semibold hover:text-accent">
          New releases
        </Link>
        {filters.data?.platforms.map((platform) => (
          <Link key={platform.slug} to={`/store?platform=${platform.slug}`} className="whitespace-nowrap py-2 text-sm font-semibold hover:text-accent">
            {platform.name}
          </Link>
        ))}
      </div>
    </nav>
  )
}

export function Header() {
  return (
    <header>
      <div className="mx-auto flex max-w-page flex-wrap items-center gap-x-6 gap-y-3 px-4 py-4">
        <Logo />

        <div className="order-last w-full md:order-none md:w-auto md:max-w-[440px] md:flex-1">
          <SearchBox />
        </div>

        <div className="ml-auto flex items-center gap-5 text-sm font-semibold">
          <CartLink />
          <div className="flex items-center gap-1">
            <UserIcon />
            <Link to="/login" className="transition-colors hover:text-accent">
              Log in
            </Link>
            <span aria-hidden className="text-muted">
              |
            </span>
            <Link to="/register" className="transition-colors hover:text-accent">
              Register
            </Link>
          </div>
        </div>
      </div>
      <CategoryNav />
    </header>
  )
}
