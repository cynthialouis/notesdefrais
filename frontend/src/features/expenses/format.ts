const dateFormatter = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' })

/** « 2026-10-01 » → « 01/10/2026 » (date sans heure : formatée en UTC pour ne jamais décaler le jour). */
export function formatIsoDate(isoDate: string): string {
  return dateFormatter.format(new Date(`${isoDate}T00:00:00Z`))
}

const dateTimeFormatter = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Europe/Paris' })

/** Instant ISO 8601 → « 08/10/2026 10:00 », heure de Paris (celle des décisions de l'entreprise). */
export function formatIsoDateTime(isoDateTime: string): string {
  return dateTimeFormatter.format(new Date(isoDateTime))
}

/** Instant ISO 8601 → « 08/10/2026 », jour à Paris. */
export function formatIsoDay(isoDateTime: string): string {
  return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeZone: 'Europe/Paris' }).format(new Date(isoDateTime))
}
