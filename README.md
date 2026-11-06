# LocalGuide 5.0.0 — Windows-ready city discovery platform

LocalGuide is a PHP 8.2+ / SQLite local discovery, business marketplace and community application. This release upgrades the supplied 4.0.2 project additively and keeps its existing public routes, account roles, listing records, uploaded images, owner claim workflow and invite-only administrator portal.

## Important upgrade guarantee and safety

The ZIP does **not** contain your live database, uploads or `.env` secrets. The supplied package did not include a live `storage/metroguide.sqlite`; it contains the application source only. The 5.0 migration uses `CREATE TABLE IF NOT EXISTS`, additive `ALTER TABLE` checks and indexes; it does not intentionally drop or rename existing tables. Your old project is never modified by the data-transfer helper.

Before changing versions, make a separate copy of:

- `storage\metroguide.sqlite`
- the complete `public\uploads\` folder
- `.env` (if present), including `ADMIN_INVITE_TOKEN` and `APP_ENCRYPTION_KEY`
- any custom code or images you added

Do not extract over your active 4.0.2 installation. Keep that directory as the rollback copy.

## Requirements

- Windows 10/11, 64-bit
- PHP 8.2 or newer (PHP 8.4 CLI syntax checks passed here; PHP 8.5.12 NTS x64 is supported by the code requirements)
- PHP extensions: `pdo_sqlite`, `sqlite3`, `fileinfo`, `openssl`
- Write access to `storage\` and `public\uploads\`
- No Composer/npm packages are required for the PHP application

For production, use HTTPS, a supported web server, secure secret storage, scheduled off-site backups and a reviewed retention policy. PHP's built-in server is for local development only.

## Install or upgrade on Windows

1. Extract the ZIP into a **new** folder, for example `C:\Local_Guide_5_0` (or `C:\Users\<YourUsername>\Downloads\Local_Guide_5_0`). The folder should directly contain `app`, `public`, `storage`, `tools`, `run-windows.bat` and this README.
2. Stop the old server with `Ctrl+C` in its CMD window.
3. Open CMD and run the helper from the new 5.0 folder:

   ```cmd
   upgrade-from-4.0.2.bat
   ```

   Enter the full path of your 4.0.2 project root, e.g. `C:\Users\<YourUsername>\Downloads\Local_Guide_Website_Buildout_4_0_2`. This copies `storage\metroguide.sqlite`, any matching SQLite `-wal` sidecar, `public\uploads\` and `.env` into the new folder. Copying the WAL matters if SQLite has committed transactions not yet checkpointed. It does not delete or modify the old project. It refuses to overwrite a database already present in the new folder.
4. If this is a new install without an old `.env`, create it from the example:

   ```cmd
   copy .env.example .env
   ```

5. Keep `.env` private. Leave `APP_URL=` blank for local testing and confirm `DATABASE_PATH=storage/metroguide.sqlite`. Do not upload `.env` to GitHub.
6. Generate the application encryption key used by optional TOTP 2FA:

   ```cmd
   setup-encryption-key.bat
   ```

   Run this once per installation. **Do not lose or casually rotate this key** after users enable 2FA: their encrypted authenticator secrets require it. Keep a protected backup of `.env`.
7. Run the verification utility and launcher:

   ```cmd
   verify-upgrade.bat
   run-windows.bat
   ```

   The first PHP request initialises the 5.0 database schema. Existing 4.0.2 records remain in place; new tables and columns are added.
8. Open the public site at `http://127.0.0.1:8000/`.
9. For administrator access, run `generate-admin-invite.bat` from the project root and open the private tokenised URL it prints. It uses the existing administrator account and password. Do not share the invite URL. The public site still has no admin link; legacy `/admin/login.php` and `/setup.php` are 404.
10. Check Admin → System health, Admin → Integrations, listings, reviews, owner claims, community posts, booking requests, collections and uploaded images before retiring the old folder.

### Manual launch in CMD

```cmd
"%USERPROFILE%\Downloads\php-8.5.12-1-nts-Win32-vs17-x64\php.exe" -S 127.0.0.1:8000 -t public
```

The launcher also searches for PHP in PATH, common XAMPP locations and PHP NTS folders in Downloads.

## LocalGuide 5.0 features

- Premium forest/ivory/lime design layer, motion-safe depth effects, parallax and a draggable 3D-inspired globe illustration.
- Existing split-screen sign-in/register with smoke/vapor transition.
- Existing rule-based Guide AI, database search, browser voice search, member-tailored discovery, local trip plans and map handoff.
- Existing Leaflet map with OpenStreetMap attribution, marker clustering, member-gated exact location and up-to-four-place comparison.
- Existing business profiles, owner claims, enquiry CRM, booking/availability requests, quote requests, offers, coupons and events.
- New owner task board and offline business content-copy studio.
- Existing community feed, moderated photo submissions, contributor profiles, score-based contributor labels, guides, follows, comments and reactions.
- New TOTP two-factor authentication for member/owner/admin sign-in. It requires OpenSSL and `APP_ENCRYPTION_KEY`.
- New admin command center, security audit viewer, integration readiness dashboard and membership/subscription/payment ledger views.
- Existing API v1, metadata/sitemap, PWA manifest, service worker and offline fallback.
- Local verification suite and Windows migration/backup helpers.

