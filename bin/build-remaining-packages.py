"""Build credential-free app bundles locally; never reads untracked private files."""
from pathlib import Path
import hashlib
import json
import subprocess
import zipfile

suite = Path(__file__).resolve().parents[1]
workspace = suite.parent
out = suite / 'release' / 'remaining-integrations'
out.mkdir(parents=True, exist_ok=True)
manifest = {}
modules = {'qa': 'qa-tracker', 'safety': 'safety-reference', 'permits': 'permits-reference', 'notices': 'docs'}
for module, folder in modules.items():
    repo = workspace / folder
    files = subprocess.check_output(['git', '-C', str(repo), 'ls-files', '-z']).decode().split('\0')
    source = subprocess.check_output(['git', '-C', str(repo), 'rev-parse', 'HEAD']).decode().strip()
    package = out / f'{module}-suite.zip'
    with zipfile.ZipFile(package, 'w', zipfile.ZIP_DEFLATED) as archive:
        for name in files:
            if not name:
                continue
            parts = Path(name).parts
            if parts[0] in {'tests', 'docs', 'bin', 'tools', '.github', 'release', 'node_modules'}:
                continue
            if module != 'qa' and parts[0] in {'database', 'uploads', 'export', 'pdfs'}:
                continue
            if name.endswith(('.example.php', '.zip', '.log')) or name == 'includes/config.example.php':
                continue
            if name in {'.gitignore', 'README.md', 'SECURITY.md', 'IMPROVEMENTS.md', 'upload_diag.php', 'mail_test.php', 'check_uploads.php', 'includes/test_includes.php'}:
                continue
            if any(p.startswith('.env') or 'private' in p or p.endswith('.local.php') for p in parts):
                continue
            path = repo / name
            if path.is_file():
                archive.write(path, name)
        if module != 'qa':
            for file in (suite / 'integrations' / 'legacy').glob('*.php'):
                archive.write(file, file.name if file.name in {'suite-login.php', 'suite-file.php'} else '.suite/' + file.name)
    manifest[module] = {'source_commit': source, 'sha256': hashlib.sha256(package.read_bytes()).hexdigest(), 'bytes': package.stat().st_size}
(out / 'manifest.json').write_text(json.dumps(manifest, indent=2) + '\n')
print(json.dumps(manifest, indent=2))
