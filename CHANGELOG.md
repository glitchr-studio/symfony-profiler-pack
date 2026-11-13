# Changelog

## Unreleased

## [1.0.0] - 2026-11-13

The first tagged release: `composer require --dev glitchr/symfony-profiler-pack` installs it in a project with
Composer's default minimum-stability. PHP 8.2, Symfony 7.4 or 8.1 and later.

### Added

- A floating web debug toolbar: a rounded bar over the page, moved by its handle (mouse or finger), resized by its
  left edge, folded by a double click on that edge, sent aside by a double click on the handle; its place and width
  kept by the browser, kept inside the window and clear of the page's fixed bars and open panels
  (`data-toolbar-avoid` for anything else), left where a hand puts it. Its pointer, mouse, touch and key events do not
  reach the page's listeners.
- On a phone (below 768px or under a finger), the toolbar in one piece across the window, clear of the safe area.
- A locale block: the request's locale, the default one, the timezone, the same page in each other enabled locale.
- Profiler pages that use the window's whole width.
- Two fixes of the toolbar as Symfony ships it: no unstyled "Loading the web debug toolbar..." while it loads, no
  script error from a block without details; and its stylesheet, linked in the body, no longer holds the page's
  scripts back.
- Each feature has its switch (`profiler_pack.*`); with every switch off the toolbar is exactly Symfony's.

### Fixed

- The test comparing the toolbar with every switch off to Symfony's own replaces each answer's nonces wherever they
  are: Symfony 8.1's toolbar also writes them in its script (the `X-SymfonyProfiler-*-Nonce` headers of its
  requests), which made the two answers differ by their random nonces alone.
- CI: the Symfony 8.0 row is gone (every 8.0 release is affected by the advisory PKSA-jvt1-tggb-8w72, so Composer
  refuses to install it; 7.4.23 and 8.1.11 have the fix); actions pinned by commit; `release.yml` publishes a tag
  after the CI passes on it.
