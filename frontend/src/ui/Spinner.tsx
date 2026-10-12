import { cx } from './cx'

/** Indicateur de chargement décoratif : le texte accessible est porté par le contexte (bouton, LoadingState). */
export function Spinner({ className }: { className?: string }) {
  return (
    <span
      aria-hidden="true"
      className={cx('inline-block size-4 animate-spin rounded-full border-2 border-current border-r-transparent', className)}
    />
  )
}

/** Zone de chargement annoncée aux lecteurs d'écran (role="status"). */
export function LoadingState({ label = 'Chargement…' }: { label?: string }) {
  return (
    <p role="status" className="flex items-center gap-2 py-8 text-sm text-gray-500">
      <Spinner />
      {label}
    </p>
  )
}
