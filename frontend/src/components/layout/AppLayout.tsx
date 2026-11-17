import { Outlet, ScrollRestoration } from 'react-router'
import { Footer } from './Footer'
import { Header } from './Header'

export function AppLayout() {
  return (
    <div className="flex min-h-screen flex-col">
      <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-accent focus:px-3 focus:py-2 focus:text-black">
        Skip to content
      </a>
      <Header />
      <main id="main" className="mx-auto w-full max-w-page flex-1 px-4 py-6">
        <Outlet />
      </main>
      <Footer />
      <ScrollRestoration />
    </div>
  )
}
