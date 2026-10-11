import { create } from 'zustand'
import { ApiError, apiFetch, type Schemas } from '../../api/client'

export type CurrentUser = Schemas['UserView']
export type Role = Schemas['UserRole']

/**
 * État de la session, en union discriminée : un utilisateur n'existe que si la session est ouverte.
 * Il vit dans un champ dédié et il est remplacé en entier à chaque changement : la fusion de
 * Zustand ne peut donc pas laisser un `user` périmé sur une session anonyme.
 */
export type Session =
  | { status: 'loading' }
  | { status: 'anonymous' }
  | { status: 'authenticated'; user: CurrentUser }

type SessionStore = {
  session: Session
  /** Lit la session côté serveur ; appelé une fois au démarrage de l'application. */
  fetchMe: () => Promise<void>
  /** Ouvre (ou remplace) la session. Lève une ApiError si la connexion est refusée. */
  login: (userId: number) => Promise<void>
  logout: () => Promise<void>
}

export const useSessionStore = create<SessionStore>()((set) => ({
  session: { status: 'loading' },

  fetchMe: async () => {
    try {
      const user = await apiFetch<CurrentUser>('/me')
      set({ session: { status: 'authenticated', user } })
    } catch {
      set({ session: { status: 'anonymous' } })
    }
  },

  login: async (userId) => {
    const user = await apiFetch<CurrentUser>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ userId }),
    })
    set({ session: { status: 'authenticated', user } })
  },

  logout: async () => {
    try {
      await apiFetch<void>('/auth/logout', { method: 'POST' })
    } catch (error) {
      // 401 : la session était déjà fermée côté serveur, l'état local doit suivre
      if (!(error instanceof ApiError && error.status === 401)) {
        throw error
      }
    }
    set({ session: { status: 'anonymous' } })
  },
}))

/** Vrai si la session donne accès au rôle demandé (la hiérarchie est calculée par le serveur). */
export function canAccess(session: Session, role: Role): boolean {
  return session.status === 'authenticated' && session.user.accessibleRoles.includes(role)
}
