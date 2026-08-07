# assets-archive

Retired theme assets kept for possible future reuse. **Nothing here is deployed.**

`.github/workflows/deploy-wpcom-sftp.yml` rsyncs only
`wp-content/themes/navigate-peptides/` (with `--delete`), so files in this
directory never reach the wordpress.com server. That is the point — restoring
them into the theme would publish them at guessable public URLs.

## glp-original-labels/

The original vial artwork for the two GLP products, with the real compound
names (`TIRZEPATIDE`, `RETATRUTIDE`) baked into the label texture:

| File | Was |
|---|---|
| `vial-tirzepatide-{5,10}mg.glb` | 3D models, product page viewer |
| `vial-retatrutide-{5,10}mg.glb` | 3D models, product page viewer |
| `vial-tirzepatide-{5,10}mg-card.webp` | 512×640 card posters, shop/archive grids |
| `vial-retatrutide-{5,10}mg-card.webp` | 512×640 card posters, shop/archive grids |

Retired August 2026 when the client moved these products to coded display
names — `GLP-1 T` (tirzepatide) and `GLP-3 R` (retatrutide). The live theme
ships re-labelled copies as `vial-glp-1-t-*` / `vial-glp-3-r-*`.

**Do not copy these back into the theme** while the coded names are in use: the
storefront names, descriptions and card art would then disagree with each
other, and the payment processor's page scanner reads all of it. If the client
later reverts to real names, restore these files *and* revert the product
titles, descriptions and `_nav_3d_model_url` meta together — see
`scripts/coa-sync.php` for the fields involved.

Provenance: `vial-*-card.webp` recovered from commit `4d79811`, `vial-*.glb`
from `dda4c41`. Both remain in git history regardless of this directory.
