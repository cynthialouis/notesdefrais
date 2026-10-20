import { z } from 'zod'
import { isRealDate, todayIso } from '../expenses/expenseFormValidation'
import type { LeaveInput } from './leavesStore'

/**
 * Validation du formulaire de demande de congé, pour afficher les erreurs avant tout envoi (C3).
 * Duplication volontaire des règles du serveur (spec 007, « Règles recalculées côté front ») : le
 * front compare à la date locale du navigateur, le serveur à la date du jour à Paris, et le serveur fait foi.
 */

const datePattern = /^\d{4}-\d{2}-\d{2}$/
const isValidDate = (value: string) => datePattern.test(value) && isRealDate(value)

const dateField = (requiredMessage: string) =>
  z
    .string()
    .min(1, requiredMessage)
    .regex(datePattern, 'Date invalide (format attendu : AAAA-MM-JJ).')
    .refine(isRealDate, 'Date invalide (format attendu : AAAA-MM-JJ).')

const leaveFormSchema = z
  .object({
    startDate: dateField('La date de début est obligatoire.').refine(
      (value) => value >= todayIso(),
      'Le congé ne peut pas commencer dans le passé.',
    ),
    endDate: dateField('La date de fin est obligatoire.'),
  })
  // Deux dates AAAA-MM-JJ se comparent comme des chaînes. Zod 4 exécute ce contrôle même si un champ a échoué :
  // il ne s'applique que si les deux dates sont valides, comme côté serveur (sinon l'erreur de format suffit)
  .refine(({ startDate, endDate }) => !isValidDate(startDate) || !isValidDate(endDate) || endDate >= startDate, {
    message: 'La date de fin ne peut pas être antérieure à la date de début.',
    path: ['endDate'],
  })

export type LeaveFormValues = z.input<typeof leaveFormSchema>
export type LeaveFormField = keyof LeaveFormValues
export type LeaveFormErrors = Partial<Record<LeaveFormField, string>>

export type LeaveFormResult = { ok: true; input: LeaveInput } | { ok: false; errors: LeaveFormErrors }

export function parseLeaveForm(values: LeaveFormValues): LeaveFormResult {
  const parsed = leaveFormSchema.safeParse(values)
  if (parsed.success) {
    return { ok: true, input: parsed.data }
  }
  const errors: LeaveFormErrors = {}
  for (const issue of parsed.error.issues) {
    const field = issue.path[0]
    if ((field === 'startDate' || field === 'endDate') && !errors[field]) {
      errors[field] = issue.message
    }
  }
  return { ok: false, errors }
}

/** Champs d'erreur renvoyés par le serveur (400) → champs du formulaire (mêmes noms). */
export function serverFieldsToLeaveErrors(fields: Readonly<Record<string, string>>): LeaveFormErrors {
  const errors: LeaveFormErrors = {}
  for (const [name, message] of Object.entries(fields)) {
    if (name === 'startDate' || name === 'endDate') {
      errors[name] = message
    }
  }
  return errors
}
