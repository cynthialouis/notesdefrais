import { describe, expect, it } from 'vitest'
import { unicodeTrim } from './unicodeTrim'

describe('unicodeTrim', () => {
  it.each([
    ['  Taxi  ', 'Taxi'],
    [' Taxi ', 'Taxi'],
    ['\u0085abcd', 'abcd'],
    ['᠎abcd﻿', 'abcd'],
    [' Taxi ', 'Taxi'],
    ['Taxi gare', 'Taxi gare'],
  ])('« %s » donne « %s » (comme le serveur)', (input, expected) => {
    expect(unicodeTrim(input)).toBe(expected)
  })
})
