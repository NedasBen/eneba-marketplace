import { useParams } from 'react-router'
import { ButtonLink } from '../components/ui/Button'

/** Temporary route content until each page is built. */
export function PlaceholderPage({ title, description }: { title: string; description: string }) {
  const params = useParams()
  const detail = Object.values(params).filter(Boolean).join(' / ')

  return (
    <section className="flex flex-col items-start gap-3 py-6">
      <title>{`${title} | Eneba Marketplace`}</title>
      <h1 className="text-3xl font-extrabold">{title}</h1>
      {detail && <p className="font-semibold text-soft">{detail}</p>}
      <p className="max-w-[60ch] text-muted">{description}</p>
      <ButtonLink to="/store" variant="secondary">
        Browse the store
      </ButtonLink>
    </section>
  )
}

export function NotFoundPage() {
  return <PlaceholderPage title="Page not found" description="This page doesn't exist. Try searching for a game instead." />
}
