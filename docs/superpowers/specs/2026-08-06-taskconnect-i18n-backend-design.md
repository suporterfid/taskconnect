# TaskConnect i18n backend completion (design)

**Goal:** finish server-side i18n so the app locale follows the authenticated user's saved preference, backend-generated messages (validation, a few hardcoded strings, dead-run emails) are localized, and the header locale switcher persists like the Settings page one already does.

## Context

The frontend is already fully localized: vue-i18n is wired, `en`/`pt-BR` message catalogs exist, and every component uses `$t()`. `user_preferences.locale` already exists and is settable via `PATCH /me/preferences`. What's missing is entirely on the backend:

- No middleware sets Laravel's app locale from the authenticated user's preference, so validation errors and any `__()` calls always render in English regardless of the user's setting.
- No `lang/` directory exists (no published validation strings, no custom messages file).
- A handful of hardcoded English strings in `ForgotPasswordController` and `EnvironmentController`.
- `TaskRunFailedMail` (subject line + text/HTML templates) is hardcoded English, sent to tenant admins who may have `pt-BR` as their preference.
- `AppLayout.vue`'s header locale `<select>` only flips the Pinia store locally (matches `SettingsPage.vue`'s pattern before it also calls `persistPreferences`) — it doesn't persist, so it's lost on reload.

Unlike jotter, TaskConnect's human users authenticate with local email/password (Sanctum), not GrandpaSSOn SSO — GrandpaSSOn here is only inbound OAuth-token introspection for service-to-service API calls, which carries no interactive user session. So there is no GrandpaSSOn locale claim to consume for this app; the locale source of truth is purely TaskConnect's own `user_preferences.locale`.

## Design

1. **`SetLocaleFromUser` middleware** — appended to the `api` middleware group (after Sanctum/API-key auth resolves `$request->user()`). Reads `$request->user()?->preferences?->locale`, defaults to `'en'`, calls `App::setLocale()`. No-op for unauthenticated requests (guest defaults to `'en'`, matching current behavior).
2. **`lang/en` / `lang/pt-BR`** — publish Laravel's validation strings (`php artisan lang:publish`) for `en`, hand-translate to `pt-BR`; add a `messages.php` with the handful of hardcoded strings found (`account_reset_link_sent`, `webhook_url_not_allowed`).
3. **Localize the two hardcoded controller strings** via `__('messages.*')`.
4. **Localize `TaskRunFailedMail`** — `FailureNotifier::notifyEmail()` sets `App::setLocale($prefs->locale ?? 'en')` immediately before each `Mail::to(...)->send(...)` call (each admin may have a different locale), then restores the app's configured default locale afterward so later code in the same request isn't affected. Blade templates switch to `__('mail.*')` keys; add `lang/en/mail.php` / `lang/pt-BR/mail.php`.
5. **`AppLayout.vue` header switcher** — call the same `persistPreferences`-style `PATCH /me/preferences` request `SettingsPage.vue` already makes, instead of only updating the local store.

## Testing

- Feature test: authenticated user with `locale = 'pt-BR'` hitting an endpoint that fails validation gets Portuguese messages; unauthenticated/`en` user gets English.
- Feature test: `ForgotPasswordController` and `EnvironmentController` responses localized per the acting user's locale.
- Unit/feature test: `FailureNotifier` sends one admin's email in `pt-BR` and another's in `en` when they have different preferences (asserting on rendered Mailable content, using `Mail::fake()` + `assertQueued`/rendering the mailable directly).
- Frontend: `AppLayout.spec.ts` (or new test) asserting the header locale change triggers a `PATCH /me/preferences` call.

## Out of scope

- No GrandpaSSOn integration (doesn't apply here, see Context).
- No further Vue component conversion — frontend i18n is already complete.
