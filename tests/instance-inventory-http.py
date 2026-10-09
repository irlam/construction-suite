#!/usr/bin/env python3
"""Authenticated web regression for a CLI-jail inventory path; disposable data only."""
import http.cookiejar, json, os, pathlib, re, secrets, shutil, socket, sqlite3, subprocess, tempfile, time, urllib.request, urllib.error, urllib.parse
ROOT = pathlib.Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BIN', 'php')
with tempfile.TemporaryDirectory(prefix='suite-web-inventory-') as temporary:
    root = pathlib.Path(temporary)/'suite.defecttracker.uk'/'httpdocs'
    root.mkdir(parents=True)
    for folder in ('app','config','public','database'): shutil.copytree(ROOT/folder,root/folder)
    private = root/'private';private.mkdir(mode=0o700)
    inventory = private/'programme-staging-instances.json'
    inventory.write_text(json.dumps([{'id':i,'organization_id':org,'project_id':org,'module_key':'programme','origin':f'https://{site}.programme.defecttracker.uk','isolation_verified':False,'gateway_verified':False} for i,org,site in [(1,7,'alpha'),(2,8,'beta')]]))
    inventory.chmod(0o600)
    db = sqlite3.connect(root/'fixture.sqlite')
    db.executescript((root/'database/schema.sqlite.sql').read_text())
    for migration in sorted((root/'database/migrations').glob('*.sqlite.sql')): db.executescript(migration.read_text())
    db.executescript("INSERT INTO organizations(id,name,slug) VALUES(7,'Alpha','programme-alpha-staging'),(8,'Beta','programme-beta-staging'); INSERT INTO projects(id,organization_id,name) VALUES(7,7,'Alpha One'),(8,8,'Beta One');")
    password = secrets.token_hex(16)
    hashed = subprocess.check_output([PHP,'-r','echo password_hash($argv[1],PASSWORD_DEFAULT);',password]).decode()
    for uid, email, platform in [(1,'owner@example.invalid',1),(2,'worker@example.invalid',0)]:
        db.execute('INSERT INTO users(id,email,name,password_hash,is_platform_admin) VALUES(?,?,?,?,?)',(uid,email,'Fixture',hashed,platform))
    db.execute("INSERT INTO memberships(user_id,organization_id,project_id,role_key) VALUES(2,7,7,'manager')");db.commit()
    with socket.socket() as sock: sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
    origin=f'http://127.0.0.1:{port}'
    env=dict(os.environ,DB_DRIVER='sqlite',DB_DATABASE=str(root/'fixture.sqlite'),SESSION_SECURE='false',SUITE_INSTANCES_FILE='/suite.defecttracker.uk/httpdocs/private/programme-staging-instances.json')
    server=subprocess.Popen([PHP,'-d','session.save_path='+temporary,'-S',f'127.0.0.1:{port}','-t',str(root/'public')],env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    def client(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(opener,path,values=None):
        data=urllib.parse.urlencode(values).encode() if values is not None else None
        try: response=opener.open(urllib.request.Request(origin+path,data=data),timeout=5)
        except urllib.error.HTTPError as error: response=error
        return response.status,response.geturl(),dict(response.headers),response.read()
    def login(email):
        opener=client();_,_,_,body=request(opener,'/login.php')
        csrf=re.search(rb'name="csrf_token" value="([^"]+)"',body).group(1).decode()
        status,_,_,body=request(opener,'/login.php',{'csrf_token':csrf,'email':email,'password':password,'next':'/'})
        assert status==200 and b'Fatal error' not in body and b'Alpha One' in body
        return opener
    try:
        for _ in range(100):
            try: request(client(),'/login.php');break
            except urllib.error.URLError: time.sleep(.02)
        else: raise AssertionError('HTTP fixture did not start')
        anonymous=client()
        assert '/login.php' in request(anonymous,'/admin/instance-inventory-check.php')[1]
        owner=login('owner@example.invalid')
        status,_,headers,body=request(owner,'/admin/instance-inventory-check.php')
        assert status==200 and 'no-store' in headers['Cache-Control']
        report=json.loads(body)
        assert report=={'web_inventory_loaded':True,'instances':2,'application_private_file':True,'cli_path_alias':True,'tenant_ready':False}
        assert str(root).encode() not in body
        worker=login('worker@example.invalid')
        assert request(worker,'/admin/instance-inventory-check.php')[0]==403
        # A public inventory path must still fail; the owner's check contains no traceback/path.
        inventory.rename(private/'preserved.json')
        status,_,_,body=request(owner,'/admin/instance-inventory-check.php')
        assert status==503 and json.loads(body)['error']=='private_inventory_unavailable'
        assert b'/tmp/' not in body and b'Stack trace' not in body
        print('PASS: real owner/manager dashboards with CLI alias; private inventory preserved; owner-only diagnostic, anonymous/member denial and sanitized missing-file failure')
    finally:
        server.terminate();server.wait(timeout=5);db.close()
