import { useId, type ComponentProps, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react'
import { cx } from './cx'

/** Attributs que Field fournit au contrôle : identifiant et liaisons ARIA. */
export type FieldControlProps = {
  id: string
  'aria-invalid'?: true
  'aria-describedby'?: string
}

type FieldProps = {
  label: ReactNode
  /** Message d'erreur : relié au contrôle (aria-describedby) et le marque invalide. */
  error?: string
  hint?: ReactNode
  children: (control: FieldControlProps) => ReactNode
  className?: string
}

/**
 * Champ de formulaire : libellé, contrôle, aide et erreur, avec la plomberie d'accessibilité.
 * Usage : <Field label="Libellé" error={errors.label}>{(control) => <Input {...control} name="label" />}</Field>
 */
export function Field({ label, error, hint, children, className }: FieldProps) {
  const id = useId()
  const hintId = `${id}-hint`
  const errorId = `${id}-error`
  const describedBy = [hint && hintId, error && errorId].filter(Boolean).join(' ') || undefined

  return (
    <div className={cx('flex flex-col gap-1.5', className)}>
      <label htmlFor={id} className="text-sm font-medium text-gray-900">
        {label}
      </label>
      {children({ id, 'aria-invalid': error ? true : undefined, 'aria-describedby': describedBy })}
      {hint && (
        <p id={hintId} className="text-xs text-gray-500">
          {hint}
        </p>
      )}
      {error && (
        <p id={errorId} className="text-sm text-red-700">
          {error}
        </p>
      )}
    </div>
  )
}

const control =
  'block w-full min-h-10 rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-900 shadow-xs ' +
  'placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/30 ' +
  'aria-invalid:border-red-500 aria-invalid:focus:ring-red-500/30'

// ComponentProps inclut ref (prop ordinaire en React 19), transmise à l'input par la décomposition
type InputProps = ComponentProps<'input'> & {
  /** Unité affichée à droite du champ (ex. « € »), décorative. */
  suffix?: string
}

export function Input({ suffix, className, ...props }: InputProps) {
  if (!suffix) {
    return <input className={cx(control, className)} {...props} />
  }
  return (
    <div className="relative">
      <input className={cx(control, 'pr-9', className)} {...props} />
      <span aria-hidden="true" className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-gray-500">
        {suffix}
      </span>
    </div>
  )
}

export function Select({ className, ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
  return <select className={cx(control, 'pr-8', className)} {...props} />
}

export function Textarea({ className, rows = 3, ...props }: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return <textarea rows={rows} className={cx(control, 'py-2', className)} {...props} />
}
