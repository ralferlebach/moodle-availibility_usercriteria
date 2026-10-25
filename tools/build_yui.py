#!/usr/bin/env python3
"""Build the YUI module of this plugin without Moodle's Grunt toolchain.

The official way is `npx grunt yui` from the Moodle root with the plugin mirrored into the tree
(it runs Shifter). Use this script only where Shifter cannot be installed. It produces the same
three files with the same wrapper: plain, debug and minified. Minification needs `terser` on the
PATH (`npm i -g terser`); without it the minified file is the unminified module, which is valid,
only larger.

    python3 tools/build_yui.py
    python3 tools/build_yui.py --check   # fail if yui/build does not match yui/src (CI)
"""

import json
import os
import shutil
import subprocess
import sys

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..')
SRC = os.path.join(ROOT, 'yui', 'src', 'form')


def main():
    with open(os.path.join(SRC, 'build.json'), encoding='utf-8') as handle:
        name = json.load(handle)['name']
    with open(os.path.join(SRC, 'meta', 'form.json'), encoding='utf-8') as handle:
        requires = json.load(handle)[name]['requires']
    with open(os.path.join(SRC, 'js', 'form.js'), encoding='utf-8') as handle:
        body = handle.read().rstrip('\n')

    module = "YUI.add('%s', function (Y, NAME) {\n\n%s\n\n\n}, '@VERSION@', %s);\n" % (
        name, body, json.dumps({'requires': [r.strip() for r in requires]}).replace('", "', '", "'))
    out = os.path.join(ROOT, 'yui', 'build', name)
    if '--check' in sys.argv:
        # The plain and the debug file are the wrapped source and must match it exactly. The minified file is only
        # checked to exist for this module: terser output differs between versions.
        stale = []
        for suffix in ('', '-debug'):
            path = os.path.join(out, name + suffix + '.js')
            if not os.path.exists(path) or open(path, encoding='utf-8').read() != module:
                stale.append(os.path.basename(path))
        minified = os.path.join(out, name + '-min.js')
        if not os.path.exists(minified) or name not in open(minified, encoding='utf-8').read():
            stale.append(os.path.basename(minified))
        if stale:
            print('yui/build is out of date, run python3 tools/build_yui.py: ' + ', '.join(stale))
            sys.exit(1)
        print('yui/build matches yui/src.')
        return
    os.makedirs(out, exist_ok=True)
    for suffix in ('', '-debug', '-min'):
        with open(os.path.join(out, name + suffix + '.js'), 'w', encoding='utf-8') as handle:
            handle.write(module)

    terser = shutil.which('terser')
    if terser:
        minified = os.path.join(out, name + '-min.js')
        subprocess.run([terser, os.path.join(out, name + '.js'), '--compress', '--mangle',
                        '--output', minified], check=True)
        print('Built plain, debug and minified (terser).')
    else:
        print('Built plain, debug and unminified -min (terser not found).')


if __name__ == '__main__':
    main()
