import { createBrowserRouter } from 'react-router'
import { AppLayout } from '../components/layout/AppLayout'
import { NotFoundPage } from '../pages/NotFoundPage'
import { CartPage } from '../pages/cart/CartPage'
import { HomePage } from '../pages/home/HomePage'
import { ProductPage } from '../pages/product/ProductPage'
import { StorePage } from '../pages/store/StorePage'
import { RequireAuth } from './RequireAuth'

/**
 * Routes from docs/ARCHITECTURE.md ("Frontend routes"). The browsing pages
 * people land on (home, store, product, cart) are in the main bundle; the
 * account and checkout pages are loaded on demand, so landing pages ship
 * less JavaScript.
 */
export const router = createBrowserRouter([
  {
    element: <AppLayout />,
    children: [
      { index: true, element: <HomePage /> },
      { path: 'store', element: <StorePage /> },
      { path: 'product/:slug', element: <ProductPage /> },
      { path: 'cart', element: <CartPage /> },
      { path: 'login', lazy: async () => ({ Component: (await import('../pages/auth/LoginPage')).LoginPage }) },
      { path: 'register', lazy: async () => ({ Component: (await import('../pages/auth/RegisterPage')).RegisterPage }) },
      {
        element: <RequireAuth />,
        children: [
          { path: 'checkout/:reference', lazy: async () => ({ Component: (await import('../pages/checkout/CheckoutPage')).CheckoutPage }) },
          { path: 'orders', lazy: async () => ({ Component: (await import('../pages/orders/OrdersPage')).OrdersPage }) },
          { path: 'orders/:reference', lazy: async () => ({ Component: (await import('../pages/orders/OrderPage')).OrderPage }) },
        ],
      },
      // Component showcase for comparing against eneba.com: deployed, but not linked anywhere.
      { path: 'dev/components', lazy: async () => ({ Component: (await import('../pages/dev/ComponentsPage')).ComponentsPage }) },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
])
