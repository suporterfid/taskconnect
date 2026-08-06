# TaskConnect i18n backend completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Backend follows the authenticated user's saved locale; validation, hardcoded messages, and dead-run emails are localized; header locale switcher persists.

**Architecture:** New `SetLocaleFromUser` middleware in the `api` group reads `$request->user()->preferences->locale` and calls `App::setLocale()`. `lang/en` and `lang/pt-BR` hold published validation strings plus `messages.php`/`mail.php`. `FailureNotifier` sets locale per-admin before sending each email.

**Tech Stack:** Laravel 12, PHPUnit, Vue 3 + Vitest (frontend, one small change).

## Global Constraints
- Use only `./scripts/tc.sh` for composer/npm/artisan/test commands.
- Supported locales: `en`, `pt-BR` (matches `UserPreferencesController`'s existing validation).

---

### Task 1: `SetLocaleFromUser` middleware

**Files:**
- Create: `app/Http/Middleware/SetLocaleFromUser.php`
- Test: `tests/Feature/SetLocaleFromUserTest.php`
- Modify: `bootstrap/app.php` (append to `$middleware->api(...)`)

- [ ] Write failing test: authenticated user with `UserPreference.locale = 'pt-BR'` making a request that trips a validation error gets a `pt-BR` message for a Laravel-native rule (e.g. `required`). Build the user with `User::factory()->create()` + `UserPreference::factory()->create(['user_id' => $user->id, 'locale' => 'pt-BR'])`, act via `actingAs($user)`, hit any existing endpoint with a required field validated by Laravel's default rules, assert the 422 response's message contains the Portuguese wording (only after `lang/pt-BR/validation.php` exists — write this test after Task 2, or stub the expectation against a custom test route + `Validator::make(...)->validate()` calling `__('validation.required', ['attribute' => 'name'])` directly if no simpler existing endpoint qualifies. Prefer reusing an existing validated endpoint if one exists (check `UserPreferencesController`'s `locale` rule failure message).
- [ ] Implement middleware:
  ```php
  <?php

  namespace App\Http\Middleware;

  use Closure;
  use Illuminate\Http\Request;
  use Illuminate\Support\Facades\App;
  use Symfony\Component\HttpFoundation\Response;

  class SetLocaleFromUser
  {
      public function handle(Request $request, Closure $next): Response
      {
          $user = $request->user();
          $locale = $user?->preferences?->locale ?? config('app.locale');
          App::setLocale($locale);

          return $next($request);
      }
  }
  ```
- [ ] Append `\App\Http\Middleware\SetLocaleFromUser::class` to the `$middleware->api(prepend: [...])` call in `bootstrap/app.php` — actually append (after auth middleware resolves the user), not prepend; add a separate `$middleware->api(append: [...])` call if `statefulApi()`/prepend ordering requires it, so this runs after `AuthenticateApiKeyOrSanctum`.
- [ ] Run test, verify pass.
- [ ] Commit: `feat(i18n): set app locale from the authenticated user's preference`

### Task 2: `lang/` scaffolding

**Files:**
- Create: `lang/en/validation.php` (via `php artisan lang:publish`)
- Create: `lang/pt-BR/validation.php` (hand-translated from `lang/en/validation.php`)
- Create: `lang/en/messages.php`, `lang/pt-BR/messages.php` with keys: `account_reset_link_sent`, `webhook_url_not_allowed`

- [ ] Run `./scripts/tc.sh artisan lang:publish`, fix ownership if root-owned.
- [ ] Hand-translate `lang/en/validation.php` into `lang/pt-BR/validation.php` (same key structure, Portuguese strings).
- [ ] Create `lang/en/messages.php`:
  ```php
  <?php

  return [
      'account_reset_link_sent' => 'If an account exists for that email, a reset link has been sent.',
      'webhook_url_not_allowed' => 'Webhook URL is not allowed.',
  ];
  ```
- [ ] Create `lang/pt-BR/messages.php`:
  ```php
  <?php

  return [
      'account_reset_link_sent' => 'Se existir uma conta com esse e-mail, um link de redefinição foi enviado.',
      'webhook_url_not_allowed' => 'A URL do webhook não é permitida.',
  ];
  ```
- [ ] Commit: `feat(i18n): scaffold lang/ with validation and messages catalogs`

### Task 3: Localize hardcoded controller strings

**Files:**
- Modify: `app/Http/Controllers/Api/V1/Auth/ForgotPasswordController.php:23`
- Modify: `app/Http/Controllers/Api/V1/EnvironmentController.php:164`
- Test: extend or add to existing feature tests for both controllers, asserting `pt-BR`-preference actor gets the translated string.

- [ ] Write failing test(s) asserting Portuguese message for a `pt-BR` actor on both endpoints.
- [ ] Replace the literal strings with `__('messages.account_reset_link_sent')` and `__('messages.webhook_url_not_allowed')` respectively (keep the `EnvironmentController` fallback-to-exception-message branch as-is; only the hardcoded fallback string changes).
- [ ] Run tests, verify pass.
- [ ] Commit: `feat(i18n): localize ForgotPasswordController and EnvironmentController messages`

### Task 4: Localize `TaskRunFailedMail`

**Files:**
- Modify: `app/Application/Notifications/FailureNotifier.php` (`notifyEmail` method)
- Modify: `app/Mail/TaskRunFailedMail.php` (subject via `__()`)
- Modify: `resources/views/mail/task-run-failed.blade.php`, `resources/views/mail/task-run-failed-html.blade.php`
- Create: `lang/en/mail.php`, `lang/pt-BR/mail.php`
- Test: `tests/Feature/FailureNotifierLocaleTest.php` (new)

- [ ] Write failing test: two admins on the same tenant, one with `locale = 'en'`, one `pt-BR`; call `FailureNotifier::notifyDeadRun()` with `Mail::fake()`; assert the rendered mailable content for each recipient is in their own locale (render via `->assertSeeInHtml()`/rendering the mailable body directly per Laravel's `Mail::fake()` + `Mailable::assertSeeInHtml()` API, keyed by recipient email).
- [ ] Add `lang/en/mail.php` / `lang/pt-BR/mail.php` with keys: `task_run_failed_subject`, `task_run_failed_heading`, `task_run_failed_task_label`, `run_label`, `state_label`, `error_code_label`, `error_code_na`, `view_run_button`, `diagnostics_note`, `task_run_status_line` (with `{run}`/`{task}`/`{state}` placeholders for the plain-text version).
- [ ] In `FailureNotifier::notifyEmail()`, wrap the per-admin send: capture `$previousLocale = app()->getLocale()` once before the loop, set `App::setLocale($prefs->locale ?? 'en')` right before each `Mail::to(...)->send(...)`, and restore `App::setLocale($previousLocale)` after the loop.
- [ ] Update `TaskRunFailedMail::envelope()` to `subject: __('mail.task_run_failed_subject')`.
- [ ] Update both blade templates to use `__('mail.*')` keys in place of literal English text (keep the interpolated `$runId`/`$taskName`/`$state`/`$error`/`$runUrl` variables as-is).
- [ ] Run test, verify pass.
- [ ] Commit: `feat(i18n): localize TaskRunFailedMail per-recipient`

### Task 5: Persist header locale switcher

**Files:**
- Modify: `frontend/src/layouts/AppLayout.vue` (`onLocaleChange`)
- Test: `frontend/src/layouts/AppLayout.spec.ts` (new, or extend existing spec if one exists — check first)

- [ ] Write failing test: mount `AppLayout`, trigger the header locale `<select>` change, assert `api.patch` was called with `/me/preferences` and `{ locale: ... }`.
- [ ] Update `onLocaleChange` in `AppLayout.vue` to call the same PATCH the Settings page makes (extract a shared `persistLocale(locale)` helper into `frontend/src/stores/auth.ts` or `locale.ts` if that avoids duplicating the try/catch — reuse `SettingsPage.vue`'s existing `persistPreferences` shape as the reference implementation, keeping local-store update as the immediate optimistic change and the PATCH as fire-and-forget with a console warning on failure, matching jotter's `useLocale.ts` composable pattern).
- [ ] Run test, verify pass.
- [ ] Run full frontend test suite + `npm run build`.
- [ ] Commit: `feat(i18n): persist header locale switcher via PATCH /me/preferences`

### Final verification
- [ ] `./scripts/tc.sh artisan test` — full backend suite green.
- [ ] `./scripts/tc.sh npm test` — full frontend suite green.
- [ ] `./scripts/tc.sh npm run build` — clean.
- [ ] Invoke `superpowers:finishing-a-development-branch`.
