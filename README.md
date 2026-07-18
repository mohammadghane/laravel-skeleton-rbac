# ACL module — CodeIgniter 3 → Laravel 11

Drop-in replacement for your `acl` module (controllers/models/helpers/views).

## What changed and why

| Old (CI3) | New (Laravel 11) |
|---|---|
| `role` table, `Role` model (raw query builder) | `roles` table, `App\Models\Role` (Eloquent) |
| `role_perms` table (`role_id`, `privilege`, `resource`, `pattern`) | `permissions` table + `permission_role` pivot (same `resource`/`pattern` scoping preserved) |
| `user_role` table | `role_user` pivot (`belongsToMany`) |
| `user` table + ad-hoc columns | Laravel's `users` table extended via migration with the same columns (`gender`, `family`, `cell`, `national_code`, …) |
| boolean `delete` column | Eloquent `SoftDeletes` |
| `acl_helper.php` (`isAllow()`, `isAdmin()`, `hasRole()`) | `App\Concerns\HasRoles` trait on `User` (`$user->hasPermission()`, `->isAdmin()`, `->hasRole()`), plus a `permission` Gate and `@permission` Blade directive |
| Controller-level `isAllow('x')` guard at the top of every action | `permission:x` route middleware (`app/Http/Middleware/CheckPermission.php`) |
| `config/permisions.php` (module → perms → items tree) | `config/permissions.php` — **template only**, see below |
| Custom `gridview` library / `Template` library | Plain Blade views + Laravel's built-in paginator (`->paginate()`, `{{ $collection->links() }}`) |
| Manual validation blocks (unique username/email/cell/national_code, format checks) in `UserController::add()` | `App\Http\Requests\Admin\UserRequest` / `RoleRequest` |
| `Perms::savePerms()` diffing logic | `Role::syncPermissions()` — uses Eloquent's `sync()`, same end result, far less code |
| Ajax JSON envelopes (`title`/`body`/`footer`) for modals | Controllers return either a JSON success/error payload (writes) or a Blade partial (reads); `public/js/acl-admin.js` wires that up to a Tailwind modal (no JS framework dependency for the modal itself) |

## What you still need to supply

1. **`config/permissions.php` is a stub.** The original `application/config/permisions.php` wasn't in the
   uploaded zip — only permission keys referenced elsewhere in the code (`system-manager`, `admin/users`,
   `admin/products`, etc.) are guessed at. Replace the contents with your real module/permission tree; the
   shape (`module => ['title' => ..., 'perms' => ['group' => ['title' => ..., 'items' => [key => ['title' => ...]]]]]`)
   is unchanged from the old config file, so it should be close to a direct copy-paste.
2. **Province/city lookups.** `UserController::add()` referenced a `Province` model that's not part of this
   module. `resources/views/admin/users/form.blade.php` has a `@php` note where you need to feed in
   `$provinces` / `$cities` from wherever that data now lives.
3. **Cell/phone/national-code format rules.** The old `CellFilter`/`Filter` libraries did custom Iranian
   mobile-number normalization and regex validation that weren't included in the zip. `UserRequest` has
   placeholder regexes — tighten them to match your actual rules, and decide where the "strip leading 0"
   normalization now happens (a mutator/cast on `User::cell` is a good spot).
2. **`GENDER_MALE`/`GENDER_FEMALE` constants** — replace the `in:1,2` rule in `UserRequest` with a proper
   PHP enum if you want type safety.
3. **`center_id` / multi-tenant scoping** and **`agency_id` "sub-account" scoping** were preserved as plain
   foreign keys and `when()` query scopes, matching the old `isAllow('system-manager')` / `is_agency_user()`
   branches — wire `center_id` resolution (`$request->user()->center_id`) to however your app currently
   determines the "current center."

## Login / authentication / authorization

The old CI3 module had no login section of its own (it assumed a session/auth layer sitting
elsewhere and just read `getCurrentUserId()`). This adds one:

