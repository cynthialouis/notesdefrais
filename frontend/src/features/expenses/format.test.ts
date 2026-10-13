import { describe, expect, it } from 'vitest'
import { formatFileSize } from './format'

describe('formatFileSize', () => {
  it.each([
    [672, '672 octets'],
    [1000, '1 Ko'],
    [350_400, '350 Ko'],
    [1_234_567, '1,2 Mo'],
    [5_000_000, '5 Mo'],
  ])('%i octets → %s', (bytes, expected) => {
    expect(formatFileSize(bytes)).toBe(expected)
  })
})
