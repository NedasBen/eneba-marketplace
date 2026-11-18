import type { ReactNode } from 'react'
import { Link } from 'react-router'
import type { Order, OrderItem } from '../../lib/api/orders'
import { formatMoney } from '../../lib/format'

/** The order's lines at their checkout prices, plus an optional slot per line (e.g. keys). */
export function OrderSummary({ order, renderExtra }: { order: Order; renderExtra?: (item: OrderItem) => ReactNode }) {
  return (
    <section aria-labelledby="order-summary-title" className="flex flex-col gap-2">
      <h2 id="order-summary-title" className="text-lg font-extrabold">
        Order summary
      </h2>
      <ul className="flex flex-col gap-2">
        {order.items.map((item) => (
          <li key={item.id} className="flex flex-col gap-3 bg-band p-3">
            <div className="flex gap-3">
              <img src={item.cover_image_url} alt="" className="aspect-[1/1.4] w-[48px] shrink-0 object-cover" />
              <div className="flex min-w-0 flex-1 flex-col gap-1">
                <Link to={`/product/${item.product_slug}`} className="font-extrabold hover:text-accent">
                  {item.product_name}
                </Link>
                <p className="text-xs text-soft">
                  Sold by {item.seller_name} · {item.quantity} × {formatMoney(item.unit_price)}
                </p>
              </div>
              <p className="font-extrabold">{formatMoney(item.line_total)}</p>
            </div>
            {renderExtra?.(item)}
          </li>
        ))}
      </ul>
      <p className="flex justify-between border-t border-white/10 pt-3">
        <span className="text-soft">Total</span>
        <span className="text-xl font-extrabold">{formatMoney(order.total)}</span>
      </p>
    </section>
  )
}
