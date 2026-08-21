#!/usr/bin/env python3
"""Split the compiled COA PDF into one file per product, and prove each is right.

    python3 scripts/coa-split-certificates.py <compiled.pdf> <out-dir>

Only slug -> page is hand-authored below. Everything used to VERIFY a split
file (sample name, lot, purity, lab) is derived from the generated markdown,
so a wrong page number cannot pass silently: every output is re-opened and
must contain the certificate its filename claims.

Freedom/Optiq pages carry a text layer and are checked on sample name + lot +
purity. The BioViridian pages are flat images with no text, so they are checked
on the md5 of the page image against scripts/coa-image-page-transcriptions.json
(the same pin the markdown converter uses).

Output filenames use OUR product slug, coded for the GLP compounds, so no
supplier name and no real GLP compound name ever appears in a public URL.
"""
import glob
import hashlib
import json
import os
import re
import sys

import fitz

SRC, OUT_DIR = sys.argv[1], sys.argv[2]
HERE = os.path.dirname(os.path.abspath(__file__))
MD = os.path.join(HERE, "..", "docs", "Azoth_Catalog_COAs_Compiled.md")
TRANSCRIPTS = os.path.join(HERE, "coa-image-page-transcriptions.json")

# product slug -> (source page, output filename stem)
#
# Selection rule (client 2026-08-10): the certificate must be for the same
# COMPOUND; where that compound also has a certificate for a size we actually
# sell, that exact match wins. Compound-only fallbacks, because no certificate
# exists for the size sold: bpc-157-tb-500 (5mg sold, 10/10mg certified),
# epithalon (10mg sold, 50mg certified), ghk-cu (20mg sold, 100mg certified),
# glp-1-s (2/5/15mg sold, 10mg certified).
MAPPING = {
    # existing catalogue
    "mots-c":              (3,  "coa-mots-c"),
    "epithalon":           (8,  "coa-epithalon"),
    "tesamorelin":         (13, "coa-tesamorelin"),
    "bpc-157":             (15, "coa-bpc-157"),        # 10mg sold, 10mg certified
    "bpc-157-tb-500":      (17, "coa-bpc-157-tb-500"),
    "nad":                 (22, "coa-nad"),
    "ghk-cu":              (26, "coa-ghk-cu"),
    "kpv":                 (29, "coa-kpv"),
    "tirzepatide":         (34, "coa-glp-1-t"),   # coded filename; 10mg sold, TRIZ 10 = 10mg
    "retatrutide":         (38, "coa-glp-3-r"),   # coded filename; 10mg sold, 10mg certified
    # imported catalogue
    "ss-31":               (5,  "coa-ss-31"),
    "aod-9604":            (6,  "coa-aod-9604"),
    "cjc-1295-ipamorelin": (7,  "coa-cjc-1295-ipamorelin"),
    "ghrp-6":              (9,  "coa-ghrp-6"),
    "igf-1-lr3":           (10, "coa-igf-1-lr3"),
    "ipamorelin":          (11, "coa-ipamorelin"),
    "sermorelin":          (12, "coa-sermorelin"),
    "tb-500":              (18, "coa-tb-500"),
    "5-amino-1mq":         (19, "coa-5-amino-1mq"),
    "n-acetyl-selank":     (20, "coa-n-acetyl-selank"),
    "n-acetyl-semax":      (21, "coa-n-acetyl-semax"),
    "kisspeptin":          (24, "coa-kisspeptin"),
    "pt-141":              (25, "coa-pt-141"),
    "glow":                (27, "coa-glow"),
    "klow":                (28, "coa-klow"),
    "melanotan-1":         (31, "coa-melanotan-1"),
    "melanotan-2":         (32, "coa-melanotan-2"),
    "dsip":                (33, "coa-dsip"),
    "glp-1-s":             (37, "coa-glp-1-s"),
}


