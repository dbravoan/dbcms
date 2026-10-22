"""Exercise asset helpers on disposable files; no network or project rewriting."""
import importlib.util
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location('optimize_images', ROOT / 'bin/optimize-images.py')
IMAGES = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(IMAGES)


class ImageHintsTest(unittest.TestCase):
    def test_normal_and_self_closing_tags_preserve_attributes_and_are_idempotent(self):
        for tag in ('<img src="photo.jpg">', '<img src="photo.jpg" />'):
            with self.subTest(tag=tag):
                result = IMAGES.patch_tag(tag, '')
                self.assertIn('src="photo.jpg" loading="lazy" decoding="async"', result)
                self.assertEqual(result.endswith('/>'), tag.endswith('/>'))
                self.assertNotIn('<img g ', result)
                self.assertEqual(IMAGES.patch_tag(result, ''), result)

    def test_existing_priority_is_not_duplicated(self):
        result = IMAGES.patch_tag('<img src="logo.svg" fetchpriority="low" />', 'hero')
        self.assertEqual(result.count('fetchpriority='), 1)
        self.assertIn('loading="eager"', result)
        self.assertIn('fetchpriority="low"', result)

    def test_blade_expressions_do_not_end_a_tag(self):
        text = '<img src="{{ $image->url }}" alt="{{ $page->title }}" />'
        self.assertEqual(list(IMAGES.find_img_tags(text)), [(0, len(text), text)])
        result = IMAGES.patch_tag(text, '')
        self.assertIn('src="{{ $image->url }}"', result)
        self.assertIn('alt="{{ $page->title }}"', result)
        self.assertEqual(IMAGES.patch_tag(result, ''), result)


class FontAssetsTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix='dbcms-fonts-')
        self.root = Path(self.temporary.name)
        for directory in ('bin', 'mock', 'public/fonts', 'resources/css/generated'):
            (self.root / directory).mkdir(parents=True)
        shutil.copy2(ROOT / 'bin/self-host-fonts.sh', self.root / 'bin/self-host-fonts.sh')
        self.stylesheet = self.root / 'resources/css/generated/fonts.css'
        self.stylesheet.write_text('existing stylesheet')
        self.font = self.root / 'public/fonts/plus-jakarta-sans-400-latin.woff2'
        self.font.write_bytes(b'wOF2original')
        curl = self.root / 'mock/curl'
        curl.write_text('''#!/usr/bin/env python3
import os, pathlib, sys
if '-o' in sys.argv:
    if os.environ.get('FAIL_FONT') == '1': sys.exit(22)
    pathlib.Path(sys.argv[sys.argv.index('-o')+1]).write_bytes(b'wOF2' + sys.argv[-1].encode())
else:
    print(pathlib.Path(os.environ['FONT_CSS']).read_text())
''')
        curl.chmod(0o755)
        self.css = self.root / 'response.css'
        self.css.write_text(''.join(
            f'''/* {subset} */
@font-face {{ font-style: normal; font-weight: 400; src: url(https://fonts.gstatic.com/{subset}.woff2) format('woff2'); unicode-range: {ranges}; }}
'''
            for subset, ranges in (('vietnamese', 'U+0102-0103'), ('latin-ext', 'U+0100-02BA'), ('latin', 'U+0000-00FF'))
        ))

    def tearDown(self):
        self.temporary.cleanup()

    def run_script(self, **extra):
        return subprocess.run(['bash', str(self.root / 'bin/self-host-fonts.sh')], text=True,
                              capture_output=True, env={**os.environ, 'PATH': f'{self.root}/mock:{os.environ["PATH"]}',
                                                       'FONT_CSS': str(self.css), **extra})

    def test_subsets_keep_their_own_ranges_and_downloaded_bytes(self):
        process = self.run_script()
        self.assertEqual(process.returncode, 0, process.stderr)
        css = self.stylesheet.read_text()
        self.assertEqual(css.count('@font-face'), 4)
        self.assertNotIn('vietnamese', css)
        self.assertIn('U+0000-00FF', css)
        self.assertIn('U+0100-02BA', css)
        self.assertEqual(self.font.read_bytes(), b'wOF2https://fonts.gstatic.com/latin.woff2')
        self.assertIn('plus-jakarta-sans-400-latin.woff2', css)
        self.assertIn('google-sans-code-400-latin.woff2', css)

    def test_failed_download_preserves_existing_stylesheet_and_fonts(self):
        process = self.run_script(FAIL_FONT='1')
        self.assertNotEqual(process.returncode, 0)
        self.assertEqual(self.stylesheet.read_text(), 'existing stylesheet')
        self.assertEqual(self.font.read_bytes(), b'wOF2original')
        self.assertEqual(sorted(p.name for p in (self.root / 'public/fonts').iterdir()), [self.font.name])

    def test_missing_latin_subset_preserves_existing_assets(self):
        self.css.write_text(self.css.read_text().replace('/* latin */', '/* unknown */'))
        process = self.run_script()
        self.assertNotEqual(process.returncode, 0)
        self.assertIn('Latin subset is missing', process.stderr)
        self.assertEqual(self.stylesheet.read_text(), 'existing stylesheet')
        self.assertEqual(self.font.read_bytes(), b'wOF2original')


if __name__ == '__main__':
    unittest.main()
