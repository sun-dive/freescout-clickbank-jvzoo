#!/bin/sh
# Test notices made outside PHP, so the readers are checked against something they did not make. ClickBank's are
# encrypted by the openssl command-line tool, with the key made as ClickBank documents it: the first 32 hex
# characters of the SHA-1 of the secret key, those 32 characters being the AES-256 key. JVZoo's are signed with
# Python's own SHA-1, in the order JVZoo documents. Run it to make the .json files in this folder again.
set -eu
cd "$(dirname "$0")"
# made-up keys: ClickBank's are up to 16 capitals and digits
CB_SECRET=TESTKEY0123ABCD
JV_SECRET=test-jvzoo-secret

key_hex=$(printf %s "$CB_SECRET" | sha1sum | cut -c1-32 | tr -d '\n' | od -An -tx1 | tr -d ' \n')
for name in clickbank-sale clickbank-test-sale clickbank-url-test clickbank-r2-sale clickbank-r2-refund; do
  iv_hex=$(openssl rand -hex 16)
  notification=$(openssl enc -aes-256-cbc -K "$key_hex" -iv "$iv_hex" -in "$name.plain.json" | base64 -w0)
  python3 - "$name" "$iv_hex" "$notification" "$CB_SECRET" <<'PY'
import base64, json, sys
name, iv_hex, notification, secret = sys.argv[1:]
body = json.dumps({'notification': notification, 'iv': base64.b64encode(bytes.fromhex(iv_hex)).decode()})
json.dump({'secret': secret, 'body': body, 'transaction': json.load(open(name + '.plain.json'))},
          open(name + '.json', 'w'), indent=2, ensure_ascii=False)
PY
done

python3 - "$JV_SECRET" <<'PY'
import hashlib, json, sys
secret = sys.argv[1]
fields = {
    'ccustname': 'José Müller', 'ccuststate': '', 'ccustcc': 'PT', 'ccustemail': 'Jose@Example.com',
    'cproditem': '412345', 'cprodtitle': 'Example Product', 'cprodtype': 'STANDARD',
    'ctransaction': 'SALE', 'ctransaffiliate': '98765', 'ctransamount': '27.00', 'ctranspaymentmethod': 'PYPL',
    'ctransvendor': '11111', 'ctransreceipt': 'AP-1A2B3C4D5E', 'cupsellreceipt': '', 'caffitid': '',
    'cvendthru': 'member=12345&src=example', 'ctranstime': '1794385815',
}
pop = ''.join(fields[k] + '|' for k in sorted(fields)) + secret
fields['cverify'] = hashlib.sha1(pop.encode('utf-8')).hexdigest()[:8].upper()
json.dump({'secret': secret, 'fields': fields}, open('jvzoo-sale.json', 'w'), indent=2, ensure_ascii=False)
PY
echo "made: $(ls *.json | grep -v plain | tr '\n' ' ')"
