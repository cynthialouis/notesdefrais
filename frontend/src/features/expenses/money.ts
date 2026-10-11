/** Plafond d'une note : 10 000,00 € (même limite que le serveur). */
export const MAX_AMOUNT_CENTS = 1_000_000

const EUROS_PATTERN = /^(\d+)(?:[.,](\d{1,2}))?$/

const eurosFormatter = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' })

/**
 * Seul point de conversion euros → centimes. Accepte « 12 », « 12,5 », « 12.50 » ;
 * refuse plus de deux décimales et tout autre format (renvoie null).
 * Calcul en entiers à partir du texte : aucun flottant, donc aucune erreur d'arrondi.
 */
export function parseEurosToCents(input: string): number | null {
  const match = EUROS_PATTERN.exec(input.trim().replace(/\s/g, ''))
  if (!match) {
    return null
  }
  const [, euros = '', decimals = ''] = match
  return Number(euros) * 100 + Number(decimals.padEnd(2, '0'))
}

/** Montant en centimes → texte à saisir (« 12,50 »), pour préremplir un formulaire. */
export function centsToEurosInput(cents: number): string {
  const euros = Math.trunc(cents / 100)
  const rest = String(cents % 100).padStart(2, '0')
  return `${euros},${rest}`
}

/** Affichage en euros, format français (« 12,50 € »). */
export function formatCents(cents: number): string {
  return eurosFormatter.format(cents / 100)
}
