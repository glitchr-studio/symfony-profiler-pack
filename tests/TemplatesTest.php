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
                // The one rule on the page: the room under the docked toolbar, only while floating.js marks <html>.
                if ('html[data-profiler-pack-room]' === $selector) {
                    continue;
                }
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
            self::assertDoesNotMatchRegularExpression('~addEventListener\\(\''.$unused.'\'~', $js);
        }

        $css = (string) file_get_contents(self::TEMPLATES.'/Toolbar/floating.css.twig');
        self::assertMatchesRegularExpression('~\.sf-toolbar-toggle-button\s*\{\s*touch-action:\s*none;~', $css);
        self::assertStringContainsString('@media (pointer: coarse)', $css);
    }

    /**
     * What is done on the toolbar is not done on the page: its pointer, mouse,
     * touch and key events stop as they leave it (a splash a press skips, a
     * click elsewhere that closes a panel), the pack's own gestures listening
     * on the window as they come down; a wheel still scrolls the page.
     */
    public function testTheToolbarsEventsDoNotReachThePage(): void
    {
        $js = (string) file_get_contents(self::TEMPLATES.'/Toolbar/floating.js.twig');

        self::assertSame(1, preg_match("~var SHIELDED = \\[(.*?)\\];~", $js, $shielded));
        foreach (['pointerdown', 'pointerup', 'mousedown', 'click', 'dblclick', 'touchstart', 'keydown'] as $type) {
            self::assertStringContainsString("'".$type."'", $shielded[1]);
        }
        self::assertStringNotContainsString("'wheel'", $shielded[1]);
        self::assertStringContainsString('shield(bar);', $js);
        self::assertStringContainsString('event.stopPropagation();', $js);

        foreach (['pointerdown', 'pointermove', 'pointerup', 'pointercancel', 'dblclick'] as $type) {
            self::assertDoesNotMatchRegularExpression("~document\\.addEventListener\\('".$type."'~", $js);
            self::assertMatchesRegularExpression("~window\\.addEventListener\\('".$type."'~", $js);
        }
    }

    /**
     * Where a hand put it, the toolbar stays: the page's layout (its bottom
     * bar, its panels, its header, its footer's controls) moves only the
     * places the pack chooses, its corner and aside. The mark is kept with
     * the place; a double click gives the pack its say again.
     */
    public function testAPlaceChosenByHandIsKeptAsItWasPut(): void
    {
        $js = (string) file_get_contents(self::TEMPLATES.'/Toolbar/floating.js.twig');

        self::assertStringContainsString('var page = !moving && !hand;', $js);
        self::assertStringContainsString('var shown = page ? clampPosition(nx, ny, true, true) : clamped;', $js);
        self::assertStringContainsString("return (page ? Math.min(bottom, window.innerHeight - pageBottomBar()) : bottom) - MARGIN;", $js);
        self::assertMatchesRegularExpression('~hand = true;\s*var placed = place\(drag\.fromX~', $js);
        self::assertStringContainsString('write(POSITION_KEY, JSON.stringify({ x: x, y: y, hand: true }));', $js);
        self::assertMatchesRegularExpression('~function foldAside\(toggle\) \{.*?hand = false;.*?write\(POSITION_KEY, JSON\.stringify\(\{ x: x, y: y, hand: false \}\)\);~s', $js);
        self::assertStringContainsString("hand = DRAGGABLE && !!position && ('hand' in position ? true === position.hand : 0 !== y);", $js);
    }

    /**
     * Resized, the toolbar stays where it is under the pointer, as it does
     * while it is moved: it is kept clear of the page's panels once let go.
     */
    public function testTheToolbarStaysPutWhileItIsResized(): void
    {
        $js = (string) file_get_contents(self::TEMPLATES.'/Toolbar/floating.js.twig');

        self::assertStringContainsString('if (!bar || drag || resize) {', $js);
        self::assertMatchesRegularExpression('~write\(WIDTH_KEY, String\(Math\.round\(width\(\)\)\)\);\s*resize = null;\s*(/\*.*?\*/\s*)?settleNow\(\);~s', $js);

        $css = (string) file_get_contents(self::TEMPLATES.'/Toolbar/floating.css.twig');
        self::assertMatchesRegularExpression('~\.sf-toolbarreset\.profiler-pack-edge\s*\{\s*cursor:\s*ew-resize;~', $css);
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

        foreach (['function bounds()', 'function floor(page)', 'function pageBottomBar()', 'function settle()', 'window.visualViewport', 'document.elementsFromPoint', "'transitionend'"] as $used) {
            self::assertStringContainsString($used, $js);
        }
        self::assertStringContainsString('var minX = MARGIN - box.handleLeft;', $js, 'Sent aside, the handle stays in view.');
        self::assertStringContainsString('var maxX = Math.max(minX, window.innerWidth - MARGIN - box.right);', $js, 'Its right end never leaves the window.');
        self::assertStringContainsString('var maxY = Math.max(minY, floor(page) - box.bottom);', $js);
        self::assertStringNotContainsString('Math.min(2 * PADDING', $js, 'The old bound let the handle out on the right.');
    }

    /**
     * Under a finger the handle is a fingertip's target: at least 44px each way
     * (Apple, WCAG; Material asks 48), open as closed.
     */
    public function testUnderAFingerTheHandleIsAFingertipsTarget(): void
    {
        $css = (string) file_get_contents(self::TEMPLATES.'/Toolbar/floating.css.twig');
        $css = preg_replace(['~\{#.*?#\}~s', '~/\*.*?\*/~s'], '', $css);

        self::assertSame(2, preg_match_all('~@media \(pointer: coarse\) \{(.*?)\n\}~s', $css, $blocks));
        $coarse = self::rulesOf($blocks[1][1]);

        self::assertGreaterThanOrEqual(44, (int) $coarse['.sf-toolbar']['--profiler-pack-handle'], 'Wide enough.');
        self::assertSame('calc(48px - 0.5em)', $coarse['.sf-toolbar:not(.sf-toolbar-closed) .sf-toolbarreset']['min-height'], 'High enough: the handle spans the pill, 48px with its padding.');

        self::assertSame(1, preg_match('~@media \(max-width: 767px\), \(pointer: coarse\) \{(.*?)\n\}~s', $css, $narrow));
        $rules = self::rulesOf($narrow[1]);
        self::assertSame('0.25em 0 0.25em 1em', $rules['.sf-toolbar .sf-toolbarreset']['padding'], 'The 0.5em taken off the 48px.');
        self::assertSame('content-box', $rules['.sf-toolbar .sf-toolbarreset']['box-sizing']);
        self::assertSame('0', $rules['.sf-toolbar:not(.sf-toolbar-closed) .sf-toolbarreset .sf-toolbar-toggle-button']['top']);
        self::assertSame('0', $rules['.sf-toolbar:not(.sf-toolbar-closed) .sf-toolbarreset .sf-toolbar-toggle-button']['bottom']);

        self::assertSame(1, preg_match('~@media \(pointer: coarse\) \{(.*?)\n\}~s', $css, $first));
        $closed = self::rulesOf($first[1])['.sf-toolbar.sf-toolbar-closed .sf-toolbarreset .sf-toolbar-toggle-button'];
        self::assertGreaterThanOrEqual(44, (int) $closed['height']);
        self::assertGreaterThanOrEqual(44, (int) $closed['width']);
    }

    /**
     * A double tap (or click) on the handle sends the toolbar aside, to the
     * left edge with its handle in view, from wherever it is; once it is
     * there, the next one brings it back to its corner.
     */
    public function testADoubleTapSendsTheToolbarAsideThenBack(): void
    {
        $js = (string) file_get_contents(self::TEMPLATES.'/Toolbar/floating.js.twig');

        self::assertStringContainsString('var left = box ? MARGIN - box.handleLeft : -(window.innerWidth - 5 * PADDING);', $js, 'Aside: the handle at the left edge.');
        self::assertStringContainsString('var aside = applied.x <= left + ASIDE;', $js, 'Back only from there, not from half way.');
        self::assertStringContainsString('var placed = place(aside ? 0 : left, 0);', $js);
        self::assertStringNotContainsString('left / 2', $js, 'A toolbar dragged half way left was taken for aside and sent back.');
        self::assertSame(2, substr_count($js, 'foldAside(event.target.closest(\'.sf-toolbar-toggle-button\'));'), 'From a double tap and from a double click.');

        /* The click a phone sends after the second tap lands where the handle was, on the page under it by then. */
        self::assertSame(1, preg_match("~window\\.addEventListener\\('click', function \\(event\\) \\{\\s*if \\(clickAfterFold && event\\.isTrusted && Date\\.now\\(\\) - clickAfterFold < \\d+\\) \\{\\s*clickAfterFold = 0;\\s*event\\.stopImmediatePropagation\\(\\);\\s*event\\.preventDefault\\(\\);~", $js), 'It reaches nothing, caught on the window before any listener of the page.');
        self::assertStringContainsString("tappedFold = now;\n                    clickAfterFold = now;", $js);
    }

    /**
     * The toolbar covers no control of a panel the page shows over itself (a
     * cookie question floating at the bottom, a dialog): a layer fixed in the
     * window found under the toolbar's column, or an open dialog wherever it
     * is, holding a control. Shown at the nearest height clear of it, the
     * place asked for kept, it goes back once the panel is closed; a move is
     * kept clear once let go, not while it is made.
     */
    public function testTheToolbarKeepsClearOfThePagesPanels(): void
    {
        $js = (string) file_get_contents(self::TEMPLATES.'/Toolbar/floating.js.twig');

        foreach (['function panels(left, right)', 'function nearestClear(', 'function clearOfPanels(', 'document.querySelectorAll(PANELS)', "'fixed' === window.getComputedStyle(el).position"] as $used) {
            self::assertStringContainsString($used, $js);
        }
        self::assertSame(1, preg_match("~var PANELS = '([^']+)';~", $js, $panels));
        foreach (['dialog[open]', '[role="dialog"]', '[role="alertdialog"]', '[aria-modal="true"]', '[data-consent-banner]', '[data-cookie-banner]', '[data-toolbar-avoid]'] as $selector) {
            self::assertStringContainsString($selector, $panels[1]);
        }
        self::assertStringContainsString('[tabindex]:not([tabindex="-1"])', $js, 'A panel focusable as a whole is not a control.');
        self::assertStringContainsString('(rect.width >= width * PAGE && rect.height >= height * PAGE)', $js, 'A page laid out in a fixed layer is not a panel.');
        self::assertStringContainsString('if (list[i].bottom - list[i].top < window.innerHeight * TALL) {', $js, 'A side rail is kept clear of by its controls, not sent over the top.');

        /* Shown clear of the panels, kept where it was asked. */
        self::assertStringContainsString("var shown = page ? clampPosition(nx, ny, true, true) : clamped;\n        bar.classList.toggle('sf-toolbar-veiled', veiled);\n        bar.style.transform = 'translate(' + shown.x + 'px, ' + shown.y + 'px)';\n        applied = shown;", $js);
        // A floating panel cleared only by a long climb (a cookie question as wide as a phone): out of sight, not over the page.
        self::assertSame(1, preg_match('~var CLIMB = (\d+);~', $js, $climb));
        self::assertLessThanOrEqual(200, (int) $climb[1]);
        self::assertStringContainsString("if (ny - y > CLIMB) {\n            veiled = true;\n            return ny;", $js);
        // A dialog over the whole page (lesringostars' intro) is no page laid out: its controls are kept clear of (the toolbar lay on its enter button).
        self::assertMatchesRegularExpression('~if \(el\.matches && el\.matches\(PANELS\)\) \{\s+var own = el\.querySelectorAll\(CONTROLS\);~', $js);
        self::assertStringContainsString('.sf-toolbar.sf-toolbar-veiled { opacity: 0 !important; pointer-events: none !important;', $css ?? file_get_contents(dirname(__DIR__).'/templates/Toolbar/floating.css.twig'));
        self::assertStringContainsString('place(drag.fromX + dx, drag.fromY + dy, true);', $js, 'Not while it is being moved.');

        /* A panel shown or hidden, sliding in, a cookie question answered. */
        self::assertStringContainsString("attributes: true, attributeFilter: ['hidden', 'class', 'open', 'aria-hidden', 'aria-expanded']", $js);
        self::assertStringContainsString("document.addEventListener('animationend', function () { settle(); });", $js);
        self::assertStringContainsString("window.addEventListener('consent:change', function () { settle(); });", $js);
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

    /**
     * The page's own content never moves the toolbar: no scroll is listened
     * to, the end of the page and the controls of the page itself (a footer's
     * links, a "back to top" fixed in the window) are not looked at. Climbing
     * over the footer at the end of the page read as a toolbar jumping for no
     * reason (glitchr's forge pages, 76px up as the page reached its end).
     */
    public function testThePagesOwnContentNeverMovesTheToolbar(): void
    {
        $js = (string) file_get_contents(self::TEMPLATES.'/Toolbar/floating.js.twig');

        self::assertStringNotContainsString("addEventListener('scroll'", $js);
        foreach (['atPageEnd', 'fewestCovered', 'drawnRect', 'function pinned(', 'function hidden(', 'CONSENT'] as $gone) {
            self::assertStringNotContainsString($gone, $js);
        }
    }

    /**
     * Instead, open and docked along the bottom of the window, the toolbar
     * gives the page room under it: its height and gap written on <html>,
     * added below the document's end, so the footer's last line scrolls up
     * above it. Measured at the place asked for, not where a panel pushed it.
     */
    public function testTheToolbarGivesThePageRoomUnderIt(): void
    {
        $js = (string) file_get_contents(self::TEMPLATES.'/Toolbar/floating.js.twig');

        self::assertMatchesRegularExpression('~applied = shown;\s*if \(!moving\) \{\s*giveRoom\(clamped\);~', $js);
        self::assertStringContainsString("if (box && isOpen()) {", $js, 'Closed, the handle alone asks no room.');
        self::assertStringContainsString("want = gap <= box.bottom - box.top ? Math.ceil(window.innerHeight - top) : 0;", $js, 'Docked along the bottom only.');
        self::assertStringContainsString("root.style.setProperty('--profiler-pack-room', want + 'px');", $js);
        self::assertStringContainsString("root.setAttribute('data-profiler-pack-room', '');", $js);
        self::assertStringContainsString("root.removeAttribute('data-profiler-pack-room');", $js);

        $css = (string) file_get_contents(self::TEMPLATES.'/Toolbar/floating.css.twig');
        $css = preg_replace(['~\{#.*?#\}~s', '~/\*.*?\*/~s'], '', $css);
        $rules = self::rulesOf($css);
        self::assertSame('var(--profiler-pack-room) !important', $rules['html[data-profiler-pack-room]']['padding-bottom']);
        self::assertSame('var(--profiler-pack-room)', $rules['html[data-profiler-pack-room]']['scroll-padding-bottom']);
    }

    /**
     * Hovering the toolbar never moves it: a change of the page decided while
     * the pointer is over it waits until it leaves. What the visitor does to
     * the toolbar or the window (open, close, resize) is done at once.
     */
    public function testHoveringTheToolbarNeverMovesIt(): void
    {
        $js = (string) file_get_contents(self::TEMPLATES.'/Toolbar/floating.js.twig');

        self::assertMatchesRegularExpression('~if \(hovered && !force\) \{\s*waiting = true;\s*return;~', $js);
        self::assertMatchesRegularExpression("~addEventListener\('pointerleave', function \(\) \{\s*hovered = false;\s*if \(waiting\) \{\s*settle\(\);~", $js);
        self::assertStringContainsString('hover(bar);', $js);
        self::assertStringContainsString("window.addEventListener('resize', settleNow);", $js);
        self::assertMatchesRegularExpression('~if \(onHandle\(event\)\) \{\s*settleNow\(\);~', $js, 'Opened or closed by the visitor.');
        self::assertStringContainsString('new ResizeObserver(function () { settle(); })', $js);
    }
}
