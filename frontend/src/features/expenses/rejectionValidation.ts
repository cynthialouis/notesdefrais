import { z } from 'zod'
import { unicodeTrim } from '../../lib/unicodeTrim'

/**
 * Motif de rejet : au moins 5 caractères hors espaces autour, au plus 1000, comptés en caractères
 * comme côté serveur (RejectionInput). Duplication volontaire listée dans la spec 003 (C5).
 */
export const rejectionSchema = z.object({
  reason: z
    .string()
    .transform(unicodeTrim)
    .pipe(
      z
        .string()
        .min(1, 'Le motif est obligatoire.')
        .refine((value) => [...value].length >= 5, 'Le motif doit faire au moins 5 caractères.')
        .refine((value) => [...value].length <= 1000, 'Le motif doit faire au plus 1000 caractères.'),
    ),
})
