#!/usr/bin/env bash
# One-time setup (safe to re-run): npm deps, edge-tts, and the Vazirmatn font
# for Chromium so Persian text renders cleanly in recordings.
set -euo pipefail
cd "$(dirname "$0")"
npm install --no-fund --no-audit
python3 -c "import edge_tts" 2>/dev/null || pip install edge-tts
mkdir -p ~/.local/share/fonts ~/.config/fontconfig
cp -u node_modules/vazirmatn/fonts/ttf/*.ttf ~/.local/share/fonts/
cat > ~/.config/fontconfig/fonts.conf <<'XML'
<?xml version="1.0"?>
<!DOCTYPE fontconfig SYSTEM "fonts.dtd">
<fontconfig>
  <match target="pattern">
    <test name="lang" compare="contains"><string>fa</string></test>
    <edit name="family" mode="prepend" binding="strong"><string>Vazirmatn</string></edit>
  </match>
  <alias><family>sans-serif</family><prefer><family>Vazirmatn</family></prefer></alias>
  <alias><family>Tahoma</family><prefer><family>Vazirmatn</family></prefer></alias>
</fontconfig>
XML
fc-cache -f >/dev/null
node -e "console.log('playwright', require('playwright/package.json').version)"
echo "setup ok"
