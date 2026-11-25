import importlib.util
import os
from pathlib import Path
from types import SimpleNamespace
import tempfile
import unittest
from unittest.mock import patch

SOURCE = Path(__file__).resolve().parents[2] / 'main/php-memory.py'
spec = importlib.util.spec_from_file_location('production_memory', SOURCE)
memory = importlib.util.module_from_spec(spec)
spec.loader.exec_module(memory)


class ProductionMemoryTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.project = Path(self.temporary.name)
        (self.project / 'public').mkdir()
        (self.project / 'public/index.php').write_text('<?php')
        self.ini = self.project / 'php.ini'
        self.ini.write_text('; memory_limit=64M\nmemory_limit=128M\nextension=redis\n')
        self.web = self.project / 'public/.user.ini'
        self.web.write_text('open_basedir=/production:/tmp\nmemory_limit=256M\n')

    def test_check_does_not_modify_or_create_files(self):
        result = memory.configure(self.project, self.ini)
        self.assertFalse(result['applied'])
        self.assertTrue(all(item['change_required'] for item in result['files']))
        self.assertIn('memory_limit=128M', self.ini.read_text())
        self.assertFalse((self.project / 'storage').exists())

    def test_apply_backs_up_preserves_other_settings_and_is_idempotent(self):
        owner = SimpleNamespace(pw_uid=os.getuid(), pw_gid=os.getgid())
        with patch.object(memory.pwd, 'getpwnam', return_value=owner), patch.object(memory.os, 'chown'):
            result = memory.configure(self.project, self.ini, apply=True)
            backup = Path(result['backup'])
            self.assertIn('memory_limit=128M', (backup / '0-php.ini').read_text())
            self.assertIn('memory_limit=256M', (backup / '1-.user.ini').read_text())
            self.assertIn('; memory_limit=64M\nmemory_limit=-1\nextension=redis', self.ini.read_text())
            self.assertEqual('open_basedir=/production:/tmp\nmemory_limit=-1\n', self.web.read_text())
            self.assertIsNone(memory.configure(self.project, self.ini, apply=True)['backup'])

    def test_missing_public_user_ini_is_created_without_touching_parent_settings(self):
        self.web.unlink()
        parent_ini = self.project / '.user.ini'
        parent_ini.write_text('open_basedir=/production:/tmp\n')
        owner = SimpleNamespace(pw_uid=os.getuid(), pw_gid=os.getgid())
        with patch.object(memory.pwd, 'getpwnam', return_value=owner), patch.object(memory.os, 'chown'):
            memory.configure(self.project, self.ini, apply=True)
        self.assertEqual('memory_limit=-1\n', self.web.read_text())
        self.assertEqual('open_basedir=/production:/tmp\n', parent_ini.read_text())

    def test_failed_web_update_restores_cli_configuration(self):
        original = self.ini.read_text()
        actual_write = memory.write_configuration
        def fail_web(path, contents, owner):
            if path == self.web:
                raise PermissionError('Protected configuration')
            actual_write(path, contents, owner)
        owner = SimpleNamespace(pw_uid=os.getuid(), pw_gid=os.getgid())
        with patch.object(memory.pwd, 'getpwnam', return_value=owner), patch.object(memory.os, 'chown'), \
                patch.object(memory, 'write_configuration', side_effect=fail_web):
            with self.assertRaises(PermissionError):
                memory.configure(self.project, self.ini, apply=True)
        self.assertEqual(original, self.ini.read_text())
        self.assertIn('memory_limit=256M', self.web.read_text())

    def test_symlinks_are_refused_without_modification(self):
        self.web.unlink()
        self.web.symlink_to(self.ini)
        with self.assertRaisesRegex(ValueError, 'symlink'):
            memory.configure(self.project, self.ini, apply=True)
        self.assertIn('memory_limit=128M', self.ini.read_text())


if __name__ == '__main__':
    unittest.main()
