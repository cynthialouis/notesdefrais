import { useEffect, useId, useRef, useState, type FocusEvent, type KeyboardEvent } from 'react'
import { matchPath, NavLink, useLocation } from 'react-router'
import { canAccess, useSessionStore, type Role } from '../features/auth/sessionStore'
import { cx } from '../ui/cx'

export type NavItem = { to: string; label: string; role: Role }
export type NavGroup = { label: string; items: ReadonlyArray<NavItem> }
export type NavEntry = NavItem | NavGroup

const itemClass = (isActive: boolean) =>
  cx(
    'block rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap',
    'focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500',
    isActive ? 'bg-brand-50 text-brand-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
  )

/** Navigation principale : liens directs et groupes dépliants, chaque lien filtré par rôle (spec 001, C8). */
export function NavMenu({ entries }: { entries: ReadonlyArray<NavEntry> }) {
  const session = useSessionStore((state) => state.session)
  const visible = entries.flatMap((entry): NavEntry[] => {
    if ('to' in entry) return canAccess(session, entry.role) ? [entry] : []
    const items = entry.items.filter((item) => canAccess(session, item.role))
    return items.length > 0 ? [{ ...entry, items }] : [] // un groupe sans lien accessible disparaît
  })

  return (
    <nav aria-label="Navigation principale" className="order-last -mx-1 w-full sm:order-none sm:w-auto">
      <ul className="flex flex-wrap gap-1">
        {visible.map((entry) =>
          'to' in entry ? (
            <li key={entry.to}>
              <NavLink to={entry.to} end={entry.to === '/'} className={({ isActive }) => itemClass(isActive)}>
                {entry.label}
              </NavLink>
            </li>
          ) : (
            <NavDisclosure key={entry.label} group={entry} />
          ),
        )}
      </ul>
    </nav>
  )
}

/**
 * Groupe dépliant (motif « disclosure » : un bouton aria-expanded qui montre une liste de liens, pas un role="menu").
 * Se referme avec Échap (focus rendu au bouton), au clic ou au focus en dehors, et à tout changement de page.
 */
function NavDisclosure({ group }: { group: NavGroup }) {
  const { pathname } = useLocation()
  // Ouvert pour la page où on l'a ouvert : changer de page le referme, sans effet
  const [openOn, setOpenOn] = useState<string | null>(null)
  const open = openOn === pathname
  const panelId = useId()
  const rootRef = useRef<HTMLLIElement>(null)
  const buttonRef = useRef<HTMLButtonElement>(null)
  const active = group.items.some((item) => matchPath({ path: item.to, end: false }, pathname) !== null)

  // Clic en dehors : abonnement au document tant que le groupe est ouvert (effet légitime : système extérieur)
  useEffect(() => {
    if (!open) return
    function handlePointerDown(event: PointerEvent) {
      if (event.target instanceof Node && !rootRef.current?.contains(event.target)) setOpenOn(null)
    }
    document.addEventListener('pointerdown', handlePointerDown)
    return () => document.removeEventListener('pointerdown', handlePointerDown)
  }, [open])

  function handleKeyDown(event: KeyboardEvent) {
    if (event.key === 'Escape' && open) {
      setOpenOn(null)
      buttonRef.current?.focus()
    }
  }

  function handleBlur(event: FocusEvent) {
    // Tab qui sort du groupe ; relatedTarget nul (clic sur une zone non focalisable) est traité par le clic en dehors
    if (event.relatedTarget instanceof Node && !rootRef.current?.contains(event.relatedTarget)) setOpenOn(null)
  }

  return (
    <li ref={rootRef} className="relative" onKeyDown={handleKeyDown} onBlur={handleBlur}>
      <button
        ref={buttonRef}
        type="button"
        aria-expanded={open}
        aria-controls={panelId}
        onClick={() => setOpenOn(open ? null : pathname)}
        className={cx(itemClass(active), 'flex items-center gap-1')}
      >
        {group.label}
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" className={cx('size-4 transition-transform', open && 'rotate-180')}>
          <path fillRule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clipRule="evenodd" />
        </svg>
      </button>
      <ul id={panelId} hidden={!open} className="absolute top-full left-0 z-10 mt-1 min-w-48 rounded-md border border-gray-200 bg-white p-1 shadow-lg">
        {group.items.map((item) => (
          <li key={item.to}>
            <NavLink to={item.to} onClick={() => setOpenOn(null)} className={({ isActive }) => itemClass(isActive)}>
              {item.label}
            </NavLink>
          </li>
        ))}
      </ul>
    </li>
  )
}
