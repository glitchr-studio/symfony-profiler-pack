<?php

namespace Glitchr\ProfilerPack\Tests;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

final class ToolbarTest extends PackTestCase
{
    private const ALL_OFF = ['floating' => false, 'fixes' => false, 'profiler_pages' => false, 'locale_panel' => false];

    public function testThePageCarriesTheOriginalToolbarThenTheFloatingOne(): void
    {
        $client = self::client();
        $client->request('GET', '/en/page');
        self::assertResponseIsSuccessful();

        $toolbar = self::toolbarOf($client);

        self::assertStringContainsString('Sfjs', $toolbar, 'The original script is there, whole.');
        self::assertStringContainsString('__profilerPackFloating', $toolbar);
        self::assertStringContainsString('profiler-pack/toolbar/position', $toolbar);
        self::assertStringContainsString('__profilerPackGuard', $toolbar);
        self::assertLessThan(strpos($toolbar, '__profilerPackFloating'), strpos($toolbar, 'Sfjs'), 'What the pack adds comes after the original.');
    }

    public function testTheStylesAreTheOriginalOnesThenThePacks(): void
    {
        $client = self::client();
        $client->request('GET', '/en/page');

        $styles = self::stylesOf($client);

        self::assertStringContainsString('--sf-toolbar-gray-800', $styles, 'The original rules are there.');
        self::assertStringContainsString('.sf-toolbarreset.inverted', $styles);
        self::assertStringContainsString('[id^="sfwdt"]:not(:has(.sf-toolbarreset))', $styles);
        self::assertLessThan(strpos($styles, '.sf-toolbarreset.inverted'), strpos($styles, '--sf-toolbar-gray-800'));
    }

    public function testWithTheFixesTheToolbarsStylesheetHoldsNoScript(): void
    {
        $client = self::client();
        $client->request('GET', '/en/page');

        $toolbar = self::toolbarOf($client);

        self::assertStringNotContainsString('<link rel="stylesheet"', $toolbar, 'No stylesheet in the body to hold the parser.');
        self::assertStringContainsString("link.rel = 'stylesheet'", $toolbar, 'Added by a script instead...');
        self::assertStringContainsString('_wdt\\/styles', $toolbar, '... the same address...');
        self::assertStringContainsString('DOMContentLoaded', $toolbar, '... once the document is read.');
        self::assertStringContainsString('html:not(.sf-wdt-styled) [id^="sfwdt"]', $toolbar, 'The toolbar hidden until its styles are there.');
    }

    public function testWithEverySwitchOffTheToolbarIsTheOneSymfonyShips(): void
    {
        $stock = self::client(null);
        $stock->request('GET', '/en/page');
        $stockToolbar = self::toolbarOf($stock);
        $stockStyles = self::stylesOf($stock);

        $off = self::client(self::ALL_OFF);
        $off->request('GET', '/en/page');

        self::assertSame($stockToolbar, self::toolbarOf($off));
        self::assertSame($stockStyles, self::stylesOf($off));
    }

    public function testNotFloatingThereIsNoGestureAndNoRestyle(): void
    {
        $client = self::client(['floating' => false]);
        $client->request('GET', '/en/page');

        self::assertStringNotContainsString('__profilerPackFloating', self::toolbarOf($client));
        self::assertStringContainsString('__profilerPackGuard', self::toolbarOf($client), 'The fixes do not depend on it.');
        self::assertStringNotContainsString('.sf-toolbarreset.inverted', self::stylesOf($client));
    }

    public function testEachGestureHasItsSwitch(): void
    {
        $client = self::client(['draggable' => false, 'minimizable' => false]);
        $client->request('GET', '/en/page');
        $toolbar = self::toolbarOf($client);

        self::assertStringContainsString('var DRAGGABLE = false;', $toolbar);
        self::assertStringContainsString('var RESIZABLE = true;', $toolbar);
        self::assertStringContainsString('var MINIMIZABLE = false;', $toolbar);

        $client = self::client(['draggable' => false, 'resizable' => false, 'minimizable' => false]);
        $client->request('GET', '/en/page');

        self::assertStringNotContainsString('__profilerPackFloating', self::toolbarOf($client), 'No gesture left: no script.');
        self::assertStringContainsString('.sf-toolbarreset.inverted', self::stylesOf($client), 'The toolbar still floats.');
    }

