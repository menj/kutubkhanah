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

## Licensed fonts (fonts/kutubkhanah/private/)

These are part of the deployed CMS and are loaded by `Library::privateFontFaces()` (public site and admin).
They are kept out of the public Git repository because their licences do not allow public redistribution;
the deployment package includes them.

| Font | Files | Used for |
|---|---|---|
| Sabon Next LT (Monotype) | `SabonNextLT-Regular/Italic/Bold/BoldItalic.woff2` | First choice for all text and headings |
| Kagnue (Youthlabs Studio) | `Kagnue-Regular.woff2` | Hero and banner titles, site name, statistics, A–Z links |
| Kagnue Outline | `Kagnue-Outline.woff2` | Large A–Z letters on the catalogue shelf |
| Ferments (Haksen Studio) | `Ferments-Regular.woff2`, `Ferments-Slant.woff2` (italic) | Hero tagline and mission quote |

Each `@font-face` rule is printed only when its file is present, so a checkout without these files
falls back to EB Garamond with no missing-file requests.
