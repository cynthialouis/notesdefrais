import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { employee, jsonResponse } from '../../test/fixtures'
import { renderAsync } from '../../test/render'
import { LoginPage } from './LoginPage'
import { useSessionStore } from './sessionStore'

const demoUsers = [
  { id: 1, name: 'Alice Martin', role: 'admin' },
  { id: 4, name: 'Léa Bernard', role: 'employee' },
]

const fetchMock = vi.fn<typeof fetch>()

function renderLogin() {
  useSessionStore.setState({ session: { status: 'anonymous' } })
  return renderAsync(
    <MemoryRouter initialEntries={['/login']}>
      <Routes>
        <Route path="login" element={<LoginPage />} />
        <Route path="/" element={<h1>Accueil</h1>} />
      </Routes>
    </MemoryRouter>,
  )
}

beforeEach(() => {
  vi.stubGlobal('fetch', fetchMock)
})

afterEach(() => {
  fetchMock.mockReset()
  vi.unstubAllGlobals()
  useSessionStore.setState(useSessionStore.getInitialState(), true)
})

describe('LoginPage', () => {
  it('C1 – affiche les comptes de démo avec leur rôle', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, demoUsers))
    await renderLogin()

    expect(await screen.findByRole('radio', { name: /Alice Martin/ })).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: /Alice Martin/ })).toHaveAccessibleName('Alice Martin Admin')
    expect(screen.getByRole('radio', { name: /Léa Bernard/ })).toHaveAccessibleName('Léa Bernard Salarié')
  })

  it('C2 – choisir un compte ouvre la session et amène au tableau de bord', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, demoUsers)).mockResolvedValueOnce(jsonResponse(200, employee))
    await renderLogin()

    await userEvent.click(await screen.findByRole('radio', { name: /Léa Bernard/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Se connecter' }))

    expect(await screen.findByRole('heading', { name: 'Accueil' })).toBeInTheDocument()
    expect(fetchMock).toHaveBeenLastCalledWith('/api/auth/login', expect.objectContaining({ body: JSON.stringify({ userId: 4 }) }))
    expect(useSessionStore.getState().session).toEqual({ status: 'authenticated', user: employee })
  })

  it('C10 – sans compte choisi, un message lié au formulaire est affiché et rien n\'est envoyé', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, demoUsers))
    await renderLogin()

    await userEvent.click(await screen.findByRole('button', { name: 'Se connecter' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Choisissez un compte.')
    expect(screen.getByRole('group', { name: 'Choisissez un compte de démo' })).toHaveAccessibleDescription('Choisissez un compte.')
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('C11 – une connexion refusée affiche le message du serveur', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse(200, demoUsers))
      .mockResolvedValueOnce(jsonResponse(401, { error: { code: 'unauthorized', message: 'Authentification requise.' } }))
    await renderLogin()

    await userEvent.click(await screen.findByRole('radio', { name: /Alice Martin/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Se connecter' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Authentification requise.')
    expect(useSessionStore.getState().session).toEqual({ status: 'anonymous' })
  })

  it('signale une liste de comptes impossible à charger', async () => {
    fetchMock.mockRejectedValueOnce(new TypeError('Failed to fetch'))
    await renderLogin()

    expect(await screen.findByRole('alert')).toHaveTextContent('Serveur injoignable.')
  })
})
