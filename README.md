# OneKhusa Hosted Checkout — PHP (Laravel 12)

A small, config-driven **Laravel 12** app that demonstrates the complete **Hosted Checkout / Request-to-Pay** flow against the **OneKhusa Payment Gateway (sandbox)** — including the failure mode that catches most people out: **what happens when your callback URL isn't reachable or doesn't match.**

**The flow in one line:** a customer buys a ticket on your page → is redirected to OneKhusa's hosted checkout → pays using a TAN → OneKhusa notifies your server over a webhook → your page shows *Payment Verified*.

> **TAN (Timed Account Number)** — a short-lived account number OneKhusa generates for a single transaction. The customer pays into it from their bank or mobile wallet, and it expires after a short window. See the [OneKhusa docs](https://docs.onekhusa.com/api-reference/get-started/quick-integration) for the current expiry period.

This is the PHP counterpart of [`Onekhusa-Request-to-pay-Sample-Integration-python`](https://github.com/garry0498/Onekhusa-Request-to-pay-Sample-Integration-python) — same routes, same architecture, same behaviour.

> ⚠️ **Sandbox only.** This is a teaching sample, not production code. See [Security Notes](#-security-notes) before you reuse any of it.

---

## Table of Contents

1. [Features](#-features)
2. [How It Works](#-how-it-works)
3. [Prerequisites](#-prerequisites)
4. [Quick Start](#-quick-start)
5. [Project Structure](#-project-structure)
6. [Configuration](#-configuration)
7. [API Endpoints](#-api-endpoints)
8. [Webhook & Callback Setup](#-webhook--callback-setup)
9. [When the Callback Is Not Reachable](#-when-the-callback-is-not-reachable)
10. [Troubleshooting](#-troubleshooting)
11. [Security Notes](#-security-notes)
12. [License](#-license)

---

## ✨ Features

| Capability                  | How it's implemented                                                                       |
| --------------------------- | ------------------------------------------------------------------------------------------ |
| **Service-oriented**        | All OneKhusa API logic lives in `App\Services\OneKhusaService`                              |
| **Config-driven**           | Every endpoint and credential resolves through `config/onekhusa.php` — no scattered `env()` calls |
| **Authenticated API calls** | Obtains a short-lived JWT from `POST /account/getAccessToken`, sends it as `Authorization: Bearer`, and includes the `authentication` body block required by the checkout endpoints |
| **Token caching**           | Access tokens are cached for 240s so the token endpoint isn't hit on every request           |
| **Idempotent requests**     | Every call includes an `X-Idempotency-Key` header                                            |
| **Webhook handling**        | Confirms payments and always acknowledges with HTTP 200                                      |
| **Status polling**          | The Blade frontend polls a lightweight Laravel endpoint backed by the cache                  |
| **No database**             | Runs entirely on Laravel's **file cache** — no migrations, no frontend build step            |

---

## 🧠 How It Works

```mermaid
sequenceDiagram
    participant U as User (Browser)
    participant A as Laravel App
    participant O as OneKhusa API
    participant C as OneKhusa Checkout Page
    participant W as Webhook (Laravel)

    U->>A: POST /api/Tickets/buy/{eventId}
    A->>O: POST /account/getAccessToken
    O-->>A: accessToken (JWT)
    A->>O: POST /checkout/rtp/initiate (authentication + Bearer + route URLs)
    O-->>A: paymentTransactionId
    A-->>U: redirectUrl
    U->>C: Redirect to hosted checkout page
    C-->>O: Customer pays (bank / mobile wallet / TAN)
    O->>W: POST callbackApiUrl (payrequest.success)
    W-->>O: HTTP 200 OK (acknowledgement)
    A-->>U: Dashboard polls status → Paid
```

**Step by step:**

1. Your app calls the OneKhusa **initiate** endpoint with an `X-Idempotency-Key` header and a `route` object containing three URLs:
   - **success redirection URL** — where the customer goes after a successful callback
   - **failure redirection URL** — where the customer goes when the callback fails
   - **callback API URL** — where OneKhusa sends the server-to-server payment notification
2. OneKhusa returns a **payment transaction ID**, and your app redirects the customer to the **hosted checkout** page.
3. The customer completes the payment using the **TAN**.
4. OneKhusa sends a **server-to-server POST** to your **callback API URL**. Your server **must** reply **HTTP 200 OK**.
5. Only then does the flow complete and the customer land on the **success page**.

> 💡 The same flow applies regardless of stack — this demo just happens to use Laravel.

---

## 📋 Prerequisites

| Requirement | Notes |
| ----------- | ----- |
| **PHP 8.2+** | Laravel 12 requires it. Check with `php -v`. |
| **Composer 2.x** | Check with `composer --version`. Install from [getcomposer.org](https://getcomposer.org/download/). |
| **PHP extensions** | `ext-curl`, `ext-mbstring`, `ext-openssl`, `ext-json`. Verify with `php -m`. On Windows/XAMPP, uncomment `extension=curl` and `extension=openssl` in `php.ini`. |
| **Git** | To clone this repo. |
| **A OneKhusa sandbox account** | Register at [onekhusa.com/developers](https://onekhusa.com/developers). You need an API key, API secret, organisation ID and merchant number from the merchant portal before anything below will work. |
| **ngrok** (or Cloudflare Tunnel / localtunnel) | OneKhusa must be able to reach your machine over public HTTPS to deliver webhooks. Download from [ngrok.com/download](https://ngrok.com/download) and sign up for a free authtoken. |

> **You cannot complete a payment without a public tunnel.** `localhost` is not reachable from OneKhusa's servers. Budget five minutes for the ngrok setup before you start.

> **No database required.** There are no migrations to run — status is kept in Laravel's file cache under `storage/framework/cache`.

---

## 🚀 Quick Start

Total time: roughly 10 minutes, assuming you already have sandbox credentials.

### 1. Clone the repository

```bash
git clone https://github.com/garry0498/Onekhusa-Request-to-pay-Sample-Integration.git
cd Onekhusa-Request-to-pay-Sample-Integration
```

### 2. Install dependencies

```bash
composer install
```

If you are on Linux or macOS and hit permission errors later, make sure the framework directories are writable:

```bash
chmod -R ug+rw storage bootstrap/cache
```

### 3. Start ngrok first

Do this **before** editing `.env`, so you have a real URL to paste in.

```bash
ngrok http 8080
```

ngrok prints a forwarding line like:

```
Forwarding    https://a1b2-41-70-12-34.ngrok-free.dev -> http://localhost:8080
```

Copy that `https://...` URL and **leave ngrok running in this terminal.**

> 🔁 On the free plan the URL changes every time you restart ngrok. Whenever it changes you must update `.env`, run `php artisan config:clear`, and restart the server.

### 4. Configure your environment

In a **second terminal**:

```bash
cp .env.example .env       # Windows: copy .env.example .env
php artisan key:generate
```

Open `.env` and fill in the sandbox credentials from your merchant portal, plus the ngrok URL from step 3:

```ini
ONEKHUSA_API_KEY=your_api_key
ONEKHUSA_API_SECRET=your_api_secret
ONEKHUSA_ORG_ID=your_org_id
ONEKHUSA_MERCHANT_NUMBER=your_merchant_number

# No trailing slash
PUBLIC_CALLBACK_URL=https://a1b2-41-70-12-34.ngrok-free.dev
```

Then clear any cached config so Laravel picks up the new values:

```bash
php artisan config:clear
```

See [Configuration](#-configuration) for where each value lives in the portal.

### 5. Start the app

```bash
php artisan serve --port=8080
```

Open **<http://localhost:8080>**. You should see the **OneTicket Fintech** dashboard: a reference field with a **Generate** button, an **Amount** field, a **Description** field, and a **PURCHASE WITH HOSTED CHECKOUT** button.

<!-- TODO(maintainer): add docs/dashboard.png here so new users can confirm they're in the right state -->

If the page loads, your setup is correct so far. It does **not** yet prove your webhook works — step 6 does.

### 6. Run a test payment

1. Click **Generate** for a reference (or type your own).
2. Enter an **Amount** and **Description**, then click **PURCHASE WITH HOSTED CHECKOUT**.
3. You're redirected to the **OneKhusa Hosted Checkout** page, which shows a **TAN**.
4. Complete the sandbox payment. If you're driving this from the API rather than the checkout UI, OneKhusa's *Simulate Accept Request To Pay* endpoint plays the role of the paying customer.
5. You return to `/?ref=...`, a syncing overlay appears, and **Payment Verified!** shows once the webhook marks the status `Paid`.

**What success looks like in your logs:**

```bash
tail -f storage/logs/laravel.log
```

```
OneKhusa webhook received
status updated to PAID
```

If you never see those two lines, the payment may still have succeeded — jump to [When the Callback Is Not Reachable](#-when-the-callback-is-not-reachable).

### 7. (Optional) Break it on purpose

Seeing the failure mode once is worth more than reading about it:

1. Stop ngrok, or set a deliberately wrong `PUBLIC_CALLBACK_URL` and run `php artisan config:clear`.
2. Repeat step 6 and complete the payment.
3. The checkout page shows **Failed Payment** and you land on the failure page — **even though the payment succeeded.**
4. Restore the correct URL, clear config, restart, and try again.

---

## 📂 Project Structure

```
Onekhusa-Request-to-pay-Sample-Integration/
├── app/
│   ├── Http/Controllers/
│   │   ├── Controller.php              # Base controller
│   │   ├── TicketController.php        # Hosted checkout: buy + status
│   │   └── WebhookController.php       # OneKhusa webhook events
│   ├── Providers/
│   │   └── AppServiceProvider.php      # Service container registration
│   └── Services/
│       └── OneKhusaService.php         # All OneKhusa API logic
├── bootstrap/
│   ├── app.php                         # Application bootstrap / routing config
│   └── providers.php                   # Registered service providers
├── config/
│   ├── onekhusa.php                    # OneKhusa credentials + endpoints
│   └── app.php, cache.php, logging.php, ...   # Laravel framework configuration
├── database/                           # Empty — no migrations needed (file cache)
├── public/
│   └── index.php                       # Front controller
├── resources/views/
│   └── welcome.blade.php               # Hosted checkout dashboard + status polling
├── routes/
│   ├── web.php                         # Web routes
│   └── api.php                         # Integration endpoints
├── storage/                            # Logs, cache, sessions
├── .env                                # Your credentials — never commit this
├── .env.example                        # Template for .env
├── artisan                             # CLI entry point
├── composer.json                       # PHP dependencies
└── phpunit.xml                         # PHPUnit configuration
```

---

## 🔧 Configuration

All settings live in `.env` and are read through `config/onekhusa.php`. **Never call `env()` outside a config file** — Laravel returns `null` from `env()` once config is cached.

| Variable                   | Required | Purpose                             | Where to find it                                      |
| -------------------------- | :------: | ----------------------------------- | ----------------------------------------------------- |
| `ONEKHUSA_API_KEY`         | ✅ | Sandbox **API key**                 | Merchant portal → **API / Credentials**               |
| `ONEKHUSA_API_SECRET`      | ✅ | Sandbox **API secret**              | Merchant portal → **API / Credentials**               |
| `ONEKHUSA_ORG_ID`          | ✅ | **Organisation ID**                 | Merchant portal → **Organisation / Account settings** |
| `ONEKHUSA_MERCHANT_NUMBER` | ✅ | **Merchant account number**         | Merchant portal → **Merchant / Accounts**             |
| `PUBLIC_CALLBACK_URL`      | ✅ | Public HTTPS base URL for redirects + webhooks. **No trailing slash.** | Your ngrok URL in development |
| `APP_KEY`                  | ✅ | Laravel encryption key              | Generated by `php artisan key:generate`               |
| `APP_DEBUG`                | — | Debug mode (`true` / `false`)       | Set `false` anywhere but your own machine             |
| `CACHE_STORE`              | — | Cache driver (defaults to `file`)   | Leave as `file` for this demo                         |

> ⚠️ Always use **your own** portal values. Never commit `.env` — it's already in `.gitignore`. If a key is ever exposed, rotate it in the merchant portal immediately.

> 🔁 **After every `.env` change, run `php artisan config:clear` and restart the server.** This is the single most common reason a "correct" callback URL still doesn't work.

---

## 🔌 API Endpoints

All integration routes are prefixed with `/api` (see `routes/api.php`).

| Method | Endpoint                          | Description                                                                              |
| ------ | --------------------------------- | ---------------------------------------------------------------------------------------- |
| `GET`  | `/`                               | Hosted checkout dashboard. `?ref=` resumes polling for a reference; `?failed=1` shows the failure state. |
| `POST` | `/api/Tickets/buy/{eventId}`      | Initiate a hosted checkout. Body: `reference`, `amount`, `description`. Returns `redirectUrl`. |
| `GET`  | `/api/Tickets/status/{reference}` | Poll the status of a checkout reference.                                                 |
| `POST` | `/api/webhooks/payments`          | OneKhusa event endpoint (`payrequest.success`). Must reply `200 OK`.                     |

**About the parameters:**

- **`{eventId}`** — an arbitrary identifier in this demo (it stands in for "which ticket is being bought"). It is not validated against anything, so any string works. In a real app this would be your own event or product ID.
- **`reference`** — your unique transaction reference. It's the key everything else hangs off: status polling, webhook matching, and support tickets. Keep it unique per transaction.
- **`amount`** — sent to OneKhusa as-is. **Confirm against the [API reference](https://docs.onekhusa.com/api-reference/get-started/quick-integration) whether the gateway expects major units (`2500` = MWK 2,500) or minor units (`2500` = MWK 25.00) before you use this pattern with real money.**

**Example — initiate a checkout:**

```bash
curl -X POST http://localhost:8080/api/Tickets/buy/event123 \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"reference":"OT-PHP-123","amount":2500,"description":"OneTicket"}'
```

```json
{
  "status": "success",
  "reference": "OT-PHP-123",
  "paymentTransactionId": "J_s4o6miAtYxJ96ov4quBKhjgS-mbBe...",
  "redirectUrl": "https://checkout.onekhusa.com/requestToPay/initiate?ptid=J_s4o6miAtYxJ96ov4quBKhjgS-mbBe..."
}
```

> 💡 Always send `Accept: application/json` when calling these routes by hand — without it, Laravel may return an HTML error page instead of a JSON error body.

**Example — poll a status:**

```bash
curl -H "Accept: application/json" \
  http://localhost:8080/api/Tickets/status/OT-PHP-123
```

**Statuses** are cached per reference: `Pending` → `Paid` | `Failed` | `NotFound`.

> 🗒️ Because this uses the **file cache**, statuses survive a server restart — but `php artisan cache:clear` wipes them all, and any reference you look up afterwards returns `NotFound`. A real integration would persist status to a database rather than a cache.

---

## 📡 Webhook & Callback Setup

1. Your app must be publicly reachable — run **ngrok** as shown in [Quick Start](#-quick-start).
2. `PUBLIC_CALLBACK_URL` in `.env` must match your **current** ngrok URL, with no trailing slash. Run `php artisan config:clear` after changing it.
3. Register `https://your-id.ngrok-free.dev/api/webhooks/payments` as the callback URL in the OneKhusa merchant portal, and subscribe to the `payrequest.success` event.
4. The app builds these three URLs automatically when it initiates a checkout:
   - Success: `PUBLIC_CALLBACK_URL/?ref={reference}`
   - Failure: `PUBLIC_CALLBACK_URL/?ref={reference}&failed=1`
   - Callback: `PUBLIC_CALLBACK_URL/api/webhooks/payments`

When OneKhusa sends a `payrequest.success` event to `/api/webhooks/payments`, the app:

1. Resolves your `reference` from the payload
2. Marks the status `Paid` (or `Failed`) in the cache
3. Replies **HTTP 200 OK** so OneKhusa doesn't retry

### Testing the webhook without paying

You can prove your handler works before touching the checkout page. Send yourself a fake event:

```bash
curl -X POST http://localhost:8080/api/webhooks/payments \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"event":"payrequest.success","data":{"reference":"OT-PHP-123"}}'
```

Then poll the status — it should flip to `Paid`. If it doesn't, the problem is in `WebhookController`, not in ngrok.

<!-- TODO(maintainer): replace the body above with a real captured sandbox payload so field names match exactly -->

> 🛡️ **CSRF:** webhook routes must live in `routes/api.php`, not `routes/web.php`. A webhook posted to a web route is rejected with `419 Page Expired`, OneKhusa never gets its `200 OK`, and the customer sees a failure.

### Inspecting live webhooks

Open **<http://localhost:4040>** while ngrok is running. That's ngrok's request inspector — it shows every request OneKhusa sends you, the full body, and your response code. It also lets you **replay** a request, which is the fastest way to debug a handler without paying again.

---

## ⚠️ When the Callback Is Not Reachable

This is the single most important scenario to understand.

**The customer completes the payment successfully** — but OneKhusa cannot reach your **callback API URL** (wrong URL, no tunnel, server down, or a non-200 response):

```mermaid
sequenceDiagram
    participant C as Customer
    participant O as OneKhusa
    participant F as Failure Page
    participant L as Laravel (unreachable)

    C->>O: Pays successfully via bank / mobile wallet
    O--xL: POST callbackApiUrl (server unreachable / URL mismatch)
    Note over O: No HTTP 200 OK received
    O-->>C: Hosted checkout shows "Failed Payment"
    C->>F: Redirected to configured failure page
    Note over C,F: The PAYMENT actually SUCCEEDED — only the callback failed
```

**The key point:** the payment itself was successful. The **failure page** is shown because OneKhusa could not deliver the confirmation to your server.

> 🚨 **A successful payment and a successful callback are two separate parts of the flow.** The callback endpoint must be reachable **and** must acknowledge with `HTTP 200 OK` for the checkout to complete successfully.

> 🔁 OneKhusa retries undelivered webhooks for a period after the first attempt, so a callback that was briefly down may still be delivered once you bring your tunnel back up. Check the current retry window and schedule in the [OneKhusa developer docs](https://onekhusa.com/developers) — don't rely on retries as a substitute for a reachable endpoint.

### Callback URL Checklist

If the checkout shows **Failed Payment** even though the customer paid, verify:

1. ✅ The URL is **correct** — it matches your *current* ngrok URL in `.env` **and** the URL registered in the OneKhusa portal, and you ran `php artisan config:clear` and restarted after changing it
2. ✅ It uses **HTTPS**
3. ✅ It is **publicly accessible** — not `localhost`, not `127.0.0.1`
4. ✅ It **accepts POST** requests and lives in `routes/api.php` (no CSRF `419`)
5. ✅ It **returns `HTTP 200 OK`** — check <http://localhost:4040> to confirm what you actually returned
6. ✅ There is **no trailing slash** on `PUBLIC_CALLBACK_URL`

---

## 🔍 Troubleshooting

### For developers

**Fastest check first:** `grep "OneKhusa webhook received" storage/logs/laravel.log`

- **Not there** → OneKhusa never reached you. It's a URL, tunnel, or routing problem.
- **There, but the customer saw a failure** → you reached the handler but didn't return `200`. Check `localhost:4040` for the response code and the log for an exception.

| Symptom | Likely cause | Fix / which file |
| ------- | ------------ | ---------------- |
| `401` / `403` from `/account/getAccessToken` | Wrong or swapped API key/secret, or production credentials against the sandbox | Re-copy both values from the portal; check for trailing whitespace — `.env`, `config/onekhusa.php` |
| `.env` change had no effect | Laravel served a cached config | `php artisan config:clear` and restart |
| `No application encryption key has been specified` | `APP_KEY` empty | `php artisan key:generate` |
| `500` with `failed to open stream: Permission denied` | `storage/` or `bootstrap/cache` not writable | `chmod -R ug+rw storage bootstrap/cache` |
| `Class "CurlHandle" not found` / TLS errors | `ext-curl` or `ext-openssl` disabled | Enable them in `php.ini`, restart PHP |
| `Address already in use` on `artisan serve` | Port 8080 taken | `php artisan serve --port=8090` and run `ngrok http 8090` to match |
| Webhook returns `419 Page Expired` | Route registered in `web.php`, so CSRF applies | Move it to `routes/api.php` |
| Webhook returns `404` | Route not registered or wrong method | `routes/api.php` — confirm `POST api/webhooks/payments`; check `php artisan route:list` |
| Checkout initiates, but the webhook never arrives | ngrok not running, URL changed, or config not cleared | Restart ngrok, update `PUBLIC_CALLBACK_URL`, `config:clear`, restart. Confirm at <http://localhost:4040>. |
| Webhook arrives but status stays `Pending` | Reference in the payload doesn't match the initiated one | Log the raw payload and compare — `app/Http/Controllers/WebhookController.php` |
| Payment confirmed but the page never updates | Frontend polling logic | `resources/views/welcome.blade.php` (`checkPaymentStatus`) |
| Status was `Paid`, now `NotFound` | Someone ran `php artisan cache:clear` | Expected; start a new transaction |
| Wrong base URL or endpoint path | Endpoint configuration | `config/onekhusa.php` |
| Route URLs built incorrectly | `successRedirectionUrl`, `failureRedirectionUrl`, `callbackApiUrl` | `app/Services/OneKhusaService.php` |
| ngrok shows `ERR_NGROK_4018` | No authtoken configured | `ngrok config add-authtoken <your-token>` |
| Hosted checkout loads but the TAN has expired | TANs are short-lived | Start a new checkout |

### For customers seeing "Failed Payment"

- The payment may still have **succeeded** — keep the confirmation from your bank or mobile wallet
- **Keep your reference number and TAN** along with any transaction details
- **Contact the merchant's support team** and give them the reference

---

## 🔒 Security Notes

This is a sandbox demo. Before adapting it for production:

- **Verify webhook authenticity.** This sample accepts any POST to `/api/webhooks/payments`. A live endpoint must verify that the request genuinely came from OneKhusa — signature verification, mTLS, or IP allowlisting, per their documentation. Without this, anyone who finds your URL can mark orders as paid.
- **Don't trust the callback for the amount.** Confirm that the paid amount and currency in the webhook match what you initiated, not just that the reference exists.
- **Never commit `.env`.** It's gitignored, but check `git log -p -- .env` if you're unsure, and rotate any key that has ever been committed.
- **Persist state properly.** The cache is not a ledger. Real integrations need a database row per reference, with an audit trail.
- **Handle duplicate webhooks.** OneKhusa may retry, so the same event can arrive more than once. Make the handler idempotent — marking an already-`Paid` reference as `Paid` should be a no-op, not a second fulfilment.
- **Set `APP_DEBUG=false` anywhere but your own machine.** Laravel's debug page prints environment variables, including your API secret, to anyone who triggers an exception.
- **Reuse idempotency keys on retries.** Sending a *new* `X-Idempotency-Key` when retrying a failed initiate can create a duplicate transaction.
- **Log references, never credentials.** Don't log API secrets or full JWTs — and remember `storage/logs/laravel.log` is plain text.
- **Rate-limit the webhook route** so an attacker can't hammer it, and keep it out of any `auth` middleware group that would reject OneKhusa's request.

---

## 📄 License

<!-- TODO(maintainer): add a LICENSE file and name it here, e.g. MIT -->

No license file is currently included. Until one is added, this code is "all rights reserved" by default and cannot be reused. If you intend it as a public sample, add a permissive license such as MIT.

---

## Useful links

- [OneKhusa developer portal](https://onekhusa.com/developers)
- [OneKhusa quick integration guide](https://docs.onekhusa.com/api-reference/get-started/quick-integration)
- [Python/Flask counterpart of this sample](https://github.com/garry0498/Onekhusa-Request-to-pay-Sample-Integration-python)
- [Laravel 12 documentation](https://laravel.com/docs/12.x)
