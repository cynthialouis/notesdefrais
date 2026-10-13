import { useActionState, useRef, useState, useTransition } from 'react'
import { ApiError } from '../../api/client'
import { Alert } from '../../ui/Alert'
import { Button } from '../../ui/Button'
import { buttonClasses } from '../../ui/buttonStyles'
import { Card } from '../../ui/Card'
import { Field, Input } from '../../ui/Field'
import { receiptUrl, useExpensesStore, type Expense } from './expensesStore'
import { formatFileSize } from './format'
import { ACCEPTED_RECEIPT_TYPES, receiptSchema } from './receiptValidation'

const typeLabels: Record<string, string> = { 'image/jpeg': 'Image JPEG', 'image/png': 'Image PNG', 'application/pdf': 'PDF' }

/** `fieldError` porte sur le fichier ; `message` sur le dépôt entier (droits, état, réseau). */
type UploadState = { fieldError: string | null; message: string | null }


/**
 * Justificatif d'une note (spec 005) : affiché et ouvrable par ceux qui lisent la note (C5) ;
 * joint, remplacé ou retiré par le propriétaire tant que la note est modifiable (C4, C6).
 * `onChange` reçoit la note renvoyée par le serveur après chaque écriture.
 */
export function ReceiptCard({ expense, onChange }: { expense: Expense; onChange: (expense: Expense) => void }) {
  const uploadReceipt = useExpensesStore((state) => state.uploadReceipt)
  const removeReceipt = useExpensesStore((state) => state.removeReceipt)
  const [isRemoving, startRemove] = useTransition()
  const [removeError, setRemoveError] = useState<string | null>(null)
  const { receipt } = expense
  // Fichier lu sur le champ plutôt que dans le FormData : même valeur dans un navigateur, et jsdom
  // ne reporte pas dans FormData les fichiers choisis par userEvent.upload
  const fileInput = useRef<HTMLInputElement>(null)

  const [state, formAction, isPending] = useActionState(
    async (): Promise<UploadState> => {
      const parsed = receiptSchema.safeParse({ file: fileInput.current?.files?.[0] })
      if (!parsed.success) {
        return { fieldError: parsed.error.issues[0]?.message ?? 'Fichier invalide.', message: null } // C4 : rien n'est envoyé
      }
      try {
        onChange(await uploadReceipt(expense.id, parsed.data.file))
        return { fieldError: null, message: null }
      } catch (error) {
        if (error instanceof ApiError && error.fields.file) {
          return { fieldError: error.fields.file, message: null }
        }
        return { fieldError: null, message: error instanceof ApiError ? error.message : 'Envoi impossible.' }
      }
    },
    { fieldError: null, message: null },
  )

  function handleRemove() {
    setRemoveError(null)
    startRemove(async () => {
      try {
        onChange(await removeReceipt(expense.id))
      } catch (error) {
        setRemoveError(error instanceof ApiError ? error.message : 'Retrait impossible.')
      }
    })
  }

  return (
    <Card title="Justificatif">
      {receipt ? (
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="min-w-0">
            <p className="truncate text-sm font-medium text-gray-900">{receipt.name}</p>
            <p className="text-xs text-gray-500">
              {typeLabels[receipt.mimeType] ?? receipt.mimeType} · {formatFileSize(receipt.sizeBytes)}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <a
              href={receiptUrl(expense.id)}
              target="_blank"
              rel="noopener noreferrer"
              aria-label={`Ouvrir le justificatif ${receipt.name} (nouvel onglet)`}
              className={buttonClasses('secondary')}
            >
              Ouvrir
            </a>
            {expense.editable && (
              <Button variant="danger" pending={isRemoving} disabled={isPending} onClick={handleRemove}>
                Retirer
              </Button>
            )}
          </div>
        </div>
      ) : (
        <p className="text-sm text-gray-600">
          Aucun justificatif.
          {expense.policy.receiptRequired && ` Il est obligatoire pour la catégorie ${expense.category.label}.`}
        </p>
      )}
      {removeError && <Alert className="mt-4">{removeError}</Alert>}
      {expense.editable && (
        <form action={formAction} noValidate className="mt-5 flex flex-wrap items-start gap-3 border-t border-gray-200 pt-5">
          <Field
            label={receipt ? 'Remplacer par un autre fichier' : 'Fichier'}
            hint="JPEG, PNG ou PDF, 5 Mo au plus."
            error={state.fieldError ?? undefined}
            className="min-w-0 flex-1"
          >
            {(control) => (
              <Input
                {...control}
                ref={fileInput}
                type="file"
                name="file"
                accept={ACCEPTED_RECEIPT_TYPES.join(',')}
                className="py-1.5 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200"
              />
            )}
          </Field>
          <Button type="submit" pending={isPending} disabled={isRemoving} className="sm:mt-6">
            {receipt ? 'Remplacer' : 'Joindre'}
          </Button>
          {state.message && <Alert className="w-full">{state.message}</Alert>}
        </form>
      )}
    </Card>
  )
}
