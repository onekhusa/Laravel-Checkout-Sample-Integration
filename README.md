# OneKhusa Hosted Checkout — Technical Walkthrough

A Laravel 12 integration with the **OneKhusa Payment Gateway** (sandbox) that demonstrates the complete **Hosted Checkout** flow — and, most importantly, what happens when the **callback URL is not reachable or does not match**.

The project implements a single **Hosted Checkout** flow behind a clean, config-driven service layer: the customer enters their payment details, is redirected to OneKhusa's managed payment page, completes the payment there, and is redirected back when the callback completes.

Payment confirmations are delivered via OneKhusa **webhooks** (`payrequest.success`) and stored in the cache so the frontend can poll status without touching the API.

---

## 🚀 Features

- **Service-Oriented Architecture** — all OneKhusa API logic lives in `App\Services\OneKhusaService`.
- **Config-driven** — every endpoint and credential is resolved through `config/onekhusa.php` (no scattered `env()` calls).
- **Authenticated API calls** — obtains a short-lived JWT via `POST /account/getAccessToken` and sends it as `Authorization: Bearer`, with the required body `authentication` block for checkout endpoints.
- **Token caching** — access tokens are cached (240s) to avoid hitting the token endpoint on every request.
- **Idempotent requests** — every call includes an `X-Idempotency-Key` header.
- **Webhook handling** — confirms payments, always acknowledges with HTTP 200.
- **Status polling** — the frontend polls a lightweight Laravel endpoint backed by the cache.

---

## 📂 Project Structure

```text
onekhusa-laravel-integration/
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
│   └── app.php, auth.php, cache.php,   # Laravel framework configuration
│       database.php, filesystems.php, logging.php, mail.php,
│       queue.php, sanctum.php, services.php, session.php
├── database/                           # Empty — no migrations needed (file cache)
├── public/
│   └── index.php                       # Front controller
├── resources/views/
│   └── welcome.blade.php               # Hosted checkout dashboard
├── routes/
│   ├── web.php                         # Web routes
│   └── api.php                         # Integration endpoints
├── storage/                            # Logs, cache, sessions (framework)
├── .env                                # Credentials (never commit)
├── .env.example                        # Template for .env
├── artisan                             # CLI entry point
├── composer.json                       # PHP dependencies
└── phpunit.xml                         # PHPUnit configuration
```

---

## 🛠️ Setup

> No database, migrations, or frontend build step is required — the demo runs entirely on **file cache**.

### 1. Install dependencies

```bash
composer install
```

### 2. Configure environment

Copy `.env.example` to `.env`, then fill in the **sandbox credentials issued to you in the OneKhusa merchant portal**:

```env
ONEKHUSA_API_KEY=
ONEKHUSA_API_SECRET=
ONEKHUSA_ORG_ID=YOUR_ORG_ID
ONEKHUSA_MERCHANT_NUMBER=

# Public callback URL (your ngrok URL in development)
PUBLIC_CALLBACK_URL=https://your-id.ngrok-free.dev
```

| Env variable | What it is | Where to find it |
| ------------ | ---------- | ---------------- |
| `ONEKHUSA_API_KEY` | Your sandbox **API key** | OneKhusa merchant portal → **API / Credentials** |
| `ONEKHUSA_API_SECRET` | Your sandbox **API secret** | OneKhusa merchant portal → **API / Credentials** |
| `ONEKHUSA_ORG_ID` | Your **organisation ID** | OneKhusa merchant portal → **Organisation / Account settings** |
| `ONEKHUSA_MERCHANT_NUMBER` | Your **merchant account number** | OneKhusa merchant portal → **Merchant / Accounts** |
| `PUBLIC_CALLBACK_URL` | Public URL OneKhusa uses for redirects + webhooks | Your ngrok URL in development |

> ⚠️ Use **your own** portal values — do not copy credentials from another merchant. If a key is ever exposed, rotate it in the merchant portal.

### 3. Generate the app key & start

```bash
php artisan key:generate
php artisan serve --port=8080
```

---

## 🧪 Try the Demo

Follow these steps to test the **Hosted Checkout** flow end-to-end.

