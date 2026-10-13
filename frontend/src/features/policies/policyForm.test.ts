import { describe, expect, it } from 'vitest'
import { policyFormSchema } from './policyForm'

describe('policyFormSchema (mêmes règles que PolicyInput)', () => {
  it.each<[string, number | null]>([
    ['', null],
    ['  ', null],
    ['50', 5000],
    ['0,01', 1],
    ['10000', 1_000_000],
  ])('« %s » donne %s', (maxAmount, expected) => {
    expect(policyFormSchema.parse({ maxAmount, receiptRequired: false }).maxAmount).toBe(expected)
  })

  it.each(['0', '10000,01', '12,505', 'abc'])('C3 – refuse « %s »', (maxAmount) => {
    expect(policyFormSchema.safeParse({ maxAmount, receiptRequired: true }).success).toBe(false)
  })
})
