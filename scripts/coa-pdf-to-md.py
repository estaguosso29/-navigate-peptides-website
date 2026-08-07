#!/usr/bin/env python3
"""Convert the compiled Azoth COA PDF to markdown.

    python3 scripts/coa-pdf-to-md.py docs/Azoth_Catalog_COAs_Compiled.pdf out.md

The document mixes three certificate formats plus a catalogue index, and — the
part that bites — the BioViridian certificates are FLAT IMAGES with no text
layer at all. Text extraction returns only the ion annotations drawn over their
plots, which reads exactly like a chromatogram continuation page. An earlier
version of this script silently dropped all eight of them and reported the
certificates as "missing from the file", which was wrong and acted upon.

So: every page must classify into a known kind, and image-only pages are
resolved from scripts/coa-image-page-transcriptions.json. A page that is
neither parseable nor transcribed is a hard error, never a silent omission.
"""
import hashlib
import json
import os
import re
import sys

import fitz

SRC = sys.argv[1]
OUT = sys.argv[2]
HERE = os.path.dirname(os.path.abspath(__file__))
TRANSCRIPTS = os.path.join(HERE, "coa-image-page-transcriptions.json")

doc = fitz.open(SRC)
tdata = json.load(open(TRANSCRIPTS))
TPAGES, COMMON = tdata["pages"], tdata["common"]

FREEDOM_LABELS = [
    "Client", "Accession #", "Search Code", "Received", "Reported", "Lot",
    "Sample Summary", "Product", "Purity", "Identity", "Net Content",
    "Appearance", "Endotoxin Threshold", "Microbial Analysis (PCR)",
    "Analytical Results", "Endotoxin Replicate 1", "Endotoxin Replicate 2",
    "Assay Sensitivity", "Microbial Analysis",
]


def lines_of(page):
    return [l.strip() for l in page.get_text().splitlines() if l.strip()]


def md_table(rows):
    rows = [[(c or "").replace("\n", " ").strip() for c in r] for r in rows]
    rows = [r for r in rows if any(r)]
    if not rows:
        return ""
    width = max(len(r) for r in rows)
    rows = [r + [""] * (width - len(r)) for r in rows]
    out = ["| " + " | ".join(rows[0]) + " |", "|" + "|".join(["---"] * width) + "|"]
    out += ["| " + " | ".join(r) + " |" for r in rows[1:]]
    return "\n".join(out)


def kv_table(pairs):
    pairs = [(k, v) for k, v in pairs if v]
    return md_table([["Field", "Value"]] + [[k, v] for k, v in pairs]) if pairs else ""


def field(lines, label):
    pat = re.compile(r"^\s*" + re.escape(label) + r"\s*:?\s*(.*)$", re.I)
    for i, line in enumerate(lines):
        m = pat.match(line)
        if not m:
            continue
        if m.group(1).strip():
            return m.group(1).strip()
        for nxt in lines[i + 1:]:
            if nxt and not nxt.rstrip().endswith(":"):
                return nxt.strip()
        return ""
    return ""


def freedom_fields(lines):
    """Label -> value lines, spanning to the next known label.

    Some certificates report per-vial results, so one label can own several
    value lines; reading only the next line drops vials 2..n.
    """
    norm = [re.sub(r"\s+", " ", l).strip().rstrip(":").strip() for l in lines]
    marks = []
    for i, n in enumerate(norm):
        for lab in FREEDOM_LABELS:
            if n.lower() == lab.lower() or n.lower().startswith(lab.lower() + ":"):
                marks.append((i, lab))
                break
        else:
            if lines[i].startswith("Method:"):
                marks.append((i, "__method__"))
    out = {}
    for k, (idx, lab) in enumerate(marks):
        end = marks[k + 1][0] if k + 1 < len(marks) else len(lines)
        inline = re.sub(r"^\s*" + re.escape(lab) + r"\s*:\s*", "", lines[idx], flags=re.I)
        vals = [inline.strip()] if inline.strip() and inline.strip() != lines[idx].strip() else []
        vals += [l.strip() for l in lines[idx + 1:end] if l.strip()]
        if lab == "__method__":
            out.setdefault("__methods__", []).append(lines[idx].strip())
        else:
            out.setdefault(lab, []).extend(v for v in vals if v)
    return out


def joined(f, label):
    return "<br>".join(f.get(label, []))


def analytical_table(f):
    vals = f.get("Analytical Results", [])
    if vals[:2] == ["Test", "Result"]:
        vals = vals[2:]
    pairs = [[vals[i], vals[i + 1]] for i in range(0, len(vals) - 1, 2)]
    return md_table([["Test", "Result"]] + pairs) if pairs else ""


def page_image_md5(page):
    """MD5 of the page's largest embedded image — identifies which certificate
    is on the page, so a re-ordered or replaced PDF cannot be paired with a
    stale transcription."""
    best = None
    for info in page.get_images(full=True):
        blob = doc.extract_image(info[0])["image"]
        if best is None or len(blob) > len(best):
            best = blob
    return hashlib.md5(best).hexdigest() if best else None


