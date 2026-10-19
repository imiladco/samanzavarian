# Style defaults

Generates `includes/style-defaults.php`: the design's own values as the
defaults of every widget's Style tab (see `includes/Style_Defaults.php`).

Run `defaults.sh` from the test rig (a WordPress + Elementor site serving on
127.0.0.1:8899, with a page holding every widget at its defaults, and the
`szw_style_defaults` filter switchable by the `szw_rig_no_defaults` option):

1. `dumpctl.js` lists every style control from the editor (`controls.json`).
2. `measure.js` reads, with the defaults off, each control's value from the
   plugin's stylesheets, at desktop and phone width (`measured.json`).
3. `decide.py` keeps only the safe ones: a value the element sets itself,
   the same on every element the control reaches, not changed by a state or
   by a breakpoint other than Elementor's phone. A fluid size keeps its
   formula (`clamp()` in the custom unit). Others get a placeholder at most.
4. `snap.js` + `diff.py` compare the computed styles of every element with
   the defaults off and on; `pix.js` compares full-page screenshots. Both
   must come out clean.

Run it again after changing a widget's CSS or its Style controls.
