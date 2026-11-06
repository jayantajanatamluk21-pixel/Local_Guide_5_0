# LocalGuide 4.0 — Feature matrix

This matrix describes the implementation actually shipped in this ZIP. External service requirements are called out rather than presented as enabled features.

## Experience and motion

| Feature | Included implementation | Boundary |
|---|---|---|
| Premium visual system | Forest/sage/ivory/lime design tokens and new homepage | Some legacy feature pages retain portions of the 3.x layout |
| Cinematic hero | Large photography, image drift, floating cards and editorial type | Uses remote image sources until owner supplies licensed local media |
| Navigation transitions | Responsive nav, active underline and mobile quick-action dock | Not a full SPA/shared-element router |
| Scroll reveals | IntersectionObserver reveal and stagger timing | Falls back to immediately visible content when unsupported/reduced-motion |
| Hover depth | Small desktop pointer tilt for cards | Disabled on touch and reduced-motion devices |
| Animated map routes | Multi-stop route handoff and map marker effects | Route drawing/ETA delegated to map provider; app does not compute live routing |
| Skeleton/micro-states | Motion classes, loading states, toast/copy interactions and focus styling | Not every legacy form has a skeleton placeholder |
| Light/dark theme | User-selectable theme persisted in localStorage; admin/owner theme button | Theme preference is browser-local, not synchronized between devices |
| Reduced motion | CSS media query turns off nonessential effects | Browsers should still be tested with platform accessibility settings |

## Discovery and maps

| Feature | Included implementation | Boundary |
|---|---|---|
| AI-assisted search | Rule-based Guide AI category/keyword search against published listings | Not an LLM or semantic-vector search |
| Voice search | Browser Speech Recognition where supported | Requires compatible browser and microphone permission |
| Advanced map | Leaflet and marker clustering | Needs coordinates and online tile provider |
| Map/list split view | Map canvas plus searchable directory results panel | Not a 3D map |
| Multi-stop planner | User-owned trip plans, reorder controls and Google Maps handoff | Requires two or more coordinates; Google Maps supplies route and traffic data |
| Business comparison | Compare up to four listings | Uses currently populated directory fields |
| Personalized feed | Saved categories, member interests and home city influence suggestions | Simple deterministic recommendations, not machine learning |
| Nearby and hidden gems | Radius search and low-view heuristic | Geolocation consent required; low views do not indicate quality |

## Marketplace and business

| Feature | Included implementation | Boundary |
|---|---|---|
| Business profiles | Listing detail, photos, contact information, reviews, services and map controls | Businesses must keep their data accurate |
| Bookings | Date/time/capacity request workflow and owner confirmation | Not instant confirmed booking/payment settlement |
| Service catalogues | Owner-managed products/services and indicative pricing | Owner responsible for price accuracy |
| Owner CRM | Lead/enquiry stages, notes and follow-up fields | No automatic outbound reminders |
| Availability | Owner-managed capacity slots | Does not synchronize third-party calendars |
| Coupons/promotions | Offers and redemption/save records | No point-of-sale validation |
| Business messaging | Authenticated messages persisted in SQLite | No real-time WebSocket or delivery guarantees |
| Payments | Integration planning only | Requires provider, signed webhooks, tax/refund/subscription engineering |

## Community and AI

| Feature | Included implementation | Boundary |
|---|---|---|
| Social feed | Member posts, moderated publication, comments and reactions | No live push feed |
| Photo sharing and city guides | Existing moderated photo and guide submission features | Human moderation required; no image AI moderation/transcoding |
| Contributor profiles | Public profiles, follows and contribution badges | Profile privacy flag now hides private profiles from other visitors |
| Local Q&A | Listing questions/answers and moderation | No real-time chat |
| AI travel assistant | Local directory assistant plus itinerary prompt workflow | No hosted model call; user may copy prompt into ChatGPT |
| Automated translation | Language foundations and prompt templates | No automatic AI translation engine |
| Review intelligence | Rule-based keyword signals and reporting workflows | Heuristic only; not fraud proof or AI verdict |
| Business marketing assistant | Copy-ready prompt and SEO/branding tools | No automated campaign publishing |

## Enterprise and platform

| Feature | Included implementation | Boundary |
|---|---|---|
| Admin control center | Private role-guarded admin routes, listings, users, reviews, claims, moderation and settings | Admin entry remains hidden and invite-token gated |
| Audit/security controls | Previous session, CSRF, role guards and invitation token system | External penetration test and WAF/CAPTCHA recommended |
| Analytics | Existing first-party listing/search analytics and admin insights | No third-party analytics connector enabled |
| SEO | Meta fields, canonical, Open Graph, JSON-LD, sitemap and robots | Search engines determine indexing and rich result eligibility |
| PWA | Manifest, install prompt, static asset cache and offline fallback | Not full offline business data sync or native apps |
| Performance | Lazy image loading, static cache, API limits and responsive CSS | CDN/edge caching needs hosting configuration |
| Automated backup | Windows backup utility using SQLite snapshot and integrity check, keeping 30 local copies | Schedule via Windows Task Scheduler; copy backups off-device |
| Multi-city | City field, city browse and area search | Requires curated data, coordinates and local operations in each city |

## Operational tools and key routes

- `/admin/system-health.php` — admin-only runtime, extension, write-permission and SQLite integrity checks.
- `backup-database.bat` — creates and verifies a local SQLite backup.
- `/api/v1/health.php` — API health metadata.
- `/api/v1/listings.php` — public published-listing search; does not return exact address/coordinates.
- `/api/v1/map.php` — member-only precise map data, with no-store response policy.
- `/collections.php` — member saved collections.
- `/notifications.php` — member notification centre.
- `/community-feed.php` — member community feed.
- `/guide-ai.php` — first-party local directory discovery assistant.

## QA status and constraints

- PHP files: syntax checked with `php -l`.
- JavaScript files: syntax checked with `node --check`.
- Database: migration schema and representative inserts checked using an available SQLite runtime.
- A full PHP/PDO_SQLite HTTP smoke test cannot be claimed in the build runner because its PHP build has no `pdo_sqlite` module. Verify locally with `php.exe -m` and execute the Windows setup steps.