### 1. Start the app

```bash
php artisan serve --port=8080
```

Open **http://localhost:8080** in your browser — you should see the **OneTicket Fintech** dashboard.

### 2. Expose your app with ngrok (needed for the callback)

The hosted checkout only completes when OneKhusa can reach your **callback API URL**. In a second terminal:

```bash
ngrok http 8080
```

Copy the `https://...ngrok-free.dev` URL and set it in `.env`:

```env
PUBLIC_CALLBACK_URL=https://your-id.ngrok-free.dev
```

Then restart the server: `php artisan serve --port=8080`.

### 3. Test the Hosted Checkout flow

1. On the dashboard, click **Generate** for a reference number, or type your own.
2. Enter an **Amount** and a **Description**, then click **PURCHASE WITH HOSTED CHECKOUT**.
3. You are redirected to the **OneKhusa Hosted Checkout** page.
4. Complete the (sandbox) payment — the page provides a **TAN** to use.
5. You are redirected back to `/?ref=...`, the syncing overlay appears, and the **Payment Verified!** success screen shows once the webhook updates the status to `Paid`.

> Watch the callback arrive in the logs:
> ```bash
> tail -f storage/logs/laravel.log   # look for "OneKhusa webhook received" + "status updated to PAID"
> ```

### 4. Test the callback-failure scenario (optional)

To see what happens when the callback is unreachable:

