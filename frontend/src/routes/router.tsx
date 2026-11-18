import { createBrowserRouter } from 'react-router'
import { AppLayout } from '../components/layout/AppLayout'
import { NotFoundPage, PlaceholderPage } from '../pages/PlaceholderPage'
import { LoginPage } from '../pages/auth/LoginPage'
import { RegisterPage } from '../pages/auth/RegisterPage'
import { CartPage } from '../pages/cart/CartPage'
import { HomePage } from '../pages/home/HomePage'
import { ProductPage } from '../pages/product/ProductPage'
import { StorePage } from '../pages/store/StorePage'
import { RequireAuth } from './RequireAuth'

/** Routes from docs/ARCHITECTURE.md ("Frontend routes"). */
export const router = createBrowserRouter([
  {
    element: <AppLayout />,
    children: [
      { index: true, element: <HomePage /> },
      { path: 'store', element: <StorePage /> },
      { path: 'product/:slug', element: <ProductPage /> },
      { path: 'cart', element: <CartPage /> },
      { path: 'login', element: <LoginPage /> },
      { path: 'register', element: <RegisterPage /> },
      {
        element: <RequireAuth />,
        children: [
          { path: 'checkout/:reference', element: <PlaceholderPage title="Checkout" description="Pay for a pending order." /> },
          { path: 'orders', element: <PlaceholderPage title="My orders" description="Order history." /> },
          { path: 'orders/:reference', element: <PlaceholderPage title="Order" description="Order details and keys." /> },
        ],
      },
      // Component showcase for comparing against eneba.com: deployed, but not linked anywhere.
      { path: 'dev/components', lazy: async () => ({ Component: (await import('../pages/dev/ComponentsPage')).ComponentsPage }) },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
])
