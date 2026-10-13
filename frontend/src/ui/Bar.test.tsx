import { render } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { Bar } from './Bar'

function fill(container: HTMLElement): HTMLElement | null {
  return container.querySelector('[aria-hidden="true"] > div')
}

describe('Bar', () => {
  it('est décorative : masquée aux technologies d\'assistance', () => {
    const { container } = render(<Bar value={5} max={10} />)
    expect(container.firstElementChild).toHaveAttribute('aria-hidden', 'true')
  })

  it.each([
    [5, 10, '50%'],
    [10, 10, '100%'],
    [0, 10, '0%'],
    [0, 0, '0%'],
  ])('%i sur %i remplit %s', (value, max, width) => {
    const { container } = render(<Bar value={value} max={max} />)
    expect(fill(container)).toHaveStyle({ width })
  })
})
