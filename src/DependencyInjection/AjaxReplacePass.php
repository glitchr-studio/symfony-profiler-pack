<?php

namespace Glitchr\ProfilerPack\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Hands profiler_pack.ajax_replace to the toolbar's listener, when the
 * application asked for a value and the toolbar is there. Setting
 * web_profiler.toolbar.ajax_replace from here would switch the toolbar on in
 * an environment that left it off: the node enables itself as soon as one of
 * its keys is given.
 */
final class AjaxReplacePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('profiler_pack.ajax_replace')) {
            return;
        }

        $ajaxReplace = $container->getParameter('profiler_pack.ajax_replace');
        if (null === $ajaxReplace || !$container->hasDefinition('web_profiler.debug_toolbar')) {
            return;
        }

        $definition = $container->getDefinition('web_profiler.debug_toolbar');
        $class = $container->getParameterBag()->resolveValue($definition->getClass());
        $constructor = (new \ReflectionClass($class))->getConstructor();

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            if ('ajaxReplace' === $parameter->getName()) {
                $definition->setArgument($parameter->getPosition(), (bool) $ajaxReplace);

                return;
            }
        }
    }
}
