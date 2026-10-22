#!/usr/bin/env python3
"""
Adds loading/decoding hints to front-office theme images.

Blade-aware: `<img>` tags are parsed attribute-by-attribute and Blade expressions
(`{{ ... }}`, `@...`) are treated as opaque, so `->` inside them is never mistaken
for the end of a tag.

- Images already carrying `loading=` are left untouched.
- Above-the-fold images (hero/header/logo/brand context) get `loading="eager"`
  and `fetchpriority="high"`; everything else `loading="lazy"`.
- `decoding="async"` is added to all.

Usage: python3 bin/optimize-images.py [--check]
"""
from __future__ import annotations

import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
THEMES = ROOT / "themes"

EAGER_HINTS = ("hero", "header", "logo", "brand", "above", "og:image")
BLADE_EXPR = re.compile(r"\{\{.*?\}\}|\{!!.*?!!\}|@[a-zA-Z]+(?:\([^)]*\))?")


def split_attrs(tag: str) -> tuple[str, list[str]]:
    """Splits a tag body into (opening, list of attributes) with Blade kept intact."""
    body = tag[4:-2].strip() if tag.endswith("/>") else tag[4:-1].strip()
    attrs: list[str] = []
    buf = ""
    i = 0

    while i < len(body):
        blade = BLADE_EXPR.match(body, i)
        if blade:
            buf += blade.group(0)
            i = blade.end()
            continue

        ch = body[i]

        if ch == '"' or ch == "'":
            end = body.find(ch, i + 1)
            end = len(body) if end == -1 else end + 1
            buf += body[i:end]
            i = end
            continue

        if ch.isspace():
            if buf:
                attrs.append(buf)
                buf = ""
            i += 1
            continue

        buf += ch
        i += 1

    if buf:
        attrs.append(buf)

    opening = tag[:4]
    return opening, attrs


def is_eager(context: str) -> bool:
    lowered = context.lower()

    return any(hint in lowered for hint in EAGER_HINTS)


def patch_tag(tag: str, context: str) -> str:
    opening, attrs = split_attrs(tag)
    names = {a.split("=", 1)[0].strip(): True for a in attrs if "=" in a}

    additions: list[str] = []

    if "loading" not in names:
        eager = is_eager(context)
        additions.append('loading="eager"' if eager else 'loading="lazy"')
        if eager and "fetchpriority" not in names:
            additions.append('fetchpriority="high"')

    if "decoding" not in names:
        additions.append('decoding="async"')

    if not additions:
        return tag

    self_closing = tag.rstrip().endswith("/>")

    return opening + " " + " ".join(attrs + additions) + (" />" if self_closing else ">")


def find_img_tags(text: str):
    """Yields (start, end, tag) for each <img ...> at the top level of the document."""
    for match in re.finditer(r"<img\b", text):
        i = match.start()
        j = i + 4
        in_blade = 0

        while j < len(text):
            blade = BLADE_EXPR.match(text, j)
            if blade:
                j = blade.end()
                continue

            ch = text[j]

            if ch in "\"'":
                end = text.find(ch, j + 1)
                j = len(text) if end == -1 else end + 1
                continue

            if ch == ">":
                yield i, j + 1, text[i : j + 1]
                break

            j += 1


def process(path: pathlib.Path, check: bool = False) -> int:
    text = path.read_text()
    changed = 0
    out = ""
    cursor = 0

    for start, end, tag in find_img_tags(text):
        out += text[cursor:start]
        context = text[max(0, start - 600) : start]
        new_tag = patch_tag(tag, context)
        if new_tag != tag:
            changed += 1
        out += new_tag
        cursor = end

    out += text[cursor:]

    if changed and not check:
        path.write_text(out)

    return changed


def main() -> None:
    check = "--check" in sys.argv
    total = 0
    files = 0

    for blade in sorted(THEMES.glob("*/**/*.blade.php")):
        n = process(blade, check=check)
        if n:
            files += 1
            total += n
            if not check:
                print(f"  {n:3d} images  {blade.relative_to(ROOT)}")

    suffix = " (check-only)" if check else ""
    print(f"[images] {total} tags would be optimised across {files} files{suffix}")


if __name__ == "__main__":
    main()