    public function testWithoutTheFixesNeitherGuardIsWritten(): void
    {
        $client = self::client(['fixes' => false]);
        $client->request('GET', '/en/page');

        self::assertStringNotContainsString('__profilerPackGuard', self::toolbarOf($client));
        self::assertStringNotContainsString(':not(:has(.sf-toolbarreset))', self::toolbarOf($client).self::stylesOf($client));
    }

    public function testTheToolbarHidesWhileThePageSaysSo(): void
    {
        $client = self::client(['hide_when' => 'html.is-loading']);
        $client->request('GET', '/en/page');

        self::assertStringContainsString('html.is-loading .sf-toolbar { display: none !important; }', self::toolbarOf($client));
    }

    public function testHideWhenIsOneSelector(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"hide_when" is one CSS selector');

        self::client(['hide_when' => 'html { display: none } body']);
    }

    public function testAjaxReplaceIsLeftAsTheApplicationSetIt(): void
    {
        $client = self::client();
        $client->xmlHttpRequest('GET', '/ajax');
        self::assertFalse($client->getResponse()->headers->has('Symfony-Debug-Toolbar-Replace'));

        $client = self::client([], ['toolbar' => ['ajax_replace' => true]]);
        $client->xmlHttpRequest('GET', '/ajax');
        self::assertSame('1', $client->getResponse()->headers->get('Symfony-Debug-Toolbar-Replace'));
    }

    public function testAjaxReplaceIsSetWhenAsked(): void
    {
        $client = self::client(['ajax_replace' => true]);
        $client->xmlHttpRequest('GET', '/ajax');
        self::assertSame('1', $client->getResponse()->headers->get('Symfony-Debug-Toolbar-Replace'));

        $client = self::client(['ajax_replace' => false], ['toolbar' => ['ajax_replace' => true]]);
        $client->xmlHttpRequest('GET', '/ajax');
        self::assertFalse($client->getResponse()->headers->has('Symfony-Debug-Toolbar-Replace'));
    }

    public function testTheProfilersPagesAreWiderAndCanBeLeftAsTheyAre(): void
    {
        $client = self::client();
        $client->request('GET', '/en/page');
        $token = $client->getResponse()->headers->get('X-Debug-Token');
        $client->request('GET', '/_profiler/'.$token.'?panel=request');
        self::assertResponseIsSuccessful();
        $page = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('--font-family-system', $page, 'The original styles are there.');
        self::assertStringContainsString('profiler-pack: the profiler\'s pages take the width of the window', $page);

        $client = self::client(['profiler_pages' => false]);
        $client->request('GET', '/en/page');
        $token = $client->getResponse()->headers->get('X-Debug-Token');
        $client->request('GET', '/_profiler/'.$token.'?panel=request');
        self::assertResponseIsSuccessful();

        self::assertStringNotContainsString('profiler-pack: the profiler\'s pages take the width of the window', (string) $client->getResponse()->getContent());
    }

