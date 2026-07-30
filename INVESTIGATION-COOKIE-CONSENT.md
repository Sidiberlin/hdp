# Investigation — Cookie Consent Banner Bugs

Time-boxed to 30 min. Investigation completed in ~10 min against static
source; no clean, low-risk fix identified within the time-box, so this
is a report — no code changes applied.

## Extension responsible

`app/extensions/BlueSpicePrivacy` — specifically the
`native-mw` cookie consent provider.

Key files:

- `app/extensions/BlueSpicePrivacy/resources/cookieConsent/MWProviderPrompt.js`
  — client-side prompt implementation
- `app/extensions/BlueSpicePrivacy/resources/cookieConsent/MWProviderPrompt.less`
  — bar + overlay styling
- `app/extensions/BlueSpicePrivacy/src/HookHandler/AddCookieConsent.php`
  — server-side config emission (populates
  `bsPrivacyCookieConsentHandlerConfig`)
- `app/extensions/BlueSpicePrivacy/extension.json` line 518 — default
  `PrivacyCookieAcceptMandatory: true`

Loaded from `app/settings.d/040-BlueSpicePro.php` line 16
(`wfLoadExtension( 'BlueSpicePrivacy' )`).

## Bug 1 — banner reappears on navigation after "Alle akzeptieren"

### Root cause (most likely)

The client sets the cookie via `mw.cookie.set()` in
`onCookieSettingsChanged()`. MediaWiki's `mw.cookie.set` defaults its
options from `wgCookieDefaults`, which on a fresh 5.1.3 install
typically includes `sameSite: 'Lax'` and — on production wikis —
`secure: true`.

Two failure modes both cause the "cookie not seen on next request":

1. If `$wgCookieSecure = true` (or `'detect'` on an HTTPS server that
   somehow serves HTTP behind a proxy), the browser drops the cookie
   because the local dev URL is `http://localhost:8080`.
2. If `$wgCookiePath` is `/w` (matches script path) rather than `/`,
   `mw.cookie.set()` writes it at `/w` — but MWProviderPrompt.js line
   27 explicitly overrides with `path: '/'`. However `mw.cookie.get` on
   the next page reads with the DEFAULT path context. In `mw.cookie`
   the default `prefix` is `wgCookiePrefix`; both set/get use the same
   prefix so that's fine. But `mw.cookie.get` returns null when there
   are TWO cookies with the same name at different paths and the
   browser picks the "wrong" one — unusual but happens with prior
   partial-consent state.

The `localStorage` fallback in `cookieExists()` (lines 21-30) should
paper over the cookie-persistence bug — but only if the browser allows
localStorage on the origin. If a strict Chrome/Firefox privacy mode is
active, localStorage may be session-only.

### Diagnostic steps the user should run

Open DevTools → Application → Storage after clicking "Alle akzeptieren":

1. **Cookies** — filter by `bs-privacy-cookie-consent`. Note the exact
   name (including `wgCookiePrefix`), path, secure flag, sameSite, and
   expiry.
2. **LocalStorage** — check for the prefixed key. If cookie is missing
   but localStorage entry is present, the fallback isn't firing on the
   next page → most likely `handlerConfig.cookieName` differs between
   page loads.
3. Reload the page. If cookie is gone but localStorage is present, bug
   is the cookie-write path. If both are gone, browser is discarding
   both (likely a `Secure` cookie on `http://`).

## Bug 2 — transparent overlay blocks form input

### Root cause

`MWProviderPrompt.less` line 19-30 defines
`.bs-privacy-cookie-consent-mw-provider-overlay` as **full-screen**
(`100% x 100%`, `top:0`, `bottom:0`) with `background-color: rgba(0, 0,
0, 0.7)` and `z-index: 1050`. That is by design — with
`acceptMandatory: true` the extension deliberately grays out the entire
viewport so the user cannot interact with anything until they accept
cookies. The docstring on `PrivacyCookieAcceptMandatory` says exactly
this: "If true, will grey-out the screen and prevent user from doing
anything until cookies are accepted".

So "overlay blocks form input pointer events" is not a bug — it's the
mandatory-consent design.

However there is a secondary bug: line 45-47 in the same LESS file has:

```less
.mw-ui-container #userloginForm {
    pointer-events: none;
}
```

This is set **globally** (not scoped to a "banner visible" class or
DOM state). The counter-fix is line 3 of MWProviderPrompt.js:

