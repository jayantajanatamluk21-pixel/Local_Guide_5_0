# Local Guide 3.2 — Authentication and route access control

## Public guest

Guests may browse/search the directory, view public business profiles and read approved reviews. Exact street addresses, latitude/longitude, interactive maps, nearby-location processing and directions are reserved for authenticated accounts. Business profiles show only the neighbourhood/city label to guests.

## Registered user and business owner

Members and owners authenticate through `/auth.php`. The map route and the nearby search geolocation flow require a signed-in account. The directions endpoint checks the session server-side and only then sends the browser to the selected external map provider. After login or sign-up from a business directions control, the user returns to the original listing profile, where the map/directions control is unlocked.

## Administrator

There are no administrator links in public navigation, public footer or standard member auth screens. `/admin/login.php` and `/setup.php` always return 404. Protected `/admin/*.php` routes also return a generic 404 unless the session was authenticated through the invite portal and the account still exists, is active and has role `admin`.

The hidden route base is `/admin/hidden-portal-auth/`. The public site and admin portal use the same PHP server, but different URL paths. The base route returns 404 unless a sufficiently long `ADMIN_INVITE_TOKEN` exists in the root `.env` and matches the `invite` query parameter. A matching invitation is held in the server-side session for ten minutes, then the URL is redirected to remove the token from the address bar. The page sends `noindex`, `noarchive`, `no-store` and `no-referrer` headers.

### Generate an admin invitation (Windows)

1. Keep the local web server running.
2. Open a second CMD window and run `generate-admin-invite.bat` from the project root.
3. Copy the private URL printed in the console and open that exact tokenized URL in the browser.
4. Existing installation: sign in using the admin username/email and password.
5. Fresh installation: create the first administrator through that same invite-gated form.
6. Keep `.env` private. Run the generator again to rotate the token; previously shared URLs then stop working.

The token generator writes only `ADMIN_INVITE_TOKEN` to `.env` (creating the file if needed). It does not put the token in PHP source or the ZIP. When `APP_URL` is configured, it uses that value for the invitation link; otherwise it prints the local `http://127.0.0.1:8000` address.

### Guest/role checks

| Route / action | Guest | Member | Owner | Admin |
|---|---:|---:|---:|---:|
| Directory, public profile, approved reviews | Allow | Allow | Allow | Allow |
| Exact address/coordinates and map pins | Block | Allow | Allow | Allow |
| Directions | Sign-in gate | Allow | Allow | Allow |
| `/admin/*` management PHP routes | Generic 404 | Generic 404 | Generic 404 | Allow |
| Hidden admin portal without valid invite | Generic 404 | Generic 404 | Generic 404 | Dashboard redirect |
| Standard `/auth.php` sign-in | Member/owner only | Already signed in | Already signed in | Admin credentials rejected |

### Test steps

- Guest listing page source: no `streetAddress`, `GeoCoordinates`, raw exact address or coordinate pair.
- Guest direction click: auth redirect includes an internal `next`; successful login/signup returns to the listing profile.
- Guest `/map.php`: redirects to standard auth; authenticated member can load map data.
- Guest/member/owner direct `/admin/index.php`: generic 404; do not redirect to a discoverable admin login page.
- Guest `/admin/login.php`, `/setup.php` or hidden portal without token: generic 404.
- Valid invite token: redirect to clean hidden portal URL; portal can authenticate only an active admin or create the first admin if no admin exists.
- Check `robots.txt` disallows `/admin/`; no admin link appears in public header/footer/auth views.

`ADMIN_INVITE_TOKEN` is a second access gate, not a substitute for administrator credentials or deployment security. Use HTTPS in production, restrict environment file access, rotate invitations after use, use strong unique administrator passwords, and configure rate limiting and backups.
