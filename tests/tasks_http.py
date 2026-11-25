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
workers=[]
for n in ('task-one','task-two'):
    code,j=api(admin,csrf,'team_user',dict(username=n,name=n,password='task-test-password',role='production',active=True));assert code==200
    workers.append(j['data'])
code,j=api(admin,csrf,'team_task_save',dict(name='Task CI',description='Private task',assignee_id=workers[0]['id']));assert code==200
t=j['data'];w,wt=login('task-one','task-test-password');other,ot=login('task-two','task-test-password')
code,j=api(other,ot,'team_task_save',dict(id=t['id'],version=t['version'],status='completed'));assert code==403
code,body,_=request(other,'?api=team_tasks');assert t['id'] not in body.decode()
boundary=uuid.uuid4().hex
def upload(payload,filename,content):
    parts=[]
    for k,v in {'csrf':csrf,'payload':json.dumps(payload)}.items():
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{filename}"\r\nContent-Type: text/plain\r\n\r\n'.encode()+content+b'\r\n')
    parts.append(f'--{boundary}--\r\n'.encode())
    code,body,_=request(admin,'?api=team_task_upload',b''.join(parts),{'Content-Type':'multipart/form-data; boundary='+boundary})
    return code,json.loads(body)
code,j=upload(dict(id=t['id'],version=t['version']),'note.txt',b'Private attachment');assert code==200
t=j['data'];f=t['attachments'][0]
path='?api=team_file&id='+t['id']+'&file='+f['id']
code,body,headers=request(w,path);assert code==200 and body==b'Private attachment' and 'attachment' in headers['Content-Disposition']
code,_,_=request(other,path);assert code==403
code,j=api(w,wt,'team_task_save',dict(id=t['id'],version=t['version'],status='completed',assignee_id=workers[1]['id'],name='Tampered'));assert code==200
assert j['data']['assignee_id']==workers[0]['id'] and j['data']['name']=='Task CI' and j['data']['status']=='completed'
code,_=api(admin,csrf,'save',dict(kind='task',data={'name':'Bypass'}));assert code==422
code,j=upload(dict(id=t['id'],version=t['version']),'attack.html',b'<html><script>alert(1)</script></html>');assert code==422
print('Tasks: persistence, uploads, downloads, isolation and mutation permissions OK')
