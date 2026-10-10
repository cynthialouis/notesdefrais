import { Suspense, use } from 'react'
import './App.css'

type HealthStatus = 'ok' | 'erreur'

// Promesse créée une seule fois, hors du rendu, pour être lue avec use()
const healthPromise: Promise<HealthStatus> = fetch('/api/health')
  .then((response) => (response.ok ? response.json() : null))
  .then((data: unknown): HealthStatus =>
    typeof data === 'object' && data !== null && 'ok' in data && data.ok === true ? 'ok' : 'erreur',
  )
  .catch((): HealthStatus => 'erreur')

function ApiStatus() {
  const status = use(healthPromise)
  return <p>API : {status === 'ok' ? 'connectée' : 'injoignable'}</p>
}

function App() {
  return (
    <section id="center">
      <h1>Notes de frais</h1>
      <Suspense fallback={<p>API : vérification…</p>}>
        <ApiStatus />
      </Suspense>
    </section>
  )
}

export default App
