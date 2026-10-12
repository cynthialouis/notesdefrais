import { cx } from './cx'

export type ButtonVariant = 'primary' | 'secondary' | 'danger'

const base =
  'inline-flex min-h-10 items-center justify-center gap-2 rounded-md px-4 text-sm font-medium transition-colors ' +
  'focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 ' +
  'disabled:cursor-not-allowed disabled:opacity-60'

const variants: Record<ButtonVariant, string> = {
  primary: 'bg-brand-600 text-white shadow-xs hover:bg-brand-700',
  secondary: 'border border-gray-300 bg-white text-gray-700 shadow-xs hover:bg-gray-50',
  danger: 'border border-red-200 bg-white text-red-700 shadow-xs hover:bg-red-50',
}

/** Classes d'un bouton, partagées par Button et ButtonLink. */
export function buttonClasses(variant: ButtonVariant = 'secondary', className?: string): string {
  return cx(base, variants[variant], className)
}
