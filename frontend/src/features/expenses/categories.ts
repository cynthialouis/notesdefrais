import { ApiError, apiFetch, type Schemas } from '../../api/client'

export type Category = Schemas['CategoryView']
export type CategoriesResult = { ok: true; categories: Category[] } | { ok: false; message: string }

/** Catégories du serveur (C10). Lues par le seul formulaire : chargées localement, pas dans un store. */
export function loadCategories(): Promise<CategoriesResult> {
  return apiFetch<Category[]>('/categories')
    .then((categories): CategoriesResult => ({ ok: true, categories }))
    .catch((error: unknown): CategoriesResult => ({
      ok: false,
      message: error instanceof ApiError ? error.message : 'Impossible de charger les catégories.',
    }))
}