| File | Purpose |
|---|---|
| `resources/views/auth/login.blade.php` + `layouts/guest.blade.php` | Login form (username **or** email + password + remember-me), Persian/RTL, Tailwind |
| `app/Http/Requests/Auth/LoginRequest.php` | Validates input, resolves username-vs-email, calls `Auth::attempt()`, blocks soft-deleted accounts, rate-limits (5/min per identifier+IP) |
| `app/Http/Controllers/Auth/LoginController.php` | `create` (show form) / `store` (log in, regenerate session) / `destroy` (log out, invalidate session) |
| `routes/auth.php` | `GET/POST /login` behind `guest` middleware, `POST /logout` behind `auth` |
| `resources/views/layouts/admin.blade.php` | Wraps the roles/users views, shows nav items conditionally via `@permission(...)`, has the logout form |

Authorization (who can *do* what once logged in) reuses the ACL system from the previous
message unchanged: `permission:admin/users` route middleware, `$user->hasPermission()`,
`@permission`/`@endpermission` Blade directive, `system-manager` super-admin bypass via
`Gate::before`.

### Wiring

`bootstrap/app.php.snippet` shows the two edits needed in Laravel 11's `bootstrap/app.php`:
registering the `permission` middleware alias so `->middleware('permission:admin/users')`
resolves to `App\Http\Middleware\CheckPermission`.

In `routes/web.php`:

```php
require __DIR__.'/auth.php';
require __DIR__.'/acl.php';
require __DIR__.'/settings.php';
require __DIR__.'/locale.php';
```

### Assumptions to double check

- Login accepts **username or email** in one field (old `User` model had both columns with
  no indication which was canonical for login) — split into two fields if you'd rather be explicit.
- "Deactivated" account = soft-deleted row (old boolean `delete` column). If you actually have
  a separate active/inactive flag distinct from delete, add that check in `LoginRequest::authenticate()`.
- No "forgot password" / email verification flow included — say the word if you want Laravel's
  built-in `Illuminate\Auth\Notifications\ResetPassword` wired up too.

## Setup

```bash
php artisan migrate
php artisan db:seed
```

`db:seed` runs `DatabaseSeeder`, which in dependency order calls:

1. **`PermissionSeeder`** — one row per permission key in `config/permissions.php` (i.e. every "developed route").
2. **`RoleSeeder`** — creates the three default roles: `admin` (granted the `system-manager` bypass, so it
   implicitly has everything), `user` (baseline authenticated role, and the fallback used by
   `UserController::store()` when a non-admin creates an account), and `guest` (placeholder, no permissions
   yet - assign what a guest should see once you know).
3. **`AdminUserSeeder`** — creates a user with `username = admin`, `password = 123456`, and assigns it the
   `admin` role. **This password is a throwaway for local/staging bootstrap only — rotate it before
   anything touches production.**

All three seeders use `updateOrCreate`, so re-running `db:seed` is safe and won't create duplicates.
Re-run `php artisan db:seed --class=PermissionSeeder` on its own whenever you add new permission keys to
`config/permissions.php` for a new route.

Add the trait to your `User` model:

```php
use App\Concerns\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasRoles, SoftDeletes; // SoftDeletes is required by the migration in this package

    protected $fillable = [
        'name', 'family', 'gender', 'username', 'email', 'password',
        'cell', 'tell', 'national_code', 'addr', 'province', 'city', 'zip_code',
        'center_id', 'agency_id', 'is_agency', 'is_provider', 'setter', 'set_with', 'sana_code',
    ];
}
```

Register the provider (Laravel 11 `bootstrap/providers.php`):

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AclServiceProvider::class,
];
```

Register the `permission` middleware alias (Laravel 11 `bootstrap/app.php`):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'permission' => \App\Http\Middleware\CheckPermission::class,
    ]);
})
```

`auth` and `guest` aliases already ship with the Laravel 11 skeleton, so no extra wiring is needed for those.

Include the routes from `routes/web.php`:

```php
require __DIR__.'/auth.php';
require __DIR__.'/acl.php';
require __DIR__.'/settings.php';
require __DIR__.'/locale.php';
```

## Authentication

