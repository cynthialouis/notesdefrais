import { describe, expect, it } from 'vitest'
import { rejectionSchema } from './rejectionValidation'

describe('rejectionSchema', () => {
  it.each(['Reçu', '    ', '', ' abcd ', 'x'.repeat(1001)])('C5 – refuse « %s »', (reason) => {
    expect(rejectionSchema.safeParse({ reason }).success).toBe(false)
  })

  it.each(['Reçus', '  Motif valable  ', 'ééééé', '🚕🚕🚕🚕🚕', 'x'.repeat(1000)])('accepte « %s »', (reason) => {
    expect(rejectionSchema.safeParse({ reason }).success).toBe(true)
  })

  it('retire les espaces autour', () => {
    expect(rejectionSchema.parse({ reason: ' Reçu illisible ' }).reason).toBe('Reçu illisible')
  })
})

describe('rejectionSchema – alignement avec le serveur', () => {
  it('retire U+0085 comme le serveur : « \\u0085abcd » ne fait que 4 caractères, refusé', () => {
    expect(rejectionSchema.safeParse({ reason: '\u0085abcd' }).success).toBe(false)
  })
})
