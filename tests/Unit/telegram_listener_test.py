import asyncio
import datetime
import json
import os
from pathlib import Path
import runpy
import sys
import tempfile
import types
import unittest
import urllib.request
from unittest.mock import AsyncMock, patch


ROOT = Path(__file__).resolve().parents[2]


class TelegramListenerTest(unittest.TestCase):
    def execute_listener(self, script, *, check=False, authorized=True, login=False, phone='', ocr_version=None, reply_failure=False, failed_ocr_mode=None):
        handlers = []
        requests = []
        calls = []
        now = datetime.datetime.now(datetime.timezone.utc)
        reply = types.SimpleNamespace(id=11, raw_text='context ' * 2000)

        class Event:
            id = 12
            date = now
            edit_date = None
            is_reply = True
            raw_text = 'Total. Over (0.5). 1st half'
            photo = types.SimpleNamespace(id=99) if ocr_version is not None else None

            async def download_media(self, **kwargs):
                return b'test-image'

            async def get_reply_message(self):
                if reply_failure and 'reply_failed' not in calls:
                    calls.append('reply_failed')
                    raise RuntimeError('Temporary reply failure')
                return reply

        class Client:
            def __init__(self, *args, **kwargs):
                self.authorized = authorized

            async def connect(self):
                calls.append('connect')

            async def is_user_authorized(self):
                return self.authorized

            async def start(self, **kwargs):
                calls.append(('login', kwargs))
                self.authorized = True

            async def get_entity(self, source):
                calls.append(('channel', source))
                return types.SimpleNamespace(id=777, title='MATCH PREDICTIONS')

            def on(self, builder):
                def register(handler):
                    handlers.append((builder, handler))
                    return handler
                return register

            async def catch_up(self):
                calls.append(('catch_up', len(handlers)))
                await handlers[0][1](Event())

            def is_connected(self):
                return True

            async def run_until_disconnected(self):
                calls.append('listen')
                event = Event()
                event.edit_date = now + datetime.timedelta(seconds=1)
                event.raw_text = 'GOAL!'
                await handlers[1][1](event)

            async def disconnect(self):
                calls.append('disconnect')

        class Response:
            status = 202
            def __enter__(self):
                return self
            def __exit__(self, *args):
                return False

        def urlopen(request, **kwargs):
            requests.append(json.loads(request.data))
            return Response()

        async def subprocess_exec(*args, **kwargs):
            if args[1] not in ('--version', 'stdin'):
                self.fail('Only --version may be used to probe Tesseract; legacy --help exits non-zero')
            class Process:
                returncode = 1 if (args[1] == '--version' and ocr_version.startswith(b'tesseract 3.')) or (args[1] == 'stdin' and args[4] == failed_ocr_mode) else 0

                async def communicate(self, data=None):
                    if args[1] == '--version':
                        return ocr_version, None
                    expected_option = '-psm' if ocr_version.startswith(b'tesseract 3.') else '--psm'
                    self_test.assertEqual(args[:4], ('/usr/bin/tesseract', 'stdin', 'stdout', expected_option))
                    self_test.assertIn(args[4], ('6', '3'))
                    calls.append(('ocr', args[4]))
                    self_test.assertEqual(data, b'test-image')
                    if args[4] == failed_ocr_mode:
                        return b'', None
                    return (b'Jordan 0:0 Australia\nFootball. Friendlies. National Teams' if args[4] == '6' else b'Jordan\n0:0\nAustralia'), None

            return Process()

        self_test = self

        events = types.SimpleNamespace(
            NewMessage=lambda **kwargs: ('new', kwargs),
            MessageEdited=lambda **kwargs: ('edited', kwargs),
        )
        telethon = types.SimpleNamespace(TelegramClient=Client, events=events)
        with tempfile.TemporaryDirectory(prefix='bfmarkets-listener-test-') as directory:
            environment = {
                'TELEGRAM_SESSION_FILE': str(Path(directory) / 'source.session'),
                'TELEGRAM_API_ID': '1', 'TELEGRAM_API_HASH': 'test-only',
                'TELEGRAM_PHONE': phone,
                'TELEGRAM_SOURCE_CHANNEL': 'test-source', 'TELEGRAM_SOURCE_SECRET': 'test-only',
                'BF_SOURCE_URL': 'https://example.test/notifications/telegram/source',
            }
            previous_umask = os.umask(0o077)
            try:
                with patch.dict(os.environ, environment), patch.dict(sys.modules, {'telethon': telethon}), \
                     patch.object(sys, 'argv', [str(script), *(['--check'] if check else []), *(['--login'] if login else [])]), \
                     patch('urllib.request.urlopen', side_effect=urlopen), \
                     patch('asyncio.sleep', new_callable=AsyncMock), \
                     patch('shutil.which', return_value='/usr/bin/tesseract' if ocr_version is not None else None), \
                     patch('asyncio.create_subprocess_exec', side_effect=subprocess_exec):
                    runpy.run_path(str(script), run_name='__main__')
            finally:
                os.umask(previous_umask)
        return calls, handlers, requests

    def test_health_check_authenticates_and_resolves_channel_without_forwarding(self):
        for location in ['server', 'local']:
            with self.subTest(location=location):
                calls, handlers, requests = self.execute_listener(ROOT / location / 'telegram-listener.py', check=True)
                self.assertEqual(calls, ['connect', ('channel', 'test-source'), 'disconnect'])
                self.assertEqual(handlers, [])
                self.assertEqual(requests, [])

    def test_image_posts_are_read_with_compatible_options_for_legacy_and_modern_tesseract(self):
        for location in ['server', 'local']:
            for version_text in [b'tesseract 3.04.00\n leptonica-1.72', b'tesseract 4.1.1', b'tesseract 5.5.0']:
                with self.subTest(location=location, version_text=version_text):
                    calls, _, requests = self.execute_listener(ROOT / location / 'telegram-listener.py', ocr_version=version_text)
                    self.assertEqual([call for call in calls if isinstance(call, tuple) and call[0] == 'ocr'], [('ocr', '6'), ('ocr', '3')])
                    self.assertIn('Jordan\n0:0\nAustralia', requests[0]['text'])
                    self.assertEqual(len(requests), 2)
                    self.assertIn('Jordan 0:0 Australia', requests[0]['text'])
                    self.assertIn('Total. Over (0.5). 1st half', requests[0]['text'])

    def test_a_failed_ocr_layout_does_not_discard_the_other_layout(self):
        for location in ['server', 'local']:
            for failed_mode, recovered in [('6', 'Jordan\n0:0\nAustralia'), ('3', 'Jordan 0:0 Australia')]:
                with self.subTest(location=location, failed_mode=failed_mode):
                    _, _, requests = self.execute_listener(ROOT / location / 'telegram-listener.py',
                        ocr_version=b'tesseract 3.04.00', failed_ocr_mode=failed_mode)
                    self.assertEqual(len(requests), 2)
                    self.assertIn(recovered, requests[0]['text'])
                    self.assertIn(recovered, requests[1]['text'])

    def test_new_and_edited_posts_and_offline_updates_reach_laravel(self):
        calls, handlers, requests = self.execute_listener(ROOT / 'server/telegram-listener.py')
        self.assertIn(('catch_up', 2), calls)
        self.assertEqual([builder[0] for builder, _ in handlers], ['edited', 'new'])
        self.assertEqual(len(requests), 2)
        self.assertEqual({request['source'] for request in requests}, {'test-source'})
        self.assertEqual({request['message_id'] for request in requests}, {12})
        self.assertEqual(len(requests[0]['reply_text']), 8192)
        self.assertNotEqual(requests[0]['sent_at'], requests[1]['sent_at'])
        self.assertEqual(requests[0]['reply_message_id'], 11)

    def test_a_temporary_reply_error_retries_without_stopping_subsequent_posts(self):
        calls, handlers, requests = self.execute_listener(ROOT / 'server/telegram-listener.py', reply_failure=True)
        self.assertIn('reply_failed', calls)
        self.assertEqual(len(requests), 2)
        self.assertIn('listen', calls)

    def test_passive_worker_does_not_attempt_interactive_login(self):
        with self.assertRaisesRegex(SystemExit, 'Owner login required'):
            self.execute_listener(ROOT / 'server/telegram-listener.py', authorized=False)

    def test_explicit_login_passes_configured_phone_and_does_not_deliver_alerts(self):
        for location in ['server', 'local']:
            with self.subTest(location=location):
                calls, handlers, requests = self.execute_listener(
                    ROOT / location / 'telegram-listener.py', authorized=False,
                    login=True, phone='+15555550123',
                )
                self.assertEqual(calls, ['connect', ('login', {'phone': '+15555550123'}), 'disconnect'])
                self.assertEqual(handlers, [])
                self.assertEqual(requests, [])

    def test_explicit_login_without_phone_keeps_interactive_phone_prompt(self):
        calls, handlers, requests = self.execute_listener(
            ROOT / 'server/telegram-listener.py', authorized=False, login=True,
        )
        self.assertEqual(calls, ['connect', ('login', {}), 'disconnect'])
        self.assertEqual(requests, [])

    def test_saved_authorization_does_not_attempt_login_again(self):
        calls, handlers, requests = self.execute_listener(
            ROOT / 'server/telegram-listener.py', login=True, phone='+15555550123',
        )
        self.assertEqual(calls, ['connect', 'disconnect'])
        self.assertEqual(requests, [])

    def test_an_existing_source_session_lock_prevents_duplicate_listeners(self):
        with patch('fcntl.flock', side_effect=BlockingIOError):
            with self.assertRaisesRegex(SystemExit, 'Another source process is using this session'):
                self.execute_listener(ROOT / 'server/telegram-listener.py')


if __name__ == '__main__':
    unittest.main()
