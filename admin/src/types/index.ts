// ─── Pagination ───────────────────────────────────────────────────────────────

export interface PaginationMeta {
  current_page: number
  last_page:    number
  per_page:     number
  total:        number
  from:         number | null
  to:           number | null
}

export interface PaginationLinks {
  first: string
  last:  string
  prev:  string | null
  next:  string | null
}

export interface Paginated<T> {
  data:  T[]
  meta:  PaginationMeta
  links: PaginationLinks
}

export interface ApiError {
  message?: string
  errors?:  Record<string, string[]>
  error?: {
    code:       string
    message:    string
    details:    Record<string, string[]> | null
    request_id: string
  }
}

// ─── Auth ─────────────────────────────────────────────────────────────────────

export interface User {
  id:                 number
  name:               string
  email:              string
  phone:              string | null
  status:             'invited' | 'active' | 'suspended'
  tenant_id:          number | null
  home_branch_id:     number | null
  branch_ids:         number[]
  roles:              string[]
  permissions:        string[]
  is_platform_admin?: boolean
  two_factor_enabled: boolean
  locale:             string | null
  timezone:           string | null
  email_verified_at:  string | null
  last_login_at:      string | null
  created_at:         string
}

export interface TokenBundle {
  access_token:  string
  token_type:    string
  expires_in:    number
  refresh_token: string
  user:          User
}

// ─── Tenant / Branch ──────────────────────────────────────────────────────────

export interface Tenant {
  id:       number
  name:     string
  slug:     string
  status:   string
  currency: string
  timezone: string
}

export type BranchStatus = 'active' | 'inactive' | 'under_maintenance'

export interface Branch {
  id:             number
  tenant_id:      number
  code:           string
  name:           string
  status:         BranchStatus
  address_line1:  string | null
  address_line2:  string | null
  city:           string | null
  district:       string | null
  division:       string | null
  postal_code:    string | null
  country:        string | null
  latitude:       number | null
  longitude:      number | null
  phone:          string | null
  email:          string | null
  timezone:       string | null
  chambers_count?: number
  created_at:     string
  updated_at:     string
}

// ─── Products ─────────────────────────────────────────────────────────────────

export type UnitOfMeasure = 'kg' | 'ton' | 'crate' | 'bag' | 'carton' | 'piece' | 'pallet'

export interface Product {
  id:                 number
  tenant_id:          number
  code:               string
  name:               string
  category:           string | null
  unit_of_measure:    UnitOfMeasure
  default_temp_min_c: number | null
  default_temp_max_c: number | null
  shelf_life_days:    number | null
  created_at:         string
}

// ─── Customers ────────────────────────────────────────────────────────────────

export interface Customer {
  id:                   number
  code:                 string
  /** "individual" | "business" */
  type:                 'individual' | 'business'
  name:                 string
  contact_person:       string | null
  email:                string | null
  phone:                string | null
  address_line1:        string | null
  address_line2:        string | null
  status:               'active' | 'inactive' | 'blocked'
  balance_poisha:       number
  credit_limit_poisha:  number
  city:                 string | null
  district:             string | null
  country:              string
  notes:                string | null
  created_at:           string
}

// ─── Chambers ─────────────────────────────────────────────────────────────────

export type ChamberType   = 'deep_freeze' | 'freezer' | 'chiller' | 'cold_room' | 'blast_freezer' | 'ripening' | 'ambient'
export type ChamberStatus = 'active' | 'maintenance' | 'offline' | 'defrost'

export interface ChamberCapacity {
  weight_kg:  number | null
  volume_m3:  number | null
  slots:      number | null
  area_sqft:  number | null
}

export interface ChamberTargetBand {
  temp_min_c:   number | null
  temp_max_c:   number | null
  humidity_min: number | null
  humidity_max: number | null
}

export interface ChamberCurrent {
  temp_c:      number | null
  humidity:    number | null
  observed_at: string | null
}

export interface Chamber {
  id:                  number
  branch_id:           number
  code:                string
  name:                string
  chamber_type:        ChamberType
  status:              ChamberStatus
  capacity:            ChamberCapacity
  target_band:         ChamberTargetBand
  current:             ChamberCurrent
  floor:               string | null
  zone:                string | null
  storage_units_count: number
  created_at:          string
}

// ─── Storage Units ────────────────────────────────────────────────────────────

export type UnitType   = 'rack' | 'shelf' | 'pallet_position' | 'bin' | 'floor_space' | 'room'
export type UnitStatus = 'available' | 'occupied' | 'reserved' | 'maintenance'

export interface StorageUnit {
  id:                   number
  chamber_id:           number
  branch_id:            number
  code:                 string
  label:                string | null
  unit_type:            UnitType
  status:               UnitStatus
  capacity_weight_kg:   number | null
  capacity_volume_m3:   number | null
  occupied_weight_kg:   number | null
  available_weight_kg:  number | null
  grid_row:             string | null
  grid_column:          string | null
  level:                string | null
  chamber?:             Chamber
  created_at:           string
  updated_at:           string
}

// ─── Inventory ────────────────────────────────────────────────────────────────

export type LotStatus    = 'in_storage' | 'partially_released' | 'released' | 'disposed'
export type MovementType = 'stock_in' | 'stock_out' | 'transfer' | 'adjustment'

