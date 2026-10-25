<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace availability_usercriteria;

/**
 * The plugin reads the current time only from the clock API, so that tests can freeze and move it.
 *
 * This test reads the source of the plugin (PHP and editor JavaScript, tests included) and fails on any access
 * that asks the system for the current time, directly or through a dynamic call. Converting a given timestamp
 * (Date.UTC, Intl.DateTimeFormat, new Date(ms) in JavaScript) is allowed: it reads no current time.
 *
 * Each rule has a negative control: test_the_check_works() feeds samples that must be found and samples that
 * must not be found.
 *
 * @package    availability_usercriteria
 * @category   test
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class clock_usage_test extends \basic_testcase {
    /** @var string[] PHP functions that read or wait for the system time. */
    private const FORBIDDEN = ['time', 'microtime', 'hrtime', 'date', 'gmdate', 'idate', 'getdate', 'localtime', 'mktime',
        'gmmktime', 'strtotime', 'usleep', 'sleep', 'time_nanosleep', 'time_sleep_until', 'date_create',
        'date_create_immutable', 'gettimeofday', 'strftime', 'gmstrftime', 'date_default_timezone_get'];

    /** @var string[] PHP functions that call a function given by name. */
    private const CALLERS = ['call_user_func', 'call_user_func_array', 'forward_static_call', 'forward_static_call_array',
        'array_map', 'array_walk', 'array_filter', 'usort', 'uasort', 'uksort', 'register_shutdown_function',
        'fromcallable'];

    /**
     * The tokens of a PHP file without whitespace and comments.
     *
     * @param string $code PHP source.
     * @return array
     */
    private static function tokens(string $code): array {
        return array_values(array_filter(token_get_all($code), fn($token) =>
            !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
    }

    /**
     * Find forbidden time access in PHP source.
     *
     * @param string $code PHP source.
     * @param string $name Name for the findings.
     * @return string[] Descriptions of the findings.
     */
    public static function php_findings(string $code, string $name): array {
        $findings = [];
        $tokens = self::tokens($code);
        $forbiddenstrings = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }
            $next = $tokens[$index + 1] ?? null;
            $previous = $tokens[$index - 1] ?? null;
            $previoustype = is_array($previous) ? $previous[0] : $previous;
            $isname = in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true);
            $plain = strtolower(ltrim($token[1], '\\'));
            // A direct call, also as a first class callable: time(), \time(), time(...).
            if (
                $isname && $next === '(' && in_array($plain, self::FORBIDDEN, true)
                    && !in_array($previoustype, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION,
                        T_NEW], true)
            ) {
                $findings[] = "$name:{$token[2]} {$token[1]}()";
            }
            // A date object for "now" or a relative time: new DateTime(), new DateTime('now'), new DateTime('+1 day').
            if ($previoustype === T_NEW && $isname && in_array($plain, ['datetime', 'datetimeimmutable'], true) && $next === '(') {
                $argument = $tokens[$index + 2] ?? null;
                $literal = is_array($argument) && $argument[0] === T_CONSTANT_ENCAPSED_STRING ? trim($argument[1], "'\"") : null;
                $absolute = '/^(@-?\d+|\d{4}-\d{2}-\d{2}([ T][\d:]+)?)$/';
                if ($argument === ')' || ($literal !== null && !preg_match($absolute, $literal))) {
                    $findings[] = "$name:{$token[2]} new {$token[1]}() reads the current time";
                }
            }
            // A function name given as a string: to a function that calls it, or kept in a variable.
            if ($token[0] === T_CONSTANT_ENCAPSED_STRING && in_array(strtolower(trim($token[1], "'\"")), self::FORBIDDEN, true)) {
                $callerindex = $index - 2;
                $caller = $tokens[$callerindex] ?? null;
                if (in_array($previoustype, ['(', ','], true)) {
                    // Walk back to the function whose argument list this is.
                    $depth = 0;
                    for ($i = $index - 1; $i >= 0; $i--) {
                        if ($tokens[$i] === ')') {
                            $depth++;
                        } else if ($tokens[$i] === '(') {
                            if ($depth === 0) {
                                $caller = $tokens[$i - 1] ?? null;
                                break;
                            }
                            $depth--;
                        }
                    }
                    if (is_array($caller) && in_array(strtolower(ltrim($caller[1], '\\')), self::CALLERS, true)) {
                        $findings[] = "$name:{$token[2]} {$token[1]} called by name";
                    }
                }
                if ($previoustype === '=' && is_array($tokens[$index - 2] ?? null) && $tokens[$index - 2][0] === T_VARIABLE) {
                    $forbiddenstrings[$tokens[$index - 2][1]] = $token[2];
                }
            }
            // A variable holding a forbidden name, called: $f = 'time'; $f().
            if ($token[0] === T_VARIABLE && $next === '(' && isset($forbiddenstrings[$token[1]])) {
                $findings[] = "$name:{$token[2]} {$token[1]}() calls a time function by name";
            }
        }
        return $findings;
    }

    /**
     * Find access to the current time in JavaScript source.
     *
     * @param string $code JavaScript source.
     * @param string $name Name for the findings.
     * @return string[] Descriptions of the findings.
     */
    public static function js_findings(string $code, string $name): array {
        // Remove comments and string contents, keeping the line structure.
        $code = preg_replace_callback(
            '~/\*.*?\*/|//[^\n]*|\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"~s',
            fn($m) => preg_replace('/[^\n]/', ' ', $m[0]),
            $code
        );
        $patterns = [
            '/\bDate\s*\.\s*now\s*\(/' => 'Date.now()',
            '/\bnew\s+Date\s*\(\s*\)/' => 'new Date()',
            '/\bnew\s+Date\b(?!\s*\()/' => 'new Date',
            '/(?<![.\w])Date\s*\(\s*\)/' => 'Date()',
            '/\bperformance\s*\.\s*now\s*\(/' => 'performance.now()',
            '/\bY\s*\.\s*Lang\s*\.\s*now\s*\(/' => 'Y.Lang.now()',
        ];
        $findings = [];
        foreach ($patterns as $pattern => $label) {
            if (preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as [, $offset]) {
                    $findings[] = $name . ':' . (substr_count($code, "\n", 0, $offset) + 1) . ' ' . $label;
                }
            }
        }
        return $findings;
    }

    /**
     * Files of the plugin with a given extension.
     *
     * @param string[] $entries Directories and files below the plugin root.
     * @param string $extension Extension.
     * @return string[]
     */
    private static function files(array $entries, string $extension): array {
        $root = dirname(__DIR__);
        $list = [];
        foreach ($entries as $entry) {
            $path = $root . '/' . $entry;
            if (!file_exists($path)) {
                continue;
            }
            $files = is_dir($path) ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
                $path,
                \FilesystemIterator::SKIP_DOTS
            )) : [new \SplFileInfo($path)];
            foreach ($files as $file) {
                if ($file->getExtension() === $extension && !str_contains($file->getPathname(), '/node_modules/')) {
                    $list[] = $file->getPathname();
                }
            }
        }
        sort($list);
        return $list;
    }

    /**
     * No direct time access anywhere in the PHP of the plugin, its tests included.
     */
    public function test_no_direct_time_access(): void {
        $findings = [];
        $files = self::files(['classes', 'tests', 'db', 'lang', 'version.php', 'lib.php'], 'php');
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $findings = array_merge($findings, self::php_findings(file_get_contents($file), basename($file)));
        }
        $this->assertSame([], $findings, "Use \\core\\clock (\\core\\di::get(\\core\\clock::class)) and mock it in tests:\n"
            . implode("\n", $findings));
    }

    /**
     * The editor JavaScript (sources and built files) never reads the current time.
     */
    public function test_no_current_time_in_javascript(): void {
        $findings = [];
        $files = array_merge(self::files(['yui/src', 'amd/src'], 'js'), self::files(['yui/build'], 'js'));
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            if (str_ends_with($file, '-min.js')) {
                continue; // Minified copy of the checked debug build.
            }
            $findings = array_merge($findings, self::js_findings(file_get_contents($file), basename($file)));
        }
        $this->assertSame([], $findings, "The editor converts given timestamps only:\n" . implode("\n", $findings));
    }

    /**
     * Negative control: the PHP check finds what it must find and nothing else.
     */
    public function test_the_check_works(): void {
        $mustfind = [
            '$a = time();', '$b = \strtotime("x");', '$c = new \DateTime();', '$d = new \DateTimeImmutable("now");',
            '$e = new DateTime("+1 day");', '$f = time(...);', '$g = call_user_func("time");',
            '$h = array_map(\'microtime\', [1]);', '$i = \Closure::fromCallable("hrtime");', '$j = "time"; $j();',
            '$k = DATE("Y");', '$l = gettimeofday();',
        ];
        foreach ($mustfind as $sample) {
            $this->assertNotEmpty(self::php_findings("<?php\n$sample\n", 'sample'), "Not found: $sample");
        }
        $mustnotfind = [
            '$a = $clock->time();', '$b = Foo::time();', '$c = new \DateTime("@5");', '$d = new DateTime("2026-03-29 02:30");',
            'function time() {}', '// time()', '$e = "time()";', '$f = ["type" => "date"];', '$g = userdate(5);',
            '$h = \core\di::get(\core\clock::class)->now();',
        ];
        foreach ($mustnotfind as $sample) {
            $this->assertSame([], self::php_findings("<?php\n$sample\n", 'sample'), "Wrongly found: $sample");
        }
    }

    /**
     * Negative control: the JavaScript check finds what it must find and nothing else.
     */
    public function test_the_javascript_check_works(): void {
        foreach (
            ['var a = Date.now();', 'var b = new Date();', 'var c = new Date;', 'var d = Date();',
                'var e = performance.now();', 'var f = Y.Lang.now();'] as $sample
        ) {
            $this->assertNotEmpty(self::js_findings($sample, 'sample'), "Not found: $sample");
        }
        foreach (
            ['var a = new Date(ms);', 'var b = Date.UTC(2026, 2, 29);', '// Date.now()', 'var c = "new Date()";',
                'var d = new Intl.DateTimeFormat("en-US");', 'var e = this.config.now;'] as $sample
        ) {
            $this->assertSame([], self::js_findings($sample, 'sample'), "Wrongly found: $sample");
        }
    }
}
