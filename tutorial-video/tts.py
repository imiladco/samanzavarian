"""One narration file per scenario step, with sentence timings for subtitles.

Usage: python3 tts.py <scenario>
Writes audio/<scenario>/<id>.mp3, <id>.json (sentence offsets) and
durations.json. Unchanged steps are reused (cache key: text + voice + rate).
"""
import asyncio, hashlib, json, os, re, ssl, subprocess, sys

import edge_tts
import edge_tts.communicate as _comm

ROOT = os.path.dirname(os.path.abspath(__file__))

# edge-tts pins certifi's CA list; trust the system/proxy bundle when one is set.
_ca = os.environ.get("SSL_CERT_FILE") or ("/root/.ccr/ca-bundle.crt" if os.path.exists("/root/.ccr/ca-bundle.crt") else None)
if _ca:
    _comm._SSL_CTX = ssl.create_default_context(cafile=_ca)


def load_steps(name):
    md = open(os.path.join(ROOT, "scenarios", name, "scenario.md"), encoding="utf-8").read()
    steps = []
    for block in re.split(r"^## ", md, flags=re.M)[1:]:
        sid = block.split("\n", 1)[0].split("·")[0].strip()
        m = re.search(r"\*\*گفتار:\*\*\s*([\s\S]*?)(?:\n\s*\n|\n\*\*|$)", block)
        if not m:
            sys.exit(f"step {sid} has no **گفتار:** line")
        steps.append((sid, re.sub(r"\s+", " ", m.group(1)).strip()))
    return steps


HARAKAT = re.compile("[\u064B-\u0652\u0670]")


def load_lexicon():
    f = os.path.join(ROOT, "branding", "pronunciation.json")
    if not os.path.exists(f):
        return []
    lex = {k: v for k, v in json.load(open(f, encoding="utf-8")).items() if not k.startswith("_")}
    return sorted(lex.items(), key=lambda kv: -len(kv[0]))


def spoken(text, lexicon):
    """Text for the voice: drops on-screen English labels written in
    parentheses, e.g. «نوشته‌ها» (Posts), which only the subtitles show; then
    applies the pronunciation lexicon (whole words/phrases, longest first)."""
    text = re.sub(r"\s*\([A-Za-z][^)]*\)", "", text)
    for word, say in lexicon:
        text = re.sub(rf"(?<![\w\u200c]){re.escape(word)}(?![\w\u200c])", say, text)
    return text


def split_sentences(text):
    return [s.strip() for s in re.findall(r"[^.!؟?]+[.!؟?]*", text) if s.strip()]


def duration(path):
    out = subprocess.run(["ffprobe", "-v", "error", "-show_entries", "format=duration",
                          "-of", "csv=p=0", path], capture_output=True, text=True, check=True)
    return round(float(out.stdout.strip()), 3)


async def synth(text, voice, rate, mp3):
    comm = edge_tts.Communicate(text, voice, rate=rate, boundary="SentenceBoundary")
    sentences = []
    with open(mp3, "wb") as f:
        async for chunk in comm.stream():
            if chunk["type"] == "audio":
                f.write(chunk["data"])
            elif chunk["type"] == "SentenceBoundary":
                sentences.append({"start": chunk["offset"] / 1e7,
                                  "end": (chunk["offset"] + chunk["duration"]) / 1e7,
                                  "text": chunk["text"]})
    return sentences


async def main(name):
    cfg = json.load(open(os.path.join(ROOT, "branding", "config.json"), encoding="utf-8"))
    voice, rate = cfg["voice"], cfg.get("rate", "+0%")
    out = os.path.join(ROOT, "audio", name)
    os.makedirs(out, exist_ok=True)
    lexicon = load_lexicon()
    durations = {}
    for sid, text in load_steps(name):
        say = spoken(text, lexicon)
        # Keyed on the shown text too, so subtitle wording changes are picked up.
        key = hashlib.sha1(f"{voice}|{rate}|{say}|{text}".encode()).hexdigest()
        mp3, meta = os.path.join(out, f"{sid}.mp3"), os.path.join(out, f"{sid}.json")
        cached = os.path.exists(meta) and json.load(open(meta, encoding="utf-8")).get("key") == key
        if not cached:
            sentences = await synth(say, voice, rate, mp3)
            # Subtitles show the scenario's own wording, not the respelled one.
            shown = split_sentences(text)
            for i, x in enumerate(sentences):
                x["text"] = shown[i] if len(shown) == len(sentences) else HARAKAT.sub("", x["text"])
            json.dump({"key": key, "text": text, "spoken": say, "sentences": sentences},
                      open(meta, "w", encoding="utf-8"), ensure_ascii=False, indent=2)
        durations[sid] = duration(mp3)
        print(f"{sid}: {durations[sid]:.2f}s{' (cached)' if cached else ''}")
    json.dump(durations, open(os.path.join(out, "durations.json"), "w"), indent=2)
    print(f"total narration: {sum(durations.values()):.1f}s")


if __name__ == "__main__":
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    asyncio.run(main(sys.argv[1]))
