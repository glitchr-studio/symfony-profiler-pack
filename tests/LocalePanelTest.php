<?php

namespace Glitchr\ProfilerPack\Tests;

final class LocalePanelTest extends PackTestCase
{
    public function testTheToolbarSaysTheLocaleAndLinksThePageInTheOthers(): void
    {
        $client = self::client();
        $client->request('GET', '/de/page/about?page=2');
        self::assertResponseIsSuccessful();

        $crawler = new \Symfony\Component\DomCrawler\Crawler(self::blocksOf($client));
        $block = $crawler->filter('.sf-toolbar-block-locale');

        self::assertCount(1, $block);
        self::assertSame('de', trim($block->filter('.sf-toolbar-value')->text()));
        self::assertSame(
            ['/en/page/about?page=2', '/fr/page/about?page=2'],
            $block->filter('a[hreflang]')->each(static fn ($a) => $a->attr('href')),
            'The same route, its parameters and its query, in each other locale.',
        );
        self::assertSame('de', trim($block->filter('.sf-toolbar-status-green')->text()), 'The locale of the page is marked, not linked.');
    }

    public function testARouteWithoutLocaleGetsNoLink(): void
    {
        $client = self::client();
        $client->request('GET', '/plain');

        $crawler = new \Symfony\Component\DomCrawler\Crawler(self::blocksOf($client));
        $block = $crawler->filter('.sf-toolbar-block-locale');

        self::assertCount(1, $block);
        self::assertSame('en', trim($block->filter('.sf-toolbar-value')->text()));
        self::assertCount(0, $block->filter('a[hreflang]'));
        self::assertStringNotContainsString('This page in', $block->text());
    }

    public function testTheProfilerHasItsPanel(): void
    {
        $client = self::client();
        $client->request('GET', '/fr/page');
        $token = $client->getResponse()->headers->get('X-Debug-Token');

        $crawler = $client->request('GET', '/_profiler/'.$token.'?panel=locale');
        self::assertResponseIsSuccessful();

        self::assertSame('fr', trim($crawler->filter('.metric .value')->first()->text()));
        self::assertStringContainsString('en, fr, de', $crawler->filter('#collector-content, .sf-reset, body')->first()->text());
        self::assertCount(3, $crawler->filter('td a[hreflang]'));
    }

    public function testSwitchedOffThereIsNoBlockAndNoPanel(): void
    {
        $client = self::client(['locale_panel' => false]);
        $client->request('GET', '/de/page');
        $token = $client->getResponse()->headers->get('X-Debug-Token');

        self::assertStringNotContainsString('sf-toolbar-block-locale', self::blocksOf($client));
        self::assertFalse($client->getContainer()->get('profiler')->loadProfile($token)->hasCollector('locale'));
    }
}
