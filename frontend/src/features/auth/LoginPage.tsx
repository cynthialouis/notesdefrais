import { Suspense, use, useActionState, useState } from 'react'
import { Navigate, useNavigate } from 'react-router'
import { z } from 'zod'
import { ApiError, apiFetch, type Schemas } from '../../api/client'
import { Alert } from '../../ui/Alert'
import { Badge } from '../../ui/Badge'
import { Button } from '../../ui/Button'
import { LoadingState } from '../../ui/Spinner'
import { roleLabels } from './roles'
import { useSessionStore } from './sessionStore'

type DemoUser = Schemas['DemoUserView']
type DemoUsersResult = { ok: true; users: DemoUser[] } | { ok: false; message: string }

const loginFormSchema = z.object({
  userId: z.coerce.number({ error: 'Choisissez un compte.' }).int().positive('Choisissez un compte.'),
})

type FormState = { error: string | null }

function loadDemoUsers(): Promise<DemoUsersResult> {
  return apiFetch<DemoUser[]>('/auth/demo-users')
    .then((users): DemoUsersResult => ({ ok: true, users }))
    .catch((error: unknown): DemoUsersResult => ({
      ok: false,
      message: error instanceof ApiError ? error.message : 'Impossible de charger les comptes.',
    }))
}

export function LoginPage() {
  const session = useSessionStore((state) => state.session)
  // Créée une fois par ce composant, qui ne se suspend pas : la promesse reste stable pour use()
  const [demoUsers] = useState(loadDemoUsers)

  if (session.status === 'authenticated') {
    return <Navigate to="/" replace /> // C13
  }

  return (
    <main className="flex min-h-svh items-center justify-center px-4 py-12">
      <title>Connexion – Notes de frais</title>
      <div className="w-full max-w-md">
        <h1 className="text-center text-2xl font-semibold tracking-tight text-gray-900">Notes de frais</h1>
        <p className="mt-2 text-center text-sm text-gray-600">Mode démo : connectez-vous sans mot de passe.</p>
        <div className="mt-8 rounded-lg border border-gray-200 bg-white p-6 shadow-xs">
          <Suspense fallback={<LoadingState label="Chargement des comptes…" />}>
            <LoginForm demoUsers={demoUsers} />
          </Suspense>
        </div>
      </div>
    </main>
  )
}

function LoginForm({ demoUsers }: { demoUsers: Promise<DemoUsersResult> }) {
  const result = use(demoUsers)
  const login = useSessionStore((state) => state.login)
  const navigate = useNavigate()

  const [state, formAction, isPending] = useActionState(async (_previous: FormState, formData: FormData): Promise<FormState> => {
    const parsed = loginFormSchema.safeParse({ userId: formData.get('userId') ?? undefined })
    if (!parsed.success) {
      return { error: parsed.error.issues[0]?.message ?? 'Choisissez un compte.' }
    }
    try {
      await login(parsed.data.userId)
    } catch (error) {
      return { error: error instanceof ApiError ? error.message : 'Connexion impossible.' }
    }
    await navigate('/') // C2
    return { error: null }
  }, { error: null })

  if (!result.ok) {
    return <Alert>{result.message}</Alert>
  }

  return (
    <form action={formAction} className="flex flex-col gap-5">
      <fieldset aria-describedby={state.error ? 'login-error' : undefined} aria-invalid={state.error ? true : undefined}>
        <legend className="mb-3 text-sm font-medium text-gray-900">Choisissez un compte de démo</legend>
        <div className="flex flex-col gap-2">
          {result.users.map((user) => (
            <label
              key={user.id}
              className="flex cursor-pointer items-center gap-3 rounded-md border border-gray-200 px-4 py-3 text-sm text-gray-900 hover:bg-gray-50 has-checked:border-brand-500 has-checked:bg-brand-50 has-focus-visible:ring-2 has-focus-visible:ring-brand-500"
            >
              <input type="radio" name="userId" value={user.id} className="size-4 accent-brand-600" />
              <span className="flex-1 font-medium">{user.name}</span> <Badge>{roleLabels[user.role]}</Badge>
            </label>
          ))}
        </div>
      </fieldset>
      {state.error && (
        <p id="login-error" role="alert" className="text-sm text-red-700">
          {state.error}
        </p>
      )}
      <Button type="submit" variant="primary" pending={isPending} className="w-full">
        {isPending ? 'Connexion…' : 'Se connecter'}
      </Button>
    </form>
  )
}
