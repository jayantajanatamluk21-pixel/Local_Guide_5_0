# LocalGuide 5.0 — 40-feature implementation status

This matrix separates working first-party features from provider-dependent or deliberately partial capabilities. “Implemented” means the local PHP/SQLite application has a working route or UI for the described scope; it does not mean a third-party provider is connected.

## A. Visual experience and motion

| # | Roadmap feature | 5.0 status | Scope and boundary |
|---:|---|---|---|
| 1 | Cinematic video homepage | Partial | Cinematic gradients, animated sheen and layered hero effects are included. No video asset is bundled, so this is not a background-video experience. |
| 2 | Advanced smoke authentication | Implemented | Split-screen sign-in/register, reversible vapor transition, existing CSRF and form fallback retained. |
| 3 | 3D card interactions | Implemented | Pointer-based perspective tilt on listing, category and discovery cards; disabled for reduced-motion preference. |
| 4 | Parallax and scroll effects | Implemented | Decorative hero/globe parallax on wider screens, with reduced-motion handling. |
| 5 | Glassmorphism UI | Implemented | Layered translucent panels and blur treatment in the 5.0 admin and visual layer. |
| 6 | Animated map routes | Partial | Leaflet marker map and route handoff remain. In-app route geometry, live traffic and animated navigation are not included. |
| 7 | Interactive 3D globe | Implemented as an illustration | Draggable CSS 3D-inspired globe is a visual affordance; it is not a geographic globe, satellite layer or source of map coordinates. |
| 8 | Advanced loading animations | Partial | Existing page/form transitions and motion cues remain; no full-screen route-loading framework is shipped. |

## B. AI and discovery

| # | Roadmap feature | 5.0 status | Scope and boundary |
|---:|---|---|---|
| 9 | Guide AI 2.0 | Implemented as local discovery | Database-backed, rule-based intent/category matching. It does not call an LLM. |
| 10 | AI trip planner | Partial | Member trip plans and ordered multi-stop handoff exist; generative itinerary optimisation is not connected. |
| 11 | Voice assistant | Partial | Browser Speech Recognition supports voice search on compatible browsers; this is not a continuous conversational assistant. |
| 12 | Natural-language search | Implemented as a rules engine | Parses simple category/area intent against published records; it does not infer facts outside the directory. |
| 13 | Personalised recommendations | Implemented | Member city/interests and first-party listing data influence suggestions. |
| 14 | AI review summaries | Not implemented | Rating distributions and aggregate review metrics exist. This release has no OpenAI request adapter or generated review-summary workflow; an API key alone will not enable it. |
| 15 | AI business writer | Partial, offline template assistant | Owner content studio creates editable drafts from listing data. It is not generative AI and does not invent claims. |
| 16 | Multilingual assistant | Partial | English/Hindi/Bengali interface foundations exist. Full automated multilingual conversational generation requires a provider and editorial review. |

## C. Maps and marketplace

| # | Roadmap feature | 5.0 status | Scope and boundary |
|---:|---|---|---|
| 17 | Advanced interactive maps | Implemented | Leaflet map, OpenStreetMap attribution, category filtering and clustered pins. Requires listing coordinates. |
| 18 | Multi-stop route planner | Implemented as handoff | Existing saved trip plans can hand off multiple stops to Google Maps. LocalGuide does not calculate guaranteed ETA or live traffic. |
| 19 | Business comparison | Implemented | Compare up to four published business listings. Exact location remains member-gated. |
| 20 | Booking and appointments | Implemented as request workflow | Booking/availability requests are persisted for owner response; a request is not a confirmed reservation or paid booking. |
| 21 | Business CRM | Implemented | Existing enquiry pipeline plus the new owner task board with due dates and status workflow. |
| 22 | Customer messaging | Implemented as stored messaging | Authenticated message/conversation records exist in SQLite. WebSocket real-time delivery and off-site notifications are not configured. |
| 23 | Subscription plans | Partial, recordkeeping | Free/Growth/Pro catalogue, subscription records and admin views are included. Paid activation is not automatic and no recurring billing is live. |
| 24 | Coupons and promotions | Implemented | Existing offers, coupon codes, event submissions and redemption records retained. |

