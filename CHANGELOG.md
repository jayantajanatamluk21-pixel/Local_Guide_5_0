# LocalGuide Changelog

## 5.1.0 — Live AI Engine, In-App OSRM Routing, WhatsApp Connect & Digital QR Vouchers

### Generative AI & Intelligence
- **Unified AI Engine (`app/ai.php`)**: Added live provider adapter supporting Google Gemini (`GEMINI_API_KEY`), OpenAI (`OPENAI_API_KEY`), and smart local heuristic synthesis for offline/zero-key operation.
- **AI Community Review Summaries**: Synthesizes approved customer reviews on listing pages into highlights, customer sentiment, and local tips.
- **AI Business Content Studio**: Upgraded owner studio to generate high-converting Local SEO descriptions, social media & WhatsApp promos, and polite review responses.
- **AI Assistant Workbench**: Enabled live AI generation directly on the Assistant page for translations, business growth audits, and local trip planning.
- **AI REST Endpoint (`public/api/v1/ai.php`)**: Added rate-limited JSON API for review summaries, copy generation, and concierge queries.

### Navigation, Maps & Geolocation
- **In-App Leaflet Routing (OSRM)**: Integrated Open Source Routing Machine (`router.project-osrm.org`) to calculate driving distance, duration, and render interactive polyline route geometry directly on the Leaflet map.
- **"Near Me" GPS Geolocation**: Added a dedicated Locate Control button with animated GPS pulsing marker and real-time distance calculations from the user's location.

### Business Monetization & Conversion
- **WhatsApp Direct Connect**: Added high-converting "Chat on WhatsApp" buttons with prefilled enquiry messages on listing profiles and discovery cards.
- **Digital QR Code Voucher Engine (`app/qr.php`)**: Pure PHP vector SVG QR code generator with zero third-party dependencies.
- **Digital Voucher Pass & Counter Validation**: Digital voucher pass view (`coupon-redeem.php?voucher=...`) with scannable QR code, and a real-time Owner Voucher Validator (`owner/coupons.php`) to redeem customer discounts in real-time.

### Communications & Media
- **Transactional Email Dispatcher (`app/email.php`)**: Supports Resend API (`RESEND_API_KEY`) and local file spool fallback (`storage/mail_spool.log`), notifying business owners instantly on new enquiries, booking requests, and quote requests.
- **WebP Image Optimization**: Enhanced image upload pipeline to automatically compress and convert uploads to `.webp` when PHP GD is available.

### Gamification & Community
- **Top Local Contributors Leaderboard**: Dynamic ranking system with medals (🥇 🥈 🥉), points calculation (+10 review, +30 guide, +15 photo, +5 follower), and tier badges (🌟 Ambassador, 🧭 Master Guide, 🎒 Scout, 📍 Explorer).


## 4.0.0 — Premium city discovery experience

### Experience, design and motion
- Introduced LocalGuide 4.0 identity: forest green, deep sage, warm ivory and lime accents.
- Rebuilt the public landing page with editorial hero, photographic category cards, discovery panels, city browse and install-app prompt.
- Added responsive primary navigation and mobile quick-action dock.
- Added accessible reveal/stagger animations, counter transitions, subtle desktop card tilt, animated hero photography and floating discovery cards.
- Added user-controlled light/dark theme with local preference storage.
- Added reduced-motion fallbacks and keyboard focus states.

### Discovery, API and personalization
- Added a versioned JSON API for health and public published listings, with member-only exact map payloads.
- Added first-party search autocomplete backed by the listings API.
- Added Guide AI local discovery page with category/keyword intent matching against published listings; no hosted LLM is called.
- Added user home-city and interest preferences and combined them with saved listing categories for recommendations.
- Added PWA install prompt behavior and refreshed static cache/offline shell.
- Added multi-stop route handoff to Google Maps for itineraries containing at least two geocoded stops.

### Community and member experience
- Added saved collections with private, unlisted and public visibility and share links.
- Added in-app notification centre and unread counter.
- Added member community feed with moderated submissions, comments and reactions.
- Added member profile fields for home city, interests and public/private contributor visibility.
- Enforced private contributor profile visibility on the public contributor route.
- Added notification events for selected owner and contributor workflow changes.

### Operations and administration
- Added administrator-only system health page for PHP runtime/extensions, storage permissions and SQLite quick-check.
- Added CLI-only SQLite backup tool and Windows launcher. Backups use SQLite `VACUUM INTO`, verify `PRAGMA quick_check` and retain the newest 30 files.
- Added API request rate limiting/logging.
- Added v4 visual theme to owner and admin workspaces, including a theme switch.
- Added profile personalization fields using additive migrations; existing member data is preserved.