def facts_from_markdown():
    """page -> {sample, lot, purity, lab, title} parsed from the converted doc."""
    md = open(MD).read()
    out = {}
    for sec in re.split(r"^### ", md, flags=re.M)[1:]:
        head = sec.splitlines()[0]
        m = re.match(r"\d+\.\s+(.*?)\s+—\s+`(.*?)`\s+\(PDF p(\d+)\)", head)
        if not m:
            continue
        title, sample, page = m.group(1), m.group(2), int(m.group(3))

        def field(*names):
            for n in names:
                f = re.search(rf"^\| {re.escape(n)} \| (.+?) \|$", sec, re.M)
                if f:
                    return f.group(1).strip()
            return ""

        out[page] = {
            "title": title, "sample": sample,
            "lot": field("Lot", "Lot number", "COA UID"),
            "purity": field("Purity", "Overall Purity"),
            "lab": field("Laboratory"),
        }
    return out


# Certificates whose printed compound legitimately differs from our product
# name. Each needs a reason: without this list the identity check below would
# either reject a correct pairing or, if made loose enough to accept them,
# stop catching genuine mismatches.
IDENTITY_ALIASES = {
    "epithalon":           "source prints the variant spelling 'Epitalon'",
    "igf-1-lr3":           "source prints 'IGF-LR3' without the 1",
    "cjc-1295-ipamorelin": "source has a typo: 'CJC-1925' (see docs); Ipamorelin still matches",
    "melanotan-1":         "source uses roman numerals: 'Melanotan-I'",
    "melanotan-2":         "source uses roman numerals: 'Melanotan-II'",
    "glow":                "blend: identity lists components, sample name is 'GLOW'",
    "klow":                "blend: identity lists components, sample name is 'KLOW'",
    "bpc-157-tb-500":      "blend: source orders it 'TB-500/BPC-157'",
    "glp-1-s":             "lab already codes this one: identity 'GLP SM', sample 'GLP-1 S'",
}


def tokens(text):
    text = text.lower().replace("-i ", "-1 ").replace("-ii", "-2")
    text = re.sub(r"\bi\b", "1", re.sub(r"\bii\b", "2", text))
    return {t for t in re.split(r"[^a-z0-9]+", text) if t and t not in
            {"mg", "iu", "the", "and"}}


def identity_matches(slug, fact):
    """Does this certificate actually belong to this product?

    Compares the product slug against the compound the certificate PRINTS,
    independently of the page number that was authored — so mapping a product
    to another compound's page is caught even when nothing is duplicated."""
    want = tokens(slug)
    got = tokens(fact["title"] + " " + fact["sample"])
    if want & got:
        return True, ""
    if slug in IDENTITY_ALIASES:
        return True, IDENTITY_ALIASES[slug]
    return False, f"slug tokens {sorted(want)} share nothing with certificate {sorted(got)}"


FACTS = facts_from_markdown()
TRANS = json.load(open(TRANSCRIPTS))["pages"]
doc = fitz.open(SRC)
os.makedirs(OUT_DIR, exist_ok=True)


def page_image_md5(owner, page):
    """md5 of the page's largest embedded image. `owner` must be the document
    the page belongs to — image xrefs are per-document, so resolving a split
    file's page against the source document raises 'not an image'."""
    best = None
    for info in page.get_images(full=True):
        blob = owner.extract_image(info[0])["image"]
        if best is None or len(blob) > len(best):
            best = blob
    return hashlib.md5(best).hexdigest() if best else None


problems, rows = [], []
seen_pages = {}

