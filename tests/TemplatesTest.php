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
}
