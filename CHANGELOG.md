# Changelog

Every change that ships bumps the version in `samanzavarian-elementor-widgets.php`
(header `Version:` and `SZW_VERSION`, which also busts the asset cache) and adds
an entry here. `tests/process-test.php` fails if the three disagree.

## 0.18.0 — Words with icons, in Elementor's own Text Editor
The About statement ("I trained in photography…") with its icon pills,
made with no widget of ours: an ordinary Text Editor (or Heading), so the
text stays a paragraph you edit like any other.

- **Write it in the text:** `{cam:photography}` gives a pill with the
  icon's navy tile; `{spark*:AI image and video}` an orange one;
  `{cam:photography|#work-photo}` makes the pill a link. Any icon of the
  library works by name; anything else in braces stays text.
- **Made on the server,** so the pills are plain HTML: indexable, nothing
  shifts, no script needed to show them. The editor's preview turns them
  live as you type, and a "Words with icons" guide under the editor lists
  every icon with its name.
- **The design, refined:** the icon is glued to its first word, so a tile
  never ends a line alone; a long pill wraps with its shape continued on
  the next line; tiles have depth (a soft gradient and a light edge);
  link pills answer hover with an orange edge and a tilt of the tile.
- **The arrival:** further down the page, the pills light up in reading
  order as the paragraph comes into view: each tile turns in, then its
  pill fills. On screen at load or with reduced motion, it is simply there.
- **`sz-statement`** in Advanced → CSS Classes gives the design's
  statement type (22px, line height 1.8; 17px on phones).

## 0.17.0 — Quote & Reply: the bubble and the reply box, as one conversation
A new widget, **Quote & Reply**: the design's "bubble" (Saman's quote with
a reaction) and its "reply box" (quick replies, a message field), together
because they are one exchange. Rethought before it was built:

- **It arrives as a conversation.** The first time it scrolls into view:
  - typing dots,
  - then the bubble opens from its corner,
  - then the reaction pops on,
  - then the quick replies rise one after another.
  Every box already has its size, so nothing around it moves (layout shift
  0). On screen already at load, with reduced motion, or in the editor, it
  is simply there.
- **The heart is drawn,** the same on every system. An emoji is optional.
  It and its little dot now stay inside the widget; the design's heart
  hung 2px past the edge of a 390px phone and over whatever sat above.
- **A quick reply writes its message** into the field, letter by letter
  (under a second), and stays marked as chosen until the text is edited.
- **The field is 16px:** at the design's 15px, iPhones zoomed the page in
  when it was tapped. The send key on phone keyboards says "send". A "28
  left" count appears only near the limit. Sending an empty field nudges
  it.
- **Sending gives feedback and hands the message on.**
  - The message stays as an outgoing bubble ("Delivered · continue below").
  - It goes into the page's contact form, found by itself (an Elementor
    form or any form with a message box), and the quick reply's option is
    ticked there (e.g. "AI visuals").
  - The page scrolls to the form, and the first empty field gets the
    cursor.
  - It can go to WhatsApp or email instead, and WhatsApp is the fallback
    when the page has no form.
- **Analytics:** sz_reply_quick and sz_reply_send (destination, length,
  whether a quick reply was used), never the message itself.
- **Editable:**
  - Avatar: a photo, or the monogram.
  - Reaction; the arrival on or off.
  - Quick replies, each with its message and form option.
  - Placeholder and length limit.
  - Destination, an advanced field selector, and the labels.
  - Colours for the bubble, chips, field, send button and sent message.
- Buttons and field are protected from site-wide button and input styles.
  At narrow widths it takes the design's phone layout by its own width.

## 0.16.1 — Highlights: it advances where it should
Reported by the site owner: the cards did not move, and the timer under them
was a fixed line.

- **In the Elementor editor** auto-advance was off on purpose, so the card
  being edited would not slide away. Shown in the editor, that read as
  broken. Now it runs there too, and choosing a card in the panel shows it
  and holds still while you edit it.
- **A resting mouse no longer freezes it.** Auto-advance paused under the
  pointer, so a mouse left on the deck stopped the clock and the segment
  stood still. Now only keyboard focus inside pauses it (besides off screen
  and a background tab); anyone who wants it stopped has the pause button,
  and picking a card stops it as before.

