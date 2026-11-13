import importlib.util
import json
import os
from pathlib import Path
import plistlib
import runpy
import subprocess
import tempfile
import time
import unittest
from unittest.mock import patch

SOURCE = Path(__file__).resolve().parents[2] / 'local/services-manager.py'
spec = importlib.util.spec_from_file_location('service_manager', SOURCE)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
server_spec = importlib.util.spec_from_file_location('server_service_manager', SOURCE.parent.parent / 'server/services-manager.py')
server_module = importlib.util.module_from_spec(server_spec)
server_spec.loader.exec_module(server_module)


class ServiceManagerTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.root = Path(self.temporary.name).resolve()
        self.environment = patch.dict(os.environ, {'SERVICES_ROOT': str(self.root), 'SERVICES_PHP': '/bin/sh', 'SERVICES_USER': 'www', 'SERVICES_ENV': 'local'}, clear=True)
        self.environment.start()
        self.platform = patch.object(module.platform, 'system', return_value='Darwin')
        self.platform.start()
        self.manager = module.Manager()

    def tearDown(self):
        self.platform.stop()
        self.environment.stop()
        self.temporary.cleanup()

    def test_launchd_definition_and_restart_policy(self):
        job = plistlib.loads(self.manager.definition('betfair', self.manager.instances['betfair']))
        self.assertEqual(['/bin/sh', '-d', 'memory_limit=2G', str(self.root / 'artisan'), 'betfair:listen'], job['ProgramArguments'])
        self.assertTrue(job['KeepAlive'])
        self.assertEqual(120, job['ExitTimeOut'])
        self.assertEqual(10, job['ThrottleInterval'])
        self.assertTrue(job['RunAtLoad'])

    def test_local_and_legacy_shell_entry_points_resolve_project_paths_from_any_directory(self):
        interpreter = self.root / 'capture-python'
        interpreter.write_text('#!/bin/sh\nprintf "%s\\n" "$SERVICES_ENV" "$SERVICES_ROOT" "$SERVICES_CONFIG" "$@"\nexit 7\n')
        interpreter.chmod(0o755)
        uname = self.root / 'uname'
        uname.write_text('#!/bin/sh\nprintf "Darwin\\n"\n')
        uname.chmod(0o755)
        environment = {'PATH': str(self.root) + ':/usr/bin:/bin', 'SERVICES_PYTHON': str(interpreter), 'SERVICES_ENV': 'production'}

        for entry in [SOURCE.parent / 'services.sh', SOURCE.parent.parent / 'services.sh']:
            with self.subTest(entry=entry):
                result = subprocess.run([str(entry), 'status', 'queue-alerts'], cwd=self.root, env=environment, capture_output=True, text=True)

                self.assertEqual(7, result.returncode)
                self.assertEqual(['local', str(SOURCE.parent.parent), str(SOURCE.parent / 'services-manager.json'), str(SOURCE), 'status', 'queue-alerts'], result.stdout.splitlines())

    def test_local_shell_refuses_to_manage_linux_services(self):
        uname = self.root / 'uname'
        uname.write_text('#!/bin/sh\nprintf "Linux\\n"\n')
        uname.chmod(0o755)

        result = subprocess.run([str(SOURCE.parent / 'services.sh'), 'restart'], cwd=self.root, env={'PATH': str(self.root) + ':/usr/bin:/bin', 'SERVICES_PYTHON': '/missing-python'}, capture_output=True, text=True)

        self.assertEqual(1, result.returncode)
        self.assertIn('Local services are for macOS only', result.stderr)
        self.assertNotIn('missing-python', result.stderr)

    def test_server_shell_resolves_project_and_config_paths_from_any_directory(self):
        server = SOURCE.parent.parent / 'server'
        interpreter = self.root / 'capture-python'
        interpreter.write_text('#!/bin/sh\nprintf "%s\\n" "$SERVICES_ENV" "$SERVICES_ROOT" "$SERVICES_CONFIG" "$SERVICES_CONTAINER" "$SERVICES_CONTAINER_ROOT" "$@"\n')
        interpreter.chmod(0o755)
        uname = self.root / 'uname'
        uname.write_text('#!/bin/sh\nprintf "Linux\\n"\n')
        uname.chmod(0o755)
        environment = {'PATH': str(self.root) + ':/usr/bin:/bin', 'SERVICES_PYTHON': str(interpreter)}

        result = subprocess.run([str(server / 'services.sh'), 'status', 'betfair'], cwd=self.root, env=environment, capture_output=True, text=True, check=True)

        self.assertEqual([
            'production', str(server.parent), str(server / 'services-manager.json'), 'aapanel',
            '/www/wwwroot/staging777.bfmarketsanalytics.com', str(server / 'services-manager.py'), 'status', 'betfair',
        ], result.stdout.splitlines())

    def test_existing_telegram_entry_point_forwards_to_local_listener_without_changing_arguments(self):
        entry = SOURCE.parent.parent / 'telegram-listener.py'

        with patch.object(runpy, 'run_path') as execute:
            exec(compile(entry.read_text(), str(entry), 'exec'), {'__name__': '__main__', '__file__': str(entry)})

        execute.assert_called_once_with(str(SOURCE.parent / 'telegram-listener.py'), run_name='__main__')

    def test_server_recovery_updates_installed_listeners_and_queue_workers_without_restarting(self):
        config = SOURCE.parent.parent / 'server/services-manager.json'
        with patch.object(server_module.platform, 'system', return_value='Linux'), patch.dict(os.environ, {
            'SERVICES_ENV': 'production', 'SERVICES_CONFIG': str(config),
        }):
            manager = server_module.Manager()
        missing = manager.instances['queue-alerts-telegram']['unit']
        effective_policy = 'Restart=always\nRestartUSec=20s\nStartLimitIntervalUSec=0\nRestartPreventExitStatus=\n'
        installed = [spec['unit'] for spec in manager.instances.values() if spec['unit'] != missing
                     and spec['command'][0] in {'betfair:listen', 'sports-api-pro:listen', 'pinnodds:listen', 'queue:work'}]
        for unit in installed:
            (self.root / unit).write_text('existing Docker unit')

        with patch.object(manager, 'inspect', side_effect=lambda spec: (spec['unit'] != missing, 'active/running')), \
                patch.object(manager, 'verify_existing') as verify, \
                patch.object(manager, 'destination', side_effect=lambda spec: self.root / spec['unit']), \
                patch.object(server_module, 'run', return_value=subprocess.CompletedProcess([], 0, effective_policy, '')) as systemctl:
            manager.execute('configure-recovery')

        self.assertEqual(len(installed), verify.call_count)
        self.assertEqual(['daemon-reload'] + ['show'] * len(installed), [call.args[0][1] for call in systemctl.call_args_list])
        for unit in installed:
            self.assertEqual('existing Docker unit', (self.root / unit).read_text())
            self.assertIn('Restart=always', (self.root / (unit + '.d') / '90-bfmarkets-recovery.conf').read_text())
        self.assertFalse((self.root / (missing + '.d')).exists())
        self.assertFalse((self.root / 'bfmarkets-reverb.service.d').exists())

    def test_local_managed_processes_use_finite_php_memory_and_keep_queue_memory_defaults(self):
        for name, spec in self.manager.instances.items():
            job = plistlib.loads(self.manager.definition(name, spec))
            self.assertEqual(['/bin/sh', '-d', 'memory_limit=2G', str(self.root / 'artisan')] + spec['command'], job['ProgramArguments'])

    def test_unlimited_or_invalid_service_memory_settings_are_rejected(self):
        for limit in ['-1', '0', '512M --bad', '']:
            with self.subTest(limit=limit), self.assertRaisesRegex(ValueError, 'finite positive size'):
                self.manager.definition('betfair', dict(self.manager.instances['betfair'], php_memory_limit=limit))
        self.manager.config.pop('php_memory_limit', None)
        self.manager.instances['betfair'].pop('php_memory_limit', None)
        job = plistlib.loads(self.manager.definition('betfair', self.manager.instances['betfair']))
        self.assertEqual(['/bin/sh', str(self.root / 'artisan'), 'betfair:listen'], job['ProgramArguments'])

    def test_production_scheduler_enabled_and_realtime_workers_configured(self):
        with patch.object(module.platform, 'system', return_value='Linux'), patch.dict(os.environ, {'SERVICES_ENV': 'production'}):
            manager = module.Manager()
            self.assertTrue(manager.instances['scheduler']['enabled'])
            self.assertEqual(9, len([name for name in manager.instances if name.startswith('queue-realtime-')]))
            unit = manager.definition('betfair', manager.instances['betfair']).decode()
            self.assertNotIn('memory_limit=', unit)
            self.assertIn('Restart=always', unit)
            self.assertIn('KillSignal=SIGTERM', unit)
            self.assertIn('WantedBy=multi-user.target', unit)

    def test_local_names_and_log_paths_do_not_depend_on_username_or_project_path(self):
        self.assertEqual('com.bfmarkets2508.reverb', self.manager.instances['reverb']['label'])
        job = plistlib.loads(self.manager.definition('reverb', self.manager.instances['reverb']))
        self.assertEqual(str(Path.home() / 'Library/Logs/bfmarkets2508/reverb.log'), job['StandardOutPath'])
        self.assertEqual(str(self.root), job['WorkingDirectory'])

    def test_local_services_pass_php_ini_settings_to_scheduled_child_processes(self):
        settings = {
            'HERD_PHP_84_INI_SCAN_DIR': '/tmp/herd/php/84/',
            'PHP_INI_SCAN_DIR': '/tmp/php/config/',
            'PHPRC': '/tmp/php/php.ini',
        }
        with patch.dict(os.environ, dict(settings, UNRELATED_SECRET='not-for-launchd')):
            job = plistlib.loads(self.manager.definition('scheduler', self.manager.instances['scheduler']))
        for key, value in settings.items():
            self.assertEqual(value, job['EnvironmentVariables'].get(key))
        self.assertNotIn('UNRELATED_SECRET', job['EnvironmentVariables'])

    def test_production_definitions_do_not_inherit_local_php_ini_settings(self):
        with patch.dict(os.environ, {'SERVICES_ENV': 'production', 'HERD_PHP_84_INI_SCAN_DIR': '/tmp/herd/php/84/'}):
            manager = module.Manager()
            job = plistlib.loads(manager.definition('scheduler', manager.instances['scheduler']))
        self.assertNotIn('HERD_PHP_84_INI_SCAN_DIR', job['EnvironmentVariables'])

    def test_linux_defaults_keep_existing_production_units_and_project_logs(self):
        with patch.object(module.platform, 'system', return_value='Linux'), patch.dict(os.environ, {}, clear=True), patch.dict(os.environ, {'SERVICES_ROOT': str(self.root), 'SERVICES_PHP': '/bin/sh', 'SERVICES_USER': 'www'}):
            manager = module.Manager()
            self.assertEqual('production', manager.environment)
            self.assertEqual('bfmarkets-reverb.service', manager.instances['reverb']['unit'])
            self.assertEqual('bfmarkets-betfair.service', manager.instances['betfair']['unit'])
            self.assertEqual('bfmarkets-realtime@football.service', manager.instances['queue-realtime-football']['unit'])
            self.assertEqual(self.root / 'storage/app/service-manager/logs', manager.logs)
            self.assertNotIn('/Users/', manager.definition('reverb', manager.instances['reverb']).decode())

    def test_explicit_prefix_override_is_validated(self):
        with patch.dict(os.environ, {'SERVICES_PREFIX': 'custom-project'}):
            self.assertEqual('com.custom-project.reverb', module.Manager().instances['reverb']['label'])
        with patch.dict(os.environ, {'SERVICES_PREFIX': '../invalid'}):
            with self.assertRaisesRegex(ValueError, 'Invalid service prefix'):
                module.Manager()

    def test_queue_arguments_match_each_dispatched_sport_queue(self):
        for sport in ['football', 'cricket', 'icehockey', 'americanfootball', 'baseball', 'basketball', 'tennis', 'greyhounds', 'horseracing']:
            command = self.manager.instances['queue-realtime-' + sport]['command']
            self.assertEqual(['queue:work', 'sports-realtime', '--queue=realtime-' + sport, '--sleep=0', '--timeout=15', '--tries=6', '--max-time=3600'], command)
        self.assertIn('--timeout=60', self.manager.instances['queue-default']['command'])
        self.assertIn('--tries=3', self.manager.instances['queue-default']['command'])
        self.assertIn('--timeout=30', self.manager.instances['queue-sports-images']['command'])
        self.assertNotIn('queue-realtime-1', self.manager.instances)

    def test_live_prematch_recovery_alerts_and_legacy_consumers_are_separate(self):
        self.assertIn('--queue=sports-statistics', self.manager.instances['queue-sports-statistics']['command'])
        self.assertNotIn('--queue=sports-statistics,sports-prematch-statistics', self.manager.instances['queue-sports-statistics']['command'])
        for service, queue in [('queue-sports-recovery', 'sports-recovery'), ('queue-sports-timeline', 'sports-timeline'), ('queue-alerts', 'alerts'), ('queue-alerts-platform', 'alerts-platform'), ('queue-alerts-telegram', 'alerts-ltd,alerts-telegram')]:
            self.assertIn('--queue=' + queue, self.manager.instances[service]['command'])
        self.assertIn('database', self.manager.instances['queue-legacy-sports-statistics']['command'])
        self.assertIn('--queue=sports-statistics', self.manager.instances['queue-legacy-sports-statistics']['command'])
        self.assertIn('queue-sports-prematch-statistics-1', self.manager.instances)
        self.assertIn('queue-sports-prematch-statistics-2', self.manager.instances)
        for spec in self.manager.instances.values():
            if spec['command'][0] == 'queue:work':
                self.assertIn('--max-time=3600', spec['command'])

    def test_multiple_workers_with_an_explicit_unit_get_distinct_linux_units(self):
        config = json.loads((SOURCE.parent / 'services-manager.json').read_text())
        config['services']['queue-realtime-football']['workers'] = 2
        path = self.root / 'multiple-workers.json'
        path.write_text(json.dumps(config))
        with patch.dict(os.environ, {'SERVICES_CONFIG': str(path)}), patch.object(module.platform, 'system', return_value='Linux'):
            manager = module.Manager()
        names = [manager.instances['queue-realtime-football-' + str(index)]['unit'] for index in (1, 2)]
        self.assertEqual(['bfmarkets-realtime@football-1.service', 'bfmarkets-realtime@football-2.service'], names)

    def test_queue_process_matching_distinguishes_connections_and_exact_queue_names(self):
        legacy = self.manager.instances['queue-legacy-sports-statistics']
        redis = '123 php ' + str(self.root / 'artisan') + ' queue:work sports-realtime --queue=sports-statistics,sports-prematch-statistics'
        with patch.object(module, 'run', return_value=subprocess.CompletedProcess([], 0, redis, '')):
            self.manager.reject_unmanaged(legacy)
        different = redis.replace('--queue=sports-statistics,sports-prematch-statistics', '--queue=sports-statistics-history')
        with patch.object(module, 'run', return_value=subprocess.CompletedProcess([], 0, different, '')):
            self.manager.reject_unmanaged(self.manager.instances['queue-sports-statistics'])
        with patch.object(module, 'run', return_value=subprocess.CompletedProcess([], 0, redis, '')):
            with self.assertRaisesRegex(ValueError, 'Unmanaged'):
                self.manager.reject_unmanaged(self.manager.instances['queue-sports-statistics'])

    def test_docker_restart_waits_for_the_previous_process_before_starting(self):
        with patch.object(server_module.platform, 'system', return_value='Linux'), patch.dict(os.environ, {
            'SERVICES_ENV': 'production', 'SERVICES_CONTAINER': 'aapanel',
            'SERVICES_CONFIG': str(SOURCE.parent.parent / 'server/services-manager.json'),
        }):
            for service in ['queue-alerts', 'queue-alerts-telegram', 'pinnodds']:
                with self.subTest(service=service):
                    manager = server_module.Manager()
                    with patch.object(manager, 'inspect', return_value=(True, 'active/running pid=123')), \
                            patch.object(manager, 'verify_existing'), \
                            patch.object(manager, 'destination', return_value=self.root / 'unit.service'), \
                            patch.object(manager, 'stop') as stop, \
                            patch.object(manager, 'reject_unmanaged', side_effect=[ValueError('Unmanaged matching Artisan process exists'), None]) as guard, \
                            patch.object(server_module, 'run') as systemctl, \
                            patch.object(time, 'monotonic', return_value=100), \
                            patch.object(time, 'sleep') as sleep:
                        manager.execute('restart', service)

                    stop.assert_called_once_with(manager.instances[service])
                    self.assertEqual(2, guard.call_count)
                    sleep.assert_called_once_with(1)
                    systemctl.assert_called_once_with(['systemctl', 'enable', '--now', manager.instances[service]['unit']])

    def test_docker_restart_keeps_duplicate_protection_when_the_process_does_not_exit(self):
        with patch.object(server_module.platform, 'system', return_value='Linux'), patch.dict(os.environ, {
            'SERVICES_ENV': 'production', 'SERVICES_CONTAINER': 'aapanel',
            'SERVICES_CONFIG': str(SOURCE.parent.parent / 'server/services-manager.json'),
        }):
            manager = server_module.Manager()
            with patch.object(manager, 'inspect', return_value=(True, 'active/running pid=123')), \
                    patch.object(manager, 'verify_existing'), \
                    patch.object(manager, 'destination', return_value=self.root / 'unit.service'), \
                    patch.object(manager, 'stop'), \
                    patch.object(manager, 'reject_unmanaged', side_effect=ValueError('Unmanaged matching Artisan process exists')), \
                    patch.object(server_module, 'run') as systemctl, \
                    patch.object(time, 'monotonic', side_effect=[100, 221]), \
                    patch.object(time, 'sleep') as sleep:
                with self.assertRaisesRegex(ValueError, 'Unmanaged'):
                    manager.execute('restart', 'queue-alerts-telegram')

            systemctl.assert_not_called()
            sleep.assert_not_called()

    def test_docker_start_rejects_an_unmanaged_process_without_waiting_or_stopping_it(self):
        with patch.object(server_module.platform, 'system', return_value='Linux'), patch.dict(os.environ, {
            'SERVICES_ENV': 'production', 'SERVICES_CONTAINER': 'aapanel',
            'SERVICES_CONFIG': str(SOURCE.parent.parent / 'server/services-manager.json'),
        }):
            manager = server_module.Manager()
            with patch.object(manager, 'inspect', return_value=(True, 'inactive/dead pid=0')), \
                    patch.object(manager, 'verify_existing'), \
                    patch.object(manager, 'destination', return_value=self.root / 'unit.service'), \
                    patch.object(manager, 'stop') as stop, \
                    patch.object(manager, 'reject_unmanaged', side_effect=ValueError('Unmanaged matching Artisan process exists')), \
                    patch.object(server_module, 'run') as systemctl, \
                    patch.object(time, 'sleep') as sleep:
                with self.assertRaisesRegex(ValueError, 'Unmanaged'):
                    manager.execute('start', 'queue-alerts-telegram')

            stop.assert_not_called()
            systemctl.assert_not_called()
            sleep.assert_not_called()

    def test_loaded_service_start_is_idempotent(self):
        with patch.object(self.manager, 'inspect', return_value=(True, 'running pid=123')), patch.object(self.manager, 'verify_existing'), patch.object(module, 'run') as run:
            self.manager.execute('start', 'betfair')
            run.assert_not_called()

    def test_disabled_services_never_start(self):
        self.manager.instances["pinnodds"]["enabled"] = False
        with patch.object(self.manager, 'inspect', return_value=(False, 'stopped')), patch.object(module, 'run') as run:
            self.manager.execute('start', 'pinnodds')
            self.assertTrue(all(call.args[0][1] != 'bootstrap' for call in run.call_args_list))

    def test_restart_stops_then_starts_only_selected_service(self):
        destination = self.root / 'betfair.plist'
        destination.write_bytes(self.manager.definition('betfair', self.manager.instances['betfair']))
        with patch.object(self.manager, 'destination', return_value=destination), patch.object(self.manager, 'inspect', return_value=(True, 'running pid=123')), patch.object(self.manager, 'verify_existing'), patch.object(self.manager, 'reject_unmanaged'), patch.object(module.os, 'kill', side_effect=ProcessLookupError), patch.object(module, 'run') as run:
            self.manager.execute('restart', 'betfair')
            calls = [call.args[0] for call in run.call_args_list]
            self.assertEqual('bootout', calls[0][1])
            self.assertEqual('bootstrap', calls[-1][1])
            self.assertTrue(all('sports-api-pro' not in ' '.join(call) for call in calls))

    def test_mac_stop_waits_for_the_owned_process_to_exit(self):
        with patch.object(self.manager, 'inspect', return_value=(True, 'running pid=123')), patch.object(module, 'run') as run, patch.object(module.os, 'kill', side_effect=[None, ProcessLookupError]) as probe:
            self.manager.stop(self.manager.instances['betfair'])

        self.assertEqual(2, probe.call_count)
        probe.assert_called_with(123, 0)
        run.assert_called_once_with(['launchctl', 'bootout', self.manager.domain + '/com.bfmarkets2508.betfair'])

    def test_mac_stop_timeout_does_not_force_kill_the_owned_process(self):
        with patch.object(self.manager, 'inspect', return_value=(True, 'running pid=123')), patch.object(module, 'run'), patch.object(module.os, 'kill') as probe, patch.object(module.time, 'monotonic', side_effect=[0, 121]):
            with self.assertRaisesRegex(ValueError, 'still stopping'):
                self.manager.stop(self.manager.instances['betfair'])

        probe.assert_called_once_with(123, 0)

    def test_pending_unmanaged_process_blocks_duplicate_start(self):
        with patch.object(module, 'run', return_value=subprocess.CompletedProcess([], 0, '123 php artisan betfair:listen\n', '')):
            with self.assertRaisesRegex(ValueError, 'Unmanaged'):
                self.manager.reject_unmanaged(self.manager.instances['betfair'])

    def test_status_comes_from_supervisor_not_pid_file(self):
        with patch.object(module, 'run', return_value=subprocess.CompletedProcess([], 0, 'state = running\npid = 123\n', '')):
            self.assertEqual((True, 'running pid=123'), self.manager.inspect(self.manager.instances['betfair']))

    def test_reload_stops_removed_owned_service(self):
        self.manager.state.mkdir(parents=True)
        old = dict(self.manager.instances['betfair'], label='com.bfmarkets.removed')
        registry = self.manager.state / 'managed-local.json'
        registry.write_text(json.dumps({'removed': old}))
        with patch.object(self.manager, 'inspect', return_value=(True, 'running')), patch.object(self.manager, 'verify_existing'), patch.object(self.manager, 'destination', return_value=self.root / 'test.plist'), patch.object(self.manager, 'stop') as stop, patch.object(module, 'run'):
            (self.root / 'test.plist').write_bytes(self.manager.definition('betfair', self.manager.instances['betfair']))
            # Only one enabled service to isolate removed-service reconciliation.
            self.manager.instances = {'betfair': self.manager.instances['betfair']}
            self.manager.execute('reload')
            stop.assert_called_once_with(old, disable=True)
            self.assertNotIn('removed', json.loads(registry.read_text()))

    def test_docker_units_require_explicit_container_and_stop_definition(self):
        self.manager.mac = False
        unit = '[Service]\nExecStart=docker exec app php /srv/app/artisan betfair:listen\nExecStop=docker exec app stop\n'
        with patch.object(module, 'run', return_value=subprocess.CompletedProcess([], 0, unit, '')):
            with self.assertRaisesRegex(ValueError, 'SERVICES_CONTAINER'):
                self.manager.verify_existing(self.manager.instances['betfair'])
            with patch.dict(os.environ, {'SERVICES_CONTAINER': 'app', 'SERVICES_CONTAINER_ROOT': '/srv/app'}):
                self.manager.verify_existing(self.manager.instances['betfair'])

    def test_stop_uses_graceful_supervisor_stop(self):
        with patch.object(self.manager, 'inspect', return_value=(True, 'running')), patch.object(self.manager, 'verify_existing'), patch.object(module, 'run') as run:
            self.manager.execute('stop', 'betfair')
            self.assertEqual(['launchctl', 'bootout', self.manager.domain + '/' + self.manager.instances['betfair']['label']], run.call_args.args[0])

    def test_reload_updates_only_changed_owned_definition(self):
        self.manager.state.mkdir(parents=True)
        previous = dict(self.manager.instances['betfair'])
        content = self.manager.definition('betfair', previous)
        previous['definition_hash'] = module.hashlib.sha256(content).hexdigest()
        destination = self.root / 'betfair.plist'
        destination.write_bytes(content)
        (self.manager.state / 'managed-local.json').write_text(json.dumps({'betfair': previous}))
        self.manager.instances['betfair']['command'] = ['betfair:listen', '--no-interaction']
        with patch.object(self.manager, 'inspect', return_value=(True, 'running')), patch.object(self.manager, 'verify_existing'), patch.object(self.manager, 'destination', return_value=destination), patch.object(self.manager, 'reject_unmanaged'), patch.object(module, 'run') as run:
            self.manager.execute('reload', 'betfair')
            self.assertEqual('bootout', run.call_args_list[0].args[0][1])
            self.assertIn('--no-interaction', plistlib.loads(destination.read_bytes())['ProgramArguments'])
            self.assertEqual('bootstrap', run.call_args_list[-1].args[0][1])

    def test_interrupted_reload_records_the_written_definition_before_starting(self):
        self.manager.state.mkdir(parents=True)
        previous = dict(self.manager.instances['betfair'])
        destination = self.root / 'betfair.plist'
        content = self.manager.definition('betfair', previous)
        destination.write_bytes(content)
        previous['definition_hash'] = module.hashlib.sha256(content).hexdigest()
        registry = self.manager.state / 'managed-local.json'
        registry.write_text(json.dumps({'betfair': previous}))
        self.manager.instances['betfair']['command'] = ['betfair:listen', '--no-interaction']

        with patch.object(self.manager, 'inspect', return_value=(True, 'running')), patch.object(self.manager, 'verify_existing'), patch.object(self.manager, 'destination', return_value=destination), patch.object(self.manager, 'reject_unmanaged', side_effect=ValueError('Unmanaged matching Artisan process exists')), patch.object(module, 'run'):
            with self.assertRaisesRegex(ValueError, 'Unmanaged'):
                self.manager.execute('reload', 'betfair')

        recorded = json.loads(registry.read_text())['betfair']['definition_hash']
        self.assertEqual(module.hashlib.sha256(destination.read_bytes()).hexdigest(), recorded)

    def test_reload_removes_memory_overrides_from_verified_legacy_mac_definition(self):
        destination = self.root / 'legacy.plist'
        job = plistlib.loads(self.manager.definition('betfair', self.manager.instances['betfair']))
        job['ProgramArguments'] = ['/bin/sh', '-d', 'memory_limit=-1', str(self.root / 'artisan'), 'betfair:listen']
        destination.write_bytes(plistlib.dumps(job))
        with patch.object(self.manager, 'inspect', return_value=(True, 'running')), patch.object(self.manager, 'verify_existing') as verify, patch.object(self.manager, 'destination', return_value=destination), patch.object(self.manager, 'reject_unmanaged'), patch.object(module, 'run'):
            self.manager.execute('reload', 'betfair')
            verify.assert_called()
            self.assertEqual(['/bin/sh', '-d', 'memory_limit=2G', str(self.root / 'artisan'), 'betfair:listen'], plistlib.loads(destination.read_bytes())['ProgramArguments'])
        registry = json.loads((self.manager.state / 'managed-local.json').read_text())
        self.assertIn('definition_hash', registry['betfair'])
        with patch.object(self.manager, 'inspect', return_value=(True, 'running')), patch.object(self.manager, 'verify_existing'), patch.object(self.manager, 'destination', return_value=destination), patch.object(module, 'run'):
            self.manager.execute('stop', 'betfair')
        self.assertEqual(registry['betfair']['definition_hash'], json.loads((self.manager.state / 'managed-local.json').read_text())['betfair']['definition_hash'])

    def test_prepare_does_not_start_or_install_services(self):
        with patch.object(module, 'run') as run:
            self.manager.execute('prepare', 'betfair')
            run.assert_not_called()
            self.assertTrue((self.manager.state / (self.manager.instances['betfair']['label'] + '.plist')).exists())

    def test_telegram_bot_and_source_processes_are_distinguished(self):
        source = subprocess.CompletedProcess([], 0, '123 php artisan telegram:listen --source --login\n', '')
        with patch.object(module, 'run', return_value=source):
            self.manager.reject_unmanaged(self.manager.instances['telegram-bot'])
            with self.assertRaisesRegex(ValueError, 'Unmanaged matching Artisan process'):
                self.manager.reject_unmanaged(self.manager.instances['telegram-source'])
        bot = subprocess.CompletedProcess([], 0, '124 php artisan telegram:listen\n', '')
        with patch.object(module, 'run', return_value=bot):
            self.manager.reject_unmanaged(self.manager.instances['telegram-source'])
            with self.assertRaisesRegex(ValueError, 'Unmanaged matching Artisan process'):
                self.manager.reject_unmanaged(self.manager.instances['telegram-bot'])

    def test_production_install_preserves_existing_units(self):
        self.manager.mac = False
        with patch.object(self.manager, 'inspect', return_value=(True, 'active/running')), patch.object(self.manager, 'verify_existing'), patch.object(self.manager, 'definition') as definition, patch.object(module, 'run') as run:
            self.manager.execute('install', 'betfair')
            definition.assert_not_called()
            run.assert_not_called()


if __name__ == '__main__':
    unittest.main()
