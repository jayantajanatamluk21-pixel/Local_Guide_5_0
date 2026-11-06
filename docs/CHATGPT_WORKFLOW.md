# ChatGPT content workflow

The SEO & Design Studio creates a detailed prompt using the business facts entered by the administrator. It does not transmit the data or invoke an API.

1. Enter the exact business name, category, neighbourhood, audience, target search phrase and verified highlights.
2. Click **Build ChatGPT content prompt**.
3. Copy the generated prompt and open ChatGPT in a separate tab.
4. Review the result. Correct any statement that cannot be verified from the business owner or an authoritative source.
5. Copy approved copy into the relevant listing fields in Admin → Listings.
6. Add the meta title and description in the listing editor. Use the SERP preview as a visual editing aid, not as a ranking guarantee.

The prompt instructs ChatGPT not to fabricate hours, prices, awards, certifications, ratings, addresses, amenities or review counts. AI output still requires human fact-checking before publication.

## Direct API integration (optional future extension)

A direct integration is intentionally not enabled in this project. To add one, use a server-side PHP endpoint, keep the API key in a server environment secret (never browser JavaScript), validate and length-limit all user input, add CSRF protection and authentication, enforce request quotas, handle upstream errors, and review generated facts before saving. Set a monthly budget/usage limit with the API provider. The public website should continue to function if AI services are unavailable.
