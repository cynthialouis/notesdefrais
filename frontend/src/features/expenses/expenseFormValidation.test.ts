import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { parseExpenseForm, serverFieldsToFormErrors, todayIso, type ExpenseFormValues } from './expenseFormValidation'

const valid: ExpenseFormValues = { label: 'Déjeuner client', amount: '12,50', date: '2026-10-01', categorySlug: 'repas' }

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date(2026, 9, 11, 12, 0, 0)) // 11/10/2026, heure locale
})

afterEach(() => {
  vi.useRealTimers()
})

describe('parseExpenseForm', () => {
  it('C1/C2 – un formulaire valide donne le corps à envoyer, montant en centimes et libellé sans espaces', () => {
    expect(parseExpenseForm({ ...valid, label: '  Déjeuner client  ' })).toEqual({
      ok: true,
      input: { label: 'Déjeuner client', amountCents: 1250, date: '2026-10-01', categorySlug: 'repas' },
    })
  })

  it.each<[Partial<ExpenseFormValues>, keyof ExpenseFormValues]>([
    [{ label: '' }, 'label'],
    [{ label: '   ' }, 'label'],
    [{ label: 'a' }, 'label'],
    [{ label: 'x'.repeat(81) }, 'label'],
    [{ amount: '' }, 'amount'],
    [{ amount: '0' }, 'amount'],
    [{ amount: '12,505' }, 'amount'],
    [{ amount: '10000,01' }, 'amount'],
    [{ amount: 'abc' }, 'amount'],
    [{ date: '' }, 'date'],
    [{ date: '2026-02-30' }, 'date'],
    [{ date: '2026-10-12' }, 'date'],
    [{ categorySlug: '' }, 'categorySlug'],
  ])('C3/C4 – %o est refusé sur le champ %s', (override, field) => {
    const result = parseExpenseForm({ ...valid, ...override })
    expect(result.ok).toBe(false)
    expect(result.ok === false && Object.keys(result.errors)).toEqual([field])
  })

  it('accepte les bornes : 10 000,00 €, 2 et 80 caractères, la date du jour', () => {
    expect(parseExpenseForm({ ...valid, amount: '10000' }).ok).toBe(true)
    expect(parseExpenseForm({ ...valid, label: 'ab' }).ok).toBe(true)
    expect(parseExpenseForm({ ...valid, label: 'é'.repeat(80) }).ok).toBe(true)
    expect(parseExpenseForm({ ...valid, date: '2026-10-11' }).ok).toBe(true)
  })

  it('C4 – signale tous les champs invalides en une fois', () => {
    const result = parseExpenseForm({ label: '', amount: '', date: '', categorySlug: '' })
    expect(result.ok === false && Object.keys(result.errors).sort()).toEqual(['amount', 'categorySlug', 'date', 'label'])
  })
})

describe('parseExpenseForm – alignement avec le serveur', () => {
  it('refuse un libellé fait d\'espaces insécables et retire les blancs Unicode autour', () => {
    expect(parseExpenseForm({ ...valid, label: '\u00a0\u202f\u00a0' }).ok).toBe(false)
    const result = parseExpenseForm({ ...valid, label: '\u00a0Taxi\u2028' })
    expect(result.ok && result.input.label).toBe('Taxi')
  })

  it('compte la longueur en caractères : 80 emoji acceptés, 81 refusés', () => {
    expect(parseExpenseForm({ ...valid, label: '🚕'.repeat(80) }).ok).toBe(true)
    expect(parseExpenseForm({ ...valid, label: '🚕'.repeat(81) }).ok).toBe(false)
  })
})

describe('todayIso', () => {
  it('donne la date locale au format AAAA-MM-JJ', () => {
    expect(todayIso()).toBe('2026-10-11')
  })
})

describe('serverFieldsToFormErrors', () => {
  it('C3 – rattache les erreurs du serveur aux champs du formulaire', () => {
    expect(serverFieldsToFormErrors({ amountCents: 'Trop élevé.', date: 'Futur.', inconnu: 'x' })).toEqual({ amount: 'Trop élevé.', date: 'Futur.' })
  })
})
