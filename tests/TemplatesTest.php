<?php

namespace Glitchr\ProfilerPack\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Two promises the templates make, read in their source: the styles reach
 * nothing of the page being debugged, and the scripts survive
 * WebProfilerBundle writing them on one line.
 */
final class TemplatesTest extends TestCase
{
    private const TEMPLATES = __DIR__.'/../templates';

    #[DataProvider('toolbarStylesheets')]
    public function testEveryRuleIsUnderAClassOfTheToolbar(string $file): void
    {
        $css = (string) file_get_contents(self::TEMPLATES.'/'.$file);
        $css = preg_replace(['~\{#.*?#\}~s', '~/\*.*?\*/~s'], '', $css);
        $css = preg_replace('~@media[^{]*\{~', '', $css);

        preg_match_all('~([^{}]+)\{[^{}]*\}~', $css, $rules);
        self::assertNotEmpty($rules[1]);

        foreach ($rules[1] as $selectors) {
            foreach (explode(',', $selectors) as $selector) {
                $selector = trim($selector);
                self::assertMatchesRegularExpression(
                    '~^(div)?(\.sf-(toolbar|minitoolbar|cancel-button)|\[id\^="sfwdt"\])~',
                    $selector,
                    \sprintf('"%s" in %s would style the page being debugged.', $selector, $file),
                );
            }
        }
    }

    public static function toolbarStylesheets(): iterable
    {
        yield ['Toolbar/floating.css.twig'];
        yield ['Toolbar/fixes.css.twig'];
    }

    #[DataProvider('scripts')]
    public function testAScriptHasNoLineCommentAndEndsItsStatements(string $file): void
    {
        $js = (string) file_get_contents(self::TEMPLATES.'/'.$file);
        $js = preg_replace('~\{#.*?#\}~s', '', $js);

        self::assertStringNotContainsString('//', $js, 'On one line, a // comment swallows the rest of the script.');

        $lines = array_values(array_filter(array_map('rtrim', explode("\n", $js)), static fn (string $line) => '' !== trim($line)));

        foreach ($lines as $number => $line) {
            // The last statement of a block needs no semicolon: a closing brace follows it.
            if (preg_match('~^\s*(/\*.*\*/|\}|\}\)|\{\{.*\}\})$~', $line) || str_starts_with(ltrim($lines[$number + 1] ?? '}'), '}')) {
                continue;
            }
            self::assertMatchesRegularExpression('~[;{},(\[:]$|\*/$|\|\|$|&&$|\+$~', $line, \sprintf('Line %d of %s does not end its statement: "%s".', $number + 1, $file, trim($line)));
        }
    }

    public static function scripts(): iterable
    {
        yield ['Toolbar/floating.js.twig'];
        yield ['Toolbar/fixes.js.twig'];
    }

    /**
     * A finger sends no mouse event while it moves: the gestures are pointer
     * events, and the handle does not scroll the page under the finger.
     */
    public function testTheToolbarIsMovedByAFingerAsByTheMouse(): void
    {
        $js = (string) file_get_contents(self::TEMPLATES.'/Toolbar/floating.js.twig');
        foreach (['pointerdown', 'pointermove', 'pointerup', 'pointercancel', 'setPointerCapture'] as $used) {
            self::assertStringContainsString($used, $js);
        }
        foreach (['mousedown', 'mousemove', 'mouseup'] as $unused) {
            self::assertStringNotContainsString("'".$unused."'", $js);
        }

        $css = (string) file_get_contents(self::TEMPLATES.'/Toolbar/floating.css.twig');
        self::assertMatchesRegularExpression('~\.sf-toolbar-toggle-button\s*\{\s*touch-action:\s*none;~', $css);
        self::assertStringContainsString('@media (pointer: coarse)', $css);
    }

