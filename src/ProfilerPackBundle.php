<?php

namespace Glitchr\ProfilerPack;

use Glitchr\ProfilerPack\DataCollector\LocaleDataCollector;
use Glitchr\ProfilerPack\DependencyInjection\AjaxReplacePass;
use Glitchr\ProfilerPack\Twig\ProfilerPackExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Additions to Symfony's WebProfilerBundle: a floating web debug toolbar, a
 * locale panel, wider profiler pages. Everything is added on top of the
 * original templates, which stay the ones Symfony ships.
 */
final class ProfilerPackBundle extends AbstractBundle
{
    protected string $extensionAlias = 'profiler_pack';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('floating')->defaultTrue()
                    ->info('The toolbar as a compact bar that floats over the page, in place of the strip across its bottom.')->end()
                ->booleanNode('draggable')->defaultTrue()
                    ->info('The floating toolbar moves by its handle; its place is remembered by the browser.')->end()
                ->booleanNode('resizable')->defaultTrue()
                    ->info('The floating toolbar is made narrower or wider by its left edge; its width is remembered.')->end()
                ->booleanNode('minimizable')->defaultTrue()
                    ->info('A double click on the left edge folds the floating toolbar, another unfolds it; one on the handle sends it to the other side.')->end()
                ->booleanNode('fixes')->defaultTrue()
                    ->info('No unstyled "Loading the web debug toolbar…" while the toolbar loads, and no script error from a block that has no details.')->end()
                ->scalarNode('hide_when')->defaultNull()
                    ->info('A CSS selector: while an element matches it, the toolbar is hidden (a page transition, say: "html.is-loading").')
                    ->validate()
                        ->ifTrue(static fn ($v) => \is_string($v) && 1 === preg_match('/[{}<>;]/', $v))
                        ->thenInvalid('"hide_when" is one CSS selector: no "{", "}", "<", ">" at its ends or ";" in it. Got %s.')
                    ->end()
                ->end()
                ->booleanNode('profiler_pages')->defaultTrue()
                    ->info('The profiler\'s pages take the whole width of the window.')->end()
                ->booleanNode('locale_panel')->defaultTrue()
                    ->info('A toolbar block and a profiler panel with the locale of the request, and a link per enabled locale when the route has a {_locale}.')->end()
                ->booleanNode('ajax_replace')->defaultNull()
                    ->info('true or false sets web_profiler.toolbar.ajax_replace (the toolbar follows AJAX requests); null leaves it as the application configured it.')->end()
            ->end()
        ;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // twig.paths come before the bundles' own templates: these are found
        // first under @WebProfiler, and each begins with its original, still
        // reachable as @!WebProfiler.
        if (!$builder->hasExtension('twig')) {
            return;
        }

        $builder->prependExtensionConfig('twig', [
            'paths' => [$this->getPath().'/templates/bundles/WebProfilerBundle' => 'WebProfiler'],
        ]);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $floating = $config['floating'];
        $options = [
            'floating' => $floating,
            'draggable' => $floating && $config['draggable'],
            'resizable' => $floating && $config['resizable'],
            'minimizable' => $floating && $config['minimizable'],
            'fixes' => $config['fixes'],
            'hide_when' => $config['hide_when'],
            'profiler_pages' => $config['profiler_pages'],
            'locale_panel' => $config['locale_panel'],
        ];

        $builder->setParameter('profiler_pack.options', $options);
        $builder->setParameter('profiler_pack.ajax_replace', $config['ajax_replace']);

        $services = $container->services();

        $services->set('profiler_pack.twig_extension', ProfilerPackExtension::class)
            ->args([param('profiler_pack.options')])
            ->tag('twig.extension');

        if ($config['locale_panel']) {
            $services->set('profiler_pack.locale_collector', LocaleDataCollector::class)
                ->args([
                    service('router')->nullOnInvalid(),
                    param('kernel.enabled_locales'),
                    param('kernel.default_locale'),
                ])
                ->tag('data_collector', [
                    'id' => LocaleDataCollector::NAME,
                    'template' => '@ProfilerPack/Collector/locale.html.twig',
                    'priority' => 200,
                ]);
        }
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new AjaxReplacePass());
    }
}