for slug, (page_no, stem) in MAPPING.items():
    if page_no in seen_pages:
        problems.append(f"{slug}: page {page_no} already used by {seen_pages[page_no]}")
        continue
    seen_pages[page_no] = slug
    if page_no not in FACTS:
        problems.append(f"{slug}: page {page_no} is not a certificate page")
        continue
    f = FACTS[page_no]

    ok, note = identity_matches(slug, f)
    if not ok:
        problems.append(f"{slug}: page {page_no} is a {f['title']!r} certificate — {note}")
        continue
    alias_note = note

    out_path = os.path.join(OUT_DIR, f"{stem}.pdf")
    single = fitz.open()
    single.insert_pdf(doc, from_page=page_no - 1, to_page=page_no - 1)
    # Fixed metadata dates: PyMuPDF otherwise stamps the current time, making
    # every run produce different bytes for identical content. The uploader
    # compares file contents to decide what to re-upload, so non-determinism
    # would rewrite all 29 certificates on every run and bury a real change
    # in the noise.
    single.set_metadata({"producer": "navigate-peptides coa-split",
                         "creationDate": "D:20260810000000Z",
                         "modDate": "D:20260810000000Z"})
    single.save(out_path, garbage=4, deflate=True)
    single.close()

    # PyMuPDF also writes a random /ID pair into the trailer on every save, so
    # fixing the metadata dates alone still produced different bytes each run.
    # The uploader compares file contents to decide what to re-upload, so that
    # made every run replace all 29 files and would bury a genuine change.
    # Rewrite /ID to a value derived from the file itself (identifier only —
    # it has no bearing on the rendered certificate).
    raw = open(out_path, "rb").read()
    # A /ID element may be a hex string <..> or a literal string (..);
    # matching only the hex form left some files un-pinned and random.
    _idpart = rb"(?:<[0-9A-Fa-f]*>|\((?:\\.|[^)\\])*\))"
    m = re.search(rb"/ID\s*\[\s*" + _idpart + rb"\s*" + _idpart + rb"\s*\]", raw)
    if m:
        body = raw[:m.start()] + raw[m.end():]
        tag = hashlib.md5(body).hexdigest().upper().encode()
        raw = raw[:m.start()] + b"/ID[<" + tag + b"><" + tag + b">]" + raw[m.end():]
        open(out_path, "wb").write(raw)

    # --- verify the artefact, not the intent -------------------------------
    chk = fitz.open(out_path)
    if chk.page_count != 1:
        problems.append(f"{slug}: {chk.page_count} pages, expected 1")
    text = re.sub(r"\s+", " ", chk[0].get_text())

    if str(page_no) in TRANS:                      # image certificate
        want = TRANS[str(page_no)]["image_md5"]
        got = page_image_md5(chk, chk[0])
        if got != want:
            problems.append(f"{slug}: image md5 {got} != expected {want}")
        source = "image+md5"
    else:                                          # text certificate
        for label, value in (("sample", f["sample"]), ("lot", f["lot"]),
                             ("purity", f["purity"])):
            probe = value.split("<br>")[0].strip()
            if probe and probe not in re.sub(r"\s+", " ", text):
                problems.append(f"{slug}: {label} {probe!r} not found in {stem}.pdf")
        source = "text"
    chk.close()

    rows.append((slug, page_no, stem, f["title"], f["sample"], f["lot"],
                 f["purity"], f["lab"], source, os.path.getsize(out_path), alias_note))

unused = sorted(set(FACTS) - set(seen_pages))

print(f"{'product':<22}{'p':>3}  {'file':<26}{'certificate':<26}{'lot':<18}{'purity':<9}{'check':<10}{'KB':>5}")
print("-" * 124)
for r in sorted(rows, key=lambda r: r[0]):
    print(f"{r[0]:<22}{r[1]:>3}  {r[2] + '.pdf':<26}{(r[3] + ' / ' + r[4])[:25]:<26}"
          f"{r[5][:17]:<18}{r[6][:8]:<9}{r[8]:<10}{r[9] // 1024:>5}")
print(f"\nsplit {len(rows)} certificates; {len(unused)} unused pages: {unused}")

MANIFEST = os.path.join(OUT_DIR, "manifest.json")
json.dump({r[0]: r[2] + ".pdf" for r in rows}, open(MANIFEST, "w"), indent=2, sort_keys=True)
print(f"manifest: {MANIFEST}")

if problems:
    # Discard the whole output directory: a partially-correct split left on
    # disk can be mistaken for a good one by a later run or a manual upload.
    for f in glob.glob(os.path.join(OUT_DIR, "*.pdf")) + [MANIFEST]:
        try:
            os.remove(f)
        except OSError:
            pass
    print("\nREFUSING — verification failed (output discarded):")
    for p in problems:
        print("  ✗", p)
    sys.exit(1)
print("all files verified against the source certificate")
