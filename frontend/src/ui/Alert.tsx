import type { ReactNode } from 'react'
import { cx } from './cx'

type AlertProps = {
  /** « error » est annoncé immédiatement (role="alert") ; « info » poliment (role="status"). */
  tone?: 'error' | 'info'
  children: ReactNode
  className?: string
}

export function Alert({ tone = 'error', children, className }: AlertProps) {
  return (
    <p
      role={tone === 'error' ? 'alert' : 'status'}
      className={cx(
        'rounded-md border px-4 py-3 text-sm',
        tone === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-blue-200 bg-blue-50 text-blue-800',
        className,
      )}
    >
      {children}
    </p>
  )
}
