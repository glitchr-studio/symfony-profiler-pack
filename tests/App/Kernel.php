<?php

namespace Glitchr\ProfilerPack\Tests\App;

use Glitchr\ProfilerPack\ProfilerPackBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Environment;

/**
 * The smallest application that shows a web debug toolbar: three bundles, the
 * pack when asked for, a page per locale, a page without one, an AJAX answer.
 * The tests build it with the options they are about; public/index.php serves
 * it for a browser.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed>|null $pack         the pack's configuration; null leaves the bundle out
     * @param array<string, mixed>      $webProfiler  merged over the application's web_profiler configuration
     */
    public function __construct(
        string $environment = 'test',
        bool $debug = true,
        private ?array $pack = [],
        private array $webProfiler = [],
    ) {
        parent::__construct($environment, $debug);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new WebProfilerBundle();

        if (null !== $this->pack) {
            yield new ProfilerPackBundle();
        }
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return __DIR__.'/var/cache/'.$this->environment.'/'.substr(hash('xxh128', serialize([$this->pack, $this->webProfiler])), 0, 12);
    }

    public function getLogDir(): string
    {
        return __DIR__.'/var/log';
    }

    public function page(Request $request, Environment $twig, string $slug = 'home'): Response
    {
        return new Response($twig->render('page.html.twig', ['locale' => $request->getLocale(), 'slug' => $slug]));
    }

    public function plain(Environment $twig): Response
    {
        return new Response($twig->render('page.html.twig', ['locale' => null, 'slug' => 'plain']));
    }

    public function ajax(): JsonResponse
    {
        return new JsonResponse(['answered' => true]);
    }

    public function failing(): never
    {
        throw new \RuntimeException('A page that fails, to see the profiler\'s exception panel.');
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'not-a-secret',
            'test' => 'test' === $this->environment,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
            'default_locale' => 'en',
            'enabled_locales' => ['en', 'fr', 'de'],
            'profiler' => ['enabled' => true, 'collect' => true, 'only_exceptions' => false],
        ]);

        $container->extension('twig', ['default_path' => __DIR__.'/templates']);
        $container->extension('web_profiler', $this->webProfiler + ['toolbar' => true, 'intercept_redirects' => false]);

        if ($this->pack) {
            $container->extension('profiler_pack', $this->pack);
        }

        // Without it the framework's own logger writes every request on the error output.
        $container->services()->set('logger', NullLogger::class);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@WebProfilerBundle/Resources/config/routing/wdt.php')->prefix('/_wdt');
        $routes->import('@WebProfilerBundle/Resources/config/routing/profiler.php')->prefix('/_profiler');

        $routes->add('page', '/{_locale}/page/{slug}')
            ->controller('kernel::page')
            ->defaults(['slug' => 'home'])
            ->requirements(['_locale' => 'en|fr|de']);
        $routes->add('plain', '/plain')->controller('kernel::plain');
        $routes->add('ajax', '/ajax')->controller('kernel::ajax');
        $routes->add('failing', '/failing')->controller('kernel::failing');
        $routes->add('home', '/')->controller('kernel::plain');
    }
}
