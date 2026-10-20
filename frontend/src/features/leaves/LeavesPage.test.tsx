import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { json, stubApi } from '../../test/api'
import { useSessionStore } from '../auth/sessionStore'
import { acceptedLeave, cancelledLeave, employee, jsonResponse, manager, pendingLeave, refusedLeave } from '../../test/fixtures'
import { renderAsync } from '../../test/render'
import { useLeavesStore } from './leavesStore'
import { LeavesPage } from './LeavesPage'

function renderPage() {
  return renderAsync(
    <MemoryRouter>
      <LeavesPage />
    </MemoryRouter>,
  )
}

function rows(tableName: string): string[][] {
  const table = screen.getByRole('table', { name: tableName })
  const [, ...bodyRows] = within(table).getAllByRole('row')
  return bodyRows.map((row) => within(row).getAllByRole('cell').map((cell) => (cell.textContent ?? '').replace(/\s/g, ' ')))
}

async function fill(startDate: string, endDate: string) {
  if (startDate) await userEvent.type(screen.getByLabelText('Premier jour'), startDate)
  if (endDate) await userEvent.type(screen.getByLabelText('Dernier jour'), endDate)
  await userEvent.click(screen.getByRole('button', { name: 'Demander ce congé' }))
}

const conflict = (message: string) => json(409, { error: { code: 'conflict', message } })

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date(2026, 9, 20, 12, 0, 0)) // 20/10/2026, heure locale
})

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
  useLeavesStore.setState(useLeavesStore.getInitialState(), true)
  useSessionStore.setState(useSessionStore.getInitialState(), true)
})

describe('LeavesPage – mes demandes', () => {
  it('C16 – liste toutes ses demandes dans l\'ordre du serveur, avec l\'état et le motif d\'un refus ou d\'une annulation', async () => {
    stubApi({ 'GET /api/leaves': json(200, []), 'GET /api/me/leaves': json(200, [cancelledLeave, acceptedLeave, pendingLeave, refusedLeave, { ...pendingLeave, id: 14, startDate: '2026-10-25', endDate: '2026-10-25' }]) })
    await renderPage()

    expect(await screen.findByRole('table', { name: 'Mes demandes' })).toBeInTheDocument()
    expect(rows('Mes demandes')).toEqual([
      ['Du 29/11/2026 au 03/12/2026', 'Annulée', 'Annulée par Alice Martin : « Dates saisies par erreur »', ''],
      ['Du 19/11/2026 au 23/11/2026', 'Acceptée', '', ''],
      ['Du 03/11/2026 au 07/11/2026', 'En attente', '', 'Retirer'],
      ['Du 27/10/2026 au 28/10/2026', 'Refusée', 'Refusée par Claire Dubois : « Période de clôture des comptes »', ''],
      ['Le 25/10/2026', 'En attente', '', 'Retirer'],
    ])
  })

  it('C16 – sans demande, un état vide l\'explique', async () => {
    stubApi({ 'GET /api/leaves': json(200, []), 'GET /api/me/leaves': json(200, []) })
    await renderPage()

    expect(await screen.findByRole('heading', { name: 'Vous n\'avez encore fait aucune demande.' })).toBeInTheDocument()
  })

  it('signale une liste impossible à charger', async () => {
    stubApi({ 'GET /api/leaves': json(200, []), 'GET /api/me/leaves': json(500, { error: { code: 'internal', message: 'Erreur interne.' } }) })
    await renderPage()

    expect(await screen.findByRole('alert')).toHaveTextContent('Erreur interne.')
  })

  it('C11 – « Retirer » retire la demande en attente et relit la liste', async () => {
    let mine: unknown[] = [pendingLeave]
    const fetchMock = stubApi({
      'GET /api/leaves': json(200, []), 'GET /api/me/leaves': () => jsonResponse(200, mine),
      'POST /api/leaves/10/withdraw': () => {
        mine = [{ ...pendingLeave, status: 'withdrawn', withdrawable: false }]
        return jsonResponse(200, mine[0])
      },
    })
    await renderPage()
    await screen.findByRole('table', { name: 'Mes demandes' })

    await userEvent.click(screen.getByRole('button', { name: 'Retirer la demande : Du 03/11/2026 au 07/11/2026' }))

    expect(await screen.findByText('Retirée')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Retirer/ })).not.toBeInTheDocument()
    expect(fetchMock.mock.calls.filter(([url]) => url === '/api/leaves/10/withdraw')).toHaveLength(1)
  })

  it('C12 – un retrait refusé par le serveur (409) affiche son message', async () => {
    stubApi({
      'GET /api/leaves': json(200, []), 'GET /api/me/leaves': json(200, [pendingLeave]),
      'POST /api/leaves/10/withdraw': conflict('Seule une demande en attente peut être retirée.'),
    })
    await renderPage()
    await screen.findByRole('table', { name: 'Mes demandes' })

    await userEvent.click(screen.getByRole('button', { name: /Retirer/ }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Seule une demande en attente peut être retirée.')
  })
})

