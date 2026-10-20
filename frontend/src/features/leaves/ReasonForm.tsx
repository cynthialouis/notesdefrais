import { useActionState } from 'react'
import type { z } from 'zod'
import { ApiError } from '../../api/client'
import { Button } from '../../ui/Button'
import { Field, Textarea } from '../../ui/Field'
import { rejectionSchema } from '../expenses/rejectionValidation'

type ReasonState = z.input<typeof rejectionSchema> & { error: string | null }

type Props = {
  label: string
  hint: string
  submitLabel: string
  /** Envoie le motif validé ; lève une ApiError si le serveur refuse. */
  onSubmit: (reason: string) => Promise<void>
  onClose: () => void
}

/**
 * Motif d'un refus ou d'une annulation (C9) : validé par rejectionSchema avant l'envoi, la même règle que
 * le rejet d'une note (research D3) ; l'erreur du serveur (400 ou 409) s'affiche sous le champ.
 */
export function ReasonForm({ label, hint, submitLabel, onSubmit, onClose }: Props) {
  const [state, action, isPending] = useActionState(
    async (_previous: ReasonState, formData: FormData): Promise<ReasonState> => {
      const raw = formData.get('reason')
      const reason = typeof raw === 'string' ? raw : ''
      const parsed = rejectionSchema.safeParse({ reason })
      if (!parsed.success) {
        return { reason, error: parsed.error.issues[0]?.message ?? 'Motif invalide.' } // C9 : rien n'est envoyé
      }
      try {
        await onSubmit(parsed.data.reason)
        return { reason: '', error: null }
      } catch (error) {
        return { reason, error: error instanceof ApiError ? (error.fields.reason ?? error.message) : 'Envoi impossible.' }
      }
    },
    { reason: '', error: null },
  )

  return (
    <form action={action} noValidate className="flex flex-col gap-3">
      <Field label={label} error={state.error ?? undefined} hint={hint}>
        {(control) => <Textarea {...control} name="reason" defaultValue={state.reason} autoFocus />}
      </Field>
      <div className="flex flex-wrap gap-3">
        <Button type="submit" variant="danger" pending={isPending}>
          {submitLabel}
        </Button>
        <Button disabled={isPending} onClick={onClose}>
          Abandonner
        </Button>
      </div>
    </form>
  )
}