`routes/auth.php` adds:

- `GET /login`, `POST /login` — `App\Http\Controllers\Auth\LoginController` (guest-only)
- `POST /logout` — same controller (auth-only)

Login accepts either **username or email** in one field (`LoginRequest::authenticate()` detects which
via `filter_var(..., FILTER_VALIDATE_EMAIL)`), matches your old model's dual-identifier approach in
`UserController::isValid()`. It also adds things the CI3 code didn't have:

- **Rate limiting** — 5 failed attempts per minute, keyed by identifier + IP, with a Persian lockout message.
- **Soft-deleted account rejection** — a disabled/deleted user can't log in even with a correct password.
- **"Remember me"** — uses Laravel's built-in `remember_token` (already on the default `users` migration).

Because the `login` route is named `login`, Laravel's default `auth` middleware will automatically
redirect unauthenticated visitors there — no extra exception-handler config needed.

After login, users land on `admin.profile` (always resolvable regardless of permissions) — swap
`LoginController::store()`'s redirect target for a real dashboard route once you build one.

### Authorization recap

Once logged in, every request already goes through:

- `Gate::before` in `AclServiceProvider` — `system-manager` bypasses all permission checks.
- `permission:x` route middleware — gates entire route groups (`admin/roles`, `admin/users`, …).
- `$user->hasPermission('x')` / `@permission('x') ... @endpermission` — for anything finer-grained inside
  a controller or view (e.g. the "roles" multiselect only rendering for users who can assign roles).

`resources/views/layouts/admin.blade.php` ties it together: nav links only show for permissions the
logged-in user actually has, and a logout button posts to `route('logout')`.

## Permission checks — usage examples

```php
// In a controller/action:
abort_unless($request->user()->hasPermission('admin/products'), 403);

// Route middleware:
Route::get('/admin/orders', OrderController::class)->middleware('permission:admin/orders');

// Blade:
@permission('admin/users')
    <a href="...">مدیریت کاربران</a>
@endpermission

// Gate/Policy style, anywhere Laravel expects an ability:
Gate::authorize('permission', 'admin/orders');
```

`system-manager` still behaves as a super-admin bypass (`Gate::before` in `AclServiceProvider`), matching
the old `isAdmin()` shortcut used throughout the CI code.

## Tailwind dashboard layout

`resources/views/layouts/admin.blade.php` was rebuilt from Bootstrap to Tailwind. It's a responsive
app shell: fixed sidebar on desktop, slide-over drawer on mobile (Alpine-driven), sticky topbar with a
role-tier badge (colour-coded to which of admin/user/guest the logged-in user holds), and a shared modal
target used by every "add/edit/permissions" form in the roles and users screens.

- **Zero-build for now** — Tailwind and Alpine.js load from CDN (`<script src="cdn.tailwindcss.com">`),
  so there's nothing to compile to try this out. For production, install Tailwind properly via Vite
  (`npm i -D tailwindcss @tailwindcss/vite`) and move the `tailwind.config` block in the `<head>` into a
  real `tailwind.config.js` — the CDN build ships the whole utility set unminified and recompiles in the
  browser on every request, which isn't something you want in production.
- **Design tokens** (colors/fonts) live in the inline `tailwind.config` script — `ink` (near-navy, sidebar
  background), `pine` (primary actions), `gold` (admin-tier accent), `brick` (destructive actions), Vazirmatn
  for Persian type, JetBrains Mono for tabular/numeric data (IDs, phone numbers, timestamps).
- **RTL throughout** — `<html dir="rtl">`, so Tailwind's logical-property utilities (`ps-`, `pe-`, `text-right`
  as the reading direction) behave correctly without an RTL plugin.
- All of `admin/roles/*` and `admin/users/*` (index, table partials, forms, permission tabs) and
  `auth/login.blade.php` were restyled to match — nothing was left on Bootstrap classes with no Bootstrap
  CSS loaded to back them.
- `public/js/acl-admin.js` drives the modal open/close (`classList` toggling, no Bootstrap JS component)
  and the existing fetch-based create/update/delete flow is unchanged.
