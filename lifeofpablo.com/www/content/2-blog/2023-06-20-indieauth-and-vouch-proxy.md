---
Title: Indieauth and Vouch Proxy
Published: 2023-06-20 11:00:24
Author: Pablo Morales
Layout: blog
Tag: Example
Status: draft
---


## The flow of login and authentication using Google Oauth

- Bob visits `https://lifeofpablo.com`
- the Nginx reverse proxy...

  - recieves the request for `lifeofpablo.com` from Bob
  - uses the `auth_request` module configured for the `/validate` path
  - `/validate` is configured to `proxy_pass` requests to the authentication service at `https://vouch.oursites.com/validate`
    - if `/validate` returns...
      - 200 OK then SUCCESS allow Bob through
      - 401 NotAuthorized then
        - respond to Bob with a 302 redirect to `https://vouch.oursites.com/login?url=https://private.oursites.com`

- Vouch Proxy `https://vouch.oursites.com/validate`

  - recieves the request for private.oursites.com from Bob via Nginx `proxy_pass`
  - looks for a cookie named "oursitesSSO" that contains a JWT
  - if the cookie is found, and the JWT is valid
    - returns `200 OK` to Nginx, which will allow access (bob notices nothing)
  - if the cookie is NOT found, or the JWT is NOT valid
    - return `401 NotAuthorized` to Nginx (which forwards the request on to login)

- Bob is first forwarded briefly to `https://vouch.oursites.com/login?url=https://private.oursites.com`

  - clears out the cookie named "oursitesSSO" if it exists
  - generates a nonce and stores it in session variable \$STATE
  - stores the url `https://private.oursites.com` from the query string in session variable `$requestedURL`
  - respond to Bob with a 302 redirect to Google's OAuth Login form, including the `$STATE` nonce

- Bob logs into his Google account using Oauth

  - after successful login
  - Google responds to Bob with a 302 redirect to `https://vouch.oursites.com/auth?state=$STATE`

- Bob is forwarded to `https://vouch.oursites.com/auth?state=$STATE`
  - if the \$STATE nonce from the url matches the session variable "state"
  - make a "third leg" request of Google (server to server) to exchange the OAuth code for Bob's user info including email address bob@oursites.com
  - if the email address matches the domain oursites.com (it does)
    - issue bob a JWT in the form of a cookie named "oursitesSSO"
    - retrieve the session variable `$requestedURL` and 302 redirect bob back to `https://private.oursites.com`

Note that outside of some innocuos redirection, Bob only ever sees `https://private.oursites.com` and the Google Login screen in his browser. While Vouch does interact with Bob's browser several times, it is just to set cookies, and if the 302 redirects work properly Bob will log in quickly.

Once the JWT is set, Bob will be authorized for all other sites which are configured to use `https://vouch.oursites.com/validate` from the `auth_request` Nginx module.

The next time Bob is forwarded to google for login, since he has already authorized the Vouch Proxy OAuth app, Google immediately forwards him back and sets the cookie and sends him on his merry way. In some browsers such as Chrome, Bob may not even notice that he logged in using Vouch Proxy.
