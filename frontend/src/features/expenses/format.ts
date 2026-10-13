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

const sizeFormatters = {
  ko: new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }),
  mo: new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }),
}

/** Taille d'un fichier, en unités décimales comme la limite du serveur (5 Mo = 5 000 000 octets) : « 350 Ko », « 1,2 Mo ». */
export function formatFileSize(bytes: number): string {
  if (bytes < 1000) {
    return `${bytes} octets`
  }
  if (bytes < 1_000_000) {
    return `${sizeFormatters.ko.format(bytes / 1000)} Ko`
  }
  return `${sizeFormatters.mo.format(bytes / 1_000_000)} Mo`
}
