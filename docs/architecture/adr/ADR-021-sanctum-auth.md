# ADR-021: Migration to Laravel Sanctum for API Authentication

## Status

Accepted (2026-09-25)

## Context

The backend authenticated requests through the `X-User-Id` header forwarded by the Next.js BFF. This approach had a critical vulnerability: anyone able to send an HTTP request directly to the backend could supply an arbitrary `X-User-Id` and access any account without verification.

The frontend stored the session in a signed HMAC cookie (`autobi_session`), but the backend did not participate in session management: logout deleted the frontend cookie, while no backend "session" existed and there was no revocation mechanism.

## Decision

Use **Laravel Sanctum** (API tokens) for authentication:

1. **Login** returns the plainText token once; the frontend stores it in an HttpOnly cookie.
2. **Every request** to the backend contains `Authorization: Bearer {token}`.
3. **Sanctum middleware** (`auth:sanctum`) validates the token, retrieves the user, and sets `authenticated_user_id` in request attributes.
4. **Logout** revokes the current token on the backend.
5. **Password changes** revoke all tokens and require signing in again.
6. **Token lifetime** is 7 days (Sanctum expiration).

### Migration Path

- API v2 with Bearer authentication coexists in parallel.
- Protected v1 routes return `410 Gone`, requiring a new sign-in.
- V1 health endpoints are retained for monitoring backward compatibility.
- The frontend routes all API requests through the BFF proxy (`/api/backend/[...path]`), which adds the Bearer token from the cookie.

### Tokens

- `tokenable_id` is a string (compatible with existing string user IDs).
- The `personal_access_tokens` table is the standard Sanctum table with an adapted ID type.

## Alternatives

| Alternative                    | Reason for Rejection                                                               |
| ------------------------------ | ---------------------------------------------------------------------------------- |
| JWT (self-contained)           | Immediate revocation is impossible without additional infrastructure (a blacklist) |
| Passport (OAuth2)              | Excessive complexity for a single-application use case                             |
| Laravel Session (cookie-based) | Difficult integration with Next.js SSR; stateful sessions scale poorly             |
| Retain X-User-Id               | Critical vulnerability — not an option                                             |

## Consequences

- All existing tests require updates: create a Sanctum token instead of using `'X-User-Id' => 'user-1'`.
- The frontend workspace-gateway and other API gateways do not pass `userId`; authorization is automatic.
- Rate limiters use `authenticated_user_id` from request attributes.
- A coordinated frontend/backend release is mandatory.
