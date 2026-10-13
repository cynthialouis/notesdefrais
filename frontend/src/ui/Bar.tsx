import { cx } from './cx'

type BarProps = {
  value: number
  /** Valeur qui remplit toute la largeur (la plus grande de la série). */
  max: number
  className?: string
}

/**
 * Barre horizontale proportionnelle, purement décorative : la valeur est toujours écrite en texte à côté
 * (lecteur d'écran, impression), la barre ne fait que la rendre comparable d'un coup d'œil.
 */
export function Bar({ value, max, className }: BarProps) {
  const percent = max > 0 ? Math.min(100, (value / max) * 100) : 0
  return (
    <div aria-hidden="true" className={cx('h-2 w-full overflow-hidden rounded-full bg-gray-100', className)}>
      {/* Largeur calculée à l'exécution : seul style en ligne (Tailwind génère ses classes à la compilation).
          Une valeur non nulle reste visible, même très petite face au maximum. */}
      <div className={cx('h-full rounded-full bg-brand-500', value > 0 && 'min-w-1')} style={{ width: `${percent}%` }} />
    </div>
  )
}