## 0.16.0 — Highlights: "What I bring to a project"
A new widget, **Highlights**: the design's deck of short cards with story
segments, rethought before it was built.

- **It moves like a deck, not a carousel.** The design drew sheets under the
  card but slid it sideways like a scroller. Now:
  - The leaving card slides away the way you travel.
  - The next card's icon springs in with a slight turn, and its title and
    text follow one after the other.
  - The sheets underneath dip as if the top card was lifted off.
  - The counter rolls (the old number up and out, the new one in from
    below).
  - It all runs on Web Animations, on the compositor.
  - A first build overlapped the two texts in the middle of a change. Now
    the new card arrives only once the old one is nearly gone, checked
    frame by frame.
- **It never jumps.** Every card sits in one grid cell, so the deck is as
  tall as its longest card.
- **Story segments.**
  - The current one fills with the time left.
  - On hover a segment thickens and names its card.
  - Segments are tabs: arrows and Home/End choose a card, and focus follows.
- **Auto-advance (5.2 s a card) that behaves.**
  - It pauses under the pointer, while focused, off screen and in a
    background tab, and runs no frames then.
  - It stops for good once the visitor picks a card (button, segment,
    swipe or key).
  - A pause/play button beside the counter stops or restarts it
    (WCAG 2.2.2). Its place is kept before it appears, so showing it moves
    nothing (layout shift 0).
- **Phones and readers.**
  - A sideways swipe changes the card; the page still scrolls vertically.
  - Screen readers hear only the changes the visitor makes.
  - Reduced motion: nothing advances by itself, and a change is a short
    crossfade.
- **Editable:**
  - Cards: icon, tile, title, text.
  - Heading and its tag; counter; sheets.
  - Auto-advance, its time and the pause button.
  - Labels, width and position, and every colour.
- In the Elementor editor it never advances by itself, so the card you are
  editing stays put.

## 0.15.0 — Stats: the figure cards of Selected Work
A new widget, **Stats**, from the prototype approved by the site owner.

- **Counts up from zero** when the cards scroll into view, once.
  - The cards start one after another (140 ms apart) and each number slows
    as it lands (1.4 s).
  - Each figure's final width is held while counting, so nothing moves
    (layout shift 0.0000).
  - A row already on screen when the page opens keeps its numbers, and so
    does everything with reduced motion: nobody watches 106 turn into 0.