```js
$( '.mw-ui-container #userloginForm' ).css( 'pointer-events', 'visible' );
```

which runs at module load — so IF module loads reliably before the
user clicks anything, form is enabled. But because ResourceLoader is
async, there is a **race**: on a slow first paint the CSS rule applies
before the JS override, and users see the login form respond to
clicks with a delay or not at all.

This explains the "transparent overlay blocks input even when the
visible banner is at the bottom": what the user perceives as
"transparent overlay" is actually the `pointer-events: none` rule on
`#userloginForm` racing the JS unlock. The extension author's comment
on lines 42-44 of the LESS file confirms awareness of this race.

## Viable fix approaches

### A. Disable mandatory consent (config-only, 1-line)

Add to `app/settings.d/040-BlueSpicePro.php` or a new
`app/settings.d/045-Privacy.php`:

```php
$GLOBALS['wgPrivacyCookieAcceptMandatory'] = false;
```

**Effect:** banner still shows, but overlay does not, and the login
form is never disabled. Bug 2 disappears entirely. Bug 1 remains — but
becomes cosmetic (user can dismiss again on the next page) rather than
blocking.

**Tradeoff:** downgrades the site's privacy posture from
"you-must-consent-before-doing-anything" to
"we-showed-you-a-banner-you-can-ignore-it". For a public-facing
production wiki in the EU this is a legal question, not a technical
one. For an internal dev/staging site it's fine.

**Effort:** 2 minutes.

### B. Fix the pointer-events race (patch to LESS)

Change the LESS rule from unconditional to conditional on
`body.bs-privacy-cookie-consent-active` (added by the JS when the
banner is showing, removed when accepted). Wrap the pointer-events
disable in that scope. Similarly scope the overlay to the same class.

**Effort:** 30-60 min including cache-bust and manual test in browser.

**Tradeoff:** patches vendored upstream code — new maintenance burden
per BlueSpice release. Consider a settings-d LESS override module
instead of editing extension files directly.

### C. Fix cookie persistence (patch to PHP + JS)

Root-cause debug requires a live browser session with DevTools open.
Once the failure mode is confirmed (see "Diagnostic steps" above), the
fix is either:

- Force `path: mw.config.get('wgCookiePath')` + explicit `secure:
  false` in the `mw.cookie.set` call in MWProviderPrompt.js line 27.
- Or in `AddCookieConsent.php`, add `"cookiePath"` matching MW's
  cookie path resolution instead of raw `CookiePath` config get.

**Effort:** 1-2 hours (needs interactive browser debug).

**Tradeoff:** vendored upstream patch again.

### D. Ship a settings-d override that fully disables the extension

`wfLoadExtension` conditionally in a new settings.d file, skipped when
`HDP_DISABLE_COOKIE_BANNER=1`. Cleanest separation but heaviest hammer.

## Recommended path forward

**For dev / staging / internal wiki:** approach A (1-line config, no
vendored patches, 2 min).

**For production / regulated deployment:** approach B first (fixes
the actual UX race), then approach C only if telemetry shows users
hitting the banner more than once. A + B together is fine — B fixes
the login form race independently of consent posture.

**Do NOT do C without a real browser reproduction session.** The
static analysis above narrowed it to cookie-persistence — but which
of the three sub-causes is the actual failure requires DevTools.

## Effort estimate summary

| Approach | Time     | Fixes bug 1 | Fixes bug 2 | Vendored patch |
| -------- | -------- | ----------- | ----------- | -------------- |
| A        | 2 min    | mitigated   | yes         | no             |
| B        | 30-60 min| no          | yes         | yes            |
| C        | 1-2 hr   | yes         | no          | yes            |
| A + B    | 30-60 min| mitigated   | yes         | yes (B only)   |

## Files for reference

- `/home/edwin/Documents/hdp/hdp/app/extensions/BlueSpicePrivacy/resources/cookieConsent/MWProviderPrompt.js`
- `/home/edwin/Documents/hdp/hdp/app/extensions/BlueSpicePrivacy/resources/cookieConsent/MWProviderPrompt.less`
- `/home/edwin/Documents/hdp/hdp/app/extensions/BlueSpicePrivacy/src/HookHandler/AddCookieConsent.php`
- `/home/edwin/Documents/hdp/hdp/app/extensions/BlueSpicePrivacy/extension.json` line 518
- `/home/edwin/Documents/hdp/hdp/app/settings.d/040-BlueSpicePro.php` line 16
