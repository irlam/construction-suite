#!/usr/bin/env python3
"""Real login/company HTTP workflow against disposable SQLite data."""
import base64
import http.cookiejar
import os
from pathlib import Path
import re
import secrets
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BIN', 'php')
PNG = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aB1cAAAAASUVORK5CYII=')

def check(ok, label):
    if not ok:
        raise AssertionError(label)

with tempfile.TemporaryDirectory(prefix='suite-company-http-') as tmp:
    dbpath = str(Path(tmp) / 'fixture.sqlite')
    password = secrets.token_hex(16)
    hashed = subprocess.check_output([PHP, '-r', 'echo password_hash($argv[1], PASSWORD_DEFAULT);', password]).decode()
    db = sqlite3.connect(dbpath)
    db.executescript((ROOT / 'database/schema.sqlite.sql').read_text())
    for migration in sorted((ROOT / 'database/migrations').glob('*.sqlite.sql')):
        db.executescript(migration.read_text())
    db.executescript("INSERT INTO organizations(id,name,slug) VALUES(1,'Alpha','alpha'),(2,'Beta','beta'); INSERT INTO projects(id,organization_id,name) VALUES(1,1,'Alpha One'),(2,2,'Beta One');")
    for uid, name in [(1, 'Alpha'), (2, 'Beta'), (3, 'Worker')]:
        db.execute('INSERT INTO users(id,email,name,password_hash) VALUES(?,?,?,?)', (uid, name.lower()+'@example.test', name, hashed))
    db.executescript("INSERT INTO memberships(user_id,organization_id,project_id,role_key) VALUES(1,1,NULL,'company_admin'),(2,2,NULL,'company_admin'),(3,1,1,'user');")
    db.commit()
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    origin = f'http://127.0.0.1:{port}'
    env = dict(os.environ, DB_DRIVER='sqlite', DB_DATABASE=dbpath, SESSION_SECURE='false', SUITE_INSTANCES_FILE='')
    with open(Path(tmp) / 'server.log', 'w+') as log:
        server = subprocess.Popen([PHP, '-d', 'session.save_path='+tmp, '-d', 'opcache.jit=0', '-d', 'opcache.jit_buffer_size=0', '-S', f'127.0.0.1:{port}', '-t', str(ROOT / 'public')], env=env, stdout=log, stderr=log)
        try:
            def client():
                return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
            def request(opener, path, values=None, raw=None, content_type=None):
                data = urllib.parse.urlencode(values).encode() if values is not None else raw
                req = urllib.request.Request(origin+path, data=data)
                if content_type:
                    req.add_header('Content-Type', content_type)
                try:
                    response = opener.open(req, timeout=5)
                except urllib.error.HTTPError as e:
                    response = e
                return response.status, dict(response.headers), response.read()
            anonymous = client()
            for attempt in range(50):
                try:
                    request(anonymous, '/login.php')
                    break
                except urllib.error.URLError:
                    time.sleep(.1)
            def token(body):
                return re.search(rb'name="csrf_token" value="([^"]+)"', body)[1].decode()
            def login(email):
                opener = client()
                _, _, page = request(opener, '/login.php')
                status, _, body = request(opener, '/login.php', {'csrf_token':token(page), 'email':email, 'password':password, 'next':'/company/'})
                return opener, status, body
            alpha, status, page = login('alpha@example.test')
            check(status == 200 and b'Company & branding' in page, 'Alpha login and dashboard')
            check(b'Beta One' not in page and b'beta@example.test' not in page, 'No foreign company disclosure')
            csrf = token(page)
            values = {'company_id':1, 'action':'save_branding', 'name':'Alpha Construction', 'contact_email':'office@example.test', 'brand_colour':'#aabbcc'}
            status, _, _ = request(alpha, '/company/', dict(values, csrf_token='wrong'))
            check(status == 419, 'Branding CSRF rejected')
            check(db.execute('SELECT name FROM organizations WHERE id=1').fetchone()[0]=='Alpha', 'CSRF leaves company unchanged')
            status, _, _ = request(alpha, '/company/?company_id=2', dict(values, csrf_token=csrf, company_id=2))
            check(status == 403, 'Forged company scope rejected')
            boundary = 'SuiteTest'+secrets.token_hex(8)
            chunks=[]
            for key,value in dict(values,csrf_token=csrf).items():
                chunks.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
            chunks.extend([f'--{boundary}\r\nContent-Disposition: form-data; name="logo"; filename="logo.png"\r\nContent-Type: image/png\r\n\r\n'.encode(), PNG, f'\r\n--{boundary}--\r\n'.encode()])
            status, _, body = request(alpha, '/company/', raw=b''.join(chunks), content_type='multipart/form-data; boundary='+boundary)
            check(status == 200 and b'Changes saved.' in body, 'Real logo upload and redirect')
            status, headers, body = request(alpha, '/company/logo.php?company_id=1')
            check(status == 200 and body==PNG and headers['Content-Type']=='image/png', 'Authenticated logo served as raster')
            check('no-store' in headers['Cache-Control'] and headers['X-Content-Type-Options']=='nosniff', 'Logo cache and content protections')
            check(request(anonymous, '/company/logo.php?company_id=1')[0]==401, 'Anonymous logo denied')
            beta, status, _ = login('beta@example.test')
            check(request(beta, '/company/logo.php?company_id=1')[0]==404, 'Foreign company logo denied')
            worker, status, _ = login('worker@example.test')
            check(status==403, 'Worker cannot administer company')
            check(request(worker, '/company/logo.php?company_id=1')[0]==200, 'Worker can view assigned company logo')
            status, _, body = request(worker, '/')
            check(status==200 and b'/company/logo.php?company_id=1' in body and b'#aabbcc' in body, 'Branding on project dashboard')
            check(request(alpha, '/company/?tools_project_id=2')[0]==403, 'Foreign project tools denied')
            values={'csrf_token':csrf,'company_id':1,'action':'save_project_tools','tools_project_id':1,'tools[]':'programme'}
            status, _, _ = request(alpha,'/company/',values)
            check(status==200 and db.execute("SELECT enabled FROM company_project_modules WHERE project_id=1 AND module_key='programme'").fetchone()[0]==1, 'Real project tool preferences form')
            check(db.execute('SELECT COUNT(*) FROM company_project_modules WHERE project_id=2').fetchone()[0]==0, 'Other project preferences untouched')
            db.execute('UPDATE organizations SET active=0 WHERE id=1'); db.commit()
            check(request(alpha,'/company/')[0]==403 and request(worker,'/company/logo.php?company_id=1')[0]==404, 'Company deactivation removes dashboard and logo access')
        finally:
            server.terminate()
            server.wait(timeout=5)
            log.seek(0)
            output=log.read()
            check('Fatal error' not in output and 'Warning:' not in output, 'HTTP workflow emitted PHP errors: '+output[-1500:])
    db.close()
print('PASS: Real Suite login, company forms, CSRF, logo upload, dashboard branding and tenant access over HTTP.')
