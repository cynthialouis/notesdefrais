import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'
import { useSessionStore } from './features/auth/sessionStore'

// Lecture de la session une seule fois au démarrage, hors de React (pas de useEffect) : C3
void useSessionStore.getState().fetchMe()

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
