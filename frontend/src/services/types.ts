export interface ApiErrorEnvelope {
  message: string
  code?: string
  errors?: Record<string, string[]>
  request_id?: string
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

export interface User {
  id: string
  name: string
  email: string
  is_platform_admin?: boolean
}

export interface Tenant {
  id: string
  name: string
  slug: string
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

export interface Task {
  id: string
  name: string
  description?: string
  status: 'active' | 'paused' | 'draft' | 'archived'
  created_at: string
  updated_at: string
}

export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

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

export interface SecretSummary {
  id: string
  name: string
}

export interface Run {
  id: string
  task_id: string
  status: 'pending' | 'running' | 'succeeded' | 'failed' | 'cancelled'
  triggered_at: string
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

export interface DashboardStats {
  active_tasks: number
  paused_tasks: number
  recent_runs: number
  failed_runs_24h: number
}

export interface PlatformHealth {
  status: 'ok' | 'degraded' | 'down'
  checks: Record<string, { status: string; message?: string }>
}
