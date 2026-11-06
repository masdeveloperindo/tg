"""Independent Python/cryptography consumer of PHP-signed PUBLIC TEST fixtures.
This is an interoperability test, not a MetaTrader verifier or offline PoC.
"""
import base64
import json
from pathlib import Path
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PublicKey
from cryptography.exceptions import InvalidSignature


def decode(value):
    return base64.b64decode(value + '=' * (-len(value) % 4), altchars=b'-_', validate=True)


checked = 0
for path in sorted((Path(__file__).resolve().parents[1] / 'fixtures').glob('license_signature_*.json')):
    vector = json.loads(path.read_text())
    envelope = vector['envelope']
    accepted = False
    try:
        # A deployed EA must pin these keys in its binary, never load keys from the response.
        public = decode(vector['pinned_keys'][envelope['kid']])
        payload_bytes = decode(envelope['payload_b64'])
        assert payload_bytes.hex() == vector['payload_hex']
        Ed25519PublicKey.from_public_bytes(public).verify(decode(envelope['signature']), payload_bytes)
        payload = json.loads(payload_bytes)
        accepted = (payload['kid'] == envelope['kid'] and payload['status'] == 'valid'
                    and all(payload.get(key) == value for key, value in vector['expected_request'].items()))
    except (InvalidSignature, KeyError, ValueError):
        accepted = False
    assert accepted is vector['accept'], path.name
    checked += 1
assert checked == 4
print('PASS: 4 PHP Ed25519 vectors verified independently by Python; MT5 PoC remains pending.')