For step-by-step CMD instructions, see `docs/WINDOWS_INSTALL_5_0.md`. Read `docs/LOCALGUIDE_5_0_FEATURE_STATUS.md` for the item-by-item 40-feature matrix. Several roadmap items are partial or credential-dependent; those boundaries are deliberate and are not described as live integrations.

## External integration status

No live provider credentials are embedded in this ZIP. The Admin → Integrations page checks environment variable presence only.

| Capability | Status in this release |
|---|---|
| Generative AI / AI review summaries | Local rule-based discovery and copy templates only. No provider request adapter is shipped; setting `OPENAI_API_KEY` alone does not enable AI responses or review summaries. |
| Maps / live routing | Leaflet + OpenStreetMap and Google Maps handoff. No managed geocoding/routing adapter is shipped; a key alone does not enable live route calculation or ETA. |
| Payments | Plan catalogue and local ledger only. No Razorpay/Stripe checkout adapter or webhook endpoint is shipped; credentials alone do not activate payments. |
| Email delivery | No email provider adapter, queue or delivery workflow is shipped. `RESEND_API_KEY` is a readiness marker only. |
| Web push | Static PWA app shell only. Push subscription registration and server-side delivery are not shipped; VAPID keys alone do not activate push. |
| CAPTCHA | No CAPTCHA adapter or server-side provider verification flow is shipped; keys alone do not activate it. |
| Real-time chat | Messages are stored in SQLite; no WebSocket or delivery guarantee is provided. |

Do not add a secret to the repository. Put credentials only in the private `.env` file or a production secret manager. The current integrations page is a readiness indicator, not a connector: adding credentials alone will not activate the services listed above. Each provider still needs implementation, account setup and sandbox/production verification.

## Two-factor authentication

1. Run `setup-encryption-key.bat` before enabling 2FA.
2. Sign in, open Account → Manage 2FA, and add the displayed base32 secret to an authenticator app.
3. Enter a current six-digit TOTP code to confirm. The secret is encrypted with AES-256-GCM before it is stored.
4. When signing in, enter the six-digit authenticator code alongside the username/email and password.
5. Keep the original `.env` encryption key backed up. If it is lost, enabled 2FA secrets cannot be decrypted by this application.

## Admin and owner workspaces

- Admin command center: `/admin/command-center.php` (requires a valid administrator session via private invite portal).
- Security audit: `/admin/security-audit.php`.
- Monetization: `/admin/monetization.php`.
- Integration readiness: `/admin/integrations.php`.
- Owner CRM task board: `/owner/tasks.php`.
- Owner content studio: `/owner/studio.php`.

Admin pages enforce the server-side administrator guard. The hidden route slug is not a substitute for authentication: the invite token, admin role check and authenticated session are still required.

## Backups and verification

- Run `backup-database.bat` before code updates. It uses SQLite's `VACUUM INTO`, runs `PRAGMA quick_check`, and retains the latest 30 snapshots in `storage\backups\`.
- Optionally install a daily Windows Task Scheduler job with `install-daily-backup-task.bat`; verify it in Task Scheduler and inspect `storage\scheduled-backup.log` after the first run.
- Run `verify-upgrade.bat` after migration.
- Run `test-suite.bat` to execute the local smoke suite.
- Keep at least one backup outside the computer running LocalGuide. Local retention is not a disaster-recovery strategy by itself.

## Rollback

1. Stop LocalGuide 5.0 with `Ctrl+C`.
2. Reopen the untouched 4.0.2 folder and start its `run-windows.bat`.
3. If you have continued to accept writes in 5.0, restore a 4.0-compatible copy of the database from before the upgrade. Do not use a 5.0-migrated database with 4.0.2: the 4.0 code may ignore extra tables but rollback must be tested against a copy first. The safest rollback is the unmodified 4.0.2 folder plus the pre-upgrade database backup.

## Troubleshooting

- **SQLite missing:** run the selected PHP executable with `-m` and enable `extension=pdo_sqlite`, `extension=sqlite3` and `extension=fileinfo` in the loaded `php.ini`.
- **2FA setup unavailable:** enable `extension=openssl`, run `setup-encryption-key.bat`, and reload the security page.
- **Database locked:** stop other PHP processes using the same SQLite file; do not copy or overwrite a live database file.
- **Map has no pins:** listings require valid latitude/longitude; the map cannot geocode missing data by itself.
- **Payment or AI not working:** no provider is bundled or activated. Review Admin → Integrations and the feature-status matrix.

## Project layout

- `app/` — bootstrap, shared functions and additive SQLite schema migrations
- `public/` — public site, API, invite-only admin, owner workspace and assets
- `storage/` — SQLite database and verified backups (excluded from the ZIP)
- `tools/` — backup, invite, credentials, migration verification and smoke-test utilities
- `docs/` — deployment, access-control, feature status and upgrade documentation
