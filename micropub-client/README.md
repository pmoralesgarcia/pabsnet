# Post

A small [Micropub](https://micropub.spec.indieweb.org/) client. Anyone whose website supports IndieAuth and Micropub can sign in with their URL and publish to their own site. It has no database, and a person's token lives only in their server-side session until they sign out. Sessions don't survive a container restart.

## Settings

| Variable | Purpose |
|---|---|
| `MICROPUB_CLIENT_URL` | This app's public URL, which is also its IndieAuth `client_id` |
| `MICROPUB_CLIENT_ONE_CLICK_ME` | The one website that may start sign-in from a plain link, for the Selfauth admin shortcut |
| `MICROPUB_CLIENT_TRUST_PROXY` | `true` behind a proxy that appends the real address to `X-Forwarded-For` |
| `MICROPUB_CLIENT_ALLOW_PRIVATE` | **Tests only.** Turns off the block on private addresses and the HTTPS requirement |

## Security

- Fetching other people's sites goes through `SafeHttp`, which refuses private addresses and ports other than 80 and 443, and pins each connection to the address it checked.
- Bearer tokens are never sent through a redirect.
- Sign-in uses PKCE and a one-use `state`, checks `iss`, and confirms that a returned `me` belongs to the server that signed the person in.
- All pages send a strict Content-Security-Policy and use no JavaScript.

## Tests

```bash
php tests/run.php
```
