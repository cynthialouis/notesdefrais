// Mêmes caractères que UnicodeTrim côté serveur (PHP : [\s\p{Z}\x{FEFF}] avec /u, qui inclut U+0085 et U+180E,
// absents de String.prototype.trim) : front et back retirent exactement les mêmes blancs.
const EDGE_BLANKS = /^[\s\p{Z}\u0085᠎﻿]+|[\s\p{Z}\u0085᠎﻿]+$/gu

export function unicodeTrim(value: string): string {
  return value.replace(EDGE_BLANKS, '')
}
