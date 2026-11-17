import { Container } from '../../components/layout/Container'
import { Button, ButtonLink } from '../../components/ui/Button'
import { ProductCardSkeleton, Skeleton } from '../../components/ui/Skeleton'
import { useHomepage } from '../../lib/api/catalog'
import { ApiError } from '../../lib/api/client'
import { blockComponentFor } from './registry'

function HomepageSkeleton() {
  return (
    <div aria-hidden>
      <Container className="grid gap-2 pt-5 md:grid-cols-[minmax(0,1fr)_auto]">
        <Skeleton className="aspect-[16/9] w-full sm:aspect-[21/9] md:aspect-auto md:h-[330px]" />
        <div className="hidden gap-2 md:flex">
          {[0, 1, 2].map((tile) => (
            <Skeleton key={tile} className="h-[330px] w-[150px] lg:w-[165px]" />
          ))}
        </div>
      </Container>
      {[0, 1].map((rail) => (
        <div key={rail} className={rail === 0 ? 'bg-band py-7' : 'py-7'}>
          <Container className="flex flex-col gap-4">
            <Skeleton className="h-[26px] w-[220px]" />
            <div className="flex gap-3 overflow-hidden">
              {Array.from({ length: 6 }, (_, card) => (
                <div key={card} className="w-[46%] shrink-0 xs:w-[175px] md:w-[190px]">
                  <ProductCardSkeleton />
                </div>
              ))}
            </div>
          </Container>
        </div>
      ))}
    </div>
  )
}

/**
 * The homepage is entirely data-driven: GET /homepage returns the blocks in
 * order and each one is rendered by the component registered for its
 * layout. Unknown layouts are skipped.
 */
export function HomePage() {
  const homepage = useHomepage()

  return (
    <>
      <title>Eneba Marketplace | Cheap game keys</title>

      {homepage.isPending ? (
        <HomepageSkeleton />
      ) : homepage.isError ? (
        <Container className="py-9">
          <div role="alert" className="flex flex-col items-start gap-3 bg-black/20 p-5">
            <h1 className="text-lg font-extrabold">We couldn’t load the homepage</h1>
            <p className="max-w-[60ch] text-sm text-soft">
              {homepage.error instanceof ApiError && homepage.error.isNetworkError
                ? 'The server didn’t respond. On the free hosting tier it can take up to a minute to wake up.'
                : 'Something went wrong on our side. Please try again.'}
            </p>
            <div className="flex gap-2">
              <Button onClick={() => void homepage.refetch()}>Try again</Button>
              <ButtonLink to="/store" variant="secondary">
                Browse the store
              </ButtonLink>
            </div>
          </div>
        </Container>
      ) : homepage.data.length === 0 ? (
        <Container className="flex flex-col items-start gap-3 py-9">
          <h1 className="text-2xl font-extrabold">Welcome</h1>
          <p className="text-soft">There is nothing featured right now.</p>
          <ButtonLink to="/store">Browse the store</ButtonLink>
        </Container>
      ) : (
        <div className="pb-2">
          <h1 className="sr-only">Eneba Marketplace: buy game keys for less</h1>
          {homepage.data.map((block, index) => {
            const Block = blockComponentFor(block.layout)

            if (Block === undefined) {
              if (import.meta.env.DEV) console.warn(`Homepage: no component for layout "${block.layout}" (block ${block.id}, type "${block.type}"); skipped.`)
              return null
            }

            return <Block key={block.id} block={block} index={index} />
          })}
        </div>
      )}
    </>
  )
}
