export interface ApiErrorBody {
  code: string
  message: string
  details?: Record<string, string[]> | unknown
  request_id?: string
}

/** Nested envelope returned by ApiErrorRenderer: `{ error: { code, message, details, request_id } }` */
export interface ApiErrorEnvelope {
  error: ApiErrorBody
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export interface UserPreferences {
  locale?: string
  timezone?: string
  failure_emails_enabled?: boolean
}

export interface AuditLogActor {
  id: string
  name: string
  email: string
}

export interface AuditLog {
  id: string
  action: string
  resource_type: string
  resource_id?: string | null
  request_id?: string | null
  summary?: Record<string, unknown> | null
  actor?: AuditLogActor | null
  created_at: string
}

export interface User {
  id: string
  name: string
  email: string
  is_platform_admin?: boolean
  preferences?: UserPreferences
}

export interface Tenant {
  id: string
  name: string
  slug: string
  outbound_allow_hosts?: string[]
  archived_at?: string | null
}

export interface Environment {
  id: string
  tenant_id?: string
  name: string
  slug: string
  archived_at?: string | null
  created_at?: string
  updated_at?: string
}

export interface EnvironmentPayload {
  name: string
  slug?: string
}

export type TaskDefinitionStatus =
  | 'draft'
  | 'active'
  | 'paused'
  | 'completed'
  | 'archived'

export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

export type ScheduleKind =
  | 'once'
  | 'every_n_minutes'
  | 'hourly_at'
  | 'daily_at'
  | 'weekly_on'
  | 'monthly_on_day'
  | 'business_days_at'

export interface ScheduleConfig {
  kind: ScheduleKind
  timezone: string
  at?: string
  interval_minutes?: number
  minute?: number
  time?: string
  /** ISO-8601 weekdays: 1=Mon … 7=Sun */
  weekdays?: number[]
  day?: number
  starts_at?: string
  ends_at?: string
}

export interface ScheduleHuman {
  kind: ScheduleKind | string
  parts: Record<string, string | number | Array<string | number>>
}

export interface RetryPolicy {
  max_attempts?: number
  strategy?: string
  success_status_ranges?: Array<[number, number]> | number[][]
  max_retry_window_seconds?: number | null
  honor_retry_after?: boolean
}

export interface Task {
  id: string
  name: string
  description?: string | null
  definition_status: TaskDefinitionStatus
  method: HttpMethod | string
  url_or_path?: string | null
  endpoint_profile_id?: string | null
  headers?: Record<string, string>
  query?: Record<string, string>
  body?: string | null
  content_type?: string | null
  timezone?: string | null
  retry_policy?: RetryPolicy | null
  next_run_at?: string | null
  last_run_at?: string | null
  last_run_state?: string | null
  schedule?: ScheduleConfig | null
  schedule_human?: ScheduleHuman | string | null
  created_at: string
  updated_at: string
}

export type AuthMode =
  | 'none'
  | 'static_header'
  | 'bearer'
  | 'basic'
  | 'query_token'

export interface EndpointProfile {
  id: string
  name: string
  description?: string | null
  base_url: string
  method: HttpMethod
  headers: Record<string, string>
  auth_mode: AuthMode
  auth_header_name?: string | null
  auth_query_param?: string | null
  secret_id?: string | null
  connect_timeout: number
  total_timeout: number
  follow_redirects: boolean
  verify_tls: boolean
  allowed_path_prefix?: string | null
  enabled: boolean
  archived_at?: string | null
  created_at: string
  updated_at?: string
}

export interface EndpointProfilePayload {
  name: string
  description?: string | null
  base_url: string
  method: HttpMethod
  headers?: Record<string, string>
  auth_mode: AuthMode
  auth_header_name?: string | null
  auth_query_param?: string | null
  secret_id?: string | null
  connect_timeout: number
  total_timeout: number
  follow_redirects: boolean
  verify_tls: boolean
  allowed_path_prefix?: string | null
  enabled: boolean
}

export interface EndpointTestResult {
  id: string
  request_url_redacted: string
  request_headers_redacted: Record<string, string> | null
  response_status: number | null
  response_body_truncated: string | null
  transport_error_code: string | null
  created_at: string
}

export interface Secret {
  id: string
  name: string
  version: number
  archived_at?: string | null
  created_at?: string
  updated_at?: string
  /** Present only on create/rotate responses — never on show/index. */
  plaintext?: string
}

/** Lightweight secret ref used by endpoint profile forms. */
export interface SecretSummary {
  id: string
  name: string
}

export type RunState =
  | 'pending'
  | 'running'
  | 'retry_wait'
  | 'succeeded'
  | 'dead'
  | 'cancelled'
  | 'blocked'

export type TriggerType = 'scheduled' | 'manual' | 'test' | 'retry' | string

export interface TaskRun {
  id: string
  task_id: string
  trigger_type: TriggerType
  scheduled_for?: string | null
  idempotency_key?: string | null
  run_state: RunState
  attempt_count: number
  next_attempt_at?: string | null
  started_at?: string | null
  finished_at?: string | null
  final_http_status?: number | null
  final_error_code?: string | null
  created_at: string
}

/** @deprecated Prefer TaskRun — kept as alias for existing imports. */
export type Run = TaskRun

export type AttemptState =
  | 'pending'
  | 'running'
  | 'succeeded'
  | 'failed_retryable'
  | 'failed_terminal'
  | 'timed_out'
  | 'interrupted'
  | 'blocked'

export interface TaskRunAttempt {
  id: string
  attempt_number: number
  attempt_state: AttemptState
  started_at?: string | null
  finished_at?: string | null
  duration_ms?: number | null
  request_url_redacted?: string | null
  request_headers_redacted?: Record<string, string> | null
  request_body_redacted?: string | null
  response_status?: number | null
  response_headers?: Record<string, string> | null
  response_body_truncated?: string | null
  transport_error_code?: string | null
  transport_error_message?: string | null
  next_retry_at?: string | null
}

export interface ApiKey {
  id: string
  name: string
  key_prefix: string
  permissions: string[]
  environment_id?: string | null
  last_used_at?: string | null
  expires_at?: string | null
  revoked_at?: string | null
  created_at: string
  plaintext?: string
}

export interface ApiKeyPayload {
  name: string
  permissions: string[]
  environment_id?: string | null
  expires_at?: string | null
}

export type TenantRole = 'tenant_admin' | 'tenant_member' | 'read_only_viewer'

export interface Member {
  id: string
  name: string
  email: string
  role: TenantRole | string
  created_at?: string
}

export interface MemberPayload {
  email?: string
  name?: string
  role: TenantRole
}

export interface UpcomingTask {
  id: string
  name: string
  next_run_at?: string | null
}

export interface DashboardRecentRun {
  id: string
  task_id?: string | null
  task_name?: string | null
  run_state: RunState | string
  finished_at?: string | null
  created_at?: string | null
}

export interface DashboardStats {
  active_tasks: number
  paused_tasks: number
  recent_runs: number
  failed_runs_24h: number
  retry_wait_runs: number
  dead_runs: number
  upcoming_tasks: UpcomingTask[]
  recent_run_items?: DashboardRecentRun[]
  oldest_due_at?: string | null
  scheduler_last_seen_at?: string | null
}

/** Flat payload from PlatformHealthController (not wrapped in `data`). */
export interface PlatformHealth {
  status: 'healthy' | 'degraded'
  database: 'ok' | 'error' | string
  scheduler_last_seen_at?: string | null
  retry_executor_last_seen_at?: string | null
  maintenance_last_seen_at?: string | null
  scheduler_stale?: boolean
  retry_executor_stale?: boolean
  stale_claims: number
  pending_runs: number
  version: string
  retention?: RetentionSettings | null
}

/** Platform retention defaults from GET /platform/retention (wrapped in `data`). */
export interface RetentionSettings {
  payload_snapshots_days: number
  attempt_metadata_days: number
  run_summary_days: number
  audit_logs_days: number
  api_idempotency_hours: number
  system_heartbeat_days: number
}
