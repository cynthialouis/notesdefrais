import type { ReactNode, TdHTMLAttributes, ThHTMLAttributes } from 'react'
import { cx } from './cx'

/** Tableau de données, avec défilement horizontal sur petit écran. `label` lui donne un nom accessible. */
export function Table({ children, label }: { children: ReactNode; label?: string }) {
  return (
    <div className="-mx-5 overflow-x-auto">
      <table aria-label={label} className="min-w-full divide-y divide-gray-200 text-sm">
        {children}
      </table>
    </div>
  )
}

type Align = { align?: 'left' | 'right' }

export function Th({ align = 'left', className, ...props }: ThHTMLAttributes<HTMLTableCellElement> & Align) {
  return (
    <th
      scope="col"
      className={cx('px-5 py-3 text-xs font-medium tracking-wide text-gray-500 uppercase', align === 'right' ? 'text-right' : 'text-left', className)}
      {...props}
    />
  )
}

/** Cellule sur une ligne ; `wrap` la laisse aller à la ligne (texte libre, ex. un motif), sans défilement horizontal. */
export function Td({ align = 'left', wrap = false, className, ...props }: TdHTMLAttributes<HTMLTableCellElement> & Align & { wrap?: boolean }) {
  return (
    <td
      className={cx('px-5 py-3 text-gray-700', wrap ? 'min-w-48 wrap-break-word' : 'whitespace-nowrap', align === 'right' && 'text-right tabular-nums', className)}
      {...props}
    />
  )
}
