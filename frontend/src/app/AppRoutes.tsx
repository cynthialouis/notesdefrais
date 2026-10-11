import { Navigate, Route, Routes } from 'react-router'
import { PolicyPage } from '../features/policies/PolicyPage'
import { TeamPage } from '../features/approvals/TeamPage'
import { LoginPage } from '../features/auth/LoginPage'
import { RequireRole } from '../features/auth/RequireRole'
import { DashboardPage } from '../features/dashboard/DashboardPage'
import { ExpenseDetailRoute } from '../features/expenses/ExpenseDetailPage'
import { NewExpensePage } from '../features/expenses/NewExpensePage'
import { NotesPage } from '../features/expenses/NotesPage'
import { AppLayout } from './AppLayout'

export function AppRoutes() {
  return (
    <Routes>
      <Route path="login" element={<LoginPage />} />
      <Route element={<RequireRole />}>
        <Route element={<AppLayout />}>
          <Route index element={<DashboardPage />} />
          <Route element={<RequireRole role="employee" />}>
            <Route path="notes" element={<NotesPage />} />
            <Route path="notes/nouvelle" element={<NewExpensePage />} />
            <Route path="notes/:id" element={<ExpenseDetailRoute />} />
          </Route>
          <Route element={<RequireRole role="manager" />}>
            <Route path="equipe" element={<TeamPage />} />
          </Route>
          <Route element={<RequireRole role="admin" />}>
            <Route path="politique" element={<PolicyPage />} />
          </Route>
        </Route>
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
