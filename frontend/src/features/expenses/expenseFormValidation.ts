import { z } from 'zod'
import type { ExpenseInput } from './expensesStore'
import { MAX_AMOUNT_CENTS, parseEurosToCents } from './money'

/**
 * Validation du formulaire de note, pour afficher les erreurs avant tout envoi (C4).
 * Duplication volontaire des règles du serveur (spec 002, « Règles recalculées côté front ») :
 * le serveur reste la source de vérité.
 */

/** Date du jour du navigateur au format AAAA-MM-JJ (le serveur compare à Europe/Paris). */
export function todayIso(now: Date = new Date()): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
}

function isRealDate(value: string): boolean {
  const [year = 0, month = 0, day = 0] = value.split('-').map(Number)
  const date = new Date(year, month - 1, day)
  return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day
}

const expenseFormSchema = z.object({
  // trim() retire les blancs Unicode, comme UnicodeTrim côté serveur ; la longueur se compte en
  // caractères (points de code) et non en unités UTF-16, comme Symfony (un emoji compte pour 1)
  label: z
    .string()
    .trim()
    .min(1, 'Le libellé est obligatoire.')
    .refine((value) => [...value].length >= 2, 'Le libellé doit faire au moins 2 caractères.')
    .refine((value) => [...value].length <= 80, 'Le libellé doit faire au plus 80 caractères.'),
  amount: z
    .string()
    .trim()
    .min(1, 'Le montant est obligatoire.')
    .transform((value, ctx) => {
      const cents = parseEurosToCents(value)
      if (cents === null) {
        ctx.addIssue({ code: 'custom', message: 'Montant invalide : chiffres et au plus deux décimales (ex. 12,50).' })
        return z.NEVER
      }
      return cents
    })
    .pipe(
      z
        .number()
        .int()
        .positive('Le montant doit être positif.')
        .max(MAX_AMOUNT_CENTS, 'Le montant ne peut pas dépasser 10 000,00 €.'),
    ),
  date: z
    .string()
    .min(1, 'La date est obligatoire.')
    .regex(/^\d{4}-\d{2}-\d{2}$/, 'Date invalide.')
    .refine(isRealDate, 'Date invalide.')
    .refine((value) => value <= todayIso(), 'La date ne peut pas être dans le futur.'),
  categorySlug: z.string().min(1, 'La catégorie est obligatoire.'),
})

/** Valeurs saisies (avant transformation) et champs du formulaire, déduits du schéma. */
export type ExpenseFormValues = z.input<typeof expenseFormSchema>
export type ExpenseFormField = keyof ExpenseFormValues
export type ExpenseFormErrors = Partial<Record<ExpenseFormField, string>>

export type ExpenseFormResult = { ok: true; input: ExpenseInput } | { ok: false; errors: ExpenseFormErrors }

export function parseExpenseForm(values: ExpenseFormValues): ExpenseFormResult {
  const parsed = expenseFormSchema.safeParse(values)
  if (parsed.success) {
    const { label, amount, date, categorySlug } = parsed.data
    return { ok: true, input: { label, amountCents: amount, date, categorySlug } }
  }
  const errors: ExpenseFormErrors = {}
  for (const issue of parsed.error.issues) {
    const field = issue.path[0]
    if ((field === 'label' || field === 'amount' || field === 'date' || field === 'categorySlug') && !errors[field]) {
      errors[field] = issue.message
    }
  }
  return { ok: false, errors }
}

/** Champs d'erreur renvoyés par le serveur (400) → champs du formulaire. */
export function serverFieldsToFormErrors(fields: Readonly<Record<string, string>>): ExpenseFormErrors {
  const mapping: Record<string, ExpenseFormField> = { label: 'label', amountCents: 'amount', date: 'date', categorySlug: 'categorySlug' }
  const errors: ExpenseFormErrors = {}
  for (const [name, message] of Object.entries(fields)) {
    const field = mapping[name]
    if (field) {
      errors[field] = message
    }
  }
  return errors
}
