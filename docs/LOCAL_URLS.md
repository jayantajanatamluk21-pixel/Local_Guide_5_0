# LocalGuide 4.0.2 — Local URLs

The launcher starts one PHP development server on port 8000 and prints two distinct URLs:

- Public website: `http://127.0.0.1:8000/`
- Hidden administrator portal base: `http://127.0.0.1:8000/admin/hidden-portal-auth/`

These are two routes served by one PHP process, not separate servers or ports. The administrator base route deliberately returns a generic 404 unless the request includes the correct invitation token.

## Generate the actual private administrator URL

1. Leave the website server running.
2. Open a second CMD window.
3. Change directory to the project root.
4. Run `generate-admin-invite.bat`.
5. Copy and open the full private URL printed by the script. It includes `?invite=...`.
6. Sign in using the administrator credentials. Keep the tokenized URL private.

Running the invitation generator again rotates the token and invalidates previously shared invitation URLs. The administrator entry point must not be added to public navigation or shared publicly.