1. Stop ngrok (or set a wrong `PUBLIC_CALLBACK_URL`).
2. Repeat step 3 — complete the payment on the hosted page.
3. The checkout page shows **Failed Payment** and you are sent to the failure page, **even though the payment succeeded**.
4. Restore ngrok / the correct URL to fix it. See the [Troubleshooting](#-troubleshooting--what-to-do--which-files-to-check) section.

---

## 🔌 API Endpoints

All integration routes are prefixed with `/api` (see `routes/api.php`).

### Hosted Checkout

| Method | Endpoint | Description |
| ------ | -------- | ----------- |
| `POST` | `/api/Tickets/buy/{eventId}` | Initiate hosted checkout for an event. Body: `reference`, `amount`, `description`. Returns `redirectUrl` to the hosted checkout page. |
| `GET`  | `/api/Tickets/status/{reference}` | Poll status for a checkout reference. |

### Webhooks

| Method | Endpoint | Description |
| ------ | -------- | ----------- |
| `POST` | `/api/webhooks/payments` | OneKhusa event endpoint (`payrequest.success`). Must respond `200 OK`. |

### Statuses

Statuses are cached per reference: `Pending` → `Paid` | `Failed` | `NotFound`.

---

## 📡 Webhook Setup (ngrok)

```bash
ngrok http 8080
```

1. Copy the generated URL into `PUBLIC_CALLBACK_URL` in your `.env`.
2. Register `https://your-id.ngrok-free.dev/api/webhooks/payments` as the callback URL in the OneKhusa portal.
3. Restart the server: `php artisan serve --port=8080`.

---

## 🧠 How It Works

### Hosted Checkout Flow

The process starts when your application sends a `POST` request to the OneKhusa **Request to Pay initiate endpoint**. The request includes:

- The required **`X-Idempotency-Key`** header.
- A **`route`** object containing the three URLs that drive the flow:
  - **success redirection URL** — where the customer goes when the callback succeeds.
  - **failure redirection URL** — where the customer goes when the callback fails.
  - **callback API URL** — where OneKhusa sends the server-to-server payment notification.

> The same integration flow applies regardless of the technology stack — this demo happens to use Laravel.

```mermaid
sequenceDiagram
    participant U as User
    participant A as Laravel App
    participant O as OneKhusa API
    participant C as Checkout Page
    participant W as Webhook (Laravel)

    U->>A: POST /api/Tickets/buy/{eventId}
    A->>O: POST /account/getAccessToken (Bearer JWT)
    O-->>A: accessToken
    A->>O: POST /checkout/rtp/initiate (authentication + Bearer + route URLs)
    O-->>A: paymentTransactionId
    A-->>U: redirectUrl
    U->>C: Redirect to OneKhusa Hosted Checkout
    C-->>O: Customer pays via TAN / bank / mobile wallet
    O->>W: POST callbackApiUrl (payrequest.success)
    W-->>O: HTTP 200 OK (acknowledgement)
    A-->>U: Frontend polls status -> Paid
```

Once the request is successfully initiated, OneKhusa returns a **payment transaction ID** and your app redirects the customer to the **OneKhusa Hosted Checkout**. The customer completes the payment using a **TAN** (Timed Account Number). OneKhusa then sends a **server-to-server POST** to your **callback API URL**. Your server **must** respond with **HTTP 200 OK** — this acknowledgment confirms to OneKhusa that the payment notification was received. Only then does the checkout flow complete and the customer get redirected to the configured **success page**.

---

## ⚠️ When the Callback URL Is Not Reachable (or Does Not Match)

This is the most important scenario to understand.

**The customer completes the payment successfully** through their bank or mobile wallet. OneKhusa then attempts to send the payment payload to the **callback API URL** provided during initiation. If that URL is **incorrect or unreachable**:

1. Our server cannot be reached, so it **cannot return `HTTP 200 OK`**.
2. Because the callback was not delivered, the OneKhusa checkout page displays **Failed Payment**.
3. The customer is redirected to the configured **failure page**.

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

**Here is the important part: the payment itself was successful.** The failure shown on the checkout page happened because OneKhusa could not communicate the payment confirmation to your system through the callback URL.

> **Key takeaway: a successful payment and a successful callback are two separate parts of the Hosted Checkout flow.** Your callback endpoint must be reachable **and** must acknowledge the payment notification (`HTTP 200 OK`) for the checkout flow to complete successfully.

### Callback URL Checklist

If you see **Failed Payment** on the Hosted Checkout even though the customer completed the payment, verify the callback URL:

1. ✅ Is **correct** — matches the URL registered in the OneKhusa portal.
2. ✅ Uses **HTTPS**.
3. ✅ Is **publicly accessible** from the internet (not `localhost`).
4. ✅ **Accepts POST requests**.
5. ✅ **Returns `HTTP 200 OK`** after receiving the payment notification.

For local development, remember that OneKhusa **cannot reach a callback running only on `localhost`**. Use a tool such as **ngrok** to expose your local application through a public HTTPS URL.

---

## 🔍 Troubleshooting — What to Do & Which Files to Check

### If you are a customer seeing "Failed Payment"

- Your payment may still have **succeeded** even though the page shows failure — keep the confirmation from your bank/mobile wallet.
- **Keep your reference number / TAN** and any transaction details.
- **Contact the merchant / support** and provide the reference. The merchant can confirm the payment and the callback status on their side.

### If you are a developer

| Symptom | What to check | Which file |
| ------- | ------------- | ---------- |
| Callback never arrives | `PUBLIC_CALLBACK_URL` value; ngrok tunnel is running | `.env` |
| Endpoint/base URL wrong | Endpoint configuration (base URL, checkout, redirect) | `config/onekhusa.php` |
| Where the 3 route URLs are built | `successRedirectionUrl`, `failureRedirectionUrl`, `callbackApiUrl` | `app/Services/OneKhusaService.php` |
| Webhook route missing or method wrong | Route registration (`POST api/webhooks/payments`) | `routes/api.php` |
| Webhook received but status not updating | Handler logic (`isSuccess` → `markAsPaid`, `ack` 200) | `app/Http/Controllers/WebhookController.php` |
| Payment processed but page not reflecting it | Frontend status-polling logic (`checkPaymentStatus`) | `resources/views/welcome.blade.php` |
| Full request / response history | Laravel log entries (`OneKhusa webhook received`, `status updated to PAID/FAILED`) | `storage/logs/laravel.log` |

**Fastest check:** look in `storage/logs/laravel.log` for `OneKhusa webhook received`.

- If it is **not** there → OneKhusa never reached your callback URL. Verify the ngrok URL, `.env`, and the route.
- If it **is** there but the customer still saw a failure → confirm your `ack()` returned `HTTP 200 OK` (it does unless an exception is thrown).


