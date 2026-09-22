# OAuth, OIDC and external identities

Read when adding or changing an external provider, callback, identity persistence, or account linking. These are protocol and ownership constraints, not a prescribed application architecture.

## Select the integration

Use a maintained framework integration or OAuth/OIDC client. Confirm the installed version handles state, PKCE, discovery, issuer/audience validation, nonce, key rotation and callback errors for the chosen flow. Configure those features instead of reimplementing them.

OAuth authorizes access; OIDC adds an authenticated identity. Prefer OIDC when the provider supports the required login flow. An OAuth-only integration needs the provider's documented identity endpoint and validation rules; an arbitrary access token or profile supplied by the browser is not proof of identity.

For each enabled provider, record from current official documentation:

- Protocol and supported flow, public/confidential client type, discovery/issuer or fixed endpoints, PKCE support and client authentication method.
- Stable subject field and its namespace; email availability and verified-email semantics; required scopes, keeping them minimal.
- Registered callback URL for each environment, response mode, identity validation, timeout/error behavior, and any provider-specific restrictions.
- Whether provider tokens are needed after login. Do not request offline access or retain provider refresh tokens solely to support the application's own session.

Examples to investigate, not hardcoded assumptions:

| Provider | Integration decisions                                                                                                                                                                                                                                                                                          |
| -------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Google   | Use its documented OIDC integration; resolve identity from validated issuer and `sub`. Email is a profile attribute, not the durable identity key. Check audience and the meaning of verified-email/domain claims for the actual account type.                                                                 |
| Yandex   | Check its current Yandex ID OAuth documentation and the selected library's adapter. The authorization-code docs describe PKCE; verify `S256` and token exchange behavior. Map the documented stable ID from its validated identity response; do not assume Google/OIDC claims or email verification semantics. |

