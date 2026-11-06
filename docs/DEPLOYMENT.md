# Deployment notes — Local Guide 3.0

## Development vs production

`run-windows.bat`, `run-linux-macos.sh` and `php -S` are local development conveniences. Do not expose PHP's built-in development server directly to the public internet. Production should use a supported PHP web server (Apache/Nginx with PHP-FPM or a managed PHP runtime) with the document root set to `public/`.

## PHP

Required PHP version: 8.2+. Required extensions: `pdo_sqlite`, `sqlite3`, `fileinfo`. PHP 8.5.12 NTS x64 is supported. Check the loaded config with `php --ini`; check modules with `php -m`.

## Database and uploads

- Database default: `storage/metroguide.sqlite`.
- Uploads: `public/uploads/`.
- Both paths must be writable by the PHP runtime.
- Back up the database and uploads together. Store off-host backups and test a restore.
- On a Version 2 to 3 upgrade, copy the old database and uploads into a new Version 3 folder after making an independent backup. Do not overwrite the old application directory during the first upgrade.

## Environment

Copy `.env.example` to `.env` and set values for the target deployment. At minimum:

```dotenv
APP_URL=https://your-domain.example
APP_TIMEZONE=Asia/Kolkata
APP_DEBUG=false
DATABASE_PATH=storage/metroguide.sqlite
SEED_DEMO=false
```

Do not commit `.env`, live database files, private backups, API keys or customer exports.

## Replit

The included `.replit` runs PHP on port 3000 and serves the `public/` document root. `replit.nix` requests PDO SQLite, SQLite3 and fileinfo. Attach persistent storage for both the database and uploads; verify persistence across restart and redeployment. Configure `APP_URL` to the HTTPS application origin.

## External integrations

- **Maps:** Leaflet and OpenStreetMap are used by the interface. Select a map tile provider for production usage and follow its current attribution, caching, rate and commercial policies. Add geocoding and routing providers separately if you need address lookup or ETAs.
- **ChatGPT:** the app generates prompts but does not call an OpenAI API. Use a server-side integration only, keep API keys in environment variables, add budgets/quotas and obtain appropriate user consent.
- **Payments:** none are processed. A real payment integration must validate webhooks server-side and handle failed payments, refunds, reconciliation and invoices.
- **Email/SMS/push:** not configured. Use a transactional provider and explicit notification preferences/consent.
- **Spam:** add provider-backed CAPTCHA or managed rate limiting before accepting substantial anonymous traffic.

## Security checklist

- HTTPS, secure cookies, strong admin password and `APP_DEBUG=false`.
- Public document root must be `public/`; keep `app/`, `.env`, `storage/` and backups private.
- Confirm uploaded files cannot execute as PHP at the production server level.
- Review administrator access, owner claims and community moderation.
- Review privacy and terms with appropriate legal support.
- Monitor PHP/web server logs without exposing secrets or stack traces to visitors.
- Keep PHP, hosting dependencies and third-party assets current.
