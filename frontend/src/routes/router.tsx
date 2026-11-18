import { createBrowserRouter } from 'react-router'
import { AppLayout } from '../components/layout/AppLayout'
import { NotFoundPage, PlaceholderPage } from '../pages/PlaceholderPage'
import { LoginPage } from '../pages/auth/LoginPage'
import { RegisterPage } from '../pages/auth/RegisterPage'
import { CartPage } from '../pages/cart/CartPage'
import { CheckoutPage } from '../pages/checkout/CheckoutPage'
import { HomePage } from '../pages/home/HomePage'
import { OrderPage } from '../pages/orders/OrderPage'
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
          { path: 'checkout/:reference', element: <CheckoutPage /> },
          { path: 'orders', element: <PlaceholderPage title="My orders" description="Order history." /> },
          { path: 'orders/:reference', element: <OrderPage /> },
        ],
      },
      // Component showcase for comparing against eneba.com: deployed, but not linked anywhere.
      { path: 'dev/components', lazy: async () => ({ Component: (await import('../pages/dev/ComponentsPage')).ComponentsPage }) },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
])
