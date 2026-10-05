<?php

namespace Glitchr\ProfilerPack\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Gives the profiler's templates the bundle's options, as the global
 * "profiler_pack": the toolbar's script and styles are rendered by
 * WebProfilerBundle's own listener and controller, with no way to pass them
 * anything.
 */
final class ProfilerPackExtension extends AbstractExtension implements GlobalsInterface
{
    /** @param array<string, bool|string|null> $options */
    public function __construct(private array $options)
    {
    }

    public function getGlobals(): array
    {
        return ['profiler_pack' => $this->options];
    }
}
