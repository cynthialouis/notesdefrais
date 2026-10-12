import type { ReactNode } from 'react'
import { cx } from './cx'

type CardProps = {
  children: ReactNode
  /** Titre affiché dans l'en-tête de la carte (h2). */
  title?: ReactNode
  /** Contenu à droite de l'en-tête (filtre, action). */
  actions?: ReactNode
  className?: string
}

export function Card({ children, title, actions, className }: CardProps) {
  return (
    <section className={cx('rounded-lg border border-gray-200 bg-white shadow-xs', className)}>
      {(title || actions) && (
        <header className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
          {title && <h2 className="text-base font-semibold text-gray-900">{title}</h2>}
          {actions}
        </header>
      )}
      <div className="p-5">{children}</div>
    </section>
  )
}
