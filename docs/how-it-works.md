# How it works

## On top of the original templates

WebProfilerBundle renders the toolbar from three templates of its own:
`@WebProfiler/Profiler/toolbar_js.html.twig` (the markup and the script, written in every page),
`@WebProfiler/Profiler/toolbar.css.twig` (the styles, served at `/_wdt/styles`) and
`@WebProfiler/Profiler/profiler.css.twig` (the styles of the profiler's pages).

The pack adds its own folder to `twig.paths` under the `WebProfiler` namespace. TwigBundle registers
those paths before the bundles' own, so the pack's three templates are found first. Each of them
starts by including its original, which stays reachable under `@!WebProfiler`:

```twig
{{- include('@!WebProfiler/Profiler/toolbar.css.twig') -}}
{# ... then what the pack adds, each part behind its switch #}
```

Nothing of Symfony's is copied: when WebProfilerBundle changes its toolbar, the change comes through.
With every switch off, the three templates give exactly what the originals give; a test compares them.

If your application overrides one of these three templates itself
(`templates/bundles/WebProfilerBundle/Profiler/...`), the pack's template is found before yours. Start
yours from the pack's (`@ProfilerPack/bundles/WebProfilerBundle/Profiler/...`) or switch the pack's part
off.

## The script is written on one line

WebProfilerBundle removes every line break from the toolbar's markup before writing it in the page.
A `//` comment would swallow the rest of the script, and a statement without its semicolon would run
into the next one. The pack's scripts use `/* */` only and end every statement; a test reads their
source for both.

## What is kept in the browser

Two entries of `localStorage`, per origin: `profiler-pack/toolbar/position` (`{"x": …, "y": …}`, an
offset from the bottom right corner) and `profiler-pack/toolbar/width`. Removing them puts the toolbar
back. Symfony's own entry for the open or closed toolbar is left alone.

## Nothing reaches the page being debugged

Every rule of the toolbar's styles starts with a class of the toolbar (`.sf-toolbar…`) or with its
`#sfwdt…` element; a test reads the stylesheets for it. Lengths are counted on the toolbar's own type
size, so the page's font size does not move it.

## What the pack leaves to Symfony

- **Where a query came from**: DoctrineBundle has it. `doctrine.dbal.profiling_collect_backtrace: true`
  adds the backtrace of every query to the Doctrine panel.
- **The toolbar following AJAX requests**: `web_profiler.toolbar.ajax_replace: true`. The pack's own
  `ajax_replace` only passes a value on, for an application that wants to set it in one place.
