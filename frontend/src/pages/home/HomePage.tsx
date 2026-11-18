import { Container } from '../../components/layout/Container'
import { ButtonLink } from '../../components/ui/Button'
import { EmptyState, ErrorState } from '../../components/ui/PageState'
import { ProductCardSkeleton, Skeleton } from '../../components/ui/Skeleton'
import { useHomepage } from '../../lib/api/catalog'
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
          <ErrorState
            as="h1"
            title="We couldn’t load the homepage"
            error={homepage.error}
            onRetry={() => void homepage.refetch()}
            actions={
              <ButtonLink to="/store" variant="secondary">
                Browse the store
              </ButtonLink>
            }
          />
        </Container>
      ) : homepage.data.length === 0 ? (
        <Container className="py-9">
          <EmptyState as="h1" title="Welcome" action={<ButtonLink to="/store">Browse the store</ButtonLink>}>
            There is nothing featured right now.
          </EmptyState>
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
