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
entry=j['data']
code,j=api(admin,csrf,'account_trash',{'id':entry['id'],'version':entry['version']});assert code==200 and j['data']['deleted']
code,j=api(admin,csrf,'account_restore',{'id':entry['id'],'version':entry['version']});assert code==409
code,j=api(admin,csrf,'account_restore',{'id':entry['id'],'version':entry['version']+1});assert code==200 and not j['data']['deleted']
for kind in ('account_entry','invoice_upload'):
    code,j=api(admin,csrf,'save',dict(kind=kind,data={'name':'Bypass'}));assert code==422
    code,body,_=request(admin,'?api=history&kind='+kind+'&id=abc');assert code==422
code,body,_=request(admin,'?api=load');assert 'account_entry' not in json.loads(body)['data']
worker,token=login('task-one','task-test-password')
for action in ('account_load','account_invoice_status&id=abc','account_invoice_file&id=abc'):
    code,_,_=request(worker,'?api='+action);assert code==403
for action in ('account_save','account_trash','account_restore','account_invoice_trash','account_invoice_restore'):
    code,_=api(worker,token,action,{});assert code==403
    code,_=api(admin,'wrong',action,{});assert code==403
code,_=api(admin,'wrong','account_save',{});assert code==403
print('Accounting HTTP permissions, persistence and generic API isolation OK')

def upload_local(client,token,content,name='test.pdf'):
    boundary=uuid.uuid4().hex
    body=(f'--{boundary}\r\nContent-Disposition: form-data; name="csrf"\r\n\r\n{token}\r\n--{boundary}\r\nContent-Disposition: form-data; name="payload"\r\n\r\n'+json.dumps({'party':'Local supplier','base':'100.00','tax':'21.00'})+f'\r\n--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{name}"\r\nContent-Type: application/pdf\r\n\r\n').encode()+content+f'\r\n--{boundary}--\r\n'.encode()
    code,body,headers=request(client,'?api=account_invoice_local',body,{'Content-Type':'multipart/form-data; boundary='+boundary})
    return code,json.loads(body)
code,j=upload_local(worker,token,b'%PDF-1.4\n');assert code==403
code,j=upload_local(admin,csrf,b'<html><script>attack</script></html>','attack.html');assert code==422
code,j=upload_local(admin,csrf,b'%PDF-1.4\n%%EOF');assert code==200
invoice_id=j['data']['id']
code,body,_=request(admin,'?api=account_invoice_status&id='+invoice_id);assert code==200 and json.loads(body)['data']['fields']['party']=='Local supplier'
code,body,headers=request(admin,'?api=account_invoice_file&id='+invoice_id);assert code==200 and body.startswith(b'%PDF-')
print('Local invoice storage, type validation and download authorization OK')

code,j=api(admin,csrf,'team_user',dict(username='account-sales',name='Sales CI',password='sales-test-password',role='sales',active=True));assert code==200
sales,st=login('account-sales','sales-test-password')
code,body,_=request(sales,'?view=accounting');assert code==200 and b'Proveedores' in body and b'accounting.js' not in body
code,_,_=request(sales,'?view=customers');assert code==200
code,_,_=request(sales,'?api=account_load');assert code==403
for action in ('account_save','account_trash','account_restore','account_invoice_upload','account_ocr_config'):
    code,_=api(sales,st,action,{});assert code==403
print('Sales retains contacts access without financial access')

code,_=api(admin,'wrong','account_ocr_config',{});assert code==403
code,_=api(admin,csrf,'account_ocr_config',{'endpoint':'https://attacker.example','key':'a'*32,'tier':'F0'});assert code==422
code,_=api(admin,csrf,'account_ocr_config',{'endpoint':'https://example.cognitiveservices.azure.com','key':'bad','tier':'F0'});assert code==422
print('OCR configuration access and input validation OK')

code,body,_=request(admin,'?api=account_load');uploads=json.loads(body)['data']['uploads']
inv=next(i for i in uploads if i['id']==invoice_id)
code,j=api(admin,csrf,'account_invoice_trash',{'id':invoice_id,'version':inv['version']});assert code==200 and j['data']['deleted']
version=j['data']['version']
code,body,_=request(admin,'?api=account_load');assert next(i for i in json.loads(body)['data']['uploads'] if i['id']==invoice_id)['deleted']
code,_,_=request(admin,'?api=account_invoice_status&id='+invoice_id);assert code==422
payload=dict(name='Deleted invoice',party='Test',type='expense',date='2026-11-17',base='100',tax='21',invoice_id=invoice_id)
code,_=api(admin,csrf,'account_save',payload);assert code==422
code,_=api(admin,csrf,'account_invoice_restore',{'id':invoice_id,'version':inv['version']});assert code==409
code,j=api(admin,csrf,'account_invoice_restore',{'id':invoice_id,'version':version});assert code==200 and not j['data']['deleted']
version=j['data']['version']
code,body,_=request(admin,'?api=account_invoice_file&id='+invoice_id);assert code==200 and body.startswith(b'%PDF-')
code,j=api(admin,csrf,'account_save',payload);assert code==200
code,_=api(admin,csrf,'account_invoice_trash',{'id':invoice_id,'version':version});assert code==422
print('Pending invoice trash/restore, stale version, original retention and registered invoice protection OK')
