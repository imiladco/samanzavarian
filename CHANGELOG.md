# Changelog

Every change that ships bumps the version in `samanzavarian-elementor-widgets.php`
(header `Version:` and `SZW_VERSION`, which also busts the asset cache) and adds
an entry here. `tests/process-test.php` fails if the three disagree.

## 0.6.0
- Service Panel — **Post-production scene, live and usable**:
  - The grading sliders are real range inputs under the design's knobs:
    visitors drag them (anywhere on the track) or use the keyboard, screen
    readers hear "Warmth, +18". The number and the grade follow.
  - The grade is real: clips (and an optional preview image) warm, cool,
    brighten or deepen — measured from each slider's start, so at rest the
    colours are exactly the design's.
  - On their own the sliders drift slowly, gliding from wherever they are;
    a held or just-released slider is left alone.
  - The timeline runs: the playhead is the export's progress ("Exporting
    46%"), cuts move on the way, "Final export ready" pops in, then it
    rewinds for the next pass — always within the visible part of the card.
  - Controls: sliders (label, effect, range, start, track colours), drift,
    preview image, clips (track, length, colour), ruler, labels, run time;
    Style: every colour.
  - Reduced motion: nothing moves on its own; the sliders still work by hand.
- Scene runtime: scene scripts are created even with reduced motion, so a
  scene's hand controls always work; only automatic motion is held back.

## 0.5.0
- Service Panel — **AI scene, editable and animated**: the prompt types itself,
  Generate is pressed, frames wait on sweeping plates, resolve from noise to
  sharp, and the approval ring and label land on the picked frame.
  - Frames repeater: each an image or a video (videos of a round play together)
    or one of the design's gradients. More than three → rounds: the old frames
    are tossed off the sheet, the next prompt (one per line) is typed.
  - Controls: prompts, approved frame, label with {n}, button label, badge icon,
    frame shape, typing speed, time per round, cycling; Style: sheet width and
    every colour.
  - Plays only while visible in an open tab; finished state without JS, with
    reduced motion, and whenever paused. Design match unchanged (0px).
- Panel runtime: a scene registry (window.szwScenes) — each scene script loads
  only on pages that use that scene.
- Fix: updating this plugin now clears Elementor's generated CSS and element
  cache once, so new stylesheets never meet old cached widget HTML.
- Fix (would have shipped in 0.4.0 if scenes had scripts): the editor preview
  no longer fails when Elementor asks the bare widget type for its scripts.

## 0.4.0
- New: **Service Tabs** ("What I do") — a nested widget like Elementor's own Tabs:
  every tab owns a real container, so any content can be built inside it. A new
  widget arrives with the design's four tabs, each holding a Service Panel.
  WAI-ARIA tabs (one tab stop, arrows/Home/End), sliding highlight, links to a
  tab by ID (optionally written to the address), editor sync both ways. Layout
  (list beside / row above) follows Elementor breakpoints; full Style tab.
- New: **Service Panel** — toned background, scene, frosted card with text,
  deliverables and a button (with a contact-form pre-select hint). Scenes for
  the design's four panels (static in this version), or an image/video that
  plays only while visible in an open tab.
- Matches the design to 0px at 1440/1100/860/390 on every tab, also under a
  hostile theme. Kept on purpose: the playhead handle the design defines but
  hides through an unrelated `.ph` rule.
- Tests split into tests/lib.php, plugin-, process- and service-test; run all
  with `wp eval-file tests/all.php`.

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
