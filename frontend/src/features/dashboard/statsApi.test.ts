import { afterEach, describe, expect, it, vi } from 'vitest'
import { json, stubApi } from '../../test/api'
import { loadMyStats, loadTeamStats } from './statsApi'

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('statsApi', () => {
  it('C1 – charge ses totaux par état', async () => {
    const byStatus = [{ status: 'draft', count: 2, totalCents: 1250 }]
    stubApi({ 'GET /api/stats/me': json(200, { byStatus }) })

    expect(await loadMyStats()).toEqual({ ok: true, stats: { byStatus } })
  })

  it('refuse un total qui n\'est pas un entier de centimes', async () => {
    stubApi({ 'GET /api/stats/me': json(200, { byStatus: [{ status: 'draft', count: 1, totalCents: 12.5 }] }) })

    expect(await loadMyStats()).toEqual({ ok: false, message: 'Réponse inattendue du serveur.' })
  })

  it('rend le message du serveur pour un salarié (403)', async () => {
    stubApi({ 'GET /api/stats/team': json(403, { error: { code: 'forbidden', message: 'Accès refusé.' } }) })

    expect(await loadTeamStats()).toEqual({ ok: false, message: 'Accès refusé.' })
  })
})
