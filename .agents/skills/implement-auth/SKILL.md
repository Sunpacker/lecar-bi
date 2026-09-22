---
name: implement-auth
description: Implement, extend, or repair application authentication and authorization, including sessions, protected resources, OAuth/OIDC providers, and account linking. Use for working auth flows across existing stacks, not merely login-page styling or building an identity provider.
---

# Implement authentication and authorization

Follow **DISCOVER → DESIGN → IMPLEMENT → VERIFY → FIX**. Preserve the requested scope and the project's architecture. Authentication establishes identity; authorization decides access. Neither a visible login screen nor a valid token alone proves both work.

## DISCOVER

Inspect instructions, manifests/lockfiles, configuration, entrypoints, routes, models, migrations, tests, and deployment files before choosing a solution. Treat documentation as intent until confirmed in code. Record evidence and unknowns for:

| Area                | Determine                                                                                                                                        |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| Architecture        | Deployment units, trust boundaries, domain/module ownership, DDD/Clean Architecture/MVC or other conventions.                                    |
| Clients and servers | Languages, framework/runtime versions, SSR/SPA/native/API-only clients, origins and reverse proxies; a frontend may be absent.                   |
| Users and access    | User identifiers, credentials, account status, tenants, roles/permissions/ownership, existing external identities.                               |
| Auth infrastructure | Middleware/guards/policies, current-user contract, session/token creation, storage, validation, refresh, logout, revocation and recovery.        |
| Persistence         | DB, ORM/query layer or managed identity store, constraints, migrations and transaction/concurrency guarantees; an ORM may be absent.             |
| Quality gates       | Actual test, lint, typecheck/static-analysis, build/package and startup commands, including CI-only checks.                                      |
| Environment         | Docker or other runtime setup, CI, TLS, public URLs, callback registrations, configuration/secret injection and target environment.              |
| Libraries           | Installed auth/OAuth/OIDC mechanisms and compatible maintained libraries; validate capabilities against official docs for the selected versions. |

Trace one existing request end to end. Distinguish reusable security properties, framework mechanisms to reuse, and product-specific rules to preserve locally. Do not copy another project's providers, schema, grants, lifetimes, roles, or package scripts. Read secret names and configuration boundaries without printing secret values.

## DESIGN

State the chosen flow, trust boundaries, session lifecycle, authorization policy, identity ownership, failure behavior, and verification plan before editing. Ask only about missing product decisions that materially affect access or account ownership; continue independent work.

- Reuse framework auth/session/CSRF/policy mechanisms and mature OAuth/OIDC libraries first. Add a dependency only for a demonstrated gap; check maintenance, version compatibility, provider support and built-in protections. Do not replace working auth or introduce custom cryptography/protocol code for convenience. If no suitable integration exists, document the gap and keep custom work narrowly scoped and tested.
- Choose sessions or tokens from client topology, trust boundaries and revocation requirements, not language. A first-party browser often fits a framework session; native/API clients may need bearer tokens. Record cookie versus header transport, storage, expirations and revocation latency. If issuing refresh tokens, define rotation/reuse detection or supported sender constraints; public OAuth clients require one of these protections. A JWT is not inherently stateless once immediate revocation is required.
- Fit existing boundaries: framework handlers/policies in MVC, existing use cases/ports in DDD or Clean Architecture, owning modules in a modular monolith, explicit issuer/audience and policy ownership across services. Do not require new layers, repositories, a separate identity service, or a particular database.
- Define login/logout and current-user contracts, guest behavior, protected routes/API, ownership/tenant checks, safe post-login destination, disabled-account handling, and how changed permissions take effect. UI guards improve navigation; the trusted resource owner enforces access.
- For external login or linking, read [OAuth and external identities](references/oauth-identities.md) before designing callbacks or storage. Google and Yandex are examples, not mandatory integrations. Registration, recovery, MFA, or session-management UI are added only when requested or needed to keep the changed flow safe.

## Security invariants

Apply each invariant at its actual trust boundary. Mark an item inapplicable only with a concrete architectural reason.