def image_coverage(page):
    """Fraction of the page covered by its largest image placement."""
    area = abs(page.rect.width * page.rect.height) or 1
    best = 0.0
    for info in page.get_images(full=True):
        for r in page.get_image_rects(info[0]):
            best = max(best, abs(r.width * r.height) / area)
    return best


def classify(page, lines):
    joined_txt = " ".join(lines).lower()
    if "certificate of analysis index" in joined_txt:
        return "index"
    if [l.lower() for l in lines[:3]] == ["#", "product", "certificate"]:
        return "index"
    if "freedomdiagnosticstesting" in joined_txt:
        return "freedom"
    if "certificate of analysis" in joined_txt and ("opt." in joined_txt or "optiq" in joined_txt):
        return "optiq"
    # No usable text layer. A page dominated by a bitmap is a scanned/flattened
    # certificate, NOT a decorative plot page — it must be transcribed.
    if image_coverage(page) > 0.5:
        return "image"
    return "unknown"


parts = [
    "# Azoth Catalog — Certificates of Analysis",
    "",
    f"Converted from `{os.path.basename(SRC)}` ({doc.page_count} pages) with "
    f"PyMuPDF {fitz.pymupdf_version}.",
    "",
    "Page numbers below refer to the source PDF. Deliberate omissions, all "
    "non-textual or exact duplicates of content kept elsewhere:",
    "",
    "- Chromatograms and mass-spectrometry plots (images in the source).",
    "- The Optiq \"RESULTS SUMMARY\" badge row, which restates the test-panel "
    "table verbatim.",
    "- Repeated per-page lab letterhead, page numbers and QR-code captions.",
    "",
    "The BioViridian certificates are flat images with no text layer; their "
    "values are transcribed in `scripts/coa-image-page-transcriptions.json` "
    "and marked below. Every other value is extracted from the PDF text layer.",
    "",
    "Lab names are given as they appear in the catalog index; that index "
    "abbreviates \"Freedom Diagnostics\" to \"Freedom\" on four rows, and those "
    "are shown here under the full name. Field labels are normalised to "
    "sentence case; all values are verbatim.",
    "",
]

cert_no = 0
disclaimer = optiq_disclaimer = optiq_contact = ""
problems = []
kinds = {}

