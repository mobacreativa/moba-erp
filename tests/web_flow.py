"""Pruebas HTTP con datos desechables; ejecutar solo en CI/local de pruebas."""
import html
import json
import http.cookiejar
import re
import urllib.error
import urllib.parse
import urllib.request

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

client = browser()
status, page, headers = request(client)
assert status == 200 and 'name="username"' in page and 'Nuevo cliente' not in page
assert 'HttpOnly' in headers['Set-Cookie'] and 'SameSite=Strict' in headers['Set-Cookie']
assert headers['X-Robots-Tag'].startswith('noindex')
csrf = token(page)
status, _, _ = request(client, {'action': 'save_customer', 'csrf': csrf, 'name': 'Unauthorized'})
assert status == 403
status, _, _ = request(client, {'action': 'login', 'csrf': 'wrong', 'username': 'ci-admin', 'password': 'ci-password-only'})
assert status == 403
status, page, _ = request(client, {'action': 'login', 'csrf': csrf, 'username': 'wrong', 'password': 'ci-password-only'})
assert 'Usuario o contraseña incorrectos' in page and 'Nuevo cliente' not in page
status, page, _ = request(client, {'action': 'login', 'csrf': csrf, 'username': 'ci-admin', 'password': 'ci-password-only'})
assert status == 200 and 'Nuevo cliente' in page
new_csrf = token(page)
assert new_csrf != csrf
status, _, _ = request(client, {'action': 'save_customer', 'csrf': csrf, 'name': 'Bad CSRF'})
assert status == 403
name = 'HTTP test <script>alert(1)</script>'
status, page, _ = request(client, {'action': 'save_customer', 'csrf': new_csrf, 'name': name})
assert status == 200 and 'Cliente creado.' in page and html.escape(name) in page
assert '<script>alert(1)</script>' not in page
customer_id = re.search(r'\?edit=(\d+)', page).group(1)
status, page, _ = request(client, path='?edit=' + customer_id)
assert f'name="id" value="{customer_id}"' in page
status, page, _ = request(client, {'action': 'save_customer', 'csrf': new_csrf, 'id': customer_id, 'name': 'HTTP updated'})
assert 'Cliente actualizado.' in page and 'HTTP updated' in page
status, payload, _ = request(client, path='?api=load')
state = json.loads(payload)['data']
assert status == 200 and state['csrf'] == new_csrf
status, _, _ = request(client, {'csrf':'wrong','payload':'{}'}, '?api=save')
assert status == 403
def api(action, payload):
    status, body, _ = request(client, {'csrf':new_csrf,'payload':json.dumps(payload)}, '?api='+action)
    return status, json.loads(body)
status, payload = api('save', {'kind':'product','data':{'name':'API product','cost':2,'pvp':7}})
assert status == 200 and payload['ok']
product = payload['data']
status, payload = api('calculate', {'product_id':product['id'],'quantity':-3})
assert status == 422 and not payload['ok']
status, payload = api('save_quote', {'customer_id':int(customer_id),'date':'2026-10-03','total':0,'lines':[{'product_id':product['id'],'quantity':3,'total':0}]})
assert status == 200 and payload['data']['total'] == 25.41
status, payload = api('save', {'kind':'quote','data':{'name':'Forged','total':0}})
assert status == 422
status, page, _ = request(client, {'action': 'logout', 'csrf': new_csrf})
assert 'name="username"' in page and 'HTTP updated' not in page
status, payload, _ = request(client, path='?api=load')
assert status == 401 and 'customers' not in payload
for _ in range(6):
    # Cambiar la cookie no debe evitar el contador de intentos por IP.
    client = browser()
    _, page, _ = request(client)
    status, _, _ = request(client, {'action': 'login', 'csrf': token(page), 'username': 'ci-admin', 'password': 'wrong'})
assert status == 429
for path in ['config/local.php', 'database/schema.sql', 'app/Customers.php', '.git/config']:
    status, _, _ = request(client, path=path)
    assert status == 404, path
print('HTTP: acceso, CSRF, CRUD, XSS, sesión y limitación de intentos OK')
