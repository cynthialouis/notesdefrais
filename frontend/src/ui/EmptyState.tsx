import type { ReactNode } from 'react'

type EmptyStateProps = {
  title: ReactNode
  description?: ReactNode
  action?: ReactNode
  /** Niveau du titre : h1 si l'état vide est toute la page (accès refusé), h2 sinon. */
  headingLevel?: 1 | 2
}

export function EmptyState({ title, description, action, headingLevel = 2 }: EmptyStateProps) {
  const Heading = headingLevel === 1 ? 'h1' : 'h2'
  return (
    <div className="flex flex-col items-center gap-2 px-6 py-12 text-center">
      <Heading className="text-base font-semibold text-gray-900">{title}</Heading>
      {description && <p className="max-w-md text-sm text-gray-600">{description}</p>}
      {action && <div className="mt-3">{action}</div>}
    </div>
  )
}
