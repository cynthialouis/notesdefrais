import { z } from 'zod'

/** Types acceptés, comme côté serveur (Assert\File de ReceiptController). */
export const ACCEPTED_RECEIPT_TYPES = ['image/jpeg', 'image/png', 'application/pdf'] as const

/** 5 Mo, comptés comme le serveur : 5 000 000 octets. */
export const MAX_RECEIPT_BYTES = 5_000_000

/**
 * Justificatif choisi dans le formulaire (spec 005, C4) : type et taille vérifiés avant l'envoi.
 * Duplication volontaire listée dans la spec : le front se fie au type annoncé par le navigateur,
 * le serveur au contenu du fichier, et c'est lui qui fait foi.
 */
export const receiptSchema = z.object({
  file: z
    .instanceof(File, { message: 'Choisissez un fichier.' })
    .refine((file) => file.size > 0, 'Le fichier est vide.')
    .refine((file) => file.size <= MAX_RECEIPT_BYTES, 'Le fichier dépasse 5 Mo.')
    .refine((file) => ACCEPTED_RECEIPT_TYPES.some((type) => type === file.type), 'Formats acceptés : JPEG, PNG ou PDF.'),
})
