"""Passive Telegram input only; all validation and delivery remain in Laravel."""
import asyncio
import json
import logging
import os
import shutil
import sys
import urllib.error
import urllib.request
from pathlib import Path

try:
    from telethon import TelegramClient, events
except ImportError:
    raise SystemExit('Install Telethon 1.45 in the TELEGRAM_PYTHON environment first.')

os.umask(0o077)
logging.basicConfig(level=logging.CRITICAL)
session = Path(os.environ['TELEGRAM_SESSION_FILE']).expanduser()
session.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
session.parent.chmod(0o700)
client = TelegramClient(str(session), int(os.environ['TELEGRAM_API_ID']),
                        os.environ['TELEGRAM_API_HASH'], sequential_updates=True,
                        auto_reconnect=True, catch_up=True)


async def main():
    await client.connect()
    if not await client.is_user_authorized():
        if '--login' not in sys.argv:
            raise SystemExit('Owner login required: php artisan telegram:listen --source --login')
        await client.start()
    if session.exists():
        session.chmod(0o600)
    if '--login' in sys.argv:
        print('Source session authorized. Start telegram:listen --source without --login.')
        await client.disconnect()
        return
    source = os.environ.get('TELEGRAM_SOURCE_CHANNEL', 'wageroddss')
    entity = await client.get_entity(source)
    ocr = shutil.which('tesseract')
    if not ocr:
        print('Tesseract is unavailable. Telegram image alerts require it.', file=sys.stderr)

    @client.on(events.NewMessage(chats=entity, incoming=True))
    @client.on(events.MessageEdited(chats=entity, incoming=True))
    async def receive(event):
        reply = await event.get_reply_message() if event.is_reply else None
        text = event.raw_text or ''
        if ocr and event.photo and ' vs ' not in text.lower():
            try:
                photo = await asyncio.wait_for(event.download_media(file=bytes), timeout=10)
                if photo:
                    process = await asyncio.create_subprocess_exec(
                        ocr, 'stdin', 'stdout', '--psm', '6',
                        stdin=asyncio.subprocess.PIPE, stdout=asyncio.subprocess.PIPE,
                        stderr=asyncio.subprocess.DEVNULL,
                    )
                    try:
                        extracted, _ = await asyncio.wait_for(process.communicate(photo), timeout=10)
                    except asyncio.TimeoutError:
                        if process.returncode is None:
                            process.kill()
                        await process.wait()
                        raise
                    if process.returncode == 0:
                        text = extracted.decode('utf-8', errors='replace') + '\n' + text
            except (OSError, asyncio.TimeoutError):
                print('Could not read Telegram alert image.', file=sys.stderr)
        if not text.strip():
            return
        payload = json.dumps({'source': source, 'message_id': event.id,
                              'sent_at': int((event.edit_date or event.date).timestamp()),
                              'text': text[:8192],
                              'reply_text': (reply.raw_text or '') if reply else None}).encode()

        def submit():
            request = urllib.request.Request(os.environ['BF_SOURCE_URL'], data=payload,
                        headers={'Content-Type': 'application/json', 'Accept': 'application/json',
                                 'X-BF-Source-Secret': os.environ['TELEGRAM_SOURCE_SECRET']})
            with urllib.request.urlopen(request, timeout=15) as response:
                return response.status

        while client.is_connected():
            try:
                if await asyncio.to_thread(submit) == 202:
                    return
            except urllib.error.HTTPError as error:
                if error.code in (401, 403, 422):
                    print('Source intake rejected; verify Laravel configuration.', file=sys.stderr)
                    return
            except (OSError, TimeoutError):
                pass
            await asyncio.sleep(5)

    print('Passive source listener connected. No messages, reactions or forwards are sent.')
    await client.run_until_disconnected()


try:
    asyncio.run(main())
except (KeyboardInterrupt, SystemExit):
    raise
except Exception:
    raise SystemExit('Source connection failed. Verify credentials, session and source membership.')
