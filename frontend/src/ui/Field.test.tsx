import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { Field, Input } from './Field'

describe('Field', () => {
  it('relie le libellé au contrôle', () => {
    render(<Field label="Libellé">{(control) => <Input {...control} />}</Field>)

    expect(screen.getByLabelText('Libellé')).toBeInTheDocument()
  })

  it('relie l\'aide et l\'erreur au contrôle et le marque invalide', () => {
    render(
      <Field label="Montant" hint="Ex. 12,50." error="Montant invalide.">
        {(control) => <Input {...control} />}
      </Field>,
    )

    const input = screen.getByLabelText('Montant')
    expect(input).toHaveAccessibleDescription('Ex. 12,50. Montant invalide.')
    expect(input).toHaveAttribute('aria-invalid', 'true')
  })

  it('sans erreur, le contrôle n\'est ni invalide ni décrit', () => {
    render(<Field label="Date">{(control) => <Input {...control} />}</Field>)

    const input = screen.getByLabelText('Date')
    expect(input).not.toHaveAttribute('aria-invalid')
    expect(input).not.toHaveAttribute('aria-describedby')
  })
})
