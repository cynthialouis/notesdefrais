import { useActionState } from 'react'
import { ApiError } from '../../api/client'
import { Alert } from '../../ui/Alert'
import { Button } from '../../ui/Button'
import { Field, Input } from '../../ui/Field'
import { todayIso } from '../expenses/expenseFormValidation'
import { parseLeaveForm, serverFieldsToLeaveErrors, type LeaveFormErrors, type LeaveFormField, type LeaveFormValues } from './leaveFormValidation'
import { useLeavesStore } from './leavesStore'

type FormState = { values: LeaveFormValues; errors: LeaveFormErrors; message: string | null; sent: boolean }

const empty: LeaveFormValues = { startDate: '', endDate: '' }

function readValues(formData: FormData): LeaveFormValues {
  const read = (name: LeaveFormField) => {
    const value = formData.get(name)
    return typeof value === 'string' ? value : ''
  }
  return { startDate: read('startDate'), endDate: read('endDate') }
}

/** Demande d'une période de congé (C1) : erreurs de dates avant l'envoi (C3), refus du serveur affichés (C2, C4, C5). */
export function LeaveRequestForm() {
  const request = useLeavesStore((state) => state.request)

  const [state, formAction, isPending] = useActionState(
    async (_previous: FormState, formData: FormData): Promise<FormState> => {
      const values = readValues(formData)
      const parsed = parseLeaveForm(values)
      if (!parsed.ok) {
        return { values, errors: parsed.errors, message: null, sent: false } // C3 : rien n'est envoyé
      }
      try {
        await request(parsed.input)
        return { values: empty, errors: {}, message: null, sent: true }
      } catch (error) {
        if (error instanceof ApiError && Object.keys(error.fields).length > 0) {
          return { values, errors: serverFieldsToLeaveErrors(error.fields), message: null, sent: false }
        }
        return { values, errors: {}, message: error instanceof ApiError ? error.message : 'Envoi impossible.', sent: false }
      }
    },
    { values: empty, errors: {}, message: null, sent: false },
  )

  const { values, errors } = state

  // Les valeurs saisies sont reprises en defaultValue : le formulaire est réinitialisé après chaque action
  return (
    <form action={formAction} className="flex flex-col gap-5" noValidate>
      <div className="grid gap-5 sm:grid-cols-2">
        <Field label="Premier jour" error={errors.startDate}>
          {(control) => <Input {...control} name="startDate" type="date" min={todayIso()} defaultValue={values.startDate} />}
        </Field>
        <Field label="Dernier jour" error={errors.endDate} hint="Inclus. Le même jour pour un congé d'une journée.">
          {(control) => <Input {...control} name="endDate" type="date" min={todayIso()} defaultValue={values.endDate} />}
        </Field>
      </div>
      {state.message && <Alert>{state.message}</Alert>}
      {state.sent && <Alert tone="info">Demande envoyée : elle attend la décision de votre responsable.</Alert>}
      <div className="flex justify-end border-t border-gray-200 pt-5">
        <Button type="submit" variant="primary" pending={isPending}>
          {isPending ? 'Envoi…' : 'Demander ce congé'}
        </Button>
      </div>
    </form>
  )
}
