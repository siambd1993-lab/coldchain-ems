import { useState, useCallback } from 'react'

interface ConfirmState {
  open:    boolean
  title?:  string
  message?: string
  danger?: boolean
  resolve?: (ok: boolean) => void
}

/**
 * Imperative confirm dialog hook.
 *
 * Usage:
 *   const { confirmProps, confirm } = useConfirm()
 *   // In JSX: <ConfirmDialog {...confirmProps} />
 *   // On action: if (await confirm({ title: 'Delete?' })) { ... }
 */
export function useConfirm() {
  const [state, setState] = useState<ConfirmState>({ open: false })

  const confirm = useCallback(
    (opts: { title?: string; message?: string; danger?: boolean } = {}) =>
      new Promise<boolean>((resolve) => {
        setState({ open: true, ...opts, resolve })
      }),
    [],
  )

  const handleClose = useCallback(() => {
    state.resolve?.(false)
    setState({ open: false })
  }, [state])

  const handleConfirm = useCallback(() => {
    state.resolve?.(true)
    setState({ open: false })
  }, [state])

  return {
    confirm,
    confirmProps: {
      open:      state.open,
      title:     state.title,
      message:   state.message,
      danger:    state.danger,
      onClose:   handleClose,
      onConfirm: handleConfirm,
    },
  }
}