- **A drawn "+".** The font's plus is a text glyph: thin and small, it
  floated beside the 44px digits (owner's review). The drawn one has the
  digits' stem weight and rounded ends, is a third of their height, and its
  top sits on their cap height (measured with Inter: within a pixel at every
  size). It pops in as the number lands. Any other suffix (%, k, ×) is text,
  also on the cap height.
- **"Years since a year" counts itself:** "Years in the field" is this
  year − 2020, so it never goes stale. A detail line can say "since
  {since}".
- **The number follows the card:** 44px at the design's width, smaller in a
  narrow card. "106+" fits from 300px phones up (checked at 300, 320, 375,
  768 and 1280).
- **Hover:** the card lifts and its icon turns. On the dark card, a soft
  light follows the pointer. A card with a link shows ↗.
- **Screen readers** hear one sentence per card ("106+ Projects delivered"),
  never the counting.
- **Per card:** number or years-since, decimals, prefix and suffix,
  thousands separator, label, detail line, icon and tile, highlighted (dark),
  link.
- **Layout and style:** columns per breakpoint (default one per card), max
  width and position (the design is 470px, on the right), and the design's
  phone sizes on the site's breakpoints. The count-up can be switched off,
  and its duration and stagger set.

## 0.14.1 — Selected Work: every state of the filter tabs
Finished the way a segmented control behaves, from the site owner's review.

- **States.**
  - Hover (unselected): a soft tint fades in behind the tab, the text and its
    count darken.
  - Press: the tab sinks (0.97) and the tint deepens, before the thumb
    arrives.
  - Selected: no tint on hover; its thumb only lifts a little.
  - Pressing the selected tab: thumb and label give together (0.96), about
    their own centre.
  - Keyboard: an orange ring that follows the tab's corners.
  - Hover applies only with a real pointer, so it never sticks on a touch
    screen.
- The profile link's tile lifts and turns slightly on hover and sinks on
  press.
- Style → Filters now has Normal / Hover / Selected tabs, including hover
  and pressed backgrounds and hover text. Saved colours carry over.
- **Fix:** unselected tabs were black, not grey, and the Normal text colour
  had no effect. The widget's own button reset outranked them.
- **Fix:** a site's global button styles (Elementor Site Settings → Buttons,
  or a theme's button:hover) could restyle the filter tabs and the slider
  arrows. Every state of both is now protected. Tested against a deliberately
  loud kit style: background, padding, border, uppercase, serif.

## 0.14.0 — Selected Work: a projects library, and two width layers
From the site owner's review: the filters and controls must not share the
cards' width, and typing each project's filter into the widget was too
crude.

**Width, in two layers.** In the design, the filters and the controls sit on
the content column (1180px), while the cards slide out to the window's
edges and come to rest with the first card on that column. In a full-width
section, everything used to follow the widget.

- New: *Content column width* and *Column position* (per breakpoint) for the
  filter row and the control bar.
- New: *Cards run out to*: the window's edges (design), the widget's edges,
  or the column itself. In every mode the first card rests on the column, and
  so does the last one at the end. The old "Slide out" switch still counts:
  off means the widget's edges.

**A projects library** (Projects in the WordPress menu).

- Projects are kept like posts, with Project types (the filters).
  - Types are picked from a checklist and never typed.
  - A project can have several types and shows under each.
  - Each type has an order number among the filters.
- Each project's card is set on its own screen:
  - The featured image is the card's picture.
  - Category label.
  - Icon, picked from a grid of the site's own glyphs.
  - Text colour, and a gradient used when there is no picture.
  - Preview video, from the Media Library.
  - Link, with a new-tab option.
  - Featured.
- The list shows each card's picture, its types, the Featured star and its
  order.
- An empty library offers one button to add the design's eight projects and
  four types.
- In the widget, *Projects from* chooses the library, a list in the widget,
  or "auto". Auto, the default, uses the library once it has projects, so
  existing pages keep their list until then. Under the library you can
  limit to some types, order by number, featured first or newest, and set
  a maximum. The panel says how many projects and types there are, with
  links to edit them.
- Filters and their counts follow from the types of the projects shown.
- Saving a project or a type drops Elementor's cached widget HTML, so changes
  show at once.

## 0.13.1 — fix: the Elementor editor would not open
Reported from the live site: opening any page in Elementor ended in a fatal
error.

- **Status (0.13.0).** Elementor also asks the bare widget type, when it
  builds the editor's config, whether its output is dynamic. Status read
  its settings to answer, and a bare type has none, which is fatal on PHP 8.
  It now answers "no" for the bare type.
- **Content scene (since 0.10.0).** Two of its text controls shared a name
  with two switches ("Trends get a fast Reel", "Strong Reels get a carousel
  version"). Elementor kept the switches and dropped the texts: they gave
  "Cannot redeclare control" notices, could not be edited, and the trend
  and repurpose notifications showed "yes" instead of their text. The texts
  have their own names now. Nothing saved is lost; the switches keep their
  values.
- Selected Work's slide-out probe no longer throws when an optimiser such
  as WP Rocket or LiteSpeed delays inline scripts.
- **New tests.**
  - Build the editor config of every widget from a bare type, the exact path
    that failed.
  - Fail on any control declared twice.
  - Check the scene's notification texts.
  - Run against the old code, they fail for both bugs.

## 0.13.0 — Status and Section Tag
Two small widgets from the design, and one stylesheet for both.

- **Status**: the "Available for new projects" pill.
  - Three states, each with its own text and dot: available (green,
    pulsing), limited (amber), booked (grey).
  - An "available again on" date fills {date} ("Booked until Jun 3"). From
    that day on the pill reads available by itself, on the server and in
    the browser, so a cached page cannot keep an expired status.
  - Optional local time ("00:06 Tehran", any time zone, 24- or 12-hour). It
    is already on the first paint and is updated on each new minute.
  - Optional link, with an arrow that leans forward on hover.
  - Light glass skin, or dark for the footer. The pulse can be switched
    off, and stops with reduced motion.
  - The script loads only for a pill with a date or a clock; otherwise the
    pill is plain HTML.
- **Section Tag**: the pill above a section title (.tag in the design).
  - An icon tile (orange, navy, white) and an optional badge ("106+",
    "New").
  - An optional link, with the same arrow.
  - The HTML tag can be chosen (span by default, so the section title
    stays the heading).
  - Light or dark skin.
  - The tile springs up as the tag scrolls into view. This uses CSS
    scroll-driven animation with no script, is off with reduced motion,
    and browsers without that feature simply show the tag.
- Both have typography, height, colour and shadow controls, and alignment
  per breakpoint.

## 0.12.1 — Photo Stack: a video behind any photo
Asked for by the site owner: hovering the main photo plays a video.

- Each photo can have a video. It plays without sound while that photo is in
  front and the deck is open: on hover with a mouse, after a tap on a
  phone. Bringing another photo forward pauses the first video and plays
  the new photo's video, if it has one. Closing the deck pauses everything
  and shows the photos again.
- No black flash: the video fades in on its first frame, and until then the
  photo (or monogram) stays.
- Light: the src is set only when the deck comes within 300px of the screen,
  and only the first frames load then.
- While it plays, a thin ring round the caption icon fills with the video's
  time.
- Never with reduced motion or data saver: then nothing is downloaded or
  played.

## 0.12.0 — Photo Stack: the About photos, fanned and shuffled
A new widget, **Photo Stack**, from the prototype approved by the site owner,
with more on top.

- **Fans out** on hover (mouse) or tap (touch). The front photo is a button
  with aria-expanded: Enter opens it, Esc closes it. On a touch screen it
  closes with a tap outside, or when the deck scrolls out of view.
- **Any photo comes to the front** while fanned: click or tap it, swipe
  sideways on a phone, or press ← → on a keyboard. It arcs up over the deck
  while the old front dips under, like shuffling a real deck (Web
  Animations, on the compositor). Its caption then says what it shows
  ("On set · Behind the scenes, product shoot"), and screen readers hear
  "On set in front". The photo under the mouse rises a little, so it is
  clear it can be picked.
- **Never past the window's edge.** The fan is sized to the room that is
  left. On a phone, where there is little room, the front photo steps back a
  little so the side photos show more than a sliver.
- **Teaches itself.**
  - The first time the deck is seen, the photos part for a moment (once,
    and never with reduced motion).
  - The hint says what to do on that device (hover or tap, then click, or
    tap and swipe) and fades away once a photo has been brought forward.
- Depth under the mouse: the front photo follows the pointer slightly, and
  the others move against it.
- The resting deck is drawn by the stylesheet, so the first paint is final
  (layout shift 0.000–0.002), and without the script a hover still fans it.
- **In Elementor:**
  - 1–5 photos, each with a caption, a label while fanned, an icon, and a
    gradient or the monogram when there is no photo.
  - Monogram letters, with the accent letters set separately.
  - Sticker: emoji, icon, or none.
  - Each behaviour can be switched off, and every hint and label is
    editable.
  - Width, radius and sticker follow the site's breakpoints.
  - "Keep fanned in the editor" shows the photos behind while editing.
- Analytics: sz_stack_open, and sz_stack_photo with the photo's title and
  how it was chosen (click, tap, swipe, key).

## 0.11.1 — Selected work: filter switch without the hitch
The site owner felt a slight lag when the new list came in as the old one
left. Measured on a CPU slowed 4× (a mid-range laptop or phone): each click
held the page for 45 ms and the worst frame took 83 ms.

- The switch now animates with the Web Animations API, transform and opacity
  only, so it runs on the compositor and a busy page cannot make it stutter.
  It also starts in the same frame as the click; it used to wait two frames
  with the cards standing still.
- All measuring happens before any change, so one layout per switch. Before,
  a marker written on each card between measurements forced a style
  recalculation per card.
- Scrolling no longer rewrites every card's screen-reader name on every
  frame (now only when the filter changes), and position updates are
  coalesced to one per frame.
- The focus ring of the rail no longer uses a sibling selector that made
  every card restyle whenever one was shown or hidden.
- Leaving cards' glass blur is dropped for their quarter-second fade.

Result on the same slowed CPU: click 45 → 24 ms, worst frame 83 → 50 ms,
nothing recalculated at rest; rapid clicking ends clean (no leftover ghosts
or animations).

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
