import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { parseLeaveForm, serverFieldsToLeaveErrors, type LeaveFormValues } from './leaveFormValidation'

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date(2026, 9, 20, 12, 0, 0)) // 20/10/2026, heure locale
})

afterEach(() => {
  vi.useRealTimers()
})

describe('parseLeaveForm', () => {
  it('C1 – une période valide donne le corps à envoyer', () => {
    expect(parseLeaveForm({ startDate: '2026-11-03', endDate: '2026-11-07' })).toEqual({
      ok: true,
      input: { startDate: '2026-11-03', endDate: '2026-11-07' },
    })
  })

  it.each<[string, LeaveFormValues]>([
    ['un début aujourd\'hui', { startDate: '2026-10-20', endDate: '2026-10-22' }],
    ['un seul jour', { startDate: '2026-11-03', endDate: '2026-11-03' }],
    ['un 29 février d\'année bissextile', { startDate: '2028-02-29', endDate: '2028-03-01' }],
  ])('C3 – accepte %s', (_case, values) => {
    expect(parseLeaveForm(values).ok).toBe(true)
  })

  it.each<[string, LeaveFormValues, Partial<Record<keyof LeaveFormValues, string>>]>([
    ['un début hier', { startDate: '2026-10-19', endDate: '2026-10-22' }, { startDate: 'Le congé ne peut pas commencer dans le passé.' }],
    ['une fin la veille du début', { startDate: '2026-11-03', endDate: '2026-11-02' }, { endDate: 'La date de fin ne peut pas être antérieure à la date de début.' }],
    ['un début absent', { startDate: '', endDate: '2026-11-02' }, { startDate: 'La date de début est obligatoire.' }],
    ['une fin absente', { startDate: '2026-11-03', endDate: '' }, { endDate: 'La date de fin est obligatoire.' }],
    ['un 30 février', { startDate: '2027-02-30', endDate: '2027-03-02' }, { startDate: 'Date invalide (format attendu : AAAA-MM-JJ).' }],
    ['un format invalide', { startDate: '2026-11-03', endDate: '03/11/2026' }, { endDate: 'Date invalide (format attendu : AAAA-MM-JJ).' }],
  ])('C3 – refuse %s sur le champ en cause, avec le message du serveur', (_case, values, errors) => {
    expect(parseLeaveForm(values)).toEqual({ ok: false, errors })
  })

  it('C3 – signale les deux champs en une fois', () => {
    const result = parseLeaveForm({ startDate: '', endDate: '' })
    expect(result.ok === false && Object.keys(result.errors).sort()).toEqual(['endDate', 'startDate'])
  })
})

describe('serverFieldsToLeaveErrors', () => {
  it('C2 – reporte les erreurs du serveur sur les champs du formulaire et ignore les autres', () => {
    expect(serverFieldsToLeaveErrors({ startDate: 'Le congé ne peut pas commencer dans le passé.', other: 'x' })).toEqual({
      startDate: 'Le congé ne peut pas commencer dans le passé.',
    })
  })
})