for pno in range(doc.page_count):
    page = doc[pno]
    lines = lines_of(page)
    kind = classify(page, lines)
    kinds[pno + 1] = kind

    if kind == "index":
        tabs = page.find_tables().tables
        if tabs:
            if pno == 0:
                parts += [f"## {lines[0]}", ""]
                for l in lines[1:3]:
                    if "catalog items" in l.lower():
                        parts += [f"*{l}*", ""]
            parts += [md_table(tabs[0].extract()), ""]
        for l in lines:
            if l.lower().startswith("rows shaded"):
                parts += [f"> {l}", ""]
        continue

    if kind == "image":
        key = str(pno + 1)
        t = TPAGES.get(key)
        if not t:
            problems.append(f"p{pno + 1}: image-only page with no transcription "
                            f"in {os.path.basename(TRANSCRIPTS)}")
            continue
        actual = page_image_md5(page)
        if actual != t.get("image_md5"):
            problems.append(f"p{pno + 1}: image md5 {actual} does not match the "
                            f"transcription's {t.get('image_md5')} — the source PDF "
                            f"changed; re-check this transcription")
            continue
        cert_no += 1
        parts += [f"### {cert_no}. {t['identity']} — `{t['sample_name']}`  (PDF p{pno + 1})", ""]
        parts += ["> Transcribed from the page image — this certificate has no text layer.", ""]
        parts += [kv_table([
            ("Laboratory", t["lab"]),
            ("Web verification code", t["coa_code"]),
            ("Vendor", t["vendor"]),
            ("Sample name", t["sample_name"]),
            ("Sample type", t["sample_type"]),
            ("Lot number", t["lot"]),
            ("MW (g/mol)", t["mw"]),
            ("Sample received", t["received"]),
            ("COA issued", t["issued"]),
        ]), ""]
        parts += ["**Test results**", "", md_table([
            ["Specification", "Result", "Method", "Status"],
            [f"Identity: {t['identity']}", t["identity"], "LC-MS/MS", "Conforms"],
            ["Content", t["content"], "HPLC Quantitation", "Conforms"],
            ["Overall Purity", t["purity"], "RP-HPLC (214 nm)", "Conforms"],
            ["Endotoxin", t["endotoxin"], "<USP85>", "Conforms"],
        ]), ""]
        parts += [f"HPLC chromatogram retention time: {t['retention_time']}", ""]
        parts += ["**Methods**", ""] + [f"- {m}" for m in COMMON["methods"]] + [""]
        continue

    if kind == "freedom":
        cert_no += 1
        f = freedom_fields(lines)
        product = joined(f, "Product")
        ares = f.get("Analytical Results", [])
        identity = ares[ares.index("Identity (LC-MS)") + 1] if "Identity (LC-MS)" in ares else ""
        parts += [f"### {cert_no}. {identity or product} — `{product}`  (PDF p{pno + 1})", ""]
        parts += [kv_table([
            ("Laboratory", "Freedom Diagnostics"),
            ("Client", joined(f, "Client")),
            ("Accession #", joined(f, "Accession #")),
            ("Search Code", joined(f, "Search Code")),
            ("Received", joined(f, "Received")),
            ("Reported", joined(f, "Reported")),
            ("Lot", joined(f, "Lot")),
            ("Product", product),
            ("Identity", joined(f, "Identity")),
            ("Purity", joined(f, "Purity")),
            ("Net Content", joined(f, "Net Content")),
            ("Appearance", joined(f, "Appearance")),
            ("Endotoxin Threshold", joined(f, "Endotoxin Threshold")),
            ("Endotoxin Replicate 1", joined(f, "Endotoxin Replicate 1")),
            ("Endotoxin Replicate 2", joined(f, "Endotoxin Replicate 2")),
            ("Assay Sensitivity", joined(f, "Assay Sensitivity")),
            ("Microbial Analysis (PCR)", joined(f, "Microbial Analysis (PCR)")),
        ]), ""]
        at = analytical_table(f)
        if at:
            parts += ["**Analytical results**", "", at, ""]
        methods = f.get("__methods__", [])
        if methods:
            parts += ["**Methods**", ""] + [f"- {m}" for m in methods] + [""]
        if not disclaimer:
            idx = next((i for i, l in enumerate(lines)
                        if l.startswith("The peptide purity analysis")), None)
            if idx is not None:
                disclaimer = " ".join(lines[idx:])
        continue

    if kind == "optiq":
        cert_no += 1
        tested = field(lines, "TESTED PEPTIDE")
        sample = field(lines, "Sample Name")
        if tested.endswith(" +"):
            follow = lines[lines.index(next(l for l in lines if "TESTED PEPTIDE" in l)) + 1]
            title = f"{tested} {follow}".strip()
        else:
            title = tested
        parts += [f"### {cert_no}. {title} — `{sample}`  (PDF p{pno + 1})", ""]
        parts += [kv_table([
            ("Laboratory", "Optiq Health Labs"),
            ("COA UID", field(lines, "COA UID")),
            ("Issued", field(lines, "Issued")),
            ("Sample Name", sample),
            ("Sample ID", field(lines, "Sample ID")),
            ("Client", field(lines, "Client")),
            ("Sample Matrix", field(lines, "Sample Matrix")),
            ("Tested peptide", title),
            ("Intended Use", field(lines, "Intended Use")),
            ("Received Date", field(lines, "Received Date")),
            ("Report Date", field(lines, "Report Date")),
            ("Purity", field(lines, "Purity")),
            ("Analyst", field(lines, "Analyst")),
            ("Analyst sign-off date", field(lines, "Date")),
        ]), ""]
        for t in page.find_tables().tables:
            ext = t.extract()
            if ext and "TEST PANEL" in " ".join(str(c) for c in ext[0]).upper():
                parts += ["**Test panel**", "", md_table(ext), ""]
        if not optiq_disclaimer:
            idx = next((i for i, l in enumerate(lines) if l.startswith("ND = Not Detected")), None)
            if idx is not None:
                end = next((j for j in range(idx, len(lines)) if "info@optiq" in lines[j]), len(lines))
                optiq_disclaimer = " ".join(lines[idx:end])
                optiq_contact = " · ".join(lines[end:])
        continue

    problems.append(f"p{pno + 1}: unrecognised page kind — {len(lines)} text lines, "
                    f"image coverage {image_coverage(page):.0%}")

parts += ["---", "", "## Standard laboratory disclaimers", ""]
if disclaimer:
    parts += ["Appears on every Freedom Diagnostics certificate in this document:", "",
              "> " + disclaimer, ""]
if optiq_disclaimer:
    parts += ["Appears on every Optiq Health Labs certificate in this document:", "",
              "> " + optiq_disclaimer, "",
              f"Optiq Health Labs contact block: {optiq_contact}", ""]
parts += ["Appears on every BioViridian certificate in this document:", "",
          "> " + COMMON["certification"], "",
          f"Signed: {COMMON['signatory']} · Verification: {COMMON['verification_url']}", ""]

if problems:
    sys.stderr.write("REFUSING TO WRITE — unresolved pages:\n")
    for p in problems:
        sys.stderr.write(f"  - {p}\n")
    sys.exit(1)

open(OUT, "w").write("\n".join(parts) + "\n")
counts = {k: sum(1 for v in kinds.values() if v == k) for k in set(kinds.values())}
print(f"wrote {OUT}: {cert_no} certificates from {doc.page_count} pages")
print("  page kinds:", ", ".join(f"{k}={v}" for k, v in sorted(counts.items())))
