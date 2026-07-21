# MVP P2 Hardening Implementation Plan

> Execute task-by-task. Prefer TDD for behavior changes.

**Goal:** Reliability + API hardening + missing tests + license/changelog for post-P0/P1 MVP.

**Branch:** `cursor/mvp-p2-hardening-722a`

### Task 1: max_retry_window_seconds
- Modify `RetryDecider::shouldRetry` to accept optional run start + now
- Wire from `AttemptExecutor`
- Unit tests in `RetryDeciderTest`

### Task 2: Overlapping claim coverage
- Extend `SchedulerClaimingTest` for occurrence-key uniqueness under re-claim

### Task 3: Auth throttling
- Apply `throttle` middleware on auth routes in `routes/api.php`
- Ensure 429 renders via `ApiErrorRenderer`
- Feature test

### Task 4: Task-run pagination + task_id filter
- Update `TaskRunController::index`
- Feature coverage

### Task 5: Task lifecycle + password reset tests
- `TaskFeatureTest`, `PasswordResetTest`

### Task 6: LICENSE + CHANGELOG + plan status

### Task 7: Verify + PR
