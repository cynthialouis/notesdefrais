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
  it.each(['/', '/notes', '/equipe', '/politique'])('C6 – %s sans session amène à la connexion', async (path) => {
    useSessionStore.setState({ session: { status: 'anonymous' } })
    await renderAsync(
      <MemoryRouter initialEntries={[path]}>
        <AppRoutes />
      </MemoryRouter>,
    )

    expect(await screen.findByRole('heading', { name: 'Notes de frais' })).toBeInTheDocument()
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
  it('C8 – un salarié ne voit que ses pages', () => {
    renderAt('/', employee)
    expect(navLinks()).toEqual(['Tableau de bord', 'Mes notes'])
  })

  it('C8 – un manager voit aussi Équipe', () => {
    renderAt('/', manager)
    expect(navLinks()).toEqual(['Tableau de bord', 'Mes notes', 'Équipe'])
  })

  it('C8 – un admin voit toutes les pages', () => {
    renderAt('/', admin)
    expect(navLinks()).toEqual(['Tableau de bord', 'Mes notes', 'Équipe', 'Politique'])
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

    expect(await screen.findByRole('heading', { name: 'Notes de frais' })).toBeInTheDocument()
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
