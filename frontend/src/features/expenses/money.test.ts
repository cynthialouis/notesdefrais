import { describe, expect, it } from 'vitest'
import { centsToEurosInput, formatCents, parseEurosToCents } from './money'

describe('parseEurosToCents', () => {
  it.each([
    ['12,50', 1250],
    ['12.50', 1250],
    ['12,5', 1250],
    ['12', 1200],
    ['0,1', 10],
    ['0,01', 1],
    ['10000', 1_000_000],
    ['10 000,00', 1_000_000],
    [' 42,00 ', 4200],
    ['0,07', 7],
  ])('C2 – « %s » donne %i centimes', (input, cents) => {
    expect(parseEurosToCents(input)).toBe(cents)
  })

  it.each(['12,505', '', 'abc', '12,', ',50', '-5', '1e3', '12,5,0'])('refuse « %s »', (input) => {
    expect(parseEurosToCents(input)).toBeNull()
  })

  it('C2 – pas d\'erreur d\'arrondi de flottant (0,29 € → 29, pas 28)', () => {
    expect(parseEurosToCents('0,29')).toBe(29)
    expect(parseEurosToCents('1,15')).toBe(115)
  })
})

describe('centsToEurosInput', () => {
  it.each([
    [1250, '12,50'],
    [7, '0,07'],
    [1_000_000, '10000,00'],
  ])('%i centimes donne « %s »', (cents, text) => {
    expect(centsToEurosInput(cents)).toBe(text)
  })

  it('C2 – un aller-retour conserve le montant exact', () => {
    for (const cents of [1, 29, 115, 1250, 99_999, 1_000_000]) {
      expect(parseEurosToCents(centsToEurosInput(cents))).toBe(cents)
    }
  })
})

describe('formatCents', () => {
  it('affiche en euros au format français', () => {
    // Intl insère des espaces insécables : on les normalise pour comparer
    expect(formatCents(1250).replace(/\s/g, ' ')).toBe('12,50 €')
    expect(formatCents(1_000_000).replace(/\s/g, ' ')).toBe('10 000,00 €')
  })
})
