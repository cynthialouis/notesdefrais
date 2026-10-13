import { describe, expect, it } from 'vitest'
import { MAX_RECEIPT_BYTES, receiptSchema } from './receiptValidation'

function fileOf(size: number, type: string, name = 'recu'): File {
  return new File([new Uint8Array(size)], name, { type })
}

function error(file: unknown): string | undefined {
  const parsed = receiptSchema.safeParse({ file })
  return parsed.success ? undefined : parsed.error.issues[0]?.message
}

// Mêmes cas que UploadReceiptTest côté serveur (spec 005, C2, C3)
describe('receiptSchema', () => {
  it.each(['image/jpeg', 'image/png', 'application/pdf'])('C1 – accepte un fichier %s', (type) => {
    expect(error(fileOf(1200, type))).toBeUndefined()
  })

  it('C3 – accepte 5 Mo exactement et refuse un octet de plus', () => {
    expect(error(fileOf(MAX_RECEIPT_BYTES, 'application/pdf'))).toBeUndefined()
    expect(error(fileOf(MAX_RECEIPT_BYTES + 1, 'application/pdf'))).toBe('Le fichier dépasse 5 Mo.')
  })

  it('C3 – refuse un fichier vide', () => {
    expect(error(fileOf(0, 'image/png'))).toBe('Le fichier est vide.')
  })

  it.each(['image/gif', 'text/plain', ''])('C2 – refuse le type « %s »', (type) => {
    expect(error(fileOf(1200, type))).toBe('Formats acceptés : JPEG, PNG ou PDF.')
  })

  it('refuse l\'absence de fichier', () => {
    expect(error(undefined)).toBe('Choisissez un fichier.')
  })
})