describe('LeavesPage – demander un congé', () => {
  it('C1 – envoie la période, confirme l\'envoi, vide le formulaire et relit ses demandes', async () => {
    let mine: unknown[] = []
    const fetchMock = stubApi({
      'GET /api/leaves': json(200, []), 'GET /api/me/leaves': () => jsonResponse(200, mine),
      'POST /api/leaves': () => {
        mine = [pendingLeave]
        return jsonResponse(201, pendingLeave)
      },
    })
    await renderPage()

    await fill('2026-11-03', '2026-11-07')

    expect(await screen.findByRole('status')).toHaveTextContent('Demande envoyée : elle attend la décision de votre responsable.')
    const post = fetchMock.mock.calls.find(([url, init]) => url === '/api/leaves' && init?.method === 'POST')
    expect(JSON.parse(String(post?.[1]?.body))).toEqual({ startDate: '2026-11-03', endDate: '2026-11-07' })
    expect(await screen.findByRole('table', { name: 'Mes demandes' })).toBeInTheDocument()
    expect(rows('Mes demandes')[0]?.[0]).toBe('Du 03/11/2026 au 07/11/2026')
    expect(screen.getByLabelText('Premier jour')).toHaveValue('')
    expect(screen.getByLabelText('Dernier jour')).toHaveValue('')
  })

  it.each([
    ['un début hier', '2026-10-19', '2026-10-22', 'Premier jour', 'Le congé ne peut pas commencer dans le passé.'],
    ['une fin avant le début', '2026-11-03', '2026-11-02', 'Dernier jour', 'La date de fin ne peut pas être antérieure à la date de début.'],
    ['une fin absente', '2026-11-03', '', 'Dernier jour', 'La date de fin est obligatoire.'],
  ])('C3 – %s est signalé sous le champ, sans rien envoyer', async (_case, start, end, label, message) => {
    const fetchMock = stubApi({ 'GET /api/leaves': json(200, []), 'GET /api/me/leaves': json(200, []) })
    await renderPage()

    await fill(start, end)

    expect(await screen.findByText(message)).toBeInTheDocument()
    expect(screen.getByLabelText(label)).toHaveAccessibleDescription(expect.stringContaining(message))
    expect(fetchMock.mock.calls.some(([, init]) => init?.method === 'POST')).toBe(false)
  })

  it('C3 – un congé d\'un seul jour commençant aujourd\'hui est envoyé', async () => {
    const fetchMock = stubApi({ 'GET /api/leaves': json(200, []), 'GET /api/me/leaves': json(200, []), 'POST /api/leaves': json(201, pendingLeave) })
    await renderPage()

    await fill('2026-10-20', '2026-10-20')

    await screen.findByRole('status')
    const post = fetchMock.mock.calls.find(([, init]) => init?.method === 'POST')
    expect(JSON.parse(String(post?.[1]?.body))).toEqual({ startDate: '2026-10-20', endDate: '2026-10-20' })
  })

  it('C2 – une erreur de date du serveur (400) s\'affiche sous le champ', async () => {
    stubApi({
      'GET /api/leaves': json(200, []), 'GET /api/me/leaves': json(200, []),
      'POST /api/leaves': json(400, { error: { code: 'validation_failed', message: 'Données invalides.', fields: { startDate: 'Le congé ne peut pas commencer dans le passé.' } } }),
    })
    await renderPage()

    await fill('2026-10-20', '2026-10-21')

    expect(await screen.findByText('Le congé ne peut pas commencer dans le passé.')).toBeInTheDocument()
    expect(screen.getByLabelText('Premier jour')).toHaveAccessibleDescription('Le congé ne peut pas commencer dans le passé.')
  })

  it.each([
    ['C4', 'Cette période chevauche une autre de vos demandes, en attente ou acceptée.'],
    ['C5', 'Vous n\'avez pas de responsable : personne ne peut accepter vos demandes de congé, vous ne pouvez donc pas en faire.'],
  ])('%s – un refus du serveur (409) affiche son message et garde la saisie', async (_criterion, message) => {
    stubApi({ 'GET /api/leaves': json(200, []), 'GET /api/me/leaves': json(200, []), 'POST /api/leaves': conflict(message) })
    await renderPage()

    await fill('2026-11-03', '2026-11-07')

    expect(await screen.findByRole('alert')).toHaveTextContent(message)
    expect(screen.getByLabelText('Premier jour')).toHaveValue('2026-11-03')
    expect(screen.getByLabelText('Dernier jour')).toHaveValue('2026-11-07')
  })
})