## D. Community and users

| # | Roadmap feature | 5.0 status | Scope and boundary |
|---:|---|---|---|
| 25 | Social media feed | Implemented | Community posts, comments, reactions and moderation retained. |
| 26 | Photo sharing | Implemented with moderation | Listing photo submissions and community post image fields are present; public display depends on approval and upload validation. |
| 27 | Creator profiles | Implemented | Contributor profiles, visibility controls, follows and public profile routes retained. |
| 28 | Local expert badges | Implemented as contribution tiers | Contributor score-based role labels are calculated from first-party activity; they are not external accreditation. |
| 29 | Events and festivals | Implemented as submissions | Event publishing/moderation workflow exists; no live third-party city-events feed is connected. |
| 30 | Public travel guides | Implemented | Community guide and guide-item records/routes retained. |
| 31 | Advanced user profiles | Implemented | Profile bio, city, interests, language and public/private visibility controls retained. |
| 32 | Two-factor authentication | Implemented | TOTP authenticator codes for member, owner and admin sign-ins; secret encrypted at rest with APP_ENCRYPTION_KEY. |

## E. Admin and infrastructure

| # | Roadmap feature | 5.0 status | Scope and boundary |
|---:|---|---|---|
| 33 | Enterprise admin command center | Implemented as local operations dashboard | Adds live operational counters, security event view and shortcuts. It is not a multi-tenant enterprise control plane. |
| 34 | Advanced analytics | Implemented as first-party analytics | Existing listing/search/booking/quote analytics retained. No third-party attribution pipeline is configured. |
| 35 | Payment gateway integration | Data-model scaffold only | Plan catalogue, subscription records and payment ledger are included. No Razorpay/Stripe checkout adapter or webhook endpoint is implemented in this release; adding keys alone will not activate payments. |
| 36 | SEO automation | Implemented as on-site foundations | Dynamic metadata, sitemap and listing SEO fields exist. External SEO rank tracking/backlink monitoring are not included. |
| 37 | Security audit logs | Implemented | New audit records for member/admin sign-in, 2FA and CRM task actions. Logs are local; a central log sink and retention policy remain operator responsibilities. |
| 38 | Automated backups | Partial / schedulable | Existing verified SQLite backup script retains 30 snapshots. Run manually or register the optional Windows scheduled task. |
| 39 | PWA and offline mode | Implemented as app shell | Manifest, service worker, static asset cache and offline fallback are present. Authenticated business data is not synchronized offline; push requires VAPID configuration and delivery engineering. |
| 40 | Automated integration testing | Implemented as local smoke suite | `tools/test-suite.php` validates runtime, SQLite schema, integrity, foreign keys, redirects and TOTP helper behavior. It is not a full browser/production end-to-end suite. |

## External services not implemented or activated in this release

- Generative AI and generated review summaries: no provider request adapter is shipped. `OPENAI_API_KEY` is only listed by the readiness dashboard; setting it does not enable AI calls.
- Hosted maps/geocoding/routing: the bundled Leaflet/OpenStreetMap map and Google Maps handoff remain. No managed geocoding, route-calculation or ETA adapter is shipped.
- Payments: the plan catalogue, subscription records and payment ledger are local data structures only. No Razorpay/Stripe checkout adapter, signed webhook endpoint, refund workflow or tax handling is shipped.
- Email: no outbound email adapter, delivery queue or provider webhook is shipped.
- Web push: the PWA shell exists, but subscription registration and server-side push delivery are not shipped.
- CAPTCHA: no provider-side verification flow is shipped.

The Admin → Integrations page only reports whether expected environment variables exist. It does not call a provider. Adding credentials alone will not make these capabilities work; each requires provider-specific implementation, account setup, sandbox testing and security review.
