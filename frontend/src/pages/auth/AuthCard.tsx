import type { ReactNode } from 'react'
import { Container } from '../../components/layout/Container'

/** Centered panel shared by the login and register pages. */
export function AuthCard({ title, children, footer }: { title: string; children: ReactNode; footer: ReactNode }) {
  return (
    <Container className="flex justify-center py-7">
      <title>{`${title} | Eneba Marketplace`}</title>
      <section aria-labelledby="auth-title" className="flex w-full max-w-[420px] flex-col gap-5 bg-card p-5 sm:p-6">
        <h1 id="auth-title" className="text-2xl font-extrabold">
          {title}
        </h1>
        {children}
        <p className="border-t border-white/10 pt-4 text-center text-sm text-soft">{footer}</p>
      </section>
    </Container>
  )
}

/** Form-level message for errors that don't belong to a single field. */
export function FormAlert({ children }: { children: ReactNode }) {
  return (
    <p role="alert" className="border-l-4 border-warning bg-warning/10 px-3 py-2 text-sm font-semibold text-white">
      {children}
    </p>
  )
}
