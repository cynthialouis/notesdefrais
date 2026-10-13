import { z } from 'zod'
import { MAX_AMOUNT_CENTS, parseEurosToCents } from '../expenses/money'

/**
 * Saisie de la politique d'une catégorie : plafond en euros (vide = aucun plafond) et justificatif.
 * Mêmes règles que PolicyInput côté serveur (spec 004, C3), qui fait foi.
 */
export const policyFormSchema = z.object({
  maxAmount: z.string().transform((value, ctx): number | null => {
    const trimmed = value.trim()
    if (trimmed === '') {
      return null
    }
    const cents = parseEurosToCents(trimmed)
    if (cents === null) {
      ctx.addIssue({ code: 'custom', message: 'Montant invalide : chiffres et au plus deux décimales (ex. 50,00).' })
      return z.NEVER
    }
    if (cents <= 0) {
      ctx.addIssue({ code: 'custom', message: 'Le plafond doit être positif.' })
      return z.NEVER
    }
    if (cents > MAX_AMOUNT_CENTS) {
      ctx.addIssue({ code: 'custom', message: 'Le plafond ne peut pas dépasser 10 000,00 €.' })
      return z.NEVER
    }
    return cents
  }),
  receiptRequired: z.boolean(),
})

export type PolicyFormValues = z.input<typeof policyFormSchema>
