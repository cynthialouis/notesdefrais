import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router'
import { describe, expect, it, vi } from 'vitest'
import { Button, ButtonLink } from './Button'

describe('Button', () => {
  it('est un bouton de type « button » par défaut (pas d\'envoi de formulaire involontaire)', () => {
    render(<Button>Annuler</Button>)
    expect(screen.getByRole('button', { name: 'Annuler' })).toHaveAttribute('type', 'button')
  })

  it('en attente : désactivé, marqué occupé et sans déclencher l\'action', async () => {
    const onClick = vi.fn()
    render(
      <Button pending onClick={onClick}>
        Enregistrer
      </Button>,
    )

    const button = screen.getByRole('button', { name: 'Enregistrer' })
    expect(button).toBeDisabled()
    expect(button).toHaveAttribute('aria-busy', 'true')
    await userEvent.click(button)
    expect(onClick).not.toHaveBeenCalled()
  })
})

describe('ButtonLink', () => {
  it('reste un lien de navigation', () => {
    render(
      <MemoryRouter>
        <ButtonLink to="/notes/nouvelle">Nouvelle note</ButtonLink>
      </MemoryRouter>,
    )
    expect(screen.getByRole('link', { name: 'Nouvelle note' })).toHaveAttribute('href', '/notes/nouvelle')
  })
})
