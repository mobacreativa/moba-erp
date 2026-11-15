"""Disposable CI only: task persistence, attachment download and access control."""
import urllib.request, urllib.parse, urllib.error, http.cookiejar, json, re, uuid
BASE='http://127.0.0.1:8080/'
def request(client,path='',body=None,headers={}):
    try: r=client.open(urllib.request.Request(BASE+path,data=body,headers=headers))
    except urllib.error.HTTPError as e: r=e
    return r.status,r.read(),r.headers
def login(name,password):
    c=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    _,body,_=request(c);token=re.search(rb'name="csrf" value="([a-f0-9]+)"',body)[1].decode()
    code,body,_=request(c,body=urllib.parse.urlencode(dict(action='login',csrf=token,username=name,password=password)).encode())
    assert code==200
    code,body,_=request(c,'?api=team_tasks');assert code==200
    return c,json.loads(body)['data']['csrf']
def api(c,token,action,payload):
    code,body,_=request(c,'?api='+action,urllib.parse.urlencode(dict(csrf=token,payload=json.dumps(payload))).encode())
    return code,json.loads(body)
admin,csrf=login('ci-admin','ci-password-only')
code,body,_=request(admin,'?api=account_load');assert code==200
j=json.loads(body)['data'];assert j['reader_enabled'] is False and 'uploads' in j
code,j=api(admin,csrf,'account_save',dict(type='income',name='HTTP invoice',party='Test customer',date='2026-11-15',base='25.00',tax='0'));assert code==200
assert j['data']['total_cents']==2500
for kind in ('account_entry','invoice_upload'):
    code,j=api(admin,csrf,'save',dict(kind=kind,data={'name':'Bypass'}));assert code==422
    code,body,_=request(admin,'?api=history&kind='+kind+'&id=abc');assert code==422
code,body,_=request(admin,'?api=load');assert 'account_entry' not in json.loads(body)['data']
worker,token=login('task-one','task-test-password')
for action in ('account_load','account_invoice_status&id=abc','account_invoice_file&id=abc'):
    code,_,_=request(worker,'?api='+action);assert code==403
code,_=api(worker,token,'account_save',{});assert code==403
code,_=api(admin,'wrong','account_save',{});assert code==403
print('Accounting HTTP permissions, persistence and generic API isolation OK')
