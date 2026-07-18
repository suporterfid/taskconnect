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
  tenant_id: string
  name: string
  slug: string
}

export interface Task {
  id: string
  name: string
  description?: string
  status: 'active' | 'paused' | 'draft' | 'archived'
  created_at: string
  updated_at: string
}

export interface EndpointProfile {
  id: string
  name: string
  base_url: string
  created_at: string
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
  prefix: string
  created_at: string
  last_used_at?: string
}

export interface Member {
  id: string
  name: string
  email: string
  role: string
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
