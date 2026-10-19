# Changelog

Every change that ships bumps the version in `samanzavarian-elementor-widgets.php`
(header `Version:` and `SZW_VERSION`, which also busts the asset cache) and adds
an entry here. `tests/process-test.php` fails if the three disagree.

## 0.26.0 — Reply Promise, a livelier Reach, the needs in one row
**Reply Promise (new widget).** "Replies within two business days", made
concrete: the line under it names the day ("Write today, hear back by
Wednesday, 21 October"), counted in working days in your time zone, skipping
your days off, the same count as the Project Form's confirmation. Worked out
on the server (no jump on load) and again in the browser (cached pages stay
right). The clock in its tile sweeps once on arrival and under the pointer.
Pill or plain look, alignment.

**Reach hover, rethought.** One white card now glides from row to row with
the pointer, so the list feels like one object following you instead of
rows popping on their own. The other rows step back; the row's tile fills
with its channel's colour (with a coloured glow and a small nod) and the
arrow opens into the action: "Message", "Connect", "Call", "Visit".
Keyboard focus does the same; touch keeps a simple press.

**Project Form: the needs stay in one row**, as in the design, on phones
too: the tiles tighten (smaller icon and words) instead of going two by
two. Only below 250px, or with more tiles than fit, do they wrap.

## 0.25.0 — Telegram to several chats, clearer settings, a new form and Reach
**Telegram: as many chats as you like.** One bot, sent to you, a teammate,
a group or a channel. Settings → Telegram is now one list:

- **Find chats** lists everyone who has written to the bot (newest first,
  with person / group / channel) and adds one with a click. A chat already
  listed without a name gets its name; **Add by id** covers the rest.
- Each chat has its own **Test**, an on/off switch (pause without losing it)
  and a name only you see. Find and Test work before saving, with the token
  as typed.
- The token is checked with Telegram before it is saved; a wrong one is
  refused with the reason, a right one shows the bot's @name.
- Sending goes to every chat that is on. If one refuses, the others still
  get it; the retry only goes to the chat that missed it (nobody gets the
  same request twice), and the request shows delivery chat by chat.
- Telegram's errors are said as what to do ("A person has to press Start in
  the bot first", "make it an admin of the channel").
- The single chat of earlier versions becomes the first in the list.

**Reply buttons (was "Reply templates").** Says plainly that these are
shortcuts, not connections: "Reply by email" opens your own mail app,
"WhatsApp" opens your own WhatsApp on their number; nothing is sent from the
site. Each text has a live preview of how it opens, on a sample request.

**Project Form, redesigned.** Needs are a tray of icon tiles: a picked tile
lifts out as a white card, its icon warms to orange with a little nod, a
check pops on its corner, and "2 selected" rolls beside the question. On a
phone the tray is two by two, icon beside the words. Each tile has an icon
control (older tiles get one by their name). Timing, budget and the link
wait behind **More details**, which opens in place and, closed, says what
was picked inside; a wrong link on sending opens it. A field filled in right
gets a small green tick. The message counter is always there, quietly.

**Reach, in the same language.** A soft tray; icons in white tiles in each
channel's colour; a row lifts out as a white card under the pointer, its
tile takes the channel's tint and the arrow moves on. The rows come in one
after another the first time the list scrolls into view.

## 0.24.0 — Requests panel in English
The whole panel now reads in English, left to right, whatever the admin
language: menu **Requests**, Overview / Requests / Settings, statuses New,
Talking, Won, Lost, Spam.

- Inter instead of Vazirmatn (the Persian font files are gone, the zip is lighter).
- Dates and numbers in English ("19 Oct 2026, 14:32", "5 min ago"), in the
  site's own time zone.
- Every physical side flipped: switch knob, input units, unread marker,
  select arrows and padding (selects now draw their own chevron); the header
  no longer scrolls sideways; the Delete card keeps its button by its text.
- Events logged before this version in Persian are shown in English.
- Reply templates, errors and Telegram's "Open in WordPress" link in English.

## 0.23.0 — Requests panel, built on the admin kit
The admin side of the project form, rebuilt from the "admin kit" (Persian,
right to left, Vazirmatn) in the site's own colours: navy for actions,
orange as the accent. One menu, **درخواست‌ها**, with three pages:

- **Overview:** health first (Telegram not connected, the last message not
  delivered, WordPress cron off, no Project Form on any page), each with
  its fix; then 7 / 30 / 90 days against the period before: requests,
  waiting for an answer (and how long the oldest has waited), **response
  time** (median, from arrival to first answer), contracts and the win
  rate; a daily chart of answered against waiting; statuses; what people
  need, their budgets and timelines, each linking to the filtered list.
- **Requests:** views with counts (new, talking, contract, lost, spam),
  search by name, email, phone or message (key /), filters by need, budget
  and time, bulk actions, unanswered rows marked, CSV export, and a notice
  when a new request arrives while the list is open.
- **One request:** the message large and readable with its needs, timeline
  and budget; **reply by email** (a greeting and their message quoted) or
  **WhatsApp** in one click, from your own templates; call; copy a summary;
  "I replied" moves it on and times the answer; status, a private note,
  its Telegram delivery (resend), and every event with who did it.
- **Settings:** Telegram (token kept apart and never shown again; may live
  in wp-config as SZW_TELEGRAM_TOKEN), a four-step setup with "Find my
  chat" and "Test message"; reply templates; how long closed requests and
  spam are kept. **System status:** Telegram, the form, the data and the
  server, with a connection test that sends nothing.

Under it: the request pipeline is now new → talking → contract | lost
(earlier "replied" and "archived" read as talking and lost), with an event
log, a note, the first-answer time and the closing time. Telegram's
"Open in WordPress" opens the request in the panel; old admin links
redirect there; the earlier Telegram settings move in by themselves.

The panel needs PHP 8.1. On older PHP it is not loaded at all and the
plain WordPress list from 0.22 serves instead, so nothing breaks.

## 0.22.0 — Project Form, Reach, Requests in the admin, and Telegram
The contact section's two halves as widgets, and somewhere for what the
form sends to go. Rethought before it was built:

**Project Form**
- **Needs you can combine.** "What do you need?" is a row of chips you can
  pick several of (a project is often AI and editing), instead of a
  one-choice switch that turned into a 2×2 grid on phones.
- **Phone (optional), timeline and budget** as optional one-tap chips; tap
  a chip again to clear it. Each can be turned off; the chips are your own.
- **The message box helps:** examples take turns while it is empty; the
  counter appears only near the limit.
- **Nothing is lost:** what is typed is kept in the browser as a draft until
  it is sent ("Your draft is back · Clear", in the place of the privacy
  note so nothing moves).
- **Mistakes in words** beside the fields, on leaving a field or sending;
  focus goes to the first one.
- **Sent:** the form gives way to a confirmation with a drawn tick, the
  visitor's name, the actual day of the reply ("I'll reply by Wednesday,
  October 21", working days and days off in your time zone) and a summary
  of what they asked for.
- **No captcha:** a field only bots fill, a minimum time, a few requests per
  hour per address, the same request counted once, and messages full of
  links kept aside as spam.
- The Quote & Reply widget's quick replies fill it (message and need).

**Reach**
- Any mix of Email, WhatsApp, Telegram, LinkedIn, Instagram, phone or a
  link: type the address, number or username and the row builds its link,
  icon and grey line.
- WhatsApp opens with a first message already written.
- **"Online"** beside WhatsApp and Telegram in your working hours (worked
  out in the browser, so cached pages stay right), never a grey "offline".
- **Email:** Copy, and on a computer the whole row copies (a mail app is
  often missing there); the row confirms "✓ Copied".

**Requests (admin)**
- Every request is kept in WordPress, private, under **Requests**: the menu
  counts the new ones; the list filters New / Replied / Archived / Spam and
  exports CSV (cells that would run as formulas neutralised).
- One request on one screen: the message, the details, **Reply by email**
  (with a greeting and their message quoted), **Reply on WhatsApp**,
  **Call**, and Mark replied / Archive.
- Archived requests can delete themselves after a number of days; spam goes
  after 30.

**Telegram**
- Requests → Settings: paste your bot's token, press **Find my chat** (after
  pressing Start in your bot) and **Send a test**. The token is never shown
  again.
