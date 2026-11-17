import { createBrowserRouter } from 'react-router'
import { AppLayout } from '../components/layout/AppLayout'
import { NotFoundPage, PlaceholderPage } from '../pages/PlaceholderPage'
import { RequireAuth } from './RequireAuth'

/** Routes from docs/ARCHITECTURE.md ("Frontend routes"). */
export const router = createBrowserRouter([
  {
    element: <AppLayout />,
    children: [
      { index: true, element: <PlaceholderPage title="Home" description="Homepage blocks (banner, best sellers, deals) come next." /> },
      { path: 'store', element: <PlaceholderPage title="Store" description="Catalog with filters, sorting and search results." /> },
      { path: 'product/:slug', element: <PlaceholderPage title="Product" description="Product details and seller offers." /> },
      { path: 'cart', element: <PlaceholderPage title="Cart" description="Your cart." /> },
      { path: 'login', element: <PlaceholderPage title="Log in" description="Sign in to check out and see your orders." /> },
      { path: 'register', element: <PlaceholderPage title="Register" description="Create an account." /> },
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
