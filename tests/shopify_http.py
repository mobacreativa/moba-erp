"""Webhook de Shopify por HTTP real contra la instancia desechable de CI."""
import base64, hashlib, hmac, http.cookiejar, json, re, urllib.error, urllib.parse, urllib.request

BASE = 'http://127.0.0.1:8080/'
def browser():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(client, data=None, path=''):
    encoded = None if data is None else urllib.parse.urlencode(data).encode()
    try:
        result = client.open(BASE + path, encoded)
    except urllib.error.HTTPError as error:
        result = error
    return result.status, result.read().decode(), result.headers
def token(page):
    return re.search(r'name="csrf" value="([a-f0-9]+)"', page).group(1)

SECRET = b'ci-shopify-secret-only'
def post(body, signature=None, topic='orders/create', shop='ci-test.myshopify.com'):
    sig = signature or base64.b64encode(hmac.new(SECRET, body, hashlib.sha256).digest()).decode()
    req = urllib.request.Request(BASE + '?webhook=shopify', data=body, method='POST', headers={
        'Content-Type': 'application/json', 'X-Shopify-Hmac-Sha256': sig, 'X-Shopify-Topic': topic, 'X-Shopify-Shop-Domain': shop})
    try:
        r = urllib.request.urlopen(req)
    except urllib.error.HTTPError as e:
        r = e
    return r.status, json.loads(r.read().decode())

admin = browser()
status, page, _ = request(admin)
status, page, _ = request(admin, {'action': 'login', 'csrf': token(page), 'username': 'ci-admin', 'password': 'ci-password-only'})
status, body, _ = request(admin, path='?api=team_load')
csrf = json.loads(body)['data']['csrf']
def api(action, payload):
    s, b, _ = request(admin, {'csrf': csrf, 'payload': json.dumps(payload)}, '?api=' + action)
    return s, json.loads(b)
if not any(u['username'] == 'ci-shopify-worker' for u in json.loads(request(admin, path='?api=team_load')[1])['data']['users']):
    s, r = api('team_user', {'username': 'ci-shopify-worker', 'name': 'Shopify Worker', 'password': 'ci-shopify-worker-pass', 'role': 'production', 'active': True})
    assert s == 200 and r['ok'], r

order = {'id': 555000111, 'name': '#2001', 'email': 'http-web@example.com', 'customer': {'first_name': 'Web', 'last_name': 'Buyer'},
         'line_items': [{'title': 'Sudadera', 'variant_title': 'Negro / M', 'quantity': 3}]}
raw = json.dumps(order).encode()
assert post(raw, signature='invalid')[0] == 401
assert post(raw, shop='evil.myshopify.com')[0] == 401
s, r = post(raw); assert s == 200 and r['result'] == 'Orden creada.', r
s, r2 = post(raw); assert s == 200 and r2['order_id'] == r['order_id'], r2
assert post(raw, topic='orders/paid')[1]['result'] == 'Evento ignorado.'
req = urllib.request.Request(BASE + '?webhook=shopify')
try: urllib.request.urlopen(req); raise AssertionError('GET must fail')
except urllib.error.HTTPError as e: assert e.code == 405
orders = json.loads(request(admin, path='?api=team_load')[1])['data']['orders']
mine = [o for o in orders if o.get('shopify_order_id') == '555000111']
assert len(mine) == 1 and mine[0]['source'] == 'shopify' and mine[0]['items'] == [{'product': 'Sudadera — Negro / M', 'quantity': 3}], mine
print('Shopify HTTP: firma, tienda, duplicados, eventos y método OK')
