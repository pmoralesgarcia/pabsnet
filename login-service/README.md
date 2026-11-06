# Sign in with your website

The service behind `login.lifeofpablo.com`, which lets a site sign people in with their own domain, in the style of [IndieLogin](https://indielogin.com/api). To the people signing in it is a standard [IndieAuth](https://indieauth.spec.indieweb.org/) client that only asks who they are. To sites it offers IndieLogin's `/authorize` and `/token`. Standard IndieAuth clients work too: it publishes metadata and also redeems codes at `/authorize`.

Usage for site owners is on the service's home page.

## Settings

| Variable | Purpose |
|---|---|
| `LOGIN_URL` | This service's public URL. Sites check it as the `iss` |
| `LOGIN_CLIENTS` | Sites allowed without registering, separated by spaces or commas |
| `LOGIN_REGISTRATION` | `closed` stops new registrations. Open by default |
| `LOGIN_DB_PATH` | SQLite file. Registered sites live here, so keep it on a volume |
| `LOGIN_TRUST_PROXY` | `true` behind a proxy that appends the real address to `X-Forwarded-For` |
| `LOGIN_ALLOW_PRIVATE` | **Tests only.** Turns off the block on private addresses and the HTTPS requirements |

## Rules

- **Sites** must be registered, use HTTPS, send PKCE (S256) and `state`, and return people to their own origin. Registering needs a sign-in with a website on the same host, which proves ownership.
- **People's websites** follow the spec's profile URL rules: a domain name, with no port, IP address, fragment or credentials. They need an `authorization_endpoint` or `indieauth-metadata`, on HTTPS. A token endpoint and Micropub are not needed.
- **Codes** are single-use, last 60 seconds, are stored only as hashes, and are burned on a failed attempt.
- Requests to other people's sites refuse private addresses and ports other than 80 and 443.

## Tests

```bash
php tests/run.php
```
