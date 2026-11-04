"""Keep the existing Laravel source-listener entry point working."""
import runpy
from pathlib import Path

if __name__ == '__main__':
    runpy.run_path(str(Path(__file__).resolve().parent / 'local/telegram-listener.py'), run_name='__main__')
