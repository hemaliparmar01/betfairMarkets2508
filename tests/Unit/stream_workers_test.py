import importlib.util
import io
import json
from pathlib import Path
import re
import shlex
import subprocess
import tempfile
import unittest
from contextlib import redirect_stdout
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('stream_workers', Path(__file__).resolve().parents[2] / 'server/configure-stream-workers.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class StreamWorkerTest(unittest.TestCase):
    app = '/www/wwwroot/staging777.bfmarketsanalytics.com'

    def command(self, options):
        return '{ path=/usr/bin/docker ; argv[]=/usr/bin/docker exec --user www -w ' + self.app + ' aapanel php artisan queue:work ' + options + ' ; ignore_errors=no ; }'

    def test_source_queue_is_added_without_changing_other_worker_arguments(self):
        command = module.worker_command(self.command('--queue=alerts-telegram --sleep=1 --timeout=60'), 'queue-alerts-telegram', 'aapanel', self.app)
        self.assertIn('--queue=alerts-ltd,alerts-telegram', command)
        self.assertIn('--sleep=1', command)
        self.assertIn('--timeout=60', command)
        value = '{ argv[]=' + ' '.join(command) + ' ; }'
        self.assertEqual(command, module.worker_command(value, 'queue-alerts-telegram', 'aapanel', self.app))

    def test_realtime_workers_wait_on_redis_without_an_extra_one_second_sleep(self):
        command = module.worker_command(self.command('sports-realtime --queue=realtime-football --sleep=1 --timeout=15'), 'queue-realtime-football', 'aapanel', self.app)
        self.assertIn('--sleep=0', command)
        self.assertNotIn('--sleep=1', command)
        self.assertIn('--timeout=15', command)
        self.assertIn('sports-realtime', command)

    def test_shell_wrapped_workers_become_direct_docker_commands(self):
        script = 'cd ' + self.app + ' && exec php artisan queue:work --queue=alerts-telegram --sleep=1'
        for shown in [script, "'" + script + "'"]:
            with self.subTest(shown=shown):
                value = '{ argv[]=/usr/bin/docker exec -i --user www aapanel sh -lc ' + shown + ' ; ignore_errors=no ; }'

                command = module.worker_command(value, 'queue-alerts-telegram', 'aapanel', self.app)

                self.assertEqual(['/usr/bin/docker', 'exec', '-w', self.app, '-i', '--user', 'www', 'aapanel', 'php', 'artisan', 'queue:work', '--queue=alerts-ltd,alerts-telegram', '--sleep=1'], command)

    def test_shell_commands_for_another_project_or_with_extra_actions_are_rejected(self):
        for script in ['cd /other && exec php artisan queue:work --queue=alerts-telegram',
                       'cd ' + self.app + ' && exec php artisan queue:work --queue=alerts-telegram && touch /tmp/unexpected']:
            with self.subTest(script=script), self.assertRaises(ValueError):
                module.worker_command('{ argv[]=/usr/bin/docker exec aapanel sh -lc ' + script + ' ; }', 'queue-alerts-telegram', 'aapanel', self.app)

    def test_stop_targets_the_updated_worker_without_matching_other_queues(self):
        command = module.worker_command(self.command('--queue=alerts-telegram --sleep=1'), 'queue-alerts-telegram', 'aapanel', self.app)

        stop = module.worker_stop_command(command)
        pattern = shlex.split(stop[-1])[3]

        self.assertIsNotNone(re.search(pattern, 'php artisan queue:work --queue=alerts-ltd,alerts-telegram --sleep=1'))
        self.assertIsNone(re.search(pattern, 'php artisan queue:work --queue=alerts-platform --sleep=1'))
        self.assertEqual(['sh', '-lc'], stop[-3:-1])

    def test_realtime_repair_keeps_the_queue_and_stops_old_and_new_option_orders(self):
        for sport in ['football', 'tennis', 'basketball', 'cricket', 'icehockey', 'americanfootball', 'baseball']:
            with self.subTest(sport=sport):
                script = 'cd ' + self.app + ' && exec php artisan queue:work sports-realtime --queue=realtime-' + sport + ' --name=realtime-' + sport + ' --sleep=1 --timeout=0'
                shown = '{ argv[]=/usr/bin/docker exec -i --user www aapanel sh -lc ' + script + ' ; }'
                command = module.worker_command(shown, 'queue-realtime-' + sport, 'aapanel', self.app)
                pattern = shlex.split(module.worker_stop_command(command)[-1])[3]

                self.assertNotIn('sh', command)
                self.assertIn('--sleep=0', command)
                self.assertIsNotNone(re.search(pattern, script.split('exec ', 1)[1]))
                self.assertIsNotNone(re.search(pattern, ' '.join(command[command.index('artisan') - 1:])))
                self.assertIsNone(re.search(pattern, ' '.join(command[command.index('artisan') - 1:]).replace('--queue=realtime-' + sport, '--queue=realtime-' + sport + '-other')))

    def test_unrelated_units_and_wrong_containers_are_rejected_before_changes(self):
        for value in [self.command('--queue=default'), self.command('--queue=alerts-telegram').replace('aapanel', 'other'), '{ argv[]=/bin/sh -c worker ; }']:
            with self.assertRaises(ValueError):
                module.worker_command(value, 'queue-alerts-telegram', 'aapanel', self.app)

    def test_selecting_one_realtime_worker_does_not_touch_telegram_or_other_sports(self):
        config = {'services': {'queue-realtime-tennis': {'command': ['queue:work'], 'unit': 'bfmarkets-realtime@tennis.service'},
                               'queue-alerts-telegram': {'command': ['queue:work'], 'unit': 'bfmarkets-queue-alerts-telegram.service'}}}
        shown = self.command('sports-realtime --queue=realtime-tennis --sleep=1')
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'services.json'
            path.write_text(json.dumps(config))
            output = io.StringIO()
            with patch('sys.argv', ['configure-stream-workers.py', '--config', str(path), '--service', 'queue-realtime-tennis']), \
                 patch.object(module.subprocess, 'run', return_value=subprocess.CompletedProcess([], 0, shown, '')) as run, redirect_stdout(output):
                module.main()

            run.assert_called_once_with(['systemctl', 'show', 'bfmarkets-realtime@tennis.service', '--property=ExecStart', '--value'], check=True, text=True, capture_output=True)
            self.assertIn('--queue=realtime-tennis', output.getvalue())
            self.assertNotIn('alerts-telegram', output.getvalue())
            self.assertEqual(config, json.loads(path.read_text()))


if __name__ == '__main__':
    unittest.main()