    /**
     * Everything of the pack a visitor can click is inside the toolbar's own
     * element (.sf-toolbar, #sfwdt…): a page that tells its own clicks from the
     * toolbar's by those selectors (omnibase/consent does) needs nothing more.
     */
    public function testWhatThePackAddsIsInsideTheToolbar(): void
    {
        $client = self::client();
        $client->request('GET', '/en/page');

        /* What is left once the scripts, the styles and the stylesheet link are taken out: one element, the toolbar's. */
        $markup = trim((string) preg_replace(['~<script\b.*?</script>~s', '~<style\b.*?</style>~s', '~<link\b[^>]*>~', '~<!--.*?-->~s'], '', self::toolbarOf($client)));
        self::assertMatchesRegularExpression('~^<div class="sf-toolbar[ "]~', $markup);
        self::assertStringEndsWith('</div>', $markup);

        preg_match_all('~<(/?)div\b~', $markup, $tags, \PREG_OFFSET_CAPTURE);
        $depth = 0;
        foreach ($tags[1] as $index => [$closing]) {
            $depth += '' === $closing ? 1 : -1;
            if (0 === $depth) {
                self::assertSame(\count($tags[1]) - 1, $index, 'Nothing of the toolbar is laid in the page outside .sf-toolbar.');
            }
        }
        self::assertSame(0, $depth);

        $blocks = new \Symfony\Component\DomCrawler\Crawler(self::blocksOf($client));
        self::assertGreaterThan(0, $blocks->filter('.sf-toolbar-toggle-button')->count(), 'The handle is Symfony\'s own button, loaded into #sfwdt….');
    }

    /** The test application's cookie question: a dialog fixed at the bottom, over the navigation along it, which the toolbar keeps clear of. */
    public function testTheCookiePanelPageShowsAPanelToKeepClearOf(): void
    {
        $client = self::client();
        $crawler = $client->request('GET', '/en/page/cookie-panel');

        $panel = $crawler->filter('#cookie-panel[role="dialog"]');
        self::assertCount(1, $panel);
        self::assertStringContainsString('position: fixed', (string) $panel->attr('style'));
        self::assertCount(3, $panel->filter('button'));
        self::assertCount(1, $crawler->filter('#bottom-nav'));
        self::assertStringContainsString('function clearOfPanels(', self::toolbarOf($client));
    }

    /** chapaland's /login on a phone: a "Menu" button fixed in the bottom right corner of a long page, which the toolbar keeps clear of before the page's end. */
    public function testTheFixedMenuPageShowsAFixedControlOnALongPage(): void
    {
        $client = self::client();
        $crawler = $client->request('GET', '/en/page/fixed-menu');

        $menu = $crawler->filter('button#fixed-menu[aria-label="Menu"]');
        self::assertCount(1, $menu);
        self::assertStringContainsString('position: fixed', (string) $menu->attr('style'));
        self::assertStringContainsString('bottom: 0', (string) $menu->attr('style'));
        self::assertCount(1, $crawler->filter('div[style*="height: 200vh"]'), 'Long enough: the top of the page is not its end.');
    }

    /** glitchr's home on a computer: an introduction over the whole window hiding the footer's links, its enter button and cookie circle just above the toolbar, a cookie question counting down every second. */
    public function testTheCountdownPageShowsAnIntroductionOverTheFooter(): void
    {
        $client = self::client();
        $crawler = $client->request('GET', '/en/page/countdown');

        $intro = $crawler->filter('section#intro');
        self::assertCount(1, $intro);
        self::assertStringContainsString('position: fixed; inset: 0', (string) $intro->attr('style'));
        self::assertNull($intro->attr('role'), 'A page laid out in a fixed layer, not a dialog.');
        self::assertCount(1, $intro->filter('button#enter'));
        self::assertCount(1, $intro->filter('[data-consent] button'));
        self::assertCount(5, $crawler->filter('footer a'));
        self::assertCount(1, $crawler->filter('#cookie-question[role="dialog"] #countdown'));
        self::assertStringContainsString("window.setInterval(function () {", (string) $client->getResponse()->getContent());
    }

    public function testThePhoneLayoutIsPartOfTheFloatingToolbar(): void
    {
        $client = self::client();
        $client->request('GET', '/en/page');
        self::assertStringContainsString('function pageBottomBar()', self::toolbarOf($client));
        self::assertStringContainsString('@media (max-width: 767px), (pointer: coarse)', self::stylesOf($client));

        $client = self::client(['floating' => false]);
        $client->request('GET', '/en/page');
        self::assertStringNotContainsString('--profiler-pack-handle', self::stylesOf($client));
    }
}
