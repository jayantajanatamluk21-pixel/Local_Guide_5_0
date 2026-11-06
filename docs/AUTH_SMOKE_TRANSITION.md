# Split-screen authentication with smoke / vapor transition

## What is included

`public/auth.php` now renders a responsive split-screen account experience:

- Left: LocalGuide brand story, benefits and decorative city-map artwork.
- Right: a Sign In / Create an Account switcher and the corresponding form.
- Switching modes runs a layered, blurred radial-gradient smoke sweep over the form stage. The form changes while the vapor is at its densest, then the new form clears into view.
- Both forms remain server-rendered HTML POST forms, with CSRF tokens, explicit mode fields and a validated post-authentication `next` destination.
- Registration keeps the existing Local explorer / Business owner choice.
- Password visibility buttons, keyboard-accessible tabs, responsive mobile layout, dark-theme styling and reduced-motion support are included.
- The administrator invite portal remains isolated and is not exposed by this page.

## Files

```text
public/
  auth.php
  includes/
    header.php       # page-specific stylesheet hook and auth body class
    footer.php       # optional auth-page footer suppression and page script hook
  assets/
    css/auth-smoke.css
    js/auth-smoke.js
```

## Integration steps

1. Copy `auth.php` into `public/auth.php` (merge if your project has local customizations).
2. Copy `auth-smoke.css` into `public/assets/css/`.
3. Copy `auth-smoke.js` into `public/assets/js/`.
4. Ensure `includes/header.php` supports `$pageStylesheets` and sets the `auth-route-body` class when `$isAuthPage` is true.
5. Ensure `includes/footer.php` skips the large public footer when `$isAuthPage` is true and includes each entry in `$pageScripts`.
6. In `auth.php`, set:

```php
$pageStylesheets = ['assets/css/auth-smoke.css'];
$pageScripts = ['assets/js/auth-smoke.js'];
$isAuthPage = true;
```

7. Keep the auth form POST handler server-side. The key hidden inputs are:

```html
<input type="hidden" name="auth_mode" value="login">
<input type="hidden" name="next" value="/account.php">
```

The registration form uses `auth_mode=register` and preserves the same validated `next` target. In PHP, read the mode from `$_POST['auth_mode']` before falling back to `$_GET['mode']` so validation errors return to the correct panel.

## How the smoke transition works

`auth-smoke.js` handles clicks on elements carrying `data-auth-mode="login"` or `data-auth-mode="register"`.

1. It disables the mode controls to prevent overlapping transitions.
2. It adds `.is-vaporizing` to `#auth-form-stage`.
3. CSS pseudo-elements and three blurred vapor plumes sweep across the form stage using layered radial gradients.
4. At approximately 500 ms, while the cloud covers the panel, JavaScript switches the visible form and updates `aria-selected`, the active indicator and the URL's `mode` parameter.
5. The vapor exits, the new panel fades/settles into place, and focus moves to the first available input on desktop.
6. The query's `next` destination and selected registration role are kept in the URL; each POST form also carries its own `next` value.

The effect is intentionally CSS-only. It does not require a video, large animation file, canvas, GSAP or an external asset. Tweak the `authSmokeSweep`, `authSmokeSweepTwo`, `authPuff*` keyframes in `auth-smoke.css` to change speed and direction. Keep the midpoint swap aligned with the densest smoke frame.

## Accessibility and responsive behavior

- Tabs have `role=tablist`, `role=tab`, `aria-selected`, `aria-controls` and arrow-key switching.
- Forms remain regular HTML forms and work without JavaScript via the `noscript` links.
- The stylesheet respects `prefers-reduced-motion: reduce` and removes decorative animation.
- At tablet width the brand panel moves above the form; at phone width the brand story compresses and the forms use a single-column layout.
- Password fields have explicit show/hide buttons with accessible labels.
- Form validation and authentication remain server-side; the transition is only presentation logic.

## Troubleshooting

- **Tabs do not switch:** confirm `assets/js/auth-smoke.js` loads (browser DevTools → Network/Console) and `$pageScripts` is emitted by `includes/footer.php`.
- **No smoke effect:** confirm `assets/css/auth-smoke.css` is loaded by the `$pageStylesheets` hook and that the browser is not set to reduced motion.
- **Wrong form after validation:** confirm each form has its own hidden `auth_mode`, and the PHP handler reads `$_POST['auth_mode']` before `$_GET['mode']`.
- **Lost redirect after sign in:** keep the hidden `next` input in both forms and continue validating it with the existing `safe_return_to()` function before redirecting.
- **Registration role not retained:** keep the `role` select in the registration form and the JS URL sync for the `role` query parameter.

Do not place admin invitation tokens or administrator credentials in either form or in client-side JavaScript.