export interface StockLot {
  id:                  number
  lot_code:            string
  status:              LotStatus
  unit_of_measure:     UnitOfMeasure
  /** Initial quantity as decimal string */
  initial_quantity:    string
  /** Current quantity as decimal string */
  quantity:            string
  weight_kg:           string | null
  received_at:         string | null
  expected_release_at: string | null
  released_at:         string | null
  description:         string | null
  chamber_id:          number | null
  storage_unit_id:     number | null
  customer:            Customer
  product:             Product | null
  created_at:          string
}

export interface StockMovement {
  id:            number
  lot_id:        number
  type:          MovementType
  quantity:      string
  balance_after: string | null
  occurred_at:   string
  reference:     string | null
  reason:        string | null
  performer:     Pick<User, 'id' | 'name'> | null
}

// ─── Rate Plans ───────────────────────────────────────────────────────────────

export type BillingMethod =
  | 'per_kg_per_day'
  | 'per_kg_per_month'
  | 'per_slot_per_day'
  | 'per_slot_per_month'
  | 'per_pallet_per_day'
  | 'per_pallet_per_month'
  | 'flat_monthly'

export interface RatePlan {
  id:                    number
  code:                  string
  name:                  string
  billing_method:        BillingMethod
  rate_poisha:           number
  minimum_charge_poisha: number
  grace_days:            number
  tax_rate:              string | number | null
  is_active:             boolean
  created_at:            string
}

// ─── Invoices ─────────────────────────────────────────────────────────────────

export type InvoiceStatus =
  | 'draft'
  | 'issued'
  | 'partially_paid'
  | 'paid'
  | 'overdue'
  | 'void'

export interface InvoicePeriod {
  start: string | null
  end:   string | null
}

export interface InvoiceLine {
  id:                number
  description:       string
  quantity:          string | number
  unit:              string | null
  unit_price_poisha: number
  discount_poisha:   number
  tax_rate:          string | number | null
  tax_poisha:        number
  amount_poisha:     number
}

export interface Invoice {
  id:                 number
  invoice_number:     string
  status:             InvoiceStatus
  issue_date:         string | null
  due_date:           string | null
  issued_at:          string | null
  period:             InvoicePeriod | null
  currency:           string
  subtotal_poisha:    number
  discount_poisha:    number
  tax_poisha:         number
  total_poisha:       number
  amount_paid_poisha: number
  amount_due_poisha:  number
  notes:              string | null
  customer:           Customer | null
  lines?:             InvoiceLine[]
  created_at:         string
}

// ─── Payments ─────────────────────────────────────────────────────────────────

export type PaymentMethod =
  | 'cash' | 'bkash' | 'nagad' | 'bank_transfer' | 'card' | 'cheque' | 'adjustment' | 'other' | string

export type PaymentStatus =
  | 'pending' | 'completed' | 'failed' | 'refunded' | 'cancelled'

export interface PaymentAllocation {
  id:            number
  invoice_id:    number
  amount_poisha: number
}

export interface Payment {
  id:                 number
  payment_number:     string
  method:             string
  status:             PaymentStatus
  currency:           string
  amount_poisha:      number
  allocated_poisha:   number
  unallocated_poisha: number
  reference:          string | null
  paid_at:            string | null
  notes:              string | null
  customer:           Customer | null
  allocations:        PaymentAllocation[]
  created_at:         string
}

// ─── Form payloads (sent TO the API) ─────────────────────────────────────────

export interface LoginPayload {
  email:    string
  password: string
  otp?:     string
}

export interface StoreCustomerPayload {
  code?:               string
  name:                string
  type:                'individual' | 'business'
  email?:              string
  phone?:              string
  address_line1?:      string
  city?:               string
  district?:           string
  country?:            string
  credit_limit_poisha?: number
  notes?:              string
}

export interface StoreBranchPayload {
  code:           string
  name:           string
  status?:        BranchStatus
  address_line1?: string
  address_line2?: string
  city?:          string
  district?:      string
  division?:      string
  postal_code?:   string
  country?:       string
  latitude?:      number
  longitude?:     number
  phone?:         string
  email?:         string
  timezone?:      string
}

export interface StoreStorageUnitPayload {
  chamber_id:          number
  code:                string
  label?:              string
  unit_type:           UnitType
  status?:             UnitStatus
  capacity_weight_kg?: number
  capacity_volume_m3?: number
  grid_row?:           string
  grid_column?:        string
  level?:              string
}

export interface IntakePayload {
  lot_code:            string
  customer_id:         number
  product_id?:         number
  chamber_id?:         number
  storage_unit_id?:    number
  unit_of_measure:     UnitOfMeasure
  quantity:            string | number
  weight_kg?:          number
  received_at:         string
  description?:        string
}

export interface ReleasePayload {
  quantity:   string | number
  reason?:    string
  reference?: string
}

/** Signed quantity: positive to add, negative to subtract */
export interface AdjustPayload {
  /** Signed correction: positive adds stock, negative subtracts */
  delta:      string | number
  reason:     string
  reference?: string
}

export interface StoreInvoicePayload {
  customer_id:  number
  issue_date?:  string
  due_date?:    string
  period_start?: string
  period_end?:  string
  notes?:       string
}

export interface StoreLinePayload {
  description:       string
  quantity:          string | number
  unit?:             string
  unit_price_poisha: number
  discount_poisha?:  number
  tax_rate?:         number
  lot_id?:           number
  rate_plan_id?:     number
}

export interface StorePaymentPayload {
  customer_id:   number
  amount_poisha: number
  method:        string
  paid_at:       string
  reference?:    string
  notes?:        string
}
