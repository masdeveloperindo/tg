# TG-020 — Integration handoff (BLOCKED: source EA and terminal unavailable)

No `.mq5`/`.mqh`, licensed EA build, approved pure MQL5 Ed25519 implementation, MetaEditor or MT5 terminal is present. This directory is a concrete integration/test handoff, not an integrated EA. Do not enable sales or mark TG-020/G-10/G-11/G-12 complete from PHP, Python or browser results.

The backend implements `POST /api/v1/licenses/verify`; see [`API_CONTRACT.md`](../technical/API_CONTRACT.md). Four real PHP-signed public fixtures are in `tests/fixtures/license_signature_*.json`. `python3 tests/Support/verify-license-vectors.py` checks signatures and identity/nonce binding independently. Fixture public keys and the well-known RFC8032 seed used to reproduce them are TEST ONLY and must never enter a deployed EA/server configuration.

When owner supplies the EA source, integrate into `OnInit` and scheduled verification while protecting every licensed trading function. Read account/server/real-demo from terminal APIs; only key is entered by the user. Generate a new CSPRNG nonce of at least16bytes per request. POST HTTPS JSON max4KiB, initial timeout5000ms, verify at init and every6hours, retry with bounded exponential backoff+jitter. Test `WebRequest` blocking impact; a non-trading harness alone does not establish trading safety.

Pin approved public keys/kid in the binary. Bound the envelope/parser; decode `payload_b64`, verify Ed25519 over those exact bytes before parsing/trusting, then compare protocol/kid/product/account/server/type/request_nonce and version, server timestamp and exclusive expiry. Unknown kid, modified bytes, wrong nonce/identity and malformed responses fail closed. Never substitute an embedded HMAC secret or arbitrary DLL dependency for the required verifier.

## Required real-terminal evidence

| Case | Required outcome |
| --- | --- |
| All4 fixture vectors | Only valid accepted by the actual MQL5 verifier; build/terminal logs archived |
| Valid, unknown key, wrong product/account/server/type | Only exact valid identity allows licensed functions |
| Expiry instant | At `now == expires_at`, stop; no grace beyond expiry |
| Suspended/revoked online | Disable immediately; cached valid result cannot override signed negative verdict |
| Admin account move | New account verifies; old account is rejected online; same key |
| Unknown kid, tampered byte, wrong nonce, malformed/oversize | Fail closed, no secret/key in terminal logs |
| Replayed nonce | Server409; do not interpret as valid or silently enter offline grace |
| Offline after a verified valid result | At most48hours since last online valid, bounded earlier by expiry |
| First launch offline / cache tamper or account-copy / clock rollback | No invented valid cache; test explicit policy and record residual limits |
| Timeout/5xx/DNS versus signed deny/422/409/429 | Only genuine network/server failure may use a still-eligible verified grace; respect rate-limit backoff |
| WebRequest allowlist / host migration | Manual terminal allowlist verified; old host continues serving eligible clients |
| Indicator without separate PoC | Unavailable for licensing/sale |

Archive terminal build/version, source revision, request IDs, safe screenshots and verifier results. Do not archive license keys, signing private keys or raw client data. Supply owner-approved EA source and terminal access before implementing/testing these rows. Backend/UI work continues independently; no simulated result substitutes for this evidence.
