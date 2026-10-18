# Changelog

Every change that ships bumps the version in `samanzavarian-elementor-widgets.php`
(header `Version:` and `SZW_VERSION`, which also busts the asset cache) and adds
an entry here. `tests/process-test.php` fails if the three disagree.

## 0.3.0
- Process: separate arrow icons for arrows above and below the cards (widget-wide
  and per step); an empty "below" icon reuses the "above" one.

## 0.2.0
- Process: responsive layout now follows Elementor's breakpoints — tablet/mobile
  values are control defaults (columns 4/2/1, gaps, grid extension); the
  stylesheet's fixed 900px breakpoint is gone.
- Process (mobile): hidden end-of-row arrows no longer cause sideways scrolling;
  one column on phones with arrows pointing down; grid stays inside the screen.
- Process: Arrows section — show/hide per breakpoint, hand-drawn or any icon,
  per-step override (own icon / none); style: colour, weight, size, rotate, offsets.
- Process: optional link per step (whole card becomes a link, keyboard focus styled).

## 0.1.0
- Plugin scaffold, shared design layer, icon library.
- Process Steps widget ("How we work").
