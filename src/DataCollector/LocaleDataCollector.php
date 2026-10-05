<?php

namespace Glitchr\ProfilerPack\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The locale a request was answered in, and where the same page is in the
 * application's other locales - when its route says the locale in its path
 * ({_locale}); nothing is guessed for a route that does not.
 */
final class LocaleDataCollector extends DataCollector
{
    public const NAME = 'locale';

    /** @param list<string> $enabledLocales */
    public function __construct(
        private ?UrlGeneratorInterface $urlGenerator = null,
        private array $enabledLocales = [],
        private string $defaultLocale = 'en',
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $locale = $request->getLocale();
        $route = $request->attributes->get('_route');
        $parameters = $request->attributes->get('_route_params', []);

        $this->data = [
            'locale' => $locale,
            'default_locale' => $this->defaultLocale,
            'enabled_locales' => $this->enabledLocales,
            'preferred_language' => $request->getPreferredLanguage(),
            'timezone' => date_default_timezone_get(),
            'route' => \is_string($route) ? $route : null,
            'switches' => [],
        ];

        if (!$this->urlGenerator || !\is_string($route) || !\is_array($parameters) || !\array_key_exists('_locale', $parameters)) {
            return;
        }

        foreach (array_unique([...$this->enabledLocales, $locale]) as $other) {
            try {
                $url = $this->urlGenerator->generate($route, ['_locale' => $other] + $parameters + $request->query->all());
            } catch (ExceptionInterface) {
                // A route that has no page in this locale (its requirements say so).
                continue;
            }

            $this->data['switches'][$other] = $url;
        }
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getLocale(): string
    {
        return $this->data['locale'] ?? '';
    }

    public function getDefaultLocale(): string
    {
        return $this->data['default_locale'] ?? '';
    }

    /** @return list<string> */
    public function getEnabledLocales(): array
    {
        return $this->data['enabled_locales'] ?? [];
    }

    public function getPreferredLanguage(): ?string
    {
        return $this->data['preferred_language'] ?? null;
    }

    public function getTimezone(): string
    {
        return $this->data['timezone'] ?? '';
    }

    public function getRoute(): ?string
    {
        return $this->data['route'] ?? null;
    }

    /** @return array<string, string> the page's address by locale; empty when its route has no {_locale} */
    public function getSwitches(): array
    {
        return $this->data['switches'] ?? [];
    }
}
