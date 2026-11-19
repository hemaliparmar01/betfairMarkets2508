import importlib.util
from pathlib import Path
import unittest

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

    def test_unrelated_units_and_wrong_containers_are_rejected_before_changes(self):
        for value in [self.command('--queue=default'), self.command('--queue=alerts-telegram').replace('aapanel', 'other'), '{ argv[]=/bin/sh -c worker ; }']:
            with self.assertRaises(ValueError):
                module.worker_command(value, 'queue-alerts-telegram', 'aapanel', self.app)


if __name__ == '__main__':
    unittest.main()
