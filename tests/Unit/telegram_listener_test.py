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
from unittest.mock import patch


ROOT = Path(__file__).resolve().parents[2]


class TelegramListenerTest(unittest.TestCase):
    def execute_listener(self, script, *, check=False, authorized=True):
        handlers = []
        requests = []
        calls = []
        now = datetime.datetime.now(datetime.timezone.utc)
        reply = types.SimpleNamespace(raw_text='context ' * 2000)

        class Event:
            id = 12
            date = now
            edit_date = None
            is_reply = True
            raw_text = 'Total. Over (0.5). 1st half'
            photo = None

            async def get_reply_message(self):
                return reply

        class Client:
            def __init__(self, *args, **kwargs):
                self.authorized = authorized

            async def connect(self):
                calls.append('connect')

            async def is_user_authorized(self):
                return self.authorized

            async def get_entity(self, source):
                calls.append(('channel', source))
                return 'channel-entity'

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

        events = types.SimpleNamespace(
            NewMessage=lambda **kwargs: ('new', kwargs),
            MessageEdited=lambda **kwargs: ('edited', kwargs),
        )
        telethon = types.SimpleNamespace(TelegramClient=Client, events=events)
        with tempfile.TemporaryDirectory(prefix='bfmarkets-listener-test-') as directory:
            environment = {
                'TELEGRAM_SESSION_FILE': str(Path(directory) / 'source.session'),
                'TELEGRAM_API_ID': '1', 'TELEGRAM_API_HASH': 'test-only',
                'TELEGRAM_SOURCE_CHANNEL': 'test-source', 'TELEGRAM_SOURCE_SECRET': 'test-only',
                'BF_SOURCE_URL': 'https://example.test/notifications/telegram/source',
            }
            previous_umask = os.umask(0o077)
            try:
                with patch.dict(os.environ, environment), patch.dict(sys.modules, {'telethon': telethon}), \
                     patch.object(sys, 'argv', [str(script), *(['--check'] if check else [])]), \
                     patch('urllib.request.urlopen', side_effect=urlopen), patch('shutil.which', return_value=None):
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

    def test_new_and_edited_posts_and_offline_updates_reach_laravel(self):
        calls, handlers, requests = self.execute_listener(ROOT / 'server/telegram-listener.py')
        self.assertIn(('catch_up', 2), calls)
        self.assertEqual([builder[0] for builder, _ in handlers], ['edited', 'new'])
        self.assertEqual(len(requests), 2)
        self.assertEqual({request['source'] for request in requests}, {'test-source'})
        self.assertEqual({request['message_id'] for request in requests}, {12})
        self.assertEqual(len(requests[0]['reply_text']), 8192)
        self.assertNotEqual(requests[0]['sent_at'], requests[1]['sent_at'])

    def test_passive_worker_does_not_attempt_interactive_login(self):
        with self.assertRaisesRegex(SystemExit, 'Owner login required'):
            self.execute_listener(ROOT / 'server/telegram-listener.py', authorized=False)


if __name__ == '__main__':
    unittest.main()
