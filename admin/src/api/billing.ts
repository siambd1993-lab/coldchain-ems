import api from './client'
import type {
  Paginated,
  RatePlan,
  Invoice,
  InvoiceLine,
  Payment,
  StoreInvoicePayload,
  StoreLinePayload,
  StorePaymentPayload,
} from '@/types'

export interface InvoiceFilters {
  page?:       number
  per_page?:   number
  customer_id?: number
  status?:     string
  from?:       string
  to?:         string
  q?:          string
}

export interface PaymentFilters {
  page?:       number
  per_page?:   number
  customer_id?: number
  status?:     string
  method?:     string
  from?:       string
  to?:         string
}

export const billingApi = {
  // ── Rate plans ────────────────────────────────────────────────────────
  listRatePlans: (params: { page?: number; active?: boolean; q?: string } = {}) =>
    api.get<Paginated<RatePlan>>('/rate-plans', { params }).then((r) => r.data),

  createRatePlan: (payload: Record<string, unknown>) =>
    api.post<{ data: RatePlan }>('/rate-plans', payload).then((r) => r.data.data),

  updateRatePlan: (id: number, payload: Record<string, unknown>) =>
    api.put<{ data: RatePlan }>(`/rate-plans/${id}`, payload).then((r) => r.data.data),

  destroyRatePlan: (id: number) =>
    api.delete(`/rate-plans/${id}`),

  // ── Invoices ──────────────────────────────────────────────────────────
  listInvoices: (params: InvoiceFilters = {}) =>
    api.get<Paginated<Invoice>>('/invoices', { params }).then((r) => r.data),

  showInvoice: (id: number) =>
    api.get<{ data: Invoice }>(`/invoices/${id}`).then((r) => r.data.data),

  createInvoice: (payload: StoreInvoicePayload) =>
    api.post<{ data: Invoice }>('/invoices', payload).then((r) => r.data.data),

  updateInvoice: (id: number, payload: Partial<StoreInvoicePayload>) =>
    api.put<{ data: Invoice }>(`/invoices/${id}`, payload).then((r) => r.data.data),

  destroyInvoice: (id: number) =>
    api.delete(`/invoices/${id}`),

  issueInvoice: (id: number) =>
    api.post<{ data: Invoice }>(`/invoices/${id}/issue`).then((r) => r.data.data),

  voidInvoice: (id: number, voidReason: string) =>
    api
      .post<{ data: Invoice }>(`/invoices/${id}/void`, { void_reason: voidReason })
      .then((r) => r.data.data),

  // ── Invoice lines ─────────────────────────────────────────────────────
  addLine: (invoiceId: number, payload: StoreLinePayload) =>
    api
      .post<{ data: InvoiceLine }>(`/invoices/${invoiceId}/lines`, payload)
      .then((r) => r.data.data),

  updateLine: (invoiceId: number, lineId: number, payload: StoreLinePayload) =>
    api
      .put<{ data: InvoiceLine }>(`/invoices/${invoiceId}/lines/${lineId}`, payload)
      .then((r) => r.data.data),

  removeLine: (invoiceId: number, lineId: number) =>
    api.delete(`/invoices/${invoiceId}/lines/${lineId}`),

  // ── Payments ──────────────────────────────────────────────────────────
  listPayments: (params: PaymentFilters = {}) =>
    api.get<Paginated<Payment>>('/payments', { params }).then((r) => r.data),

  showPayment: (id: number) =>
    api.get<{ data: Payment }>(`/payments/${id}`).then((r) => r.data.data),

  createPayment: (payload: StorePaymentPayload) =>
    api.post<{ data: Payment }>('/payments', payload).then((r) => r.data.data),

  allocatePayment: (id: number, allocations: { invoice_id: number; amount_poisha: number }[]) =>
    api
      .post<{ data: Payment }>(`/payments/${id}/allocate`, { allocations })
      .then((r) => r.data.data),

  getRatePlan: (id: number) =>
    api.get<{ data: RatePlan }>(`/rate-plans/${id}`).then((r) => r.data.data),
}
