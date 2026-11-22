#!/usr/bin/env python3
"""Prepare targeted systemd overrides for exchange broadcasts and Telegram source jobs."""
import argparse
import json
import os
from pathlib import Path
import re
import shlex
import subprocess


def worker_command(value, service, container, app):
    matches = re.findall(r'argv\[\]=(.*?)\s+;', value)
    if len(matches) != 1:
        raise ValueError('Expected one direct ExecStart command for ' + service)
    args = shlex.split(matches[0])
    shell = next((index for index, arg in enumerate(args) if arg in ('sh', '/bin/sh')), None)
    if shell is not None:
        if args[shell + 1:shell + 2] != ['-lc']:
            raise ValueError('Unsupported worker shell for ' + service)
        script = args[shell + 2:]
        script = shlex.split(script[0]) if len(script) == 1 else script
        if script[:6] != ['cd', app, '&&', 'exec', 'php', 'artisan'] or any(re.search(r'[;&|`$<>\n\r]', arg) for arg in script[6:]):
            raise ValueError('Unexpected worker shell command for ' + service)
        args = args[:2] + ['-w', app] + args[2:shell] + script[4:]
    if not args or Path(args[0]).name != 'docker' or args[1:2] != ['exec'] or container not in args or app not in args:
        raise ValueError('Unexpected Docker container/project for ' + service)
    if 'artisan' not in args or args[args.index('artisan') + 1:args.index('artisan') + 2] != ['queue:work']:
        raise ValueError('Expected a direct Artisan queue worker for ' + service)
    queues = [index for index, arg in enumerate(args) if arg.startswith('--queue=')]
    if len(queues) != 1:
        raise ValueError('Expected one queue option for ' + service)
    index = queues[0]
    if service == 'queue-alerts-telegram':
        names = args[index].split('=', 1)[1].split(',')
        if 'alerts-telegram' not in names:
            raise ValueError('Telegram worker is not listening to alerts-telegram')
        args[index] = '--queue=' + ','.join(['alerts-ltd'] + [name for name in names if name != 'alerts-ltd'])
    else:
        expected = 'realtime-' + service[len('queue-realtime-'):]
        if args[index] != '--queue=' + expected or 'sports-realtime' not in args:
            raise ValueError('Unexpected realtime queue/connection for ' + service)
        args = [arg for arg in args if not arg.startswith('--sleep=')] + ['--sleep=0']
    return args


def systemd_quote(value):
    return '"' + value.replace('\\', '\\\\').replace('"', '\\"').replace('%', '%%').replace('$', '$$') + '"'


def worker_stop_command(command):
    php = command.index('artisan') - 1
    prefix = [arg for arg in command[:php] if arg != '-i']
    args = command[php:]
    queue = next(arg for arg in args if arg.startswith('--queue='))
    connection = args[3] if len(args) > 3 and not args[3].startswith('--') else None
    identity = args[:3] + ([connection] if connection else []) + [queue]
    pattern = '^' + re.escape(' '.join(identity)) + '( |$)'
    return prefix + ['sh', '-lc', 'pkill -TERM -f ' + shlex.quote(pattern) + ' || true']


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--config', default='/opt/bfmarkets-service-manager/server/services-manager.json')
    parser.add_argument('--container', default='aapanel')
    parser.add_argument('--app', default='/www/wwwroot/staging777.bfmarketsanalytics.com')
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--service', action='append', help='Limit changes to the named worker; repeat for multiple workers')
    args = parser.parse_args()
    path = Path(args.config)
    config = json.loads(path.read_text())
    services = config.get('overrides', {}).get('production', {}).get('services', config['services'])
    if args.service and any(name not in services or (name != 'queue-alerts-telegram' and not name.startswith('queue-realtime-')) for name in args.service):
        raise ValueError('Select only configured realtime or Telegram workers')
    changes = []
    for name, original in services.items():
        if name != 'queue-alerts-telegram' and not name.startswith('queue-realtime-'):
            continue
        if args.service and name not in args.service:
            continue
        spec = dict(original, **original.get('overrides', {}).get('production', {}))
        unit = spec.get('unit', config.get('prefix', 'bfmarkets') + '-' + name + '.service')
        if not re.fullmatch(r'[a-zA-Z0-9@_.-]+\.service', unit):
            raise ValueError('Invalid unit name')
        shown = subprocess.run(['systemctl', 'show', unit, '--property=ExecStart', '--value'], check=True, text=True, capture_output=True).stdout
        command = worker_command(shown, name, args.container, args.app)
        changes.append((unit, original, command))
    if not args.service and not any('alerts-ltd' in arg for _, _, command in changes for arg in command):
        raise ValueError('Telegram source queue configuration was not found')
    if args.apply and os.geteuid() != 0:
        raise ValueError('Apply this configuration with sudo')
    for unit, spec, command in changes:
        print(unit + ': ' + ' '.join(map(shlex.quote, command)))
        if args.apply:
            directory = Path('/etc/systemd/system') / (unit + '.d')
            directory.mkdir(parents=True, exist_ok=True)
            target = directory / '30-market-stream.conf'
            backup = target.with_suffix('.conf.before-market-stream')
            if target.exists() and not backup.exists():
                backup.write_bytes(target.read_bytes())
            temporary = target.with_suffix('.conf.tmp')
            temporary.write_text('[Service]\nExecStart=\nExecStart=' + ' '.join(map(systemd_quote, command))
                                 + '\nExecStop=\nExecStop=' + ' '.join(map(systemd_quote, worker_stop_command(command))) + '\n')
            temporary.chmod(0o644)
            temporary.replace(target)
            definition = spec.get('overrides', {}).get('production', spec)
            definition['command'] = command[command.index('artisan') + 1:]
    if args.apply:
        backup = path.with_suffix('.json.before-market-stream')
        if not backup.exists():
            backup.write_bytes(path.read_bytes())
        temporary = path.with_suffix('.json.tmp')
        temporary.write_text(json.dumps(config, indent=2) + '\n')
        temporary.chmod(path.stat().st_mode & 0o777)
        temporary.replace(path)
        subprocess.run(['systemctl', 'daemon-reload'], check=True)
        print('Overrides installed. Restart these workers with the service manager.')


if __name__ == '__main__':
    main()
