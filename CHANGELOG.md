# Changelog

Every change that ships bumps the version in `samanzavarian-elementor-widgets.php`
(header `Version:` and `SZW_VERSION`, which also busts the asset cache) and adds
an entry here. `tests/process-test.php` fails if the three disagree.

## 0.11.0 — Selected work: a filterable project slider
A new widget, **Selected Work**, built from the prototype approved by the site
owner: the filters and the slider only (heading and stats stay outside it).

- **Filters.** A segmented control whose white thumb springs from filter to
  filter, each with its project count; a filter with no projects is not
  shown, so the slider is never empty. Arrow keys, Home and End; the result is
  read out ("3 projects in Photo"). On phones the row scrolls, fades at a cut
  edge, and keeps the active filter in view.
- **Filtering moves, never jumps.** Leaving cards fade out where they were,
  staying cards slide to their new place (a card that changes size fades
  instead of stretching), new cards rise one after another, and the slider
  returns to the start. The first card of each filter is wide (under "All",
  the featured project); never on phones.
- **The slider.** Mouse drag with a throw that settles on a card; a drag over
  40px always lands at least one card further. Page buttons move by as many
  cards as are in view and disable at the ends. The progress bar is a
  scrubber (drag it or click the track). The position reads "1–3 of 8",
  counting projects only. Arrow keys, Home and End when the slider has focus.
  Cards slide out to the window's edges and still stop aligned with the
  content column; measured before the first paint, so nothing moves on load
  (layout shift 0.0001).
- **Cards.** Image or one of the design's eight gradients; a preview video
  that is downloaded on the first hover and plays only while hovered, with a
  "Preview" label; tilt and glare under a mouse; light or dark text; a
  "Featured" label. A last card leads to the full archive ("See all 106+
  projects"); projects without a link of their own go to the profile.
- **Address and analytics.** Choosing Photo writes #work-photo (replaceState,
  no history spam); opening that link, or clicking one anywhere on the page,
  opens the filter. sz_work_filter, sz_work_open and sz_work_profile go to
  GTM's dataLayer, or GA4 without GTM.
- **Editor.** Every text, label, link and colour is editable, card width and
  shape per breakpoint. The editor points out a project whose filter matches
  none, and a profile URL still left at behance.net.
- Works without its script: every card and count is in the HTML and the
  active filter is drawn correctly. Reduced motion: no tilt, no preview, and
  filtering changes at once.

## 0.10.0 — the four "What I do" scenes, rebuilt
Each scene now shows what the work really looks like, worked out with the
site owner on a live prototype before any of it was built. Every text, item,
colour and timing is editable; the Animation setting, the pause button and
reduced motion work as before.

- **AI visual production — brief to showreel.** The top line is the client's
  brief to the freelancer (who, what, the constraint), not a prompt being
  typed. Under it, a reel of delivered pieces, each in its real shape (a Reel
  or Story is 9:16, a website loop 16:9, a product shot square…), never
  cropped. It opens on the whole reel so it is clear there are several, then:
  a piece plays its own length (video length, shown as a clock and a time
  bar), the camera pulls back, glides to another piece (random, all before
  any repeats, never twice in a row), pushes in, and the focus pulls sharp
  with autofocus brackets. Neighbours stay soft at the sides. Only the piece
  in focus plays its video. Placeholder footage until videos are added.
- **Creative direction — three routes, one choice.** Three pictures of one
  product in three formats (post 4:5, story 9:16, ad 1:1; pictures only, no
  text). Before direction they look like three different shoots; the
  director shows three routes on the same pictures (palette, note, light,
  set and grade change together), and the client chooses one.
- **Post-production — an editor at work.** The playhead is a real transport
  (1×, 2×, 4×, rewind, stop, shown by the timecode) and the timecode is
  always exactly where it is (hh:mm:ss:ff at 24/25/30 fps). The view moves
  like an editor's hand: the playhead travels and pages on, or holds while
  the timeline runs under it, or both move, or the editor looks ahead. Cuts,
  trims (snapping, rippling) and inserts happen at the playhead and are
  reviewed at 1×; every few edits a version is exported. The timeline never
  repeats. The grading sliders grade the clips; visitors can drag the sliders
  and scrub the timeline.
- **Content systems — a rolling plan that runs on rules.** Yesterday, today
  and five days ahead, rolling a day at a time with "Today" fixed in place.
  Smart time-lapse: quiet days pass in about a second, never quite the same,
  and time slows as a post's moment comes. Posts go out at their time (shown
  under each) only if ready, then are shared to stories; the team makes them
  in office hours, a few at a time, from a few days ahead; next week is
  planned on Thursday; in some weeks a trend gets a fast Reel and something
  else may move a day, saying why; a strong Reel gets a carousel version. A
  key under the days explains every mark. Plans of later weeks follow a
  cadence (one post a day, quieter weekend, no format twice in a row).

