# Kutubkhanah fonts

| Role | Font | File | Licence |
|---|---|---|---|
| Headings and reading text | EB Garamond (variable, roman + italic) | `EBGaramond-*.woff2` | SIL OFL 1.1 (`licenses/EBGaramond-OFL.txt`) |
| Arabic headings and titles | Amiri | `Amiri-*.woff2` | SIL OFL 1.1 (`licenses/Amiri-OFL.txt`) |
| Arabic body and interface text | Noto Naskh Arabic (variable) | `NotoNaskhArabic-Variable.woff2` | SIL OFL 1.1 (`licenses/NotoNaskhArabic-OFL.txt`) |
| Quranic text (`.kk-quran`) | KFGQPC HAFS Uthmanic Script | `KFGQPC-HAFS-Uthmanic-Script.ttf` (original, unmodified) | KFGQPC licence (`licenses/KFGQPC-EULA.txt`) |
| Catalogue-card data (`.kk-typewriter`) | Special Elite | `SpecialElite-Regular.woff2` | Apache 2.0 (`licenses/SpecialElite-Apache-2.0.txt`) |

The open fonts were converted to WOFF2 without subsetting or other changes.
The KFGQPC font is the original file because its licence does not allow it to be altered.

## Optional licensed fonts (not distributed)

These are referenced by the CSS but are **not** included, because their licences do not allow
redistribution. If you hold a web-font licence, place the files in `fonts/kutubkhanah/private/`
on your own server. They are git-ignored and excluded from release packages.

| Font | Expected file names | Used for |
|---|---|---|
| Kagnue (Youthlabs Studio) | `private/Kagnue-Regular.woff2` (or `.woff`, `.ttf`, `.otf`) | Display titles (hero, page banners) |
| Ferments (Haksen Studio) | `private/Ferments-Regular.woff2` (or `.woff`, `.ttf`, `.otf`) | Hero quote accent |

Sabon Next LT (Monotype) is listed first in the serif stack as a locally installed font only
(`local()`), so visitors who have it installed see it. It is never downloaded or redistributed:
the supplied file's licence limits it to Microsoft Sway.
