# LocalGuide API v1

All endpoints are same-origin and return JSON. No public endpoint returns precise street addresses or coordinates.

- `GET /api/v1/health.php` — status and platform version.
- `GET /api/v1/listings.php?q=&city=&category=&page=1&limit=20` — public listing search (maximum 40 per page).
- `GET /api/v1/map.php?category=` — member-only precise map data. Requires a signed-in member session; it is never cached.

The endpoints use prepared SQL statements and a basic per-visitor request limit. For public production APIs, add a reverse-proxy/WAF rate limit, request monitoring and version lifecycle policy. Do not enable permissive CORS without a specific application requirement.
