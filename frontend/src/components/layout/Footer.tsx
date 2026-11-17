import { Link } from 'react-router'

const COPYRIGHT_YEAR = new Date().getFullYear()

const columns:{ heading: string; links: { label: string; to: string }[] }[] = [
  {
    heading: 'Shop',
    links: [
      { label: 'All games', to: '/store' },
      { label: 'Best sellers', to: '/store?sort=popularity' },
      { label: 'New releases', to: '/store?sort=release_date' },
      { label: 'Lowest prices', to: '/store?sort=price_asc' },
    ],
  },
  {
    heading: 'Account',
    links: [
      { label: 'Log in', to: '/login' },
      { label: 'Register', to: '/register' },
      { label: 'My orders', to: '/orders' },
      { label: 'Cart', to: '/cart' },
    ],
  },
]

export function Footer() {
  return (
    <footer className="mt-auto bg-footer">
      <div className="mx-auto max-w-page px-4 py-9">
        <div className="flex flex-wrap gap-x-12 gap-y-6">
          {columns.map((column) => (
            <div key={column.heading}>
              <h2 className="mb-3 text-sm font-extrabold text-white">{column.heading}</h2>
              <ul className="flex flex-col gap-2">
                {column.links.map((link) => (
                  <li key={link.label}>
                    <Link to={link.to} className="text-[13px] text-white transition-colors hover:text-accent-hover">
                      {link.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}

          <div className="max-w-[380px]">
            <h2 className="mb-3 text-sm font-extrabold text-white">About this demo</h2>
            <p className="text-[13px] leading-relaxed text-soft">
              An internship project styled after eneba.com. Payments are simulated: no real money is charged and keys are generated test data.
            </p>
          </div>
        </div>

        <div className="mt-9 flex flex-col gap-2 border-t border-white/10 pt-5 text-xs text-muted">
          <p>
            Game data and images © their respective owners, sourced from the{' '}
            <a href="https://store.steampowered.com" target="_blank" rel="noreferrer" className="underline hover:text-accent-hover">
              Steam Store
            </a>
            . Not affiliated with Valve or Eneba.
          </p>
          <p>© {COPYRIGHT_YEAR} Eneba Marketplace demo.</p>
        </div>
      </div>
    </footer>
  )
}
