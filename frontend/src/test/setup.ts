import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach } from 'vitest'

// Sans `globals: true`, Testing Library ne nettoie pas le DOM tout seul entre deux tests
afterEach(cleanup)
