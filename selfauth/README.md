# Selfauth

Selfauth is a self-hosted [Authorization Endpoint](https://indieweb.org/authorization-endpoint) used to log in with a personal URL (as [Web sign-in](http://indieweb.org/Web_sign-in)) via [IndieAuth](https://indieweb.org/IndieAuth). See [How it works](#how-it-works) for more.

Selfauth is not a [Token Endpoint](https://indieweb.org/token-endpoint). To fully use Selfauth for authorization (and not just authentication) a separate token endpoint needs to be set up, e.g. when using [Micropub](https://micropub.net/) clients.

This version adds:

- **Modern password hashing.** Passwords are stored with `password_hash()` (Argon2id, falling back to bcrypt), not raw MD5. Existing installs upgrade automatically the next time you log in successfully.
- **An admin portal** at `/admin/` — see who has signed in, block clients/redirect URIs/IPs, moderate webmentions, and change your password.
- **A Webmention receiver**, similar to [webmention.io](https://webmention.io), at `/webmention.php`.
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
| `SELFAUTH_TRUST_PROXY` | no | Set `true` if running behind a reverse proxy that sets `X-Forwarded-For` |
| `SELFAUTH_COOKIE_SECURE` | no | `true` (default) — set `false` only for local http testing |
| `SELFAUTH_SYSLOG_SUCCESS` / `SELFAUTH_SYSLOG_FAILURE` | no | `true` to log sign-in attempts to syslog, in addition to the admin portal's own log |
| `TZ` | no | Timezone for timestamps, default `UTC` |

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

Visit `/admin/` and log in with the same password you use to sign in. From there you can:

- **Sign-ins** — see every login attempt (success and failure), with client, redirect URI, scope, IP, and timestamp. One click to block a client or IP straight from a log entry.
- **Webmentions** — see pending/verified/failed/spam mentions, re-check verification, approve, mark as spam, or delete.
- **Blocklist** — manage blocked `client_id`/`redirect_uri` hostnames (wildcards like `*.example.com` supported) and IPs/CIDR ranges directly.
- **Settings** — change your password, and update the app/user URLs.

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

Requests to fetch a `source` refuse to resolve to private/loopback/link-local addresses, as basic SSRF protection.

## Changing your password

From the admin portal: **Settings → Change password**. (No Docker restart required — it's stored in the database.)

For classic installs without the admin portal available, delete `config.php` and run `setup.php` again.

## Security notes

- Passwords are hashed with Argon2id (or bcrypt if Argon2 isn't available in your PHP build) via `password_hash()`/`password_verify()`. A legacy raw-MD5 hash from an older Selfauth install is still accepted for login and is automatically upgraded to Argon2id afterwards.
- Auth codes and CSRF tokens are HMAC-SHA256-signed, time-limited, single-purpose tokens — unchanged from before, since this was already a sound design.
- The admin portal uses its own session-based login (separate CSRF tokens, `HttpOnly`/`SameSite=Lax` cookies) and every state-changing action requires a CSRF token.
- In the Docker image, `src/`, `data/`, and `config.php` live outside the web-served `public/` directory, so the SQLite database and application source can't be downloaded directly even if misconfigured.

## How it works

On a (Web)App which supports [IndieAuth](https://indieweb.org/IndieAuth), you can enter your personal URL. The App will detect Selfauth as Authorization Endpoint and redirect you to it. After you enter your password in Selfauth, you are redirected back to the App with a code. The App will verify the code with Selfauth and logs you in as your personal URL.

To test it, you can go to an App that supports IndieAuth and enter your personal URL. [IndieAuth.com](https://indieauth.com/) has a test-form on the front page.

## License

Copyright 2017 by Ben Roberts and contributors.

Available under the Creative Commons CC0 1.0 Universal and MIT licenses. See CC0-LICENSE.md and MIT-LICENSE.md for the text of these licenses.