Primary references: [OAuth security BCP](https://www.rfc-editor.org/rfc/rfc9700.html), [OIDC Core](https://openid.net/specs/openid-connect-core-1_0.html), [Google OIDC](https://developers.google.com/identity/openid-connect/openid-connect), [Yandex authorization code](https://yandex.ru/dev/id/doc/en/codes/code-url). Follow their linked token/identity documentation for the selected integration; do not freeze provider capabilities in copied code.

## Transaction and callback boundary

Use the library's transaction mechanism. It must correlate the request, provider, callback, PKCE verifier, nonce where used, safe return destination, intent (`login` versus `link`) and initiating session. The transaction expires and cannot be reused. Linking also binds the intended local account and requires that the authenticated session still matches it at completion.

For a backend/BFF exchanging codes or establishing local sessions, perform transaction validation there. A browser-only comparison followed by a backend endpoint accepting any code leaves that endpoint unprotected. Do not accept a caller's `state` without comparing it to an independently established, browser-bound transaction.

For a genuinely public browser/native client, let the maintained client library validate its own transaction and use PKCE; the resource API separately validates issued access tokens. Do not force a server session into that architecture. Public clients cannot keep a client secret. Confidential client secrets stay on the server.

A provider callback may be narrowly exempted from ordinary form-CSRF middleware when the maintained OAuth integration validates a single-use transaction bound to the initiating browser/session. Keep application-origin login, logout, refresh and linking mutations protected. Do not exempt general completion endpoints or rely on SameSite alone. Use the integration's callback routing and correlation-cookie configuration for its actual response mode.

Reject missing, mismatched, expired or replayed state, a missing/mismatched required verifier, unexpected issuer/provider, conflicting success/error parameters and malformed callback data. Check correlation before any identity mutation, including cancellation/error paths. Consume transactions with race-safe library/store semantics; retries require a fresh transaction rather than reusable state or authorization codes. Do not disable PKCE for a public client whose provider cannot support it; choose a supported integration or leave the flow blocked. For confidential clients lacking PKCE support, document that limitation and the alternative transaction protections.

Use the exact configured redirect URI consistently during authorization and exchange when the protocol requires it. For native apps, use the documented platform redirect mechanisms. Resolve endpoints only from trusted provider configuration/discovery; do not fetch arbitrary callback-supplied issuer or key URLs. Prevent provider mix-up by binding and checking the expected issuer or by the library's documented equivalent.

For OIDC, use the library to verify the ID token's signature and allowed algorithms, issuer, audience and applicable authorized party, expiry and relevant time claims. Generate and verify a transaction-bound nonce for the chosen login flow. If requesting UserInfo, its subject must match the validated ID token. A claim called `email_verified` is not permission to merge local accounts.

Apply timeouts and handle provider denial, unavailable endpoints, invalid JSON and invalid identities without creating accounts or sessions. Do not blindly retry a code exchange with an uncertain outcome. Provider failure must not invalidate an unrelated existing session. Keep codes/tokens out of analytics, referrers, proxy/application logs and error screens; finish on a clean URL with a validated return destination and safe fallback.

## Extensible identity ownership

Use the framework's account/identity store if it provides the needed guarantees. Otherwise model the relationship conceptually; SQL tables, documents, managed IdP records or another store are all acceptable:

| Concept              | Required property                                                                                                                                                                                         |
| -------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Local account        | Stable local identifier, account status, profile and access policy remain locally owned. A federated-only account need not have a local password.                                                         |
| External identity    | References its local account and a trusted provider/issuer namespace plus stable opaque subject. One account can have multiple identities.                                                                |
| Uniqueness           | Each namespace/subject pair belongs to at most one local account, enforced atomically by the authoritative store. Include client/tenant namespace only where the provider's subject semantics require it. |
| Profile attributes   | Optional display data with an explicit update policy. Email, username and avatar are not identity keys; missing email must not create a fake address or silently merge accounts.                          |
| Provider credentials | Optional, separately protected, minimal scopes and retention. Encrypt recoverable provider secrets with managed/platform mechanisms; never include them in a public user view.                            |

Do not introduce `googleId`, `yandexId`, etc. columns per provider or a mutually exclusive `authProvider` discriminator when accounts can have multiple sign-in methods. Existing single-provider schemas need a compatibility migration, not destructive replacement. Keep subjects as opaque strings; preserve case/format according to the provider contract.

Resolve a validated identity by namespace/subject first. For an unknown identity, apply the product's registration/invitation policy. If email is absent or unverified and the product requires it, use an explicit collection/verification path. Preserve owner-edited profile values unless the product deliberately treats the provider as authoritative.

On duplicate creation/linking, re-read the exact identity after a constraint conflict and verify ownership. Never catch every persistence error as “already exists,” and never use a colliding email as a recovery shortcut. Keep account creation and linking atomic so failures do not leave unintended accounts or stolen bindings.

## Account linking and unlinking

Linking is a security-sensitive operation separate from login, even when both use the same provider adapter.

- Require an authenticated local account with recent reauthentication appropriate to its assurance level, plus fresh proof of the external identity. Bind that local account and link intent to the OAuth transaction; a caller-supplied account ID is insufficient.
- When a signed-out provider login collides with an existing email, request authentication to the existing account before linking. Do not expose its login methods or account details. Matching emails, even marked verified, do not prove authority over the local account.
- Reject an identity already owned by another local account. Account merging is a separate product operation, not a side effect of a unique-constraint handler. Do not overwrite passwords, permissions, tenant memberships, or another binding during linking.
- Protect link/unlink actions with the applicable CSRF controls. Reauthenticate for unlinking; preserve another usable login/recovery method or explicitly guide the user to establish one first. Revoke no-longer-needed provider grants when supported and authorized, and remove stored credentials. Define the effect on existing application sessions separately.
- Record redacted link/unlink security events. Test callbacks completed after logout, account switching, session expiry, and simultaneous attempts to bind the same identity to different accounts.
