import type { ButtonHTMLAttributes } from 'react'
import { Link, type LinkProps } from 'react-router'
import { buttonClasses, type ButtonVariant } from './buttonStyles'
import { Spinner } from './Spinner'

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: ButtonVariant
  /** Action en cours : le bouton est désactivé et affiche un indicateur. */
  pending?: boolean
}

export function Button({ variant = 'secondary', pending = false, disabled, className, children, type = 'button', ...props }: ButtonProps) {
  return (
    <button type={type} disabled={disabled || pending} aria-busy={pending || undefined} className={buttonClasses(variant, className)} {...props}>
      {pending && <Spinner />}
      {children}
    </button>
  )
}

/** Lien de navigation qui a l'apparence d'un bouton (ex. « Nouvelle note »). */
export function ButtonLink({ variant = 'secondary', className, ...props }: LinkProps & { variant?: ButtonVariant }) {
  return <Link className={buttonClasses(variant, className)} {...props} />
}