- **OAuth transaction:** Bind unpredictable, expiring, single-use `state` to the initiating client transaction and validate it before accepting the callback. Use authorization code with PKCE `S256` for public clients and for confidential clients where supported; verify support and never silently downgrade. The OAuth reference defines callback and OIDC validation.
- **CSRF:** Protect cookie-authenticated mutations, including login/logout, refresh and linking, with framework CSRF protections appropriate to the topology. Account for cookies even when bearer headers also work. SameSite and CORS alone are not sufficient. Header-only bearer APIs need a documented reason for CSRF inapplicability, not a blanket exemption for “JWT.”
- **Cookies:** Credential cookies are server-set, `HttpOnly`, `Secure` in HTTPS environments, explicitly `SameSite`, and narrowly scoped in domain/path. Choose SameSite for the real callback method and site relationship; `None` requires `Secure`. A local HTTP exception stays local. Delete cookies with matching scope. Never expose credentials just to let the UI infer login status.
- **Redirects:** Validate registered callback URLs independently from post-login return URLs. Use configured canonical origins and framework URL validation; reject external destinations unless explicitly allowlisted, including encoded/normalized bypasses. Do not trust arbitrary Host/forwarded headers.
- **Session lifecycle:** Regenerate session identity after authentication and privilege elevation to prevent fixation. Enforce expiry and revocation at the trusted validator, including disabled/deleted accounts. Define idle/absolute limits and token renewal where applicable. Logout invalidates the intended server session/grant, clears local auth state, and does not claim server revocation if it failed. Bound residual access-token validity explicitly.
- **Credentials:** Keep server secrets out of frontend bundles, public runtime config, URLs, logs and git. Prefer protected server storage for browser refresh credentials; do not default to persistent JavaScript-readable token storage. Use platform CSPRNG and maintained password-hashing/token-validation libraries. Verify JWT signature, allowed algorithm, issuer, audience, time and token purpose; decoding is not verification. Do not use an ID token as an API access token.
- **Account safety:** Use stable provider subjects, not email, as external identity keys. No automatic linking solely by matching email. Require proof of both identities for linking. Limit login/recovery abuse and avoid account enumeration through response text, status or obvious timing differences. Keep diagnostic detail in redacted operational events.
- **Authorization:** Deny by default; check action, resource ownership and tenant on the server for every relevant entrypoint, including alternative transports. Never trust client-supplied user IDs, roles or ownership claims. Define policy freshness; prevent self-escalation through role/profile updates. Fail closed on unavailable security dependencies.
- **Concurrency and isolation:** Make identity linking, one-time consumption and session rotation atomic using available store guarantees. Test replay and simultaneous requests; never recover a uniqueness conflict by attaching a different identity. Keep SSR/session state scoped to a request/user and private responses out of shared caches.

For implementation-specific security questions, consult official library docs and the relevant OWASP guides: [session management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html), [CSRF](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html), [authentication](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html), and [authorization](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html).

## IMPLEMENT

Implement the smallest complete vertical flow using the design and existing conventions: configuration/storage → trusted auth and policy enforcement → client behavior, when a client exists. Preserve working login methods and account data during migrations.

Centralize current-user/session handling within existing mechanisms. Return a minimal user view; do not expose password hashes or provider credentials. Distinguish anonymous/expired credentials from forbidden actions using the project's protocol conventions. Avoid login loops, unbounded refresh retries, and stale in-flight responses restoring a logged-out session. Clear private client caches on logout/account switch.

Handle cancellation, timeouts, malformed provider responses and partial failure explicitly. Add migrations/constraints and redacted security events where behavior requires them. Update environment examples with names and safe sample values, callback registrations and operational documentation alongside the code.

## VERIFY → FIX

Use [verification and completion](references/verification.md) to select behavioral, integration and runtime checks for the actual topology. Run the discovered commands and retain outcomes; do not substitute invented scripts or mocks for required environment evidence.

For each failure: identify whether it is code, configuration, provider behavior or environment; fix the cause; add a focused regression check for a demonstrated defect; rerun affected checks and required gates. Never pass verification by weakening state/PKCE/CSRF/TLS validation or suppressing failures. Stop dependent work when credentials, approval, or external access is missing; report the exact blocker and continue checks that remain possible. Do not repeatedly retry account mutations or live logins without a new reason.

Avoid these anti-patterns: frontend-only access control; email-based identity merging; per-provider columns added to the user model; handwritten OAuth when a maintained integration fits; global per-user state; insecure cookie workarounds; treating a successful build or mocked callback as complete authentication.

## Definition of Done

Authentication is complete only when required flows and negative security cases pass; lint and typecheck/static analysis pass; the production build or stack-equivalent packaging passes; the application starts and its auth flow is exercised; the named target environment is verified; and env examples/documentation reflect the result.

For interpreted stacks without a compile step, identify and run their deployment/package/import/config validation equivalent. Record missing tooling or access as a gap, never as a passed check. Containerization, an ORM, and a frontend are not prerequisites. Report changed behavior, decisions, commands/results, environment tested and remaining blockers. If a required gate is blocked or failing, say **incomplete** and specify what evidence is still needed.
