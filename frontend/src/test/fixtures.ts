import type { CurrentUser } from '../features/auth/sessionStore'

export const employee: CurrentUser = { id: 4, name: 'Léa Bernard', email: 'lea@demo.test', role: 'employee', accessibleRoles: ['employee'] }
export const manager: CurrentUser = { id: 2, name: 'Claire Dubois', email: 'claire@demo.test', role: 'manager', accessibleRoles: ['manager', 'employee'] }
export const admin: CurrentUser = { id: 1, name: 'Alice Martin', email: 'alice@demo.test', role: 'admin', accessibleRoles: ['admin', 'manager', 'employee'] }

export function jsonResponse(status: number, body?: unknown): Response {
  return new Response(body === undefined ? null : JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
