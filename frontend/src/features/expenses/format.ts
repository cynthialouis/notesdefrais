const dateFormatter = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' })

/** « 2026-10-01 » → « 01/10/2026 » (date sans heure : formatée en UTC pour ne jamais décaler le jour). */
export function formatIsoDate(isoDate: string): string {
  return dateFormatter.format(new Date(`${isoDate}T00:00:00Z`))
}
