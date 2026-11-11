# ClickBank & JVZoo for FreeScout

A [FreeScout](https://freescout.net) module that keeps your ClickBank and JVZoo orders inside FreeScout, so support
sees what each person bought.

**What it does today:** it receives ClickBank's Instant Notifications (version 6.0 to 8.0) and JVZoo's JVZIPN, checks
each against your secret key, and keeps one order per product bought: who bought it, what, for how much, through which
affiliate, and whether it is paid, canceled, refunded or charged back. Test purchases are kept and marked as tests.

**Beside each ticket** it shows the customer's orders, read-only and newest first, found by the customer's email
addresses: each with its status, whether the buyer has accessed what they bought, the price, the date and the receipt.


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