- Each new request arrives with who, what, timeline and budget, the message,
  and buttons to open it in WordPress or answer on WhatsApp. It is sent
  after the visitor has their answer, so a slow Telegram never slows the
  form; if Telegram does not take it, it is tried again (2, 4, 8 minutes)
  and the request says what happened.

Also: four new icons (Telegram, Instagram, phone, link).

## 0.21.0 — Toolkit: education and tools, under the career
A new widget, **Toolkit**: the design's card of Education, "Editing and
finishing" and "AI image and video", one row each.

- **Rows of two kinds:** two lines of text (the degree, then where; years,
  if given, join the second line after a dot), or tools as pills with the
  row's colour as their dot. Add, remove or reorder rows freely.
- **How many:** a row of pills can show its count right after its label
  (on for the AI row: 8).
- **Used every day:** end a tool's line with * and its pill says "daily"
  after a hairline, in words rather than a symbol to decode.
- **Honest affordance:** the pills are not buttons, so they do not react to
  the pointer.
- **The arrival:** the first time the card scrolls into view the rows rise
  in turn, each tile springs in and its pills follow in a quick cascade;
  then the card is plain again. On screen at load or with reduced motion it
  is simply there.
- **Phones:** below 700px of card the label sits above its content.

## 0.20.0 — Career Timeline: a timeline you work, not a picture of one
0.19.0 rebuilt the box properly but it still looked like the design. This
release changes what you see and what you can do with it:

