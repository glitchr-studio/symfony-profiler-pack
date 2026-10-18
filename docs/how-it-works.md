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

Two entries of `localStorage`, per origin: `profiler-pack/toolbar/position` (`{"x": …, "y": …,
"hand": …}`, an offset from the bottom right corner, and whether a hand put it there) and `profiler-pack/toolbar/width`. Removing them puts the toolbar
back. Symfony's own entry for the open or closed toolbar is left alone.

The position kept is the one asked for. Where the toolbar is shown may be nearer, never farther out:

- its handle never leaves the window, nor does its right end; its body may slide off to the left,
  which is what sending it aside does;
- its bottom stays inside what the browser shows of the page (`window.visualViewport`: not under a
  browser's own bar or a keyboard);
- it stays above a bar the page keeps fixed along the bottom of the window. Such a bar is found under
  three points of the window's last line (`document.elementsFromPoint`): a `position: fixed` element
  touching the bottom, at least half the window wide and at most 120px (and a quarter of the window)
  high. A taller one is a panel, not a bar: the toolbar keeps clear of it as below.

That last bound, and everything below (the page's panels, its own controls), is for the places the
pack chooses: the corner, and aside. A place chosen by hand, a drag let go, is shown as it was put,
inside the window and what the browser shows, nothing else: the page's header, its bars, its panels,
its footer never move it. A double click or a double tap, which sends the toolbar aside or back to
its corner, gives the pack its say again. A place kept before the mark existed counts as chosen by hand
unless it is on the bottom line, where the corner and the place aside are.

## Clear of the page's panels

The toolbar never covers what a visitor presses in a panel the page shows over itself: a cookie
question floating at the bottom of the window (omnibase/consent's), a dialog, a chat button. A panel is

- a layer of the page fixed in the window (`position: fixed`), found under the toolbar's column: five
  points across its width, every 32px down the window (`document.elementsFromPoint`), the nearest
  fixed element above what is there;
- or an open dialog wherever it is laid, even scrolling with the page: `dialog[open]`,
  `[role="dialog"]`, `[role="alertdialog"]`, `[aria-modal="true"]`, the banner of a cookie library
  (`[data-consent-banner]`, `[data-cookie-banner]`: @glitchr/flowjs/cookie's, under either prefix),
  and anything a page marks `data-toolbar-avoid`;

shown, across the toolbar's width, and holding a control (a link, a button, a field, a `summary`, an
element a keyboard reaches). A fixed layer of nearly the whole window (90% each way) is the page laid
out in it, not a panel.

Where the toolbar would cover one, it is shown at the nearest height where it covers none, upwards
first (the question at the bottom: the toolbar just above it). A panel nearly as high as the window
(75%: a side rail, a drawer) is kept clear of by its controls only, so the toolbar is not sent over
the top of the window for a rail along its side. When no height is clear of the panels, the toolbar
keeps clear of their controls alone; when none is either, it stays where it was. Its width and its
place across are left as they are, and so is the place asked for: the question answered, the toolbar
goes back down. While it is dragged it goes where the pointer takes it, over a panel too; let go, it
is shown clear of it.

The toolbar is put back inside on every change: a resized window, a scrolled page, a toolbar opened,
closed or filled (once its transitions are over), a bar or a panel of the page that comes or goes,
is shown or hidden (`hidden`, `class`, `open`, `aria-hidden`, `aria-expanded`), or has slid in (the
end of a transition or an animation). A bar or a panel that goes takes the toolbar back down with it:
the place asked for was kept. While it is resized it stays where it is too, its edge under the
pointer; let go, it is put back clear of the page's panels for its new width.

What is done on the toolbar is not done on the page. Its pointer, mouse, touch and key events stop
as they leave it, after the toolbar's own listeners and the pack's (which listen on the window as the
events come down): a page whose splash ends at the first press anywhere, or whose panel closes on a
click elsewhere, never hears a press, a drag or a click on the toolbar. A wheel is left to scroll the
page.

## Clear of the page's own controls

At the end of the page, where nothing scrolls out from under the toolbar any more, it keeps clear of
the page's own controls too (a footer's links, its cookie circle), for a short climb only (160px):
past it, it climbs no more than that, to the height covering the fewest cookie openers, then leaving
the page's last line clear (a footer's credits), then covering the fewest controls fixed in the
window, then the fewest controls, then the nearest.

Before the end, the page's controls pass under the toolbar as it scrolls and it stays where it is,
but for a control fixed or stuck in the window (a "Menu" button in a corner, "back to top"), which
nothing scrolls away: it is kept clear of wherever the page is, ranked the same way. A layer as high
as the window (75%) is the page laid out in it, a column scrolling inside a fixed layer, not such a
control.

A control another layer lies over (a footer under an introduction over the whole page) is out of
reach and nothing to keep clear of. What lies over it is looked for under the toolbar, the toolbar
looked through: where the toolbar is never decides where it goes, so it does not move while nothing
it keeps clear of moves.

## On a narrow window or under a finger

Below 768px wide, or wherever the pointer is coarse, the floating toolbar is one piece. The handle,
Symfony's own toggle button, is the right end of the pill instead of a box set beside it by a negative
margin, which a phone's width pushed apart and out of the window. The pill spans the window less its
safe areas (`env(safe-area-inset-left|right|bottom)`), ends where the handle starts, and its blocks
scroll sideways inside it. A block's details open from the pill's left end, never wider than it.
Closed, the handle alone is the toolbar, in the bottom right corner. Opening and closing are not
animated there: the handle stays under the finger for the second tap of a double tap. Under a finger
the pill is 48px high, and the handle, which spans it, 48px wide: a fingertip's target (Material 48,
Apple and WCAG 44), open as closed.

A double tap on the handle sends the toolbar aside, to the left edge with its handle in view, from
wherever it is; from there, the next one brings it back to its corner. The click a phone sends after
the second tap lands where the handle was, on the page under it by then (a link, a cookie panel): the
pack catches it on the window, before any listener of the page, and lets it reach nothing.

## Inside the toolbar's element

Everything the pack shows is inside `.sf-toolbar` (and Symfony's `#sfwdt…` within it); what it writes
beside it is a `<style>` and two `<script>`s. A page that tells its own clicks from the toolbar's by
`.sf-toolbar`, `[id^="sfwdt"]` or `[id^="sfToolbar"]` (omnibase/consent does) needs nothing more; a
test checks the markup for it.

## Nothing reaches the page being debugged

Every rule of the toolbar's styles starts with a class of the toolbar (`.sf-toolbar…`) or with its
`#sfwdt…` element; a test reads the stylesheets for it. Lengths are counted on the toolbar's own type
size, so the page's font size does not move it.

## What the pack leaves to Symfony

- **Where a query came from**: DoctrineBundle has it. `doctrine.dbal.profiling_collect_backtrace: true`
  adds the backtrace of every query to the Doctrine panel.
- **The toolbar following AJAX requests**: `web_profiler.toolbar.ajax_replace: true`. The pack's own
  `ajax_replace` only passes a value on, for an application that wants to set it in one place.
