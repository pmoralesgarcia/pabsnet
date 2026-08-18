# Selfauth

Selfauth is a self-hosted [Authorization Endpoint](https://indieweb.org/authorization-endpoint) used to log in with a personal URL (as [Web sign-in](http://indieweb.org/Web_sign-in)) via [IndieAuth](https://indieweb.org/IndieAuth). See [How it works](#how-it-works) for more.

Selfauth is not a [Token Endpoint](https://indieweb.org/token-endpoint). To fully use Selfauth for authorization (and not just authentication) a separate token endpoint needs to be set up, e.g. when using [Micropub](https://micropub.net/) clients.

This version adds:

- **Modern password hashing.** Passwords are stored with `password_hash()` (Argon2id, falling back to bcrypt), not raw MD5. Existing installs upgrade automatically the next time you log in successfully.
- **An admin portal** at `/admin/` — see who has signed in, block clients/redirect URIs/IPs, moderate webmentions, manage delegated access, API tokens, and webhooks.
- **OIDC/OAuth2 login** (e.g. via [Kanidm](https://kanidm.com/)) for *both* the IndieAuth "me" sign-in itself and the admin portal — two independent toggles, kept strictly separate. See [OIDC/OAuth2 login](#oidcoauth2-login-eg-kanidm).
- **Delegated admin access** — grant other people (via SSO only) read-only or manager-level access to the admin portal, without sharing your password and without letting them ever authenticate as "me".
- **A Webmention receiver**, similar to [webmention.io](https://webmention.io), at `/webmention.php`, plus a private RSS feed of verified mentions.
- **A bearer-token JSON API** for sign-ins, blocklist, and webmentions, scoped and rate-limited.
- **Webhooks** (HMAC-signed) and **email notifications** for sign-in and webmention events.
- **Mobile-friendly, custom-CSS-able UI.**
- **Docker packaging**, fully configured via environment variables.

## Quick start (Docker)

```bash
git clone <this repo> selfauth && cd selfauth
cp docker-compose.yml docker-compose.yml   # edit the environment block first!
docker compose up -d --build
```

Edit `docker-compose.yml` (or use an `.env` file / your orchestrator's secret store) and set at minimum:

| Variable | Required | Description |
|---|---|---|
| `SELFAUTH_APP_URL` | yes | Public URL this endpoint is served at, e.g. `https://example.com/auth/` |
| `SELFAUTH_USER_URL` | yes | Your personal URL ("me"), e.g. `https://example.com/` |
| `SELFAUTH_ADMIN_PASSWORD` | first boot only | Plaintext password, hashed and stored in the database on first start. Remove it from your environment (or change it from `/admin/`) after that — it is never read again once a hash exists. |
| `SELFAUTH_ADMIN_PASSWORD_HASH` | alternative | Provide an already-hashed password instead of plaintext. |
| `SELFAUTH_APP_KEY` | no | HMAC signing key. Auto-generated and persisted in the database if omitted. |
| `SELFAUTH_WEBMENTIONS_ENABLED` | no | `true` (default) or `false` |
| `SELFAUTH_WEBMENTION_FEED_PUBLIC` | no | `false` (default, token-gated) or `true` (public RSS feed) |
| `SELFAUTH_TRUST_PROXY` | no | Set `true` if running behind a reverse proxy that sets `X-Forwarded-For` |
| `SELFAUTH_COOKIE_SECURE` | no | `true` (default) — set `false` only for local http testing |
| `SELFAUTH_SYSLOG_SUCCESS` / `SELFAUTH_SYSLOG_FAILURE` | no | `true` to log sign-in attempts to syslog, in addition to the admin portal's own log |
| `TZ` | no | Timezone for timestamps, default `UTC` |

OIDC/OAuth2 login and email notifications have their own variable groups — see [OIDC/OAuth2 login](#oidcoauth2-login-eg-kanidm) and [Email notifications](#email-notifications) below. Webhooks and API tokens are managed entirely from the admin portal, no env vars needed.

All of this data (SQLite database, admin sessions) lives in the `/app/data` volume, so it survives container restarts and rebuilds.

Once running, point your homepage at it:

```html
<link rel="authorization_endpoint" href="https://example.com/auth/" />
<link rel="webmention" href="https://example.com/auth/webmention.php" />
```

(A `Link:` HTTP header with the same `rel` values works too.)

## Quick start (classic / non-Docker hosting)

1. Copy the contents of this repository to a folder on your webserver, e.g. `https://example.com/auth/`. Point the webserver's document root at `public/`, or if that's not possible on shared hosting, place the whole repo in that folder as before — `src/`, `data/`, and `config.php` aren't served directly by `index.php`'s routing either way, but a proper document root is safer.
2. Visit `https://example.com/auth/setup.php` and fill in your personal URL and a password. This writes `config.php` (one level above `public/`) with an Argon2id password hash.
3. Add the `<link rel="authorization_endpoint" ...>` tag shown above to your homepage.
4. Make sure `data/` is writable by the webserver (for the SQLite database used by the admin portal and webmention receiver).
5. You can delete `setup.php` afterwards if you like.

## The admin portal

Visit `/admin/` and log in with the same password you use to sign in (or via SSO, see below). Depending on your role, you can:

- **Sign-ins** — see every login attempt (success and failure), with client, redirect URI, scope, IP, and timestamp. One click to block a client or IP straight from a log entry. Also available as a private RSS feed.
- **Webmentions** — see pending/verified/failed/spam mentions, re-check verification, approve, mark as spam, or delete. Verified mentions are also available as an RSS feed.
- **Blocklist** — manage blocked `client_id`/`redirect_uri` hostnames (wildcards like `*.example.com` supported) and IPs/CIDR ranges directly.
- **Admins** *(owner only)* — grant other people SSO-only access to the admin portal (see [Delegated admin access](#delegated-admin-access)).
- **API Tokens** *(owner only)* — create/revoke bearer tokens for the [JSON API](#json-api).
- **Webhooks** *(owner only)* — configure HMAC-signed webhook deliveries for sign-in and webmention events.
- **Settings** *(owner only)* — change your password, update the app/user URLs, manage private feed links, and set custom CSS.

The nav only ever shows what your role can actually do; every action is also enforced server-side regardless of what the UI shows, so a delegate can't bypass their role by crafting requests directly.

## OIDC/OAuth2 login (e.g. Kanidm)

Selfauth can authenticate via an external OIDC provider using Authorization Code + PKCE (S256). ID tokens are verified locally — signature (RS256 or ES256; Kanidm signs with ES256 by default), issuer, audience, expiry, and nonce — against the provider's published JWKS. No dependency on a particular IdP, but the defaults (ES256, required PKCE, `client_secret_basic`/`client_secret_post`) match Kanidm out of the box.

There are **two independent toggles**, and they are kept strictly separate on purpose:

1. **`SELFAUTH_LOGIN_OIDC_ENABLED`** — lets you sign into the IndieAuth endpoint itself via SSO, i.e. other websites see you authenticate through Kanidm when they log you in as "me". Only identities on the **owner allow-list** can ever succeed here.
2. **`SELFAUTH_ADMIN_OIDC_ENABLED`** — lets you (and optionally delegates) sign into the `/admin/` portal via SSO. The owner allow-list gets full access; delegates (added from the Admins page, see below) get a limited role. Delegates can *never* trigger an IndieAuth "me" login, no matter what.

Both share the same `SELFAUTH_OIDC_ISSUER`/`SELFAUTH_OIDC_CLIENT_ID`/etc. connection settings and the same callback URL, so Kanidm-side client registration only needs to happen once.

**Because anyone in your Kanidm could otherwise authenticate**, both toggles stay hard-disabled until you set an owner allow-list — `SELFAUTH_OIDC_ALLOWED_EMAILS` and/or `SELFAUTH_OIDC_ALLOWED_SUBJECTS`. If a toggle is on but the allow-list (or the rest of the config) is incomplete, Selfauth automatically falls back to the password form with a visible warning, rather than locking you out.

### 1. Create an OAuth2/OIDC client in Kanidm

```bash
kanidm system oauth2 create selfauth "Selfauth" https://example.com/auth/
kanidm system oauth2 update-redirect-url selfauth https://example.com/auth/oidc-callback.php
kanidm system oauth2 update-scope-map selfauth <your-kanidm-group> openid profile email
# Public client using PKCE only (recommended for this use case):
kanidm system oauth2 show-basic-secret selfauth   # or skip and treat it as public, see below
```

Kanidm requires PKCE by default and defaults to ES256 ID token signatures — both are what this integration expects, no extra Kanidm-side configuration needed. If you'd rather run it as a confidential client, grab the client secret with the command above and set `SELFAUTH_OIDC_CLIENT_SECRET`; otherwise leave it unset and PKCE alone protects the exchange.

### 2. Configure Selfauth

| Variable | Required | Description |
|---|---|---|
| `SELFAUTH_OIDC_ISSUER` | yes, for either toggle | e.g. `https://idm.example.com/oauth2/openid/selfauth` — must serve `/.well-known/openid-configuration` |
| `SELFAUTH_OIDC_CLIENT_ID` | yes, for either toggle | The client ID you created in Kanidm, e.g. `selfauth` |
| `SELFAUTH_OIDC_CLIENT_SECRET` | no | Leave unset for a public PKCE-only client |
| `SELFAUTH_OIDC_ALLOWED_EMAILS` | one of these two required | Comma-separated list of allowed emails (case-insensitive) |
| `SELFAUTH_OIDC_ALLOWED_SUBJECTS` | one of these two required | Comma-separated list of allowed `sub` claims (Kanidm UUIDs) — more stable than email if yours can change |
| `SELFAUTH_OIDC_SCOPES` | no | Default `openid profile email` |
| `SELFAUTH_OIDC_REDIRECT_URI` | no | Default `{SELFAUTH_APP_URL}/oidc-callback.php` (shared by both toggles) |
| `SELFAUTH_LOGIN_OIDC_ENABLED` | yes, for toggle 1 | `true` to allow SSO on the IndieAuth "me" login |
| `SELFAUTH_LOGIN_AUTH_MODE` | no | `password` (default off screen unless enabled), `oidc` (hide the password form), or `both` |
| `SELFAUTH_ADMIN_OIDC_ENABLED` | yes, for toggle 2 | `true` to allow SSO on the admin portal |
| `SELFAUTH_ADMIN_AUTH_MODE` | no | `password`, `oidc` (hide the password form), or `both` (default) |

### 3. Log in

On the IndieAuth login screen or `/admin/login.php`, click **Log in with SSO**. The admin Settings page shows the live status of both toggles (enabled, misconfigured, or off) and your current session's auth method/identity.

## Delegated admin access

Selfauth is built around a single owner identity (your `SELFAUTH_USER_URL`) — that's fundamental to IndieAuth, and never changes. But you can still let other people help manage *this instance's admin portal*, via the **Admins** page (owner only, requires admin SSO to be enabled):

- **Manager** — can act on sign-ins, blocklist, and webmentions, but can't touch Settings, Admins, API Tokens, Webhooks, or the password.
- **Viewer** — read-only across the admin portal.

Delegates only ever exist via SSO (there's no shared password for them) and are matched by email or `sub` claim, same as the owner allow-list but stored in the database instead of an env var so they can be managed without redeploying. Every role check is enforced server-side on every request — the nav just reflects it, it doesn't grant it.

## JSON API

Bearer-token authenticated, scoped, and rate-limited (120 requests/minute per token). Create tokens from **API Tokens** in the admin portal — the plaintext value is shown once, only its SHA-256 hash is stored.

```bash
curl -H "Authorization: Bearer sfa_xxxxx" https://example.com/auth/api/signins.php
curl -H "Authorization: Bearer sfa_xxxxx" https://example.com/auth/api/blocklist.php
curl -H "Authorization: Bearer sfa_xxxxx" -X POST -d '{"type":"client_id","pattern":"spam.example"}' \
  https://example.com/auth/api/blocklist.php
curl -H "Authorization: Bearer sfa_xxxxx" https://example.com/auth/api/mentions.php
```

Scopes: `signins:read`, `blocklist:read`, `blocklist:write`, `mentions:read`, `mentions:write`. This is separate from the public, unauthenticated `webmention.php` endpoints (the receiver, and the read-only `?target=` query), which stay open per the Webmention spec.

## Webhooks

Configure from the **Webhooks** page (owner only). Selfauth POSTs a JSON payload to every enabled, subscribed webhook when `signin.success`, `signin.failed`, `signin.blocked`, `webmention.received`, or `webmention.verified` happens:

```json
{"event": "signin.failed", "sent_at": "2026-08-16T15:56:19+00:00", "data": {"client_id": "...", "ip": "...", "method": "password"}}
```

Each request carries `X-Selfauth-Event`, `X-Selfauth-Timestamp`, and `X-Selfauth-Signature: sha256=<hmac>` — the HMAC-SHA256 of `"{timestamp}.{raw body}"` keyed by the webhook's secret (shown once, at creation). Verify it with `hash_equals()` before trusting the payload. Delivery is best-effort with a short timeout; check "last delivery" on the Webhooks page if something looks stuck.

## Email notifications

Selfauth can email you the same events as the webhooks above, using a minimal built-in SMTP client (STARTTLS/implicit TLS, AUTH LOGIN) — no external mail service dependency.

| Variable | Required | Description |
|---|---|---|
| `SELFAUTH_SMTP_HOST` | yes | SMTP server hostname |
| `SELFAUTH_SMTP_PORT` | no | Default `587` |
| `SELFAUTH_SMTP_USER` / `SELFAUTH_SMTP_PASS` | no | Omit for an unauthenticated relay |
| `SELFAUTH_SMTP_ENCRYPTION` | no | `tls` (STARTTLS, default), `ssl` (implicit TLS), or `none` |
| `SELFAUTH_SMTP_FROM` | no | Defaults to `SELFAUTH_SMTP_USER` |
| `SELFAUTH_NOTIFY_EMAIL` | yes | Where to send notifications |
| `SELFAUTH_NOTIFY_EVENTS` | yes | Comma-separated event names, e.g. `signin.failed,signin.blocked` — same event list as webhooks |

Notifications are disabled unless both `SELFAUTH_SMTP_HOST` and `SELFAUTH_NOTIFY_EVENTS` are set. Delivery failures are logged but never block the request that triggered them.

## Mobile & custom CSS

The login screen and admin portal are both responsive out of the box (the admin tables collapse into cards on narrow screens). The owner can add custom CSS from **Settings → Custom CSS** — it's applied to both the admin portal and the public login page, served from a dedicated `custom.css.php` route.

## Webmentions

Point senders at `https://example.com/auth/webmention.php` as your `rel="webmention"` endpoint. Selfauth will:

1. Accept `POST source=...&target=...`, check that `target` is on your domain, and store it as `pending`.
2. Make a best-effort synchronous check (short timeout) that `source` really links to `target`, extracting basic microformats2/Open Graph metadata (author, title, content snippet, published date) when it succeeds.
3. If that quick check doesn't finish in time, the mention stays `pending` and is retried by `bin/verify-mentions.php`, which the Docker image runs automatically every 5 minutes. Outside Docker, add it to cron yourself:
   ```
   */5 * * * * php /path/to/selfauth/bin/verify-mentions.php
   ```
4. Serve verified mentions back out with a small JSON API, similar to webmention.io:
   ```
   GET /webmention.php?target=https://example.com/some-post
   ```
5. Also serve them as an RSS feed at `/feed.php` — private by default (a token, shown on the admin Settings page, is required as `?token=`), or set `SELFAUTH_WEBMENTION_FEED_PUBLIC=true` to make it public.

Requests to fetch a `source` refuse to resolve to private/loopback/link-local addresses, as basic SSRF protection.

## Changing your password

From the admin portal: **Settings → Change password**. (No Docker restart required — it's stored in the database.)

For classic installs without the admin portal available, delete `config.php` and run `setup.php` again.

## Security notes

- Passwords are hashed with Argon2id (or bcrypt if Argon2 isn't available in your PHP build) via `password_hash()`/`password_verify()`. A legacy raw-MD5 hash from an older Selfauth install is still accepted for login and is automatically upgraded to Argon2id afterwards.
- Auth codes and CSRF tokens are HMAC-SHA256-signed, time-limited, single-purpose tokens — unchanged from before, since this was already a sound design.
- The admin portal uses its own session-based login (separate CSRF tokens, `HttpOnly`/`SameSite=Lax` cookies) and every state-changing action requires a CSRF token *and* a server-side role check — never just a hidden UI element.
- OIDC login is purpose-scoped: the IndieAuth "me" identity can only ever be asserted by the owner allow-list; delegated admin-portal identities (however privileged) can never trigger it. ID tokens are verified locally (signature, issuer, audience, expiry, nonce) against the provider's published JWKS.
- The IndieAuth login, admin login, and webmention receiver are all rate-limited (fixed-window, per IP or per token) against brute-force and abuse.
- API tokens are random 192-bit secrets, stored only as a SHA-256 hash, scoped, individually revocable, and optionally time-limited.
- Webhook deliveries are HMAC-SHA256 signed with a per-webhook secret shown only once, at creation.
- The webmention fetcher and OIDC HTTP client both refuse to resolve to private/loopback/link-local addresses (basic SSRF protection).
- Custom CSS is owner-only (delegates, even managers, cannot set it), since it's rendered on the public login page and could otherwise be used for UI redress.
- In the Docker image, `src/`, `data/`, and `config.php` live outside the web-served `public/` directory, so the SQLite database and application source can't be downloaded directly even if misconfigured.

## How it works

On a (Web)App which supports [IndieAuth](https://indieweb.org/IndieAuth), you can enter your personal URL. The App will detect Selfauth as Authorization Endpoint and redirect you to it. After you enter your password in Selfauth, you are redirected back to the App with a code. The App will verify the code with Selfauth and logs you in as your personal URL.

To test it, you can go to an App that supports IndieAuth and enter your personal URL. [IndieAuth.com](https://indieauth.com/) has a test-form on the front page.

## License

Copyright 2017 by Ben Roberts and contributors.

Available under the Creative Commons CC0 1.0 Universal and MIT licenses. See CC0-LICENSE.md and MIT-LICENSE.md for the text of these licenses.
