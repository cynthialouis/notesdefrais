import { Link } from 'react-router'
import { PageHeader } from '../../ui/PageHeader'
import { roleLabels } from '../auth/roles'
import { useSessionStore } from '../auth/sessionStore'

const shortcuts = [
  { to: '/notes', title: 'Mes notes', description: 'Consulter vos notes de frais et leur état.' },
  { to: '/notes/nouvelle', title: 'Nouvelle note', description: 'Déclarer une dépense en brouillon.' },
]

export function DashboardPage() {
  const session = useSessionStore((state) => state.session)

  return (
    <>
      <title>Tableau de bord – Notes de frais</title>
      <PageHeader
        title="Tableau de bord"
        description={
          session.status === 'authenticated' &&
          `Connecté en tant que ${session.user.name}, ${roleLabels[session.user.role].toLowerCase()}.`
        }
      />
      <div className="grid gap-4 sm:grid-cols-2">
        {shortcuts.map((shortcut) => (
          <Link
            key={shortcut.to}
            to={shortcut.to}
            className="rounded-lg border border-gray-200 bg-white p-5 shadow-xs transition-colors hover:border-brand-500 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500"
          >
            <span className="block text-base font-semibold text-gray-900">{shortcut.title}</span>
            <span className="mt-1 block text-sm text-gray-600">{shortcut.description}</span>
          </Link>
        ))}
      </div>
    </>
  )
}