    /**
     * On a narrow window or under a finger the toolbar is one piece: the
     * handle is the pill's right end (no box of its own beside it, which a
     * phone's width pushed apart and out of the window), the pill spans the
     * window and keeps clear of the bottom a phone keeps for itself.
     */
    public function testOnAPhoneTheHandleIsTheEndOfThePill(): void
    {
        $css = (string) file_get_contents(self::TEMPLATES.'/Toolbar/floating.css.twig');
        $css = preg_replace(['~\{#.*?#\}~s', '~/\*.*?\*/~s'], '', $css);

        self::assertSame(1, preg_match('~@media \(max-width: 767px\), \(pointer: coarse\) \{(.*?)\n\}~s', $css, $narrow), 'The narrow and touch layout has its block.');
        $rules = self::rulesOf($narrow[1]);

        $handle = $rules['.sf-toolbar:not(.sf-toolbar-closed) .sf-toolbarreset .sf-toolbar-toggle-button'] ?? null;
        self::assertNotNull($handle, 'The open handle is laid out for a phone.');
        self::assertSame('absolute', $handle['position']);
        self::assertSame('0', $handle['right'], 'At the right end of the toolbar...');
        self::assertSame('0', $handle['margin'], '...not pushed beside it by a negative margin.');

        $pill = $rules['.sf-toolbar .sf-toolbarreset'];
        self::assertSame('0 calc(var(--profiler-pack-handle) + var(--profiler-pack-grip)) 0 auto', $pill['margin'], 'The pill ends where the handle starts: its blocks scroll under nothing.');
        self::assertSame('0.25em 0 0.25em 1em', $pill['padding']);
        self::assertStringStartsWith('calc(100% ', $pill['max-width'], 'A width kept from a wider window does not overflow.');
        self::assertSame('none', $pill['transition'], 'Opened or closed at once: the handle stays under the finger.');

        $bar = $rules['.sf-toolbar'];
        self::assertStringContainsString('env(safe-area-inset-bottom', $bar['margin']);
        self::assertStringContainsString('env(safe-area-inset-left', $bar['left']);
        self::assertStringContainsString('env(safe-area-inset-right', $bar['right']);

        $closed = $rules['.sf-toolbar.sf-toolbar-closed .sf-toolbarreset .sf-toolbar-toggle-button'];
        self::assertSame('relative', $closed['position'], 'Closed, the handle alone is the toolbar.');
        self::assertSame('0', $closed['margin']);
    }

    /**
     * Wherever the toolbar is put, kept or restored, its handle stays in the
     * window, inside what the browser shows of the page and above a bar the
     * page keeps along its bottom; the place asked for is kept apart from the
     * place shown, so the toolbar comes back down when that bar goes.
     */
    public function testTheToolbarIsKeptInsideTheWindow(): void
    {
        $js = (string) file_get_contents(self::TEMPLATES.'/Toolbar/floating.js.twig');

        foreach (['function bounds()', 'function floor()', 'function pageBottomBar()', 'function settle()', 'window.visualViewport', 'document.elementsFromPoint', "'transitionend'"] as $used) {
            self::assertStringContainsString($used, $js);
        }
        self::assertStringContainsString('var minX = MARGIN - box.handleLeft;', $js, 'Sent aside, the handle stays in view.');
        self::assertStringContainsString('var maxX = Math.max(minX, window.innerWidth - MARGIN - box.right);', $js, 'Its right end never leaves the window.');
        self::assertStringContainsString('var maxY = Math.max(minY, floor() - box.bottom);', $js);
        self::assertStringNotContainsString('Math.min(2 * PADDING', $js, 'The old bound let the handle out on the right.');
    }

    /** @return array<string, array<string, string>> each selector of a block of rules, with its declarations */
    private static function rulesOf(string $css): array
    {
        preg_match_all('~([^{}]+)\{([^{}]*)\}~', $css, $matches, \PREG_SET_ORDER);
        $rules = [];
        foreach ($matches as [, $selectors, $body]) {
            $declarations = [];
            foreach (array_filter(array_map('trim', explode(';', $body))) as $declaration) {
                [$property, $value] = array_map('trim', explode(':', $declaration, 2));
                $declarations[$property] = $value;
            }
            foreach (explode(',', $selectors) as $selector) {
                $rules[trim($selector)] = $declarations + ($rules[trim($selector)] ?? []);
            }
        }

        return $rules;
    }
}
