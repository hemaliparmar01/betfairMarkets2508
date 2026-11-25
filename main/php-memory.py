#!/usr/bin/env python3
"""Inspect or apply production-only CLI and per-site PHP memory configuration."""

import argparse
from datetime import datetime, timezone
import json
import os
from pathlib import Path
import pwd
import re
import shutil
import tempfile


PROJECT = Path('/www/wwwroot/bfmarketsanalytics.com')
CLI_INI = Path('/etc/php/8.3/cli-custom/php.ini')
MEMORY = re.compile(r'^\s*memory_limit\s*=.*$', re.MULTILINE | re.IGNORECASE)


def unlimited(contents):
    if MEMORY.search(contents):
        return MEMORY.sub('memory_limit=-1', contents)
    return 'memory_limit=-1\n' + contents


def write_configuration(path, contents, owner):
    previous = path.stat() if path.exists() else None
    with tempfile.NamedTemporaryFile(dir=path.parent, prefix='.php-memory-', delete=False) as temporary:
        name = Path(temporary.name)
        temporary.write(contents.encode())
    try:
        os.chmod(name, previous.st_mode & 0o777 if previous else 0o644)
        os.chown(name, previous.st_uid if previous else owner.pw_uid, previous.st_gid if previous else owner.pw_gid)
        os.replace(name, path)
    finally:
        name.unlink(missing_ok=True)


def configure(project, cli_ini, apply=False):
    targets = [cli_ini, project / 'public/.user.ini']
    if not cli_ini.is_file() or not (project / 'public/index.php').is_file():
        raise ValueError('Production PHP CLI configuration or public/index.php is missing')
    for path in targets:
        if path.is_symlink():
            raise ValueError('Review configuration symlink before changing: ' + str(path))
    original = {path: path.read_text() if path.exists() else None for path in targets}
    changed = {path: unlimited(contents or '') for path, contents in original.items()
               if unlimited(contents or '') != contents}
    backup = None
    if apply and changed:
        owner = pwd.getpwnam('www')
        backup_root = project / 'storage/app/service-manager/php-memory-backups'
        backup_root.mkdir(parents=True, exist_ok=True, mode=0o700)
        os.chmod(backup_root, 0o700)
        backup = backup_root / datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%S%fZ')
        backup.mkdir(mode=0o700)
        for index, path in enumerate(changed):
            if original[path] is not None:
                shutil.copy2(path, backup / (str(index) + '-' + path.name))
        applied = []
        try:
            for path, contents in changed.items():
                write_configuration(path, contents, owner)
                applied.append(path)
        except Exception:
            for path in reversed(applied):
                if original[path] is None:
                    path.unlink()
                else:
                    write_configuration(path, original[path], owner)
            raise
    return {
        'applied': apply, 'backup': str(backup) if backup else None,
        'files': [{'path': str(path), 'memory_limits': MEMORY.findall(path.read_text()) if path.exists() else [],
                   'change_required': not apply and path in changed} for path in targets],
        'web_verification_required': True,
    }


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--apply', action='store_true', help='Back up and update production PHP configuration; does not restart services')
    arguments = parser.parse_args()
    if Path(__file__).resolve().parent != PROJECT / 'main':
        parser.error('Run the deployed production main/php-memory.py; local and staging paths are refused')
    if arguments.apply and os.geteuid() != 0:
        parser.error('--apply requires sudo')
    print(json.dumps(configure(PROJECT, CLI_INI, arguments.apply), indent=2))