- **The playhead is the way through the career.** A white playhead stands
  on the selected role's first frame and glides to each role you pick.
  **Drag it** (or drag along the ruler) to scrub: the card follows the
  role under it as it passes and the flag reads the month ("Aug 2023");
  let go and it settles on that role. It is a keyboard slider too.
- **Play.** A Play button in the bar runs the career like a reel: the
  playhead plays through every role, equal time each so a short role is as
  readable as a long one, and the card follows; on a phone the timeline
  pans along. Picking anything stops it; it waits off screen and in a
  background tab. (Can be turned off.)
- **Clips look like clips.** Each one carries the role's icon as its first
  frame and an audio waveform underneath (fixed per company, as long as
  the role); the tracks get an editor's V1 / V2 headers, pinned while the
  timeline scrolls under them.
- **Skimming:** with a mouse, a quiet line follows the pointer over the
  timeline with the month it is on.
- **"Now" is a fixed line** (dashed, orange, with its recording light);
  it steps aside when the playhead stands on it. On arrival it is "Now"
  that runs from the first role to today laying the clips down, then the
  playhead drops in.
- **How long:** the card shows each role's length beside its dates
  ("2 yrs", "1 yr 2 mo", "10 mo").

## 0.19.0 — Career Timeline: the career as an edit
A new widget, **Career Timeline**: one clip per role on an editor's
timeline, a "Now" playhead, and an inspector card above it showing the
selected role. Rethought before it was built:

- **Dates, not hand placement.** Type "2022" or "2022-03" (an empty End
  means now). Clips are placed on the ruler from the dates and packed into
  as few lanes as overlapping roles need, with the longest unbroken run on
  the top lane; nobody assigns tracks. The clip labels write themselves
  ("2022 to 2023", "2026 to now", "Before 2022"), and any of them can be
  overridden.
- **Earlier years grouped:** years before 2022 (adjustable, or off) share
  one hatched "Before 2022" stretch of the ruler, as in the design. Quarter
  ticks along the ruler make it read as an editor's.
- **Nothing jumps.** Every role's inspector is in the page (indexable,
  readable without script) and all share one cell, so the card keeps its
  height through every role and the timeline below never moves (layout
  shift 0; it did in the design).
- **Switching roles:** the old inspector slides out against the direction
  of travel, the new one comes in from it, its icon springing in first.
  The selected clip lights up with an editor's trim handles at both ends,
  and the timeline scrolls it to the middle.
- **The arrival:** the first time the box scrolls into view, the playhead
  plays from the first role to "Now" and lays each clip down as it passes;
  on a phone the timeline pans along with it. The "Now" flag carries a
  recording light that beats three times, then rests. On screen at load,
  with reduced motion or in the editor, it is simply there.
- **Phones:** the edges fade only where there is more to scroll; the swipe
  hint leaves once the visitor has swiped.
- **Keyboard and screen readers:** the clips are tabs (arrows, Home, End),
  the bar's arrows step through roles by date and dim at either end without
  losing focus, the counter is announced.
- **In the editor,** picking a role in the panel selects it in the preview.

## 0.18.2 — Icon words: the pill, measured and set right
The pills' spacing, measured in the browser and redrawn:

- **The tile sits concentric in its pill.** Before, its inset was uneven:
  6.3px above, 5.7 below, but only 4.8 on the left (3.3 and 3.0 on phones),
  and a rounded square in a fully round capsule left the tile's corners
  crowding the curve. Now one inset (.2em) on three sides and the pill's
  corner = the tile's corner + that inset, so the two curves run parallel.
  Everything is in em, so it holds at every size (no phone override).
- **The text side gets about twice the inset,** so the pill reads tile-then-word.
- **A shorter pill** (1.44em instead of 1.65em, in a 1.8 line): pills on
  consecutive lines keep clear air between them.
- **Punctuation right after a pill tucks into its padding:** "video." no
  longer reads "video .". On the server and in the editor preview.

## 0.18.1 — Words with icons in Elementor 4's Paragraph and Heading
The icon codes now work in Elementor 4's own **Paragraph** and **Heading**
widgets too (0.18.0 only knew the classic Text Editor and Heading), so
`{cam:photography}` in a Paragraph becomes its pill.

- **`sz-statement`** works where Elementor 4 puts the class: on the
  paragraph itself, not on a widget wrapper.
- **In the editor,** the pills show in the canvas and update after every
  change. While you edit the text in place, it stays as typed (the codes
  visible, the cursor never disturbed) and turns back into pills when you
  click away.
- The arrival (pills lighting up in reading order) plays for these widgets
  as well.

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
