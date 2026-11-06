# LocalGuide 5.0 — Windows installation and 4.0.2 migration

## Before you start

- Use Windows 10/11 (64-bit) and PHP 8.2+.
- Enable `pdo_sqlite`, `sqlite3`, `fileinfo` and `openssl` in the `php.ini` loaded by the PHP executable you will run.
- Stop the LocalGuide 4.0.2 server with `Ctrl+C` before copying its database.
- Keep the old 4.0.2 project directory as a rollback copy. Also make a separate backup of `storage\metroguide.sqlite`, `public\uploads\` and `.env`.

## 1. Extract into a new folder

Extract the ZIP into a new directory, for example:

`C:\Local_Guide_5_0` (or `C:\Users\<YourUsername>\Downloads\Local_Guide_5_0`)

Do not extract over 4.0.2. The extracted root must contain `app`, `public`, `storage`, `tools` and `run-windows.bat`.

## 2. Transfer your 4.0.2 data

Open CMD in the new 5.0 folder and run:

```cmd
upgrade-from-4.0.2.bat
```

Enter the full 4.0.2 project-root path when prompted. The helper copies the SQLite database, any SQLite `-wal` sidecar, uploaded files and `.env`. It does not delete or modify the source folder. It refuses to overwrite a database already present in the new folder. Keep the old project closed while the transfer runs.

The supplied source ZIP contained the application code but did not contain your personal SQLite database, `.env` or uploaded business media. These must come from your actual 4.0.2 installation.

## 3. Prepare secrets

If you migrated an existing `.env`, keep its administrator invite configuration and review `APP_URL` and `DATABASE_PATH`. If this is a new installation, create `.env` first:

```cmd
copy .env.example .env
```

Then generate the local encryption key used for TOTP two-factor authentication:

```cmd
setup-encryption-key.bat
```

Run this only once per installation. Do not lose or rotate `APP_ENCRYPTION_KEY` after users enable 2FA. Keep `.env` private and back it up securely.

## 4. Verify and start

```cmd
verify-upgrade.bat
run-windows.bat
```

Open the public website:

`http://127.0.0.1:8000/`

The first PHP connection to SQLite applies the additive 5.0 schema migration. Existing account IDs, listing IDs and existing application records are not intentionally dropped or renamed.

For the administrator portal, run `generate-admin-invite.bat` from the project root and use the private invitation URL it prints. The URL is a secret. Do not share it or post it publicly. Public/admin routes use the same local PHP server; the admin portal is protected by the invite gate and server-side administrator session.

## 5. Run the smoke tests

Open a second CMD window in the 5.0 project folder:

```cmd
test-suite.bat
```

The verifier checks the migrated schema, required extensions, SQLite integrity, foreign keys, preserved record counts and 5.0 version marker. The smoke suite checks the local helper logic and schema. Also manually review:

- existing public listings and uploaded images;
- sign-in for a member, owner and administrator;
- owner claims and existing owner listing access;
- reviews, bookings, enquiries, messages and offers;
- community posts, photos, profiles and saved collections;
- member-only exact-location visibility;
- invite-only administrator route and its 404 behaviour without an invitation.

## 6. Enable 2FA

Sign in, open **My account → Manage 2FA**, save the displayed base32 secret in an authenticator app, then enter a current six-digit code. 2FA is optional until a user enables it. The key in `.env` encrypts the secret at rest; losing that key can make already-enabled 2FA accounts unable to authenticate.

## 7. Backups

Run `backup-database.bat` before every upgrade. It checks the SQLite backup and keeps up to 30 local snapshots. `install-daily-backup-task.bat` optionally schedules a daily backup at 02:00; verify the task and its log after the first scheduled run. A local backup on the same disk is not a substitute for an encrypted off-device backup.

## 8. Integrations and feature boundaries

The application does not ship with live AI, routing, payments, email, CAPTCHA, WebSocket or push credentials. Admin → Integrations shows whether expected environment variables are present; it does not make a test API call. Review `LOCALGUIDE_5_0_FEATURE_STATUS.md` before enabling a provider. Payment checkout, signed webhooks, refunds, taxes, external AI generation and push delivery need provider-specific code, credentials and sandbox testing.

## Rollback

Stop the 5.0 server. Start the untouched 4.0.2 folder using its own launcher and restore the pre-upgrade 4.0.2 database backup if necessary. Do not point 4.0.2 at a database that has accepted new 5.0 writes. Keep the original 4.0.2 database and uploads unchanged until the 5.0 site has been reviewed.

## Troubleshooting

- `pdo_sqlite` or `sqlite3` missing: enable both extensions in the `php.ini` shown by `php --ini`.
- 2FA setup disabled: enable `openssl`, run `setup-encryption-key.bat`, and reload the account security page.
- No map markers: add correct latitude and longitude to business listings; the map does not geocode absent coordinates.
- Admin route returns 404: use the private tokenized invite URL generated by `generate-admin-invite.bat`.
- PHP port busy: stop the process currently using port 8000 before launching LocalGuide.
