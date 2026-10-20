import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { LeaveStatusBadge } from './LeaveStatusBadge'

describe('LeaveStatusBadge', () => {
  it.each([
    ['pending', 'En attente'],
    ['accepted', 'Acceptée'],
    ['refused', 'Refusée'],
    ['withdrawn', 'Retirée'],
    ['cancelled', 'Annulée'],
  ] as const)('affiche l\'état %s en toutes lettres', (status, label) => {
    render(<LeaveStatusBadge status={status} />)
    expect(screen.getByText(label)).toBeInTheDocument()
  })
})