describe('LeavesPage – demandes à traiter', () => {
  const tomPending = { ...pendingLeave, id: 20, requester: { id: 5, name: 'Tom Petit' }, startDate: '2026-10-25', endDate: '2026-10-25', decidable: true, withdrawable: false }
  const leaPending = { ...pendingLeave, id: 21, decidable: true, withdrawable: false }
  const asManager = () => useSessionStore.setState({ session: { status: 'authenticated', user: manager } })
  const items = () => within(screen.getByRole('list', { name: 'Demandes à traiter' })).getAllByRole('listitem')

  it('C6 – un salarié ne voit pas la section et ne charge pas la liste', async () => {
    useSessionStore.setState({ session: { status: 'authenticated', user: employee } })
    const fetchMock = stubApi({ 'GET /api/leaves': json(200, []), 'GET /api/me/leaves': json(200, []) })
    await renderPage()
    await screen.findByRole('heading', { name: 'Mes demandes' })

    expect(screen.queryByRole('heading', { name: 'Demandes à traiter' })).not.toBeInTheDocument()
    expect(fetchMock.mock.calls.some(([url]) => url === '/api/team/leaves')).toBe(false)
  })

  it('C6 – le manager voit les demandes de son équipe dans l\'ordre du serveur', async () => {
    asManager()
    stubApi({ 'GET /api/leaves': json(200, []), 'GET /api/me/leaves': json(200, []), 'GET /api/team/leaves': json(200, [tomPending, leaPending]) })
    await renderPage()

    await screen.findByRole('list', { name: 'Demandes à traiter' })
    expect(items().map((item) => item.querySelector('p')?.textContent)).toEqual(['Tom Petit · Le 25/10/2026', 'Léa Bernard · Du 03/11/2026 au 07/11/2026'])
  })

  it('C6 – sans demande à traiter, un état vide l\'explique', async () => {
    asManager()
    stubApi({ 'GET /api/leaves': json(200, []), 'GET /api/me/leaves': json(200, []), 'GET /api/team/leaves': json(200, []) })
    await renderPage()

    expect(await screen.findByRole('heading', { name: 'Aucune demande à traiter.' })).toBeInTheDocument()
  })

  it('C7 – « Accepter » accepte la demande, qui sort de la liste', async () => {
    asManager()
    let team: unknown[] = [leaPending]
    const fetchMock = stubApi({
      'GET /api/leaves': json(200, []), 'GET /api/me/leaves': json(200, []),
      'GET /api/team/leaves': () => jsonResponse(200, team),
      'POST /api/leaves/21/accept': () => {
        team = []
        return jsonResponse(200, { ...acceptedLeave, id: 21 })
      },
    })
    await renderPage()
    await screen.findByRole('list', { name: 'Demandes à traiter' })

    await userEvent.click(screen.getByRole('button', { name: 'Accepter la demande de Léa Bernard (Du 03/11/2026 au 07/11/2026)' }))

    expect(await screen.findByRole('heading', { name: 'Aucune demande à traiter.' })).toBeInTheDocument()
    expect(fetchMock.mock.calls.find(([url]) => url === '/api/leaves/21/accept')?.[1]?.method).toBe('POST')
  })

  it('C8 – « Refuser » demande un motif, l\'envoie et la demande sort de la liste', async () => {
    asManager()
    let team: unknown[] = [leaPending]
    const fetchMock = stubApi({
      'GET /api/leaves': json(200, []), 'GET /api/me/leaves': json(200, []),
      'GET /api/team/leaves': () => jsonResponse(200, team),
      'POST /api/leaves/21/refuse': () => {
        team = []
        return jsonResponse(200, { ...refusedLeave, id: 21 })
      },
    })
    await renderPage()
    await screen.findByRole('list', { name: 'Demandes à traiter' })

    await userEvent.click(screen.getByRole('button', { name: /^Refuser la demande de Léa Bernard/ }))
    await userEvent.type(screen.getByLabelText('Motif du refus pour Léa Bernard'), '  Période de clôture  ')
    await userEvent.click(screen.getByRole('button', { name: 'Refuser la demande' }))

    expect(await screen.findByRole('heading', { name: 'Aucune demande à traiter.' })).toBeInTheDocument()
    const post = fetchMock.mock.calls.find(([url]) => url === '/api/leaves/21/refuse')
    expect(JSON.parse(String(post?.[1]?.body))).toEqual({ reason: 'Période de clôture' })
  })

  it('C8 – « Abandonner » referme le motif sans rien envoyer', async () => {
    asManager()
    const fetchMock = stubApi({ 'GET /api/leaves': json(200, []), 'GET /api/me/leaves': json(200, []), 'GET /api/team/leaves': json(200, [leaPending]) })
    await renderPage()
    await screen.findByRole('list', { name: 'Demandes à traiter' })

    await userEvent.click(screen.getByRole('button', { name: /^Refuser la demande de Léa Bernard/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Abandonner' }))

    expect(screen.queryByLabelText('Motif du refus pour Léa Bernard')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: /^Accepter la demande de Léa Bernard/ })).toBeInTheDocument()
    expect(fetchMock.mock.calls.some(([, init]) => init?.method === 'POST')).toBe(false)
  })

  it.each([
    ['vide', '', 'Le motif est obligatoire.'],
    ['de 4 caractères entourés d\'espaces', '   abcd   ', 'Le motif doit faire au moins 5 caractères.'],
  ])('C9 – un motif %s est signalé sous le champ, sans rien envoyer', async (_case, reason, message) => {
    asManager()
    const fetchMock = stubApi({ 'GET /api/leaves': json(200, []), 'GET /api/me/leaves': json(200, []), 'GET /api/team/leaves': json(200, [leaPending]) })
    await renderPage()
    await screen.findByRole('list', { name: 'Demandes à traiter' })

    await userEvent.click(screen.getByRole('button', { name: /^Refuser la demande de Léa Bernard/ }))
    if (reason) await userEvent.type(screen.getByLabelText('Motif du refus pour Léa Bernard'), reason)
    await userEvent.click(screen.getByRole('button', { name: 'Refuser la demande' }))

    expect(await screen.findByText(message)).toBeInTheDocument()
    expect(screen.getByLabelText('Motif du refus pour Léa Bernard')).toHaveAccessibleDescription(expect.stringContaining(message))
    expect(fetchMock.mock.calls.some(([, init]) => init?.method === 'POST')).toBe(false)
  })

  it('C10 – une acceptation refusée par le serveur (409) affiche son message', async () => {
    asManager()
    stubApi({
      'GET /api/leaves': json(200, []), 'GET /api/me/leaves': json(200, []),
      'GET /api/team/leaves': json(200, [leaPending]),
      'POST /api/leaves/21/accept': conflict('Seule une demande en attente peut être acceptée ou refusée.'),
    })
    await renderPage()
    await screen.findByRole('list', { name: 'Demandes à traiter' })

    await userEvent.click(screen.getByRole('button', { name: /^Accepter la demande de Léa Bernard/ }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Seule une demande en attente peut être acceptée ou refusée.')
  })

  it('C10 – un refus rejeté par le serveur (409) affiche son message sous le motif', async () => {
    asManager()
    stubApi({
      'GET /api/leaves': json(200, []), 'GET /api/me/leaves': json(200, []),
      'GET /api/team/leaves': json(200, [leaPending]),
      'POST /api/leaves/21/refuse': conflict('Seule une demande en attente peut être acceptée ou refusée.'),
    })
    await renderPage()
    await screen.findByRole('list', { name: 'Demandes à traiter' })

    await userEvent.click(screen.getByRole('button', { name: /^Refuser la demande de Léa Bernard/ }))
    await userEvent.type(screen.getByLabelText('Motif du refus pour Léa Bernard'), 'Période de clôture')
    await userEvent.click(screen.getByRole('button', { name: 'Refuser la demande' }))

    expect(await screen.findByText('Seule une demande en attente peut être acceptée ou refusée.')).toBeInTheDocument()
  })

  it('une demande non décidable par le lecteur n\'a pas de boutons', async () => {
    asManager()
    stubApi({ 'GET /api/leaves': json(200, []), 'GET /api/me/leaves': json(200, []), 'GET /api/team/leaves': json(200, [{ ...leaPending, decidable: false }]) })
    await renderPage()
    await screen.findByRole('list', { name: 'Demandes à traiter' })

    expect(within(items()[0] as HTMLElement).queryByRole('button')).not.toBeInTheDocument()
  })
})

describe('LeavesPage – congés à venir', () => {
  const inesAccepted = { ...acceptedLeave, id: 30, requester: { id: 6, name: 'Inès Moreau' }, startDate: '2026-11-10', endDate: '2026-11-14', decidedBy: { id: 3, name: 'Hugo Lefèvre' } }
  const tomOngoing = { ...acceptedLeave, id: 31, requester: { id: 5, name: 'Tom Petit' }, startDate: '2026-10-18', endDate: '2026-10-20' }

  it('C15 – liste les congés acceptés de tous dans l\'ordre du serveur, avec la personne et la période', async () => {
    stubApi({ 'GET /api/me/leaves': json(200, []), 'GET /api/leaves': json(200, [tomOngoing, inesAccepted, acceptedLeave]) })
    await renderPage()

    expect(await screen.findByRole('table', { name: 'Congés à venir' })).toBeInTheDocument()
    expect(rows('Congés à venir')).toEqual([
      ['Tom Petit', 'Du 18/10/2026 au 20/10/2026'],
      ['Inès Moreau', 'Du 10/11/2026 au 14/11/2026'],
      ['Léa Bernard', 'Du 19/11/2026 au 23/11/2026'],
    ])
  })

  it('C17 – n\'affiche ni état ni motif ni décideur', async () => {
    stubApi({ 'GET /api/me/leaves': json(200, []), 'GET /api/leaves': json(200, [inesAccepted]) })
    await renderPage()

    const table = await screen.findByRole('table', { name: 'Congés à venir' })
    expect(within(table).queryByText('Hugo Lefèvre')).not.toBeInTheDocument()
    expect(within(table).queryByText('Acceptée')).not.toBeInTheDocument()
  })

  it('C15 – sans congé à venir, un état vide l\'explique', async () => {
    stubApi({ 'GET /api/me/leaves': json(200, []), 'GET /api/leaves': json(200, []) })
    await renderPage()

    expect(await screen.findByRole('heading', { name: 'Aucun congé en cours ou à venir.' })).toBeInTheDocument()
  })

  it('C7/C15 – un congé accepté par le manager apparaît aussitôt dans les congés à venir', async () => {
    useSessionStore.setState({ session: { status: 'authenticated', user: manager } })
    let accepted: unknown[] = []
    let team: unknown[] = [{ ...pendingLeave, decidable: true, withdrawable: false }]
    stubApi({
      'GET /api/me/leaves': json(200, []),
      'GET /api/leaves': () => jsonResponse(200, accepted),
      'GET /api/team/leaves': () => jsonResponse(200, team),
      'POST /api/leaves/10/accept': () => {
        accepted = [{ ...acceptedLeave, id: 10, startDate: '2026-11-03', endDate: '2026-11-07' }]
        team = []
        return jsonResponse(200, accepted[0])
      },
    })
    await renderPage()
    await screen.findByRole('heading', { name: 'Aucun congé en cours ou à venir.' })

    await userEvent.click(await screen.findByRole('button', { name: /^Accepter la demande de Léa Bernard/ }))

    expect(await screen.findByRole('table', { name: 'Congés à venir' })).toBeInTheDocument()
    expect(rows('Congés à venir')).toEqual([['Léa Bernard', 'Du 03/11/2026 au 07/11/2026']])
  })
})
