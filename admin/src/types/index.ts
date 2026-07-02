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
  role_ids?:          number[]
  permissions:        string[]
  is_platform_admin?: boolean
  two_factor_enabled: boolean
  locale:             string | null
  timezone:           string | null
  email_verified_at:  string | null
  last_login_at:      string | null
  created_at:         string
}

// ─── Team management ──────────────────────────────────────────────────────────

export interface Role {
  id:          number
  name:        string
  slug:        string
  description: string | null
  permissions: string[]
  is_system:   boolean
  users_count?: number
  created_at:  string | null
}

export interface PermissionGroup {
  module:      string
  label:       string
  permissions: { value: string; label: string }[]
}

export interface StoreUserPayload {
  name:        string
  email:       string
  phone?:      string
  password?:   string
  status?:     'active' | 'suspended'
  branch_id?:  number | null
  branch_ids?: number[]
  role_ids?:   number[]
}

// ─── IoT & energy ─────────────────────────────────────────────────────────────

export interface DeviceChannel {
  id:            number
  channel_key:   string
  metric:        string
  unit:          string | null
  label:         string | null
  min_threshold: number | null
  max_threshold: number | null
  last_value:    number | null
  last_value_at: string | null
  is_active:     boolean
}

export interface Device {
  id:               number
  device_uid:       string
  name:             string
  device_type:      string
  protocol:         string | null
  status:           'provisioning' | 'online' | 'offline' | 'fault' | 'decommissioned'
  branch_id:        number
  chamber_id:       number | null
  chamber?:         { id: number; name: string; code: string } | null
  model:            string | null
  manufacturer:     string | null
  firmware_version: string | null
  last_seen_at:     string | null
  channels?:        DeviceChannel[]
  created_at:       string | null
}

export interface AlertRow {
  id:              number
  alert_type:      string
  severity:        'info' | 'warning' | 'critical' | 'emergency'
  status:          'active' | 'acknowledged' | 'resolved' | 'suppressed'
  title:           string
  message:         string | null
  metric:          string | null
  threshold_value: number | null
  observed_value:  number | null
  chamber?:        { id: number; name: string } | null
  triggered_at:    string | null
  acknowledged_at: string | null
  resolved_at:     string | null
  resolution_note: string | null
}

export interface EnergyLive {
  mode:         'simulated' | 'live'
  timestamp:    string
  is_peak_hour: boolean
  solar_kw:     number
  load_kw:      number
  generator_kw: number
  /** positive = importing from grid, negative = exporting */
  grid_kw:      number
  battery: {
    soc_pct: number
    /** positive = discharging, negative = charging */
    kw: number
  }
  flows: { from: string; to: string; kw: number }[]
}

export interface EnergyInsight {
  type:                   string
  severity:               'info' | 'warning' | 'opportunity'
  title:                  string
  detail:                 string
  saving_poisha_monthly?: number
  cost_poisha_monthly?:   number
}

export interface EnergyInsights {
  generated_at: string
  engine:       string
  insights:     EnergyInsight[]
}

export interface EnergySummary {
  from:              string
  to:                string
  total_kwh:         number
  total_cost_poisha: number
  total_co2_kg:      number
  solar_share_pct:   number
  by_source:         { source: string; kwh: number; cost_poisha: number; co2_kg: number }[]
  series:            { date: string; grid: number; solar: number; generator: number; mixed: number }[]
}

// ─── Reports ──────────────────────────────────────────────────────────────────

export interface OccupancyRow {
  chamber_id:         number
  name:               string
  code:               string
  chamber_type:       string
  status:             string
  lots:               number
  quantity:           number
  weight_kg:          number
  capacity_weight_kg: number | null
  occupancy_pct:      number | null
}

export interface RevenueReport {
  from:                   string
  to:                     string
  total_billed_poisha:    number
  total_collected_poisha: number
  series: { date: string; billed_poisha: number; collected_poisha: number }[]
}

export interface ReceivablesReport {
  total_due_poisha: number
  aging: Record<'0_30' | '31_60' | '61_90' | '90_plus', number>
  customers: {
    customer_id: number
    code: string | null
    name: string | null
    phone: string | null
    due_poisha: number
    invoices: number
    oldest_days: number
  }[]
}

export interface StockReport {
  total_lots: number
  by_product: { product_id: number | null; code: string | null; name: string; unit: string; lots: number; quantity: number; weight_kg: number }[]
  expiring_soon: { lot_id: number; lot_code: string; product: string | null; quantity: number; unit: string; expiry_date: string; days_left: number }[]
  expiring_days: number
}

export interface AuditLogRow {
  id:          number
  action:      string
  description: string | null
  actor_type:  string
  actor_label: string | null
  subject:     string | null
  old:         Record<string, unknown> | null
  new:         Record<string, unknown> | null
  ip:          string | null
  created_at:  string | null
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

export interface StoreProductPayload {
  code:                string
  name:                string
  category?:           string
  unit_of_measure:     UnitOfMeasure
  default_temp_min_c?: number
  default_temp_max_c?: number
  shelf_life_days?:    number
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
