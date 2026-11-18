import { Container } from '../components/layout/Container'
import { ButtonLink } from '../components/ui/Button'
import { EmptyState } from '../components/ui/PageState'

export function NotFoundPage() {
  return (
    <Container className="py-6">
      <title>Page not found | Eneba Marketplace</title>
      <EmptyState as="h1" title="Page not found" action={<ButtonLink to="/store">Browse the store</ButtonLink>}>
        This page doesn’t exist. Try searching for a game instead.
      </EmptyState>
    </Container>
  )
}
