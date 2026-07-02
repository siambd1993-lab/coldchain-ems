import { Button } from './Button'
import { Modal } from './Modal'

interface ConfirmDialogProps {
  open:        boolean
  onClose:     () => void
  onConfirm:   () => void
  title?:      string
  message?:    string
  confirmLabel?: string
  danger?:     boolean
  loading?:    boolean
}

export function ConfirmDialog({
  open,
  onClose,
  onConfirm,
  title    = 'Are you sure?',
  message  = 'This action cannot be undone.',
  confirmLabel = 'Confirm',
  danger   = false,
  loading  = false,
}: ConfirmDialogProps) {
  return (
    <Modal open={open} onClose={onClose} title={title} size="sm">
      <p className="text-sm text-gray-600">{message}</p>
      <div className="mt-5 flex justify-end gap-2">
        <Button variant="outline" size="sm" onClick={onClose} disabled={loading}>
          Cancel
        </Button>
        <Button
          variant={danger ? 'danger' : 'primary'}
          size="sm"
          onClick={onConfirm}
          loading={loading}
        >
          {confirmLabel}
        </Button>
      </div>
    </Modal>
  )
}
