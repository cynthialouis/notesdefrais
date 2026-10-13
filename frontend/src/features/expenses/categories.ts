import { z } from 'zod'
import { ApiError, apiFetch, type Schemas } from '../../api/client'

/** Catégorie et sa politique (spec 004), vérifiée à la réception : l'alerte de plafond en dépend. */
const categorySchema = z.object({
  slug: z.string(),
  label: z.string(),
  maxAmountCents: z.number().int().positive().nullable(),
  receiptRequired: z.boolean(),
})

export type Category = z.infer<typeof categorySchema>
export type CategoriesResult = { ok: true; categories: Category[] } | { ok: false; message: string }

// Contrôle à la compilation : le type déduit reste compatible avec le contrat généré
const matchesContract: (category: Category) => Schemas['CategoryView'] = (category) => category
void matchesContract

/** Catégories du serveur (C10). Lues par le seul formulaire : chargées localement, pas dans un store. */
export function loadCategories(): Promise<CategoriesResult> {
  return apiFetch<unknown>('/categories')
    .then((body): CategoriesResult => {
      const parsed = z.array(categorySchema).safeParse(body)
      return parsed.success ? { ok: true, categories: parsed.data } : { ok: false, message: 'Réponse inattendue du serveur.' }
    })
    .catch((error: unknown): CategoriesResult => ({
      ok: false,
      message: error instanceof ApiError ? error.message : 'Impossible de charger les catégories.',
    }))
}
