import { render, screen, within } from '@testing-library/react'
import { renderAsync } from '../test/render'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useSessionStore, type CurrentUser } from '../features/auth/sessionStore'
import { admin, employee, jsonResponse, manager } from '../test/fixtures'
import { AppRoutes } from './AppRoutes'

const fetchMock = vi.fn<typeof fetch>()

function renderAt(path: string, user: CurrentUser | null) {
  useSessionStore.setState({ session: user ? { status: 'authenticated', user } : { status: 'anonymous' } })
  return render(
    <MemoryRouter initialEntries={[path]}>
      <AppRoutes />
    </MemoryRouter>,
  )
}

function navLinks(): string[] {
  const nav = screen.getByRole('navigation', { name: 'Navigation principale' })
  return within(nav).getAllByRole('link').map((link) => link.textContent ?? '')
}

beforeEach(() => {
  vi.stubGlobal('fetch', fetchMock)
  // La page de connexion charge la liste des comptes
  fetchMock.mockImplementation(async () => jsonResponse(200, []))
})

afterEach(() => {
  fetchMock.mockReset()
  vi.unstubAllGlobals()
  useSessionStore.setState(useSessionStore.getInitialState(), true)
})

describe('protection des pages', () => {
  it.each(['/', '/notes', '/conges', '/equipe', '/politique'])('C6 – %s sans session amène à la connexion', async (path) => {
    useSessionStore.setState({ session: { status: 'anonymous' } })
    await renderAsync(
      <MemoryRouter initialEntries={[path]}>
        <AppRoutes />
      </MemoryRouter>,
    )

    expect(await screen.findByRole('heading', { name: 'Espace salarié' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Mes notes' })).not.toBeInTheDocument()
  })

  it('C7 – un salarié qui ouvre la page Équipe voit « Accès refusé »', () => {
    renderAt('/equipe', employee)

    expect(screen.getByRole('heading', { name: 'Accès refusé' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Équipe' })).not.toBeInTheDocument()
  })

  it('C7 – un manager qui ouvre la page Politique voit « Accès refusé »', () => {
    renderAt('/politique', manager)

    expect(screen.getByRole('heading', { name: 'Accès refusé' })).toBeInTheDocument()
  })

  it('C9 – un manager accède à Mes notes', () => {
    renderAt('/notes', manager)
    expect(screen.getByRole('heading', { name: 'Mes notes' })).toBeInTheDocument()
  })

  it('C9 – un manager accède à Équipe', () => {
    renderAt('/equipe', manager)
    expect(screen.getByRole('heading', { name: 'Équipe' })).toBeInTheDocument()
  })

  it.each([employee, manager, admin])('spec 007 – $role accède à Congés', (user) => {
    renderAt('/conges', user)
    expect(screen.getByRole('heading', { name: 'Congés' })).toBeInTheDocument()
  })

  it.each([
    ['/notes', 'Mes notes'],
    ['/equipe', 'Équipe'],
    ['/politique', 'Politique de dépenses'],
  ])('C9 – un admin accède à %s', (path, heading) => {
    renderAt(path, admin)
    expect(screen.getByRole('heading', { name: heading })).toBeInTheDocument()
  })

  it('affiche un chargement tant que la session est inconnue', () => {
    useSessionStore.setState({ session: { status: 'loading' } })
    render(
      <MemoryRouter initialEntries={['/']}>
        <AppRoutes />
      </MemoryRouter>,
    )
    expect(screen.getByRole('status')).toHaveTextContent('Chargement')
  })
})

describe('menu', () => {
  const notesButton = () => screen.getByRole('button', { name: 'Notes de frais' })
  const openNotes = () => userEvent.click(notesButton())

  it('C8 – un salarié ne voit que ses pages', async () => {
    renderAt('/', employee)
    expect(navLinks()).toEqual(['Tableau de bord', 'Congés'])
    await openNotes()
    expect(navLinks()).toEqual(['Tableau de bord', 'Mes notes', 'Congés'])
  })

  it('C8 – un manager voit aussi Équipe', async () => {
    renderAt('/', manager)
    await openNotes()
    expect(navLinks()).toEqual(['Tableau de bord', 'Mes notes', 'Équipe', 'Congés'])
  })

  it('C8 – un admin voit toutes les pages', async () => {
    renderAt('/', admin)
    await openNotes()
    expect(navLinks()).toEqual(['Tableau de bord', 'Mes notes', 'Équipe', 'Politique', 'Congés'])
  })

  it('le groupe Notes de frais est fermé par défaut et s\'ouvre au clic', async () => {
    renderAt('/', employee)
    expect(notesButton()).toHaveAttribute('aria-expanded', 'false')
    await openNotes()
    expect(notesButton()).toHaveAttribute('aria-expanded', 'true')
    await openNotes()
    expect(notesButton()).toHaveAttribute('aria-expanded', 'false')
  })

  it('Échap referme le groupe et rend le focus au bouton', async () => {
    renderAt('/', employee)
    await openNotes()
    await userEvent.keyboard('{Escape}')
    expect(notesButton()).toHaveAttribute('aria-expanded', 'false')
    expect(notesButton()).toHaveFocus()
  })

  it('un clic en dehors referme le groupe', async () => {
    renderAt('/', employee)
    await openNotes()
    await userEvent.click(screen.getByRole('main'))
    expect(notesButton()).toHaveAttribute('aria-expanded', 'false')
  })

  it('le focus qui sort du groupe le referme', async () => {
    renderAt('/', employee)
    await openNotes()
    await userEvent.tab() // Mes notes
    await userEvent.tab() // Congés, hors du groupe
    expect(screen.getByRole('link', { name: 'Congés' })).toHaveFocus()
    expect(notesButton()).toHaveAttribute('aria-expanded', 'false')
  })

  it('choisir une page referme le groupe, qui reste mis en évidence sur cette page', async () => {
    renderAt('/', employee)
    expect(notesButton()).not.toHaveClass('bg-brand-50')
    await openNotes()
    await userEvent.click(screen.getByRole('link', { name: 'Mes notes' }))
    expect(await screen.findByRole('heading', { name: 'Mes notes' })).toBeInTheDocument()
    expect(notesButton()).toHaveAttribute('aria-expanded', 'false')
    expect(notesButton()).toHaveClass('bg-brand-50')
  })
})

describe('session', () => {
  it('C13 – un utilisateur connecté qui ouvre la connexion arrive au tableau de bord', async () => {
    // Rendu asynchrone : le tableau de bord charge ses totaux avec use() depuis la spec 006
    useSessionStore.setState({ session: { status: 'authenticated', user: employee } })
    await renderAsync(
      <MemoryRouter initialEntries={['/login']}>
        <AppRoutes />
      </MemoryRouter>,
    )
    expect(await screen.findByRole('heading', { name: 'Tableau de bord' })).toBeInTheDocument()
  })

  it('C4 – la déconnexion ferme la session et ramène à la connexion', async () => {
    fetchMock.mockImplementation(async (input) =>
      String(input) === '/api/auth/logout' ? new Response(null, { status: 204 }) : jsonResponse(200, []),
    )
    renderAt('/', employee)

    await userEvent.click(screen.getByRole('button', { name: 'Se déconnecter' }))

    expect(await screen.findByRole('heading', { name: 'Espace salarié' })).toBeInTheDocument()
    expect(useSessionStore.getState().session).toEqual({ status: 'anonymous' })
  })

  it('C4 – une déconnexion impossible est signalée et garde l\'utilisateur sur la page', async () => {
    fetchMock.mockRejectedValue(new TypeError('Failed to fetch'))
    renderAt('/', employee)

    await userEvent.click(screen.getByRole('button', { name: 'Se déconnecter' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Déconnexion impossible')
    expect(screen.getByRole('heading', { name: 'Tableau de bord' })).toBeInTheDocument()
  })
})
