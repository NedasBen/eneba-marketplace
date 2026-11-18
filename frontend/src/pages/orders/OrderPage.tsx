import { useQuery } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import { useLocation, useParams } from 'react-router'
import { Container } from '../../components/layout/Container'
import { Button, ButtonLink } from '../../components/ui/Button'
import { EmptyState, ErrorState } from '../../components/ui/PageState'
import { Skeleton } from '../../components/ui/Skeleton'
import { ApiError } from '../../lib/api/client'
import { orderQueries, type OrderKey } from '../../lib/api/orders'
import { OrderSummary } from './OrderSummary'

/** Copy to the clipboard, falling back to selecting the text where the Clipboard API is unavailable. */
async function copyText(text: string, fallbackInput: HTMLInputElement | null): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(text)
    return true
  } catch {
    fallbackInput?.select()
    return document.execCommand?.('copy') ?? false
  }
}

function KeyRow({ productKey }: { productKey: OrderKey }) {
  const inputRef = useRef<HTMLInputElement>(null)
  const [copied, setCopied] = useState<'idle' | 'copied' | 'select'>('idle')
  const timer = useRef<number | undefined>(undefined)

  useEffect(() => () => window.clearTimeout(timer.current), [])

  const copy = async () => {
    const ok = await copyText(productKey.code, inputRef.current)
    setCopied(ok ? 'copied' : 'select')
    window.clearTimeout(timer.current)
    timer.current = window.setTimeout(() => setCopied('idle'), ok ? 2000 : 6000)
  }

  return (
    <li className="flex items-center gap-2">
      <input
        ref={inputRef}
        readOnly
        value={productKey.code}
        aria-label="Product key"
        onFocus={(event) => event.target.select()}
        className="h-[38px] min-w-0 flex-1 border border-white/25 bg-black/30 px-3 font-mono text-sm font-semibold tracking-wider text-white outline-none focus:border-white"
      />
      <Button size="sm" variant={copied === 'copied' ? 'secondary' : 'primary'} onClick={() => void copy()} className="min-h-[38px] min-w-[76px]">
        {copied === 'copied' ? 'Copied ✓' : 'Copy'}
      </Button>
      <span role="status" className={copied === 'select' ? 'text-xs text-soft' : 'sr-only'}>
        {copied === 'copied' && 'Key copied to the clipboard.'}
        {copied === 'select' && 'Selected: press Ctrl+C to copy.'}
      </span>
    </li>
  )
}

/**
 * Order detail. For a paid order this is the success page: every key with
 * a copy button. Pending orders link back to payment.
 */
export function OrderPage() {
  const { reference = '' } = useParams()
  const location = useLocation()
  const justPaid = (location.state as { justPaid?: boolean } | null)?.justPaid === true
  const order = useQuery(orderQueries.detail(reference))

  return (
    <Container className="flex flex-col gap-5 py-6">
      <title>{justPaid ? 'Payment successful | Eneba Marketplace' : 'Order | Eneba Marketplace'}</title>

      {order.isPending ? (
        <Skeleton className="h-[260px] w-full" />
      ) : order.isError ? (
        order.error instanceof ApiError && order.error.status === 404 ? (
          <EmptyState as="h1" title="Order not found" action={<ButtonLink to="/orders">My orders</ButtonLink>}>
            This order doesn’t exist or belongs to another account.
          </EmptyState>
        ) : (
          <ErrorState as="h1" title="We couldn’t load this order" error={order.error} onRetry={() => void order.refetch()} />
        )
      ) : (
        <>
          <header className="flex flex-col gap-2">
            {order.data.status === 'paid' && (
              <p role="status" className="flex items-center gap-2 font-extrabold text-success">
                <span aria-hidden className="grid size-[22px] place-items-center rounded-full bg-success text-xs text-badge-ink">
                  ✓
                </span>
                {justPaid ? 'Payment successful' : 'Paid'}
              </p>
            )}
            <h1 className="text-2xl font-extrabold">{order.data.status === 'paid' ? 'Your keys are ready' : 'Order'}</h1>
            <p className="text-sm text-muted">
              Order <span className="font-mono">{order.data.reference}</span>
              {order.data.paid_at && ` · paid ${new Date(order.data.paid_at).toLocaleString()}`}
            </p>
          </header>

          {order.data.status === 'pending' && (
            <div className="flex flex-col items-start gap-3 bg-raised p-4">
              <p className="text-soft">This order is waiting for payment.</p>
              <ButtonLink to={`/checkout/${order.data.reference}`}>Continue to payment</ButtonLink>
            </div>
          )}
          {order.data.status === 'expired' && (
            <div className="flex flex-col items-start gap-3 bg-band p-4">
              <p className="text-soft">This order expired before it was paid, and its keys went back on sale.</p>
              <ButtonLink to="/cart">Back to cart</ButtonLink>
            </div>
          )}

          <div className="max-w-[760px]">
            <OrderSummary
              order={order.data}
              renderExtra={(item) =>
                item.keys && item.keys.length > 0 ? (
                  <div className="flex flex-col gap-2 border-t border-white/10 pt-3">
                    <p className="text-xs font-semibold text-soft">
                      {item.keys.length === 1 ? 'Your key' : `Your ${item.keys.length} keys`}: activate on {item.platform_name} ({item.region_name})
                    </p>
                    <ul className="flex flex-col gap-2">
                      {item.keys.map((productKey) => (
                        <KeyRow key={productKey.id} productKey={productKey} />
                      ))}
                    </ul>
                  </div>
                ) : null
              }
            />
          </div>
        </>
      )}
    </Container>
  )
}