Under the hood
- Each scene is its own class (`includes/Widgets/Scenes/`), with its own
  script and stylesheet, loaded only where that scene is used.
- Scenes draw on a fixed design stage that is scaled to the panel's width
  and height (a compact stage for narrow panels), so they look the same in a
  wide tab, a column or a phone. Scaling is a transform: no layout shift.
- Scenes run on a clock that only moves while the scene is visible and
  playing, so a paused or hidden scene holds exactly where it is.
- Layout shift over all four stories: 0.0009. After "Play once" a scene's
  clock stops; the AI placeholders keep moving while on screen, as videos
  would (never off screen, in a closed tab, when paused, "Still" or with
  reduced motion).
- Settings of the previous scenes (prompts, frames, boards, weekly grids) are
  replaced; panels saved with them show the new defaults until edited.
- Tests: 220 checks, plus browser checks of every scene in the editor,
  "Play once" to rest, reduced motion and layout shift.

## 0.9.0 — product review of "What I do"
Reviewed as the site owner, the visitor and the business, measured on a real
page. Changes:

Visitors
- **Animation setting** per panel: *Play once, then settle* (default) / *Keep
  playing* / *Still*. "Once" tells each scene's story the first time it is seen
  and rests on the finished picture; revisiting a tab does not replay it.
  Replaces the per-scene "keep cycling" switches.
- **Pause / play button** whenever something moves (scenes or videos), per
  WCAG 2.2.2. The visitor's choice applies to every panel and is remembered.
- Fix: with reduced motion the twinkles and the light sweep kept running (a
  CSS specificity bug since 0.4.0). Fix: decorative loops on pseudo-elements
  kept running in closed tabs.
- Sliders show they can be dragged (grab cursor, two pulses the first time).
- Phones: the tab row fades at the edge that has more tabs; the calendar shows
  colour chips and a legend instead of 9.5px names that overflowed their day
  (the design's own phone calendar had stretched columns).

Speed / SEO (Core Web Vitals)
- Layout shift (CLS) from the panels: 0.12 → 0.001. Moving pictures, knobs,
  playhead and the sweep now use transforms only; text no longer reflows when
  Inter loads (a metric-matched fallback: Arial / Arimo / Liberation Sans).
- After a scene settles: 0 layouts, 0 style recalculations, 0 ms script.

Business
- Analytics: `sz_services_view`, `sz_service_tab` (service_name, tab_id,
  position) and `sz_service_cta` (service_name, link_url) — to GTM's dataLayer,
  or to GA4's gtag() when installed without GTM; never both. Switchable.
- Tabs get link IDs from their titles (#ai-visual-production), unique on the
  page, so deep links work without setup.

Site owner
- In the editor scenes wait, finished; the same button previews them.
- Editor-only notes for input the widget would otherwise work around silently
  (unknown post type, impossible slider range, frames not in threes, a board
  without palette).
- Size guidance on every image/video control (dimensions, format, weight).

## 0.8.0
- Service Panel — **Creative direction scene, "from chaos to direction"**:
  references land on the board in a loose pile, a pointer chooses three
  (orange ring), the rest are cleared away, the chosen three glide into the
  design's composition, the palette's colours fly out of the pictures into the
  swatch, and the direction note is written. Then the next board.
  - Boards repeater: three chosen pictures (image, video, or a design gradient),
    up to four palette colours, the note. Other references come from one
    shared gallery; each board takes the next few (gradients when empty).
  - Controls: note title, time per board, cycling; Style: note, palette and
    selection-ring colours.
  - Rest state, no JS, reduced motion: board one finished — the design.
- All four design scenes are now editable and animated.

## 0.7.0
- Service Panel — **Content calendar scene that plans itself**:
  - Real week (default): the visitor's own ISO week number and today, from
    their device (the server prints the site's current week as a first guess).
    Fixed week: any number and highlighted day — Week 42 / Wednesday is the design.
  - Posts land day by day, the notification comes in with the week's count
    ("4 posts scheduled"), then the page lifts and turns like a desk calendar
    to the next week, filled from the next plan. Weeks roll over correctly
    (53 → 1); only the current week has a "today".
  - Controls: post types (name and colours), weekly plans (per day, "Reel" or
    "Reel + Story"), day names, title, week label {n}, notification icon and
    texts {count}, timing; Style: every colour.
  - Reduced motion: dated to the visitor, finished week, nothing moves.

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
