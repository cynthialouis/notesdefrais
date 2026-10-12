import { describe, expect, it } from 'vitest'
import { overLimitByCents } from './checkPolicy'

describe('overLimitByCents (mêmes cas que PolicyChecker côté serveur)', () => {
  it.each<[number, number | null, number | null]>([
    [4999, 5000, null],
    [5000, 5000, null], // C5 : égal au plafond = conforme
    [5001, 5000, 1],
    [12000, 5000, 7000],
    [999999, null, null], // aucun plafond
  ])('%i centimes pour un plafond de %s : écart %s', (amount, max, expected) => {
    expect(overLimitByCents(amount, max)).toBe(expected)
  })
})
