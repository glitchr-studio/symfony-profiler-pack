<?php

namespace Glitchr\ProfilerPack\Tests;

use Glitchr\ProfilerPack\Tests\App\Kernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The test application, built with the pack's configuration a test is about:
 * client(['draggable' => false]), client(null) for the application without
 * the bundle.
 */
abstract class PackTestCase extends WebTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel('test', true, \array_key_exists('pack', $options) ? $options['pack'] : [], $options['web_profiler'] ?? []);
    }

    /**
     * @param array<string, mixed>|null $pack
     * @param array<string, mixed>      $webProfiler
     */
    protected static function client(?array $pack = [], array $webProfiler = []): KernelBrowser
    {
        static::ensureKernelShutdown();

        return static::createClient(['pack' => $pack, 'web_profiler' => $webProfiler]);
    }

    /**
     * Everything WebProfilerBundle wrote in the page: from the end of the page's
     * own content to </body>, the token and the nonces of this answer replaced
     * by a word, so that two answers compare.
     */
    protected static function toolbarOf(KernelBrowser $client): string
    {
        $content = (string) $client->getResponse()->getContent();
        self::assertSame(1, preg_match('~<!-- end of the page -->(.*)</body>~s', $content, $matches));
        self::assertStringContainsString('<!-- START of Symfony Web Debug Toolbar -->', $matches[1], 'The page carries the web debug toolbar.');

        $toolbar = str_replace((string) $client->getResponse()->headers->get('X-Debug-Token'), 'TOKEN', $matches[1]);

        return preg_replace('~ nonce="[^"]+"~', ' nonce="NONCE"', $toolbar);
    }

    /** The toolbar's styles, as the page links them. */
    protected static function stylesOf(KernelBrowser $client): string
    {
        $client->request('GET', '/_wdt/styles');
        self::assertResponseIsSuccessful();

        return (string) $client->getResponse()->getContent();
    }

    /** The toolbar's content for the last page asked: what the browser fetches to fill it. */
    protected static function blocksOf(KernelBrowser $client): string
    {
        $token = $client->getResponse()->headers->get('X-Debug-Token');
        self::assertNotNull($token, 'The answer names its profile.');
        $client->request('GET', '/_wdt/'.$token);
        self::assertResponseIsSuccessful();

        return (string) $client->getResponse()->getContent();
    }
}