### Retained from 3.2.1
- Hidden invite-token-gated admin portal; public `/admin/login.php` and `/setup.php` remain 404.
- Server-side role and route guards; guest/member location privacy; safe return-to flow.
- Business profiles, owner claim/approval, bookings, quote requests, service prices, availability, owner CRM and stored messaging.
- Reviews, events/offers, community moderation, smart discovery, compare listings, trip planner, SEO metadata and PWA foundation.

### Known boundaries / external configuration still required
- Hosted LLM/ChatGPT API generation, automated translation and production AI image generation.
- Payment gateway, subscription billing, tax/refund workflow and signed payment webhooks.
- Real-time WebSocket messaging and email/SMS/push notification delivery.
- Production map tile/geocoding/routing providers and guaranteed ETAs.
- Full offline data synchronization and native Android/iOS apps.
- CDN-based media transcoding, CAPTCHA/WAF and external penetration testing.

### QA
- PHP syntax and JavaScript syntax checks are included in release QA.
- SQLite schema/migration and representative workflow tests are performed with a SQLite runtime.
- A full PHP-to-SQLite HTTP smoke test is not available in the build runner because its PHP installation has no `pdo_sqlite` extension; target Windows setup must be tested locally.

## 3.2.1 — Simplified sign-in and registration navigation
- Removed separate local-user and business-owner sign-up links from public header and login screen.
- Kept one sign-in link and one create-account action, with role selection inside registration.

## 3.2.0 — Route-based access control
- Added strict guest/member/admin access boundaries for maps and precise location data.
- Added the hidden invitation-token administrator portal and generic 404s for legacy/public admin entry points.
- Preserved validated return paths through login/registration and redacted exact location data from guest output.

## 3.1.0 — Username-based account access
- Added unique usernames, safe backfill, username/email login and account credential changes.
- Added CLI-only admin credential updater.

## 3.0.0 — Smart discovery, marketplace and community
- Added natural-phrase search, nearby discovery, voice search, map clustering, comparisons and personalized suggestions.
- Added booking/quote requests, business messaging, availability, service price lists, offers and owner CRM.
- Added moderated community photos, guides, follows, reviews, Q&A and reports.
- Added AI prompt desk and admin trust/duplicate heuristics.


## 4.0.1 — Split-screen smoke authentication

- Replaced the standard single-column auth card with a responsive two-panel sign-in experience.
- Added in-place Sign In / Create an Account switching with a layered CSS smoke/vapor sweep.
- Kept both POST workflows server-rendered and CSRF-protected; each form explicitly posts its mode so validation errors return to the correct view.
- Preserved safe post-auth return targets and owner/member selection.
- Added show/hide password controls, keyboard-accessible tabs, mobile layout, dark-theme styling and reduced-motion support.
- Added an integration guide covering markup, CSS, JavaScript, timing, accessibility and troubleshooting.


## 4.0.2 — Separate public and admin route display

- Windows launcher now prints the public website URL and the distinct hidden administrator portal route.
- Clarifies that both routes use one PHP server; admin access still requires a tokenized invitation generated by `generate-admin-invite.bat`.
- Added `docs/LOCAL_URLS.md` with exact local access steps and security notes.


## 4.0.2 — Distinct public and administrator URLs

- Windows launcher now prints the public website URL and the distinct hidden administrator portal base route.
- Clarifies that both routes run on one local PHP server; administrator access still requires a private tokenized invitation.
- Updated setup documentation: generate the invite in a second CMD window while the server is running.

## 5.0.0 — 2026-11-05

- Additive SQLite migration for owner CRM tasks, membership plans, subscription records, payment ledger, security audit events, integration readiness and feature flags.
- Added admin command center, security audit viewer, monetization ledger and integration readiness dashboard.
- Added owner CRM task board and offline business copy studio.
- Added encrypted TOTP two-factor authentication for member, owner and administrator accounts (requires OpenSSL and APP_ENCRYPTION_KEY).
- Added LocalGuide 5.0 visual layer: depth/tilt interactions, parallax, glass panels, animated local globe illustration and reduced-motion support.
- Updated PWA cache namespace and launcher version.
- Added setup and migration documentation, verification script and integration boundary matrix.
- No payment, generative AI, hosted routing, email or push provider is claimed as live without credentials and provider-side setup.

## 5.0.0 — 2026-11-05

- Additive SQLite migration for owner CRM tasks, membership plans, subscription records, payment ledger, security audit events, integration readiness and feature flags.
- Added admin command center, security audit viewer, monetization ledger and integration readiness dashboard.
- Added owner CRM task board and offline business copy studio.
- Added encrypted TOTP two-factor authentication for member, owner and administrator accounts (requires OpenSSL and APP_ENCRYPTION_KEY).
- Added LocalGuide 5.0 visual layer: depth/tilt interactions, parallax, glass panels, draggable globe illustration and reduced-motion support.
- Updated PWA cache namespace and launcher version.
- Added installation, migration, verification and integration boundary documentation.
- No payment, generative AI, hosted routing, email or push provider is claimed as live without credentials and provider-side setup.
