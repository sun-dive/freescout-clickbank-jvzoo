# ClickBank & JVZoo for FreeScout

A [FreeScout](https://freescout.net) module that keeps your ClickBank and JVZoo orders inside FreeScout, so support
sees what each person bought.

**What it does today:** it receives ClickBank's Instant Notifications (version 6.0 to 8.0) and JVZoo's JVZIPN, checks
each against your secret key, and keeps one order per product bought: who bought it, what, for how much, through which
affiliate, and whether it is paid, canceled, refunded or charged back. Test purchases are kept and marked as tests.

**Beside each ticket** it shows the customer's orders, read-only and newest first, found by the customer's email
addresses: each with its status, whether the buyer has accessed what they bought, the price, the date and the receipt.


**Codes:** every order gets a code, like `K7QM-3XDP-9RWT` (no 0, O, 1, I, L or U). A member site lets the buyer use
it there, asking this help desk first in one signed call (below). The first member to use a code keeps it.

## The member site's call

`POST` to the redeem address shown in Settings, body JSON, header `X-Signature`: the base64 Ed25519 signature of the
body's exact bytes, made with the member site's key (its public key goes in Settings).

    {"code": "K7QM-3XDP-9RWT", "member": "12345", "time": 1794390000}

`time` is Unix seconds; a request more than five minutes from this server's clock is refused. The answer is signed the
same way by this help desk (its public key is shown in Settings):

    {"ok": true,  "code": "K7QM-3XDP-9RWT", "member": "12345", "product": {"source": "clickbank", "item": "1", "title": "..."}, "test": false, "time": ...}
    {"ok": false, "code": "K7QM-3XDP-9RWT", "member": "99999", "reason": "used", "time": ...}

`reason` is `unknown`, `used` (by another member), or the order's status: `refunded`, `charged back` or `canceled`.
A bad signature or time is answered 401; before the member site's key is set, 503.

## Install

1. Copy this folder into FreeScout's `Modules` folder as `Modules/ClickBankJVZoo`.
2. In FreeScout, open Manage » Modules and activate it (or run `php artisan module:enable ClickBankJVZoo` and
   `php artisan freescout:module-install clickbankjvzoo`).
3. Open Manage » Settings, then ClickBank & JVZoo in the list on the left. Set each seller's secret key there and in the seller's account, and give each
   seller the address shown on that page.

The secret keys are kept in FreeScout's settings, encrypted with the app's key; they are never shown again or put in
this code.

## How each notice is checked

- **ClickBank:** the notice is AES-256-CBC encrypted; the key is the first 32 hex characters of the SHA-1 of your secret
  key. A notice that does not decrypt is answered 401 and keeps nothing.
- **JVZoo:** `cverify` must equal the first 8 hex characters, in capitals, of the SHA-1 of every other field's value in
  the order of the field names, each followed by `|`, then your secret key.

A notice sent twice is kept once. An order's status follows its latest event by when it happened, so a sale whose
notice is retried after its refund's stays refunded.

## Tests

- `php Tests/run.php` checks the two readers against notices made outside PHP (`Tests/vectors/make.sh`: ClickBank's
  encrypted with the `openssl` command-line tool, JVZoo's signed with Python's SHA-1). No FreeScout needed.
- `php Tests/integration.php /path/to/freescout` posts the same notices through a FreeScout **test copy** and checks
  what it keeps. It refuses any FreeScout whose `storage/.installed` does not say `(test copy)`, and it empties the
  module's tables and keys.

## Licence

AGPL-3.0, as FreeScout. By sun-dive.
