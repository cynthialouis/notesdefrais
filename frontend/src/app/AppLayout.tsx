import { useState } from 'react'
import { Outlet, useNavigate } from 'react-router'
import { roleLabels } from '../features/auth/roles'
import { useSessionStore } from '../features/auth/sessionStore'
import { Alert } from '../ui/Alert'
import { Badge } from '../ui/Badge'
import { Button } from '../ui/Button'
import { NavMenu, type NavEntry } from './NavMenu'

/** Menu par domaine : les pages des notes de frais sont regroupées, chaque lien garde son rôle minimal. */
const entries: ReadonlyArray<NavEntry> = [
  { to: '/', label: 'Tableau de bord', role: 'employee' },
  {
    label: 'Notes de frais',
    items: [
      { to: '/notes', label: 'Mes notes', role: 'employee' },
      { to: '/equipe', label: 'Équipe', role: 'manager' },
      { to: '/politique', label: 'Politique', role: 'admin' },
    ],
  },
  { to: '/conges', label: 'Congés', role: 'employee' },
]

export function AppLayout() {
  const session = useSessionStore((state) => state.session)
  const logout = useSessionStore((state) => state.logout)
  const navigate = useNavigate()
  const [logoutError, setLogoutError] = useState<string | null>(null)

  async function handleLogout() {
    try {
      await logout()
      await navigate('/login') // C4
    } catch {
      setLogoutError('Déconnexion impossible, réessayez.')
    }
  }

  return (
    <div className="min-h-svh">
      <header className="border-b border-gray-200 bg-white">
        <div className="mx-auto flex max-w-5xl flex-wrap items-center gap-x-8 gap-y-2 px-4 py-3 sm:px-6">
          <p className="text-base font-semibold text-gray-900">Espace salarié</p>
          <NavMenu entries={entries} />
          <div className="ml-auto flex items-center gap-3">
            {session.status === 'authenticated' && (
              <p className="flex min-w-0 items-center gap-2 text-sm text-gray-700">
                <span className="truncate">{session.user.name}</span> <Badge>{roleLabels[session.user.role]}</Badge>
              </p>
            )}
            <Button onClick={handleLogout}>Se déconnecter</Button>
          </div>
        </div>
      </header>
      <main className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        {logoutError && <Alert className="mb-6">{logoutError}</Alert>}
        <Outlet />
      </main>
    </div>
  )
}
