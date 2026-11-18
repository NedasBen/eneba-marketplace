import { Outlet, ScrollRestoration } from 'react-router'
import { AuthProvider } from '../../lib/auth/AuthContext'
import { Footer } from './Footer'
import { Header } from './Header'

export function AppLayout() {
  return (
    <AuthProvider>
      <div className="flex min-h-screen flex-col">
        <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-accent focus:px-3 focus:py-2 focus:text-black">
          Skip to content
        </a>
        <Header />
        <main id="main" className="w-full flex-1">
          <Outlet />
        </main>
        <Footer />
        <ScrollRestoration />
      </div>
    </AuthProvider>
  )
}
