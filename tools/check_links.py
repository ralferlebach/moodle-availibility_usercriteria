#!/usr/bin/env python3
"""Check that every relative link in the Markdown files of a plugin tree resolves inside that tree.

    python3 tools/check_links.py <plugin directory>

Used on the extracted release archive, so a README never points to a file the package does not contain.
Links to web pages (http, https, mailto) and anchors within a page are not checked.
"""

import os
import re
import sys

LINK = re.compile(r'\]\(([^)\s]+)(?:\s+"[^"]*")?\)')


def main(root):
    broken = []
    checked = 0
    for folder, _dirs, files in os.walk(root):
        for filename in files:
            if not filename.endswith('.md'):
                continue
            path = os.path.join(folder, filename)
            with open(path, encoding='utf-8') as handle:
                text = handle.read()
            for target in LINK.findall(text):
                if re.match(r'^(https?:|mailto:|#)', target):
                    continue
                checked += 1
                resolved = os.path.normpath(os.path.join(folder, target.split('#')[0]))
                if not os.path.exists(resolved):
                    broken.append('%s -> %s' % (os.path.relpath(path, root), target))
    if broken:
        print('Broken links:\n  ' + '\n  '.join(broken))
        return 1
    print('%d relative links checked, all resolve.' % checked)
    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1] if len(sys.argv) > 1 else '.'))
