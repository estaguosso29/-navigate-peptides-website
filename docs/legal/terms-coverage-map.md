# Terms & Conditions — processor template coverage map

Maps every section of the processor-supplied *Terms and Conditions of
Purchase* template onto the Navigate Peptides Terms of Service
(`terms-of-service.html`, published at `/terms`, page ID 29).

Purpose: the template arrived as a fill-in-the-blank skeleton
(`[Merchant Name]`, `MerchantWebsite.com`, `[insert]`, and two
"*to be inserted based on merchant's…*" slots). This table lets a
reviewer confirm that nothing in it was dropped, and shows exactly
which clauses were reworded and why.

**Every template section is covered.** Four were reworded; the reasons
are compliance-driven and are set out under the table.

| # | Template section | Lands in | Treatment |
|---|---|---|---|
| 1 | Your Use of Our Web Site | §1 Acceptance, §9 Acceptable use, §10 IP | Adapted — download/print permission reframed from "personal, non-commercial use" to "internal, non-commercial research use" ⚠️A |
| 2 | ACH payments payable to `[Merchant Name]` | §6 | Placeholder filled: **Navigate Peptides** |
| 3 | ACH Debit Authorization for Order Payment | §6 | Near-verbatim; initiating party substituted |
| 4 | ACH Debit/Credit Authorization for Micro-Entries | §6 | Near-verbatim; initiating party substituted |
| 5 | Use of Information | §5 ¶2 | Verbatim in substance |
| 6 | Product and Sale Limitations *(slot)* | §3 | **Filled with the client's supplied text, verbatim** |
| 7 | Refund and Return Policy *(slot)* | §8 | **Filled from the live `/refund-policy` page, reproduced in full** |
| 8 | Trade-Marks and Other IP Rights | §10 | Adapted — no "registered trademark" assertion ⚠️B |
| 9 | Indemnity | §17 | Merged; template's vendor/payments-provider list added |
| 10 | Links/Software | §12 | New section, substance retained |
| 11 | Availability of Our Web Site | §13 | Adapted — audience clause rewritten ⚠️C |
| 12 | Information You Provide | §11 | New section, substance retained |
| 13 | Disclaimer of Warranties | §15 | Merged; template's technical-failure and virus language added |
| 14 | Agreement to Abide by All Applicable Laws | §3 ¶2, §9, §21 | Covered in three places |
| 15 | Relationship between merchant and users | §20 | New section, verbatim in substance |
| 16 | Governing Law and Jurisdiction `[insert]` | §21 | Ours retained — already names California/Orange County and adds venue, jury waiver, 1-year limitation ⚠️D |
| 17 | Prices; Payment Terms; Interest | §5 | Covered |
| 18 | Consequences | §19 Termination | Merged |
| 19 | Entire Agreement | §22 | Covered |
| 20 | Severability | §22 | Covered |
| 21 | Headings | §23 | New section, verbatim in substance |
| 22 | Force Majeure | §18 | Merged; template's backorder/customs/lost-shipment examples added |
| 23 | Complete Agreement — "By clicking I agree…" | §25 | New section; ties acceptance to the checkout checkbox + Place Order |

## Why four clauses were reworded

**⚠️A — "for your own personal, non-commercial use"**
Changed to "internal, non-commercial research use". On a research-use-only
site, "personal use" of purchased material is the exact implication the
processor's own content scanner flags. See `docs/COMPLIANCE` →
Prohibited Language.

**⚠️B — "is a registered trademark"**
Asserted only if the mark is in fact registered. The existing wording
("the trade dress of the Navigate Peptides mark") claims protection
without claiming registration. Restore the stronger wording once a
registration number exists.

**⚠️C — "use by non-individuals or the agents, attorneys or representatives
of non-individuals is prohibited"**
Dropped and replaced. This clause bans institutional purchasers, who are
precisely the permitted buyers under `docs/COMPLIANCE` ("laboratory,
academic, or institutional research"). §13 instead restricts the Site to
qualified researchers and institutions acting through authorized
representatives — same restrictive intent, correct audience.

Two related sentences were also not imported, for the same reason:
- *"…information applicable to your personal circumstances or your use of products sold"*
- *"Before using any product you should confirm any information of importance to you on the product packaging"*

Both imply personal use of the product by the purchaser.

**⚠️D — governing-law `[insert]`**
The template leaves the state blank. Ours already specifies California
with Orange County venue, a jury-trial waiver, and a one-year limitation
period — strictly more complete, so it was kept rather than replaced.

## If the processor wants closer literal alignment

Each reworded clause is a self-contained paragraph. Reverting any one of
them is a single-paragraph edit to `terms-of-service.html` followed by a
re-run of `scripts/terms-update-wpcom.sh`. Reverting ⚠️A or ⚠️C would
reintroduce human-use implications on the page most likely to be scanned;
raise it with the processor before doing so.
