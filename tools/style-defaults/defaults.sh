#!/bin/sh
# Regenerates includes/style-defaults.php: dump controls (editor), measure the design with the defaults off, decide.
set -e
cd "$(dirname "$0")/../site"
W="php ../wp-cli.phar --allow-root"
flush='delete_post_meta_by_key("_elementor_element_cache"); \Elementor\Plugin::$instance->files_manager->clear_cache();'
$W eval "update_option('szw_rig_no_defaults',1); $flush" >/dev/null 2>&1
cd ../rig
NODE_PATH=$(npm root -g) node dumpctl.js
NODE_PATH=$(npm root -g) node measure.js
NODE_PATH=$(npm root -g) node snap.js before >/dev/null
python3 decide.py
cd ../site && $W eval "delete_option('szw_rig_no_defaults'); $flush" >/dev/null 2>&1
cd ../rig && NODE_PATH=$(npm root -g) node snap.js after >/dev/null && python3 diff.py | tail -40
