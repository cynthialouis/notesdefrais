/**
 * Dépassement de plafond, pour l'alerte immédiate du formulaire (spec 004, C4).
 * Duplication volontaire de PolicyChecker côté serveur, qui fait foi (C7) : mêmes règles, mêmes cas testés.
 * Renvoie l'écart en centimes, ou null si le montant est conforme (égal au plafond compris, C5) ou sans plafond.
 */
export function overLimitByCents(amountCents: number, maxAmountCents: number | null | undefined): number | null {
  if (maxAmountCents === null || maxAmountCents === undefined || amountCents <= maxAmountCents) {
    return null
  }
  return amountCents - maxAmountCents
}
