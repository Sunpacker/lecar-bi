import React from 'react'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { LoginForm } from './login-form'

const mockPush = vi.fn()
const mockRefresh = vi.fn()

vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: mockPush,
    refresh: mockRefresh,
  }),
}))

describe('LoginForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    global.fetch = vi.fn()
  })

  it('renders input fields, submit button, and demo accounts', () => {
    render(<LoginForm />)

    expect(screen.getByLabelText(/рабочий email/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/пароль/i)).toBeInTheDocument()
    expect(screen.getByTestId('login-submit')).toBeInTheDocument()
    expect(screen.getByText('Elena Rostova')).toBeInTheDocument()
    expect(screen.getByText('Dmitry Smirnov')).toBeInTheDocument()
  })

  it('validates required fields with Zod schema on empty submission', async () => {
    render(<LoginForm />)

    fireEvent.click(screen.getByTestId('login-submit'))

    await waitFor(() => {
      expect(screen.getByText('Email обязателен для заполнения')).toBeInTheDocument()
      expect(screen.getByText('Пароль обязателен для заполнения')).toBeInTheDocument()
    })

    expect(global.fetch).not.toHaveBeenCalled()
  })

  it('validates email format with Zod schema', async () => {
    render(<LoginForm />)

    fireEvent.change(screen.getByLabelText(/рабочий email/i), {
      target: { value: 'invalid-email-format' },
    })
    fireEvent.change(screen.getByLabelText(/пароль/i), {
      target: { value: 'password123' },
    })

    fireEvent.click(screen.getByTestId('login-submit'))

    await waitFor(() => {
      expect(screen.getByText('Некорректный формат email')).toBeInTheDocument()
    })

    expect(global.fetch).not.toHaveBeenCalled()
  })

  it('submits form with user credentials and redirects on success', async () => {
    vi.mocked(global.fetch).mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        user: { id: 'user-1', email: 'elena@autobi.internal', name: 'Elena' },
      }),
    } as Response)

    render(<LoginForm />)

    fireEvent.change(screen.getByLabelText(/рабочий email/i), {
      target: { value: 'elena@autobi.internal' },
    })
    fireEvent.change(screen.getByLabelText(/пароль/i), {
      target: { value: 'password123' },
    })

    fireEvent.click(screen.getByTestId('login-submit'))

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledWith('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: 'elena@autobi.internal', password: 'password123' }),
      })
      expect(mockPush).toHaveBeenCalledWith('/')
      expect(mockRefresh).toHaveBeenCalled()
    })
  })

  it('triggers onSuccess callback if provided', async () => {
    const onSuccess = vi.fn()
    vi.mocked(global.fetch).mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        user: { id: 'user-1', email: 'elena@autobi.internal', name: 'Elena' },
      }),
    } as Response)

    render(<LoginForm onSuccess={onSuccess} />)

    fireEvent.change(screen.getByLabelText(/рабочий email/i), {
      target: { value: 'elena@autobi.internal' },
    })
    fireEvent.change(screen.getByLabelText(/пароль/i), {
      target: { value: 'password123' },
    })

    fireEvent.click(screen.getByTestId('login-submit'))

    await waitFor(() => {
      expect(onSuccess).toHaveBeenCalled()
      expect(mockPush).not.toHaveBeenCalled()
    })
  })

  it('displays error message when login fails', async () => {
    vi.mocked(global.fetch).mockResolvedValueOnce({
      ok: false,
      json: async () => ({ message: 'Неверный email или пароль' }),
    } as Response)

    render(<LoginForm />)

    fireEvent.change(screen.getByLabelText(/рабочий email/i), {
      target: { value: 'wrong@autobi.internal' },
    })
    fireEvent.change(screen.getByLabelText(/пароль/i), {
      target: { value: 'wrongpass' },
    })

    fireEvent.click(screen.getByTestId('login-submit'))

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Неверный email или пароль')
    })
  })

  it('performs quick login when clicking Elena Rostova demo button', async () => {
    vi.mocked(global.fetch).mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        user: { id: 'user-1', email: 'elena@autobi.internal', name: 'Elena' },
      }),
    } as Response)

    render(<LoginForm />)

    fireEvent.click(screen.getByText('Elena Rostova'))

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledWith('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: 'elena@autobi.internal', password: 'password123' }),
      })
    })
  })
})
