import type { ReactNode } from 'react'
import { cx } from './cx'

type AlertProps = {
  /** « error » est annoncé immédiatement (role="alert") ; « info » et « warning » poliment (role="status"). */
  tone?: 'error' | 'warning' | 'info'
  children: ReactNode
  className?: string
}

export function Alert({ tone = 'error', children, className }: AlertProps) {
  return (
    <p
      role={tone === 'error' ? 'alert' : 'status'}
      className={cx(
        'rounded-md border px-4 py-3 text-sm',
        tone === 'error' && 'border-red-200 bg-red-50 text-red-800',
        tone === 'warning' && 'border-amber-200 bg-amber-50 text-amber-900',
        tone === 'info' && 'border-blue-200 bg-blue-50 text-blue-800',
        className,
      )}
    >
      {children}
    </p>
  )
}
