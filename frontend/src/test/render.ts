import { act, render, type RenderResult } from '@testing-library/react'
import type { ReactNode } from 'react'

/**
 * Rend dans un act asynchrone. Avec le render synchrone de Testing Library, React 19 ne
 * reprend pas un composant suspendu par use() une fois la promesse résolue (constaté avec
 * React 19.3 et Testing Library 16.3) ; dans un act asynchrone, il le fait.
 */
export async function renderAsync(ui: ReactNode): Promise<RenderResult> {
  let result: RenderResult | undefined
  await act(async () => {
    result = render(ui)
  })
  if (!result) {
    throw new Error('Rendu impossible')
  }
  return result
}
