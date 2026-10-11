import { Suspense, use, useActionState, useState } from 'react'
import { Navigate, useNavigate } from 'react-router'
import { z } from 'zod'
import { ApiError, apiFetch, type Schemas } from '../../api/client'
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
    <main className="login">
      <title>Connexion – Notes de frais</title>
      <h1>Notes de frais</h1>
      <Suspense fallback={<p role="status">Chargement des comptes…</p>}>
        <LoginForm demoUsers={demoUsers} />
      </Suspense>
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
    return <p role="alert">{result.message}</p>
  }

  return (
    <form action={formAction}>
      <fieldset aria-describedby={state.error ? 'login-error' : undefined} aria-invalid={state.error ? true : undefined}>
        <legend>Choisissez un compte de démo</legend>
        {result.users.map((user) => (
          <label key={user.id} className="login-account">
            <input type="radio" name="userId" value={user.id} />
            {user.name} <span className="login-role">{roleLabels[user.role]}</span>
          </label>
        ))}
      </fieldset>
      {state.error && (
        <p id="login-error" role="alert">
          {state.error}
        </p>
      )}
      <button type="submit" disabled={isPending}>
        {isPending ? 'Connexion…' : 'Se connecter'}
      </button>
    </form>
  )
}
