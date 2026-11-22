"""Passive Telegram input only; all validation and delivery remain in Laravel."""
import asyncio
import fcntl
import json
import logging
import os
import re
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
session_lock = Path(str(session) + '.listener.lock').open('a')
try:
    fcntl.flock(session_lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
except BlockingIOError:
    session_lock.close()
    raise SystemExit('Another source process is using this session. Stop it before starting a new listener.')
client = TelegramClient(str(session), int(os.environ['TELEGRAM_API_ID']),
                        os.environ['TELEGRAM_API_HASH'], sequential_updates=True,
                        auto_reconnect=True)


async def main():
    if '--check' in sys.argv:
        print('Checking source connection...', flush=True)
    await asyncio.wait_for(client.connect(), timeout=20)
    if not await asyncio.wait_for(client.is_user_authorized(), timeout=15):
        if '--login' not in sys.argv:
            raise SystemExit('Owner login required: php artisan telegram:listen --source --login')
        phone = os.environ.get('TELEGRAM_PHONE', '').strip()
        if phone:
            await client.start(phone=phone)
        else:
            await client.start()
    if session.exists():
        session.chmod(0o600)
    if '--login' in sys.argv:
        print('Source session authorized. Start telegram:listen --source without --login.')
        await client.disconnect()
        return
    source = os.environ.get('TELEGRAM_SOURCE_CHANNEL', 'wageroddss')
    if '--check' in sys.argv:
        print('Source session authorized. Checking channel access...', flush=True)
    entity = await asyncio.wait_for(client.get_entity(source), timeout=15)
    if '--check' in sys.argv:
        print('Source session authorized and channel accessible. No alerts were delivered.', flush=True)
        await client.disconnect()
        return
    print(f'Source channel resolved: {getattr(entity, "title", source)} (id={getattr(entity, "id", "unknown")}).', flush=True)
    ocr = shutil.which('tesseract')
    psm_option = '--psm'
    if not ocr:
        print('Tesseract is unavailable. Telegram image alerts require it.', file=sys.stderr)
    else:
        probe = await asyncio.create_subprocess_exec(
            ocr, '--version', stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.STDOUT,
        )
        try:
            version_text, _ = await asyncio.wait_for(probe.communicate(), timeout=5)
        except asyncio.TimeoutError:
            if probe.returncode is None:
                probe.kill()
            await probe.wait()
            raise SystemExit('Tesseract compatibility check timed out.')
        version = re.search(rb'\btesseract\s+(\d+)\.', version_text.lower())
        if version is None:
            raise SystemExit(f'Tesseract version could not be identified (exit {probe.returncode}).')
        if int(version.group(1)) < 4:
            psm_option = '-psm'
        print(f'Telegram image OCR ready ({psm_option}).', flush=True)

    image_text_cache = {}

    @client.on(events.NewMessage(chats=entity, incoming=True))
    @client.on(events.MessageEdited(chats=entity, incoming=True))
    async def receive(event):
        print(f'Source post {event.id} received (photo={bool(event.photo)}).', flush=True)
        for attempt in range(3):
            try:
                await forward(event)
                return
            except Exception as error:
                print(f'Source post {event.id} processing failed ({type(error).__name__}, attempt {attempt + 1}/3).', file=sys.stderr, flush=True)
                if attempt < 2:
                    await asyncio.sleep(2)

    async def forward(event):
        reply = await asyncio.wait_for(event.get_reply_message(), timeout=15) if event.is_reply else None
        text = event.raw_text or ''
        if ocr and event.photo:
            try:
                photo_id = getattr(event.photo, 'id', None)
                extracted_text = image_text_cache.get(photo_id) if photo_id is not None else None
                if extracted_text is None:
                    photo = await asyncio.wait_for(event.download_media(file=bytes), timeout=10)
                    fragments = []
                    if photo:
                        for mode in ('6', '3'):
                            process = await asyncio.create_subprocess_exec(
                                ocr, 'stdin', 'stdout', psm_option, mode,
                                stdin=asyncio.subprocess.PIPE, stdout=asyncio.subprocess.PIPE,
                                stderr=asyncio.subprocess.DEVNULL,
                            )
                            try:
                                extracted, _ = await asyncio.wait_for(process.communicate(photo), timeout=10)
                            except asyncio.TimeoutError:
                                if process.returncode is None:
                                    process.kill()
                                await process.wait()
                                continue
                            if process.returncode == 0:
                                fragment = extracted.decode('utf-8', errors='replace').strip()[:3000]
                                if fragment and fragment not in fragments:
                                    fragments.append(fragment)
                            else:
                                print(f'Source post {event.id} image OCR failed (mode {mode}, exit {process.returncode}).', file=sys.stderr)
                    extracted_text = '\n'.join(fragments)
                    if extracted_text and photo_id is not None:
                        if len(image_text_cache) >= 64:
                            image_text_cache.pop(next(iter(image_text_cache)))
                        image_text_cache[photo_id] = extracted_text
                if extracted_text:
                    text = extracted_text + '\n' + text
                    print(f'Source post {event.id} image OCR completed ({len(extracted_text.encode())} bytes, modes 6/3).', flush=True)
            except (OSError, asyncio.TimeoutError):
                print('Could not read Telegram alert image.', file=sys.stderr)
        if not text.strip():
            print(f'Source post {event.id} skipped: no readable text.', flush=True)
            return
        payload = json.dumps({'source': source, 'message_id': event.id,
                              'sent_at': int((event.edit_date or event.date).timestamp()),
                              'text': text[:8192],
                              'reply_message_id': getattr(reply, 'id', None) if reply else None,
                              'reply_text': (reply.raw_text or '')[:8192] if reply else None}).encode()

        def submit():
            request = urllib.request.Request(os.environ['BF_SOURCE_URL'], data=payload,
                        headers={'Content-Type': 'application/json', 'Accept': 'application/json',
                                 'X-BF-Source-Secret': os.environ['TELEGRAM_SOURCE_SECRET']})
            with urllib.request.urlopen(request, timeout=15) as response:
                return response.status

        while client.is_connected():
            try:
                if await asyncio.to_thread(submit) == 202:
                    print(f'Source post {event.id} acknowledged by Laravel.', flush=True)
                    return
            except urllib.error.HTTPError as error:
                if error.code in (400, 401, 403, 404, 422):
                    print(f'Source post {event.id} rejected by Laravel (HTTP {error.code}).', file=sys.stderr)
                    return
                print(f'Source post {event.id} submission retry (HTTP {error.code}).', file=sys.stderr, flush=True)
            except (OSError, TimeoutError) as error:
                print(f'Source post {event.id} submission retry ({type(error).__name__}).', file=sys.stderr, flush=True)
            await asyncio.sleep(5)

    await client.catch_up()
    print('Passive source listener connected. No messages, reactions or forwards are sent.', flush=True)
    await client.run_until_disconnected()


try:
    asyncio.run(main())
except (KeyboardInterrupt, SystemExit):
    raise
except Exception as error:
    raise SystemExit(f'Source connection failed ({type(error).__name__}). Verify network access, credentials, session and source membership.')
finally:
    session_lock.close()