- **Form partials render standalone when hit directly.** `admin.roles.form`, `admin.users.form`, and
  `admin.roles.permissions` are fragments meant to be injected into `#admin-modal` via `fetch()` — they
  have no `@extends`. If you open one of their URLs directly (typed in the address bar, a bookmark, a
  link somewhere that isn't caught by `.open-modal`, or before `acl-admin.js` has loaded) you'd get raw
  unstyled HTML. `RoleController@edit/@permissions` and `UserController@edit/@profile` now check
  `$request->ajax()`: on a real AJAX fetch they return the bare partial as before; on a direct page load
  they wrap it in `admin.modal-page`, which extends the dashboard layout so it renders as a normal styled
  page instead.
- **Mobile sidebar visibility uses `x-show` + `lg:!flex`, not a dynamic transform class.** An earlier
  version toggled a `translate-x-full`/`translate-x-0` class dynamically per breakpoint - the Tailwind
  Play CDN's in-browser JIT wasn't reliably generating that combination, which either hid the sidebar on
  desktop or left it stuck open on mobile depending on the exact class shape. `x-show="sidebarOpen"` sets
  a plain inline `display:none`/`flex` (same mechanism the overlay backdrop and profile dropdown already
  use, which never had this problem), and `lg:!flex` forces it visible at desktop regardless of
  `sidebarOpen` — `!important` is the only thing that can override an inline style, which is exactly
  what's needed here since Alpine doesn't know about breakpoints. No animation on open/close currently;
  add `x-transition` back in if you want one, now that the underlying show/hide is solid.

## Settings module

Generic key-value settings engine, ported from the old `center/{controllers,models,views}/settings`
module. A single config file (`config/settings.php`) defines groups → items (text, textarea, checkbox,
select, from_to), and the whole form/save cycle is driven off that — adding a new setting is a config
edit, not a migration.

| Old (CI3) | New (Laravel 11) |
|---|---|
| `setting` table (`key`, `value`, `user_id`, `center_id`, `insert_at`) | `settings` table — same shape, `insert_at` → `created_at`/`updated_at`, unique index on `(key, center_id, user_id)` |
| `Settings` model (`fetch`/`fetchAll`/`save`/`delete`, raw query builder) | `App\Models\Setting` (Eloquent) + `App\Services\SettingsManager` (the actual fetch/save/diff logic — kept as a service rather than fattening the model) |
| `options['#type'] == 'function'` + `provider` method name, resolved via `$this->$func()` | A plain closure directly in `config/settings.php`'s `options` key — no magic method dispatch, see `shipping.post_cost_type` for the ported example |
| `SettingsController::settings()` (modal, current center) | `SettingsController::edit()` / `store()` — shows every group by default; same AJAX-vs-direct-navigation fallback as the roles/users forms |
| `SettingsController::centerSettings($center_id)` (full page, every group, managing another center) | `SettingsController::centerEdit()` / `centerStore()` — gated by `permission:admin/settings` |
| `item['privilage']` guard in the Blade loop | Same, checked server-side in `SettingsController::resolveGroups()` so a field a user can't see is never rendered *or* processed on save |
| "only write a row if the value actually changed from current/default" optimization | Preserved exactly in `SettingsManager::save()` — most settings stay at their config default and never touch the table |
| `key:remove` pseudo-field to delete an override | Preserved exactly |

**`config/settings.php` is a representative template** (the real one wasn't in the zip) — it demonstrates
every field type plus the closure-based dynamic-options pattern. Replace its contents with your actual
groups/items; the shape is unchanged from the old config file.

Routes live in `routes/settings.php` — add `require __DIR__.'/settings.php';` to `routes/web.php` alongside
the other two `require` lines.

## UI/UX pass: modal behavior, toasts, RTL/LTR, multi-language

Five related changes, all from the same follow-up:

1. **Settings opens as a full page, not a modal.** The sidebar link no longer has the `open-modal`
   class, so a normal browser navigation hits `SettingsController::edit()`'s non-AJAX branch, which
   already returned the full `admin.modal-page`-wrapped view (see the "AJAX-vs-direct-navigation
   fallback" note above) — it just wasn't being used because the link forced AJAX.
2. **The shared modal (`#admin-modal`) no longer closes on a backdrop click.** Only the explicit ✕
   button (`[data-modal-close]`) or the Escape key close it now — `public/js/acl-admin.js` no longer
   treats a click on the backdrop itself as a close trigger, so an accidental click outside the dialog
   can't discard unsaved input.
3. **Toast notifications replace the browser's native `alert()`** for every form-submit and delete
   error. `resources/views/partials/toast.blade.php` is a small Alpine component listening for a
   `window` `toast` CustomEvent; `acl-admin.js`'s `toast(message, type)` dispatches it. Non-2xx fetch
   responses are parsed properly now too — a 422 validation failure surfaces every field's message
   (joined), not just a generic "something went wrong."
4. **RTL/LTR is dynamic, not hardcoded.** `<html dir="...">` is computed per-request from
   `config('locales.rtl')` against the active locale, in both `layouts/admin.blade.php` and
   `layouts/guest.blade.php`. Layout-level Tailwind classes were switched from physical utilities
   (`right-0`, `text-right`, `left-0`) to logical ones (`end-0`, `text-start`, `text-end`) so they
   flip automatically with `dir`. The mobile sidebar doesn't need direction-aware handling here at
   all — see the note below on how it's shown/hidden.
5. **Multi-language content and validation errors**, via Laravel's locale JSON files
   (`resources/lang/en.json`) rather than PHP array keys — since almost everything was already
   hardcoded Persian text, using the Persian string itself as the translation key meant wrapping
   existing text in `__('...')` instead of rewriting every view around new key names. `fa` needs no
   JSON file: `__()` returns the key verbatim when no translation exists for the active locale, and
   the keys already are the correct Persian text.

### What's involved

| Piece | Purpose |
|---|---|
| `config/locales.php` | Single source of truth: `available` (code → label, drives the switcher, middleware, and route) and `rtl` (which locales render right-to-left) |
| `App\Http\Middleware\SetLocale` | Applies the locale stored in session to `app()->setLocale()` on every request |
| `routes/locale.php` (`GET /locale/{locale}`) | Sets the session value and redirects back |
| `resources/views/partials/locale-switcher.blade.php` | Dropdown in both layouts' header |
| `resources/lang/en.json` | Every Persian string in the app, mapped to English — includes UI text, validation messages, and the config-driven titles from `config/permissions.php`/`config/settings.php` |
| `resources/lang/fa/validation.php` | Persian translations for Laravel's *built-in* validation rules (`required`, `email`, `unique`, etc.) — Laravel only ships `en` out of the box |
| `resources/lang/fa/pagination.php` | Persian "Previous"/"Next" for `$paginator->links()` |
| `resources/views/partials/js-i18n.blade.php` | Injects a small `window.i18n` object so `acl-admin.js` (a static file, can't call `__()`) can show translated confirm/error text too |

### Wiring

Same pattern as the other route files — add to `routes/web.php`:
```php
require __DIR__.'/locale.php';
```

Register `SetLocale` in `bootstrap/app.php`'s `withMiddleware()` (see the updated
`bootstrap/app.php.snippet`):
```php
$middleware->web(append: [SetLocale::class]);
```

**Set the default locale.** Without this, a first-time visitor (no session value yet) sees whatever
`config('app.locale')` in your actual `config/app.php` is set to — which is `'en'` by default in a
fresh Laravel install. Since this app is Persian-first, set:
```php
// config/app.php
'locale' => 'fa',
'fallback_locale' => 'fa',
```

### Adding a new locale

Add it to `config/locales.php`'s `available` array (and `rtl` if it's right-to-left), then add a
`resources/lang/{code}.json` with translations for the keys in `resources/lang/en.json` — the switcher,
middleware, and route all pick it up automatically, nothing else to touch.

### Scope note

The layout shell (sidebar, topbar, modal, toast, login) is fully bidi-correct. The inner content
partials (roles/users tables and forms, settings form) were only spot-fixed for the two `text-right`
instances that existed — most of their layout already used direction-agnostic utilities (`flex`,
`justify-end`, `grid`), which are direction-aware by default in CSS and didn't need changing. If you
add new markup there with physical `mr-`/`ml-`/`pr-`/`pl-` utilities, prefer the logical
`ms-`/`me-`/`ps-`/`pe-` equivalents so it keeps working when someone switches to `en`.

## Audit log

Generic CRUD audit logging, applied via a trait rather than scattered controller code, so it
covers every form that exists today **and** any model added later with a one-line opt-in.

### How to make any model audited

```php
class Product extends Model
{
    use \App\Concerns\Auditable;
}
```

That's the entire integration cost. `create()`, `update()`, `delete()` — including via
`updateOrCreate()`, `fill()->save()`, mass assignment, whatever — are logged automatically with
a field-level before/after diff, visible at `/admin/audit-logs`. No controller changes needed.
Currently applied to `User`, `Role`, `Permission`, and `Setting`.

### What it captures

Each entry: who (`user_id`, nullable — `null` means an unauthenticated/console action, shown as
"(system)"), what model + which record (`auditable_type`/`auditable_id`, polymorphic — new models
need no migration), what happened (`action`: created/updated/deleted/restored), a human label for
the record (`label` — auto-guessed from `name`/`title`/`key`/`username`, override per-model with
`auditLabel()`), and the diff itself (`changes`, JSON: `{"email": {"old": "a@b.c", "new":
"aaa@bbb.ccc"}}` — this is the literal shape for the email-change example this feature was built
around).

- **Redaction, not exclusion, for sensitive fields.** `password` is masked (`••••••••`) rather
  than dropped entirely — so "the password was changed" is still auditable without ever storing
  or displaying the actual value. Add more via `protected array $auditRedact = [...]` on a model.
- **System/noise fields are excluded outright** — `remember_token`, `api_token`, and the
  timestamp columns, by default. Add more per-model with `protected array $auditExcept = [...]`.
- **Unchanged updates don't create noise.** If `getChanges()` comes back empty after excludes are
  applied (e.g. someone re-saved a form with no actual edits), nothing is written.

### What the trait can't see on its own

A `belongsToMany` `sync()` call (role/permission assignment) doesn't dirty-track the *owning*
model's attributes at all — Eloquent has nothing to diff automatically. Both places this happens
already have an explicit manual log call at the point `sync()` runs:

```php
AuditLog::record('updated', $role, [
    'permissions' => ['old' => $before, 'new' => $after],
]);
```

See `RoleController::savePermissions()` and `UserController::store()`. If you add another
many-to-many relationship somewhere, follow the same pattern — capture the "before" list right
before calling `sync()`, compare after, log only if it actually changed.

**A mass query-builder `delete()`/`update()` bypasses Eloquent model events entirely**, so a
`Model::where(...)->delete()` call silently isn't audited. `SettingsManager::delete()` was fixed
to iterate and call `->delete()` per row instead of a single mass delete, specifically so its
audit trail wouldn't have this gap — the same applies to any future service that deletes/updates
in bulk.

### Viewer

`/admin/audit-logs`, gated by the `admin/audit-logs` permission, filterable by model type, action,
and date range. Each row shows actor/action/subject at a glance; the field-level diff table
expands on click rather than always rendering, so a bulk-role-change entry with a dozen fields
doesn't dominate the list.

`config/audit.php` maps each `Auditable` model to a Persian display label for that filter dropdown
and the model-type column — `class_basename()` alone gives English names (`User`, `Role`), which
don't fit this app's translation convention of using Persian text as the `__()` key, so every
audited model needs one line there. Falls back to the raw class basename if you forget to add one.

## Not carried over

The `groups()` / `groupsGridView()` actions in the old `UserController` operated on a separate `group`
table that appears to be a *customer/product* grouping concept, unrelated to roles/permissions — it wasn't
part of this ACL refactor. Port it separately if you still need it.
