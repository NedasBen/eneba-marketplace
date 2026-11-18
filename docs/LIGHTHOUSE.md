# Lighthouse audit

Lighthouse 12.8.2 (`npx lighthouse@12`) was run on the homepage, the store and a product page, before and after a performance and accessibility pass on 2026-11-19.

## How it was measured

- **Build:** the production build (`npm run build`, served by `vite preview`), against the local API (`php artisan serve`) with the seeded demo data.
- **Device:** Lighthouse's default mobile emulation: a 412 px wide phone at 1.75× pixel density, with simulated slow 4G and a 4× slower CPU. Categories: performance, accessibility, best practices and SEO.
- **Pages:** `/`, `/store` and `/product/timberborn-steam-key-global`.
- **Runs:** two per page. The tables show the lower performance score, with the other run in brackets. The other categories and the metrics come from that same lower run.

Because the API ran locally rather than on Render, the scores reflect the frontend, not hosting latency. `vite preview` doesn't compress responses, while Vercel serves Brotli, so the "Enable text compression" audit is a local-only finding.

## Scores

| Page | Performance | Accessibility | Best practices | SEO |
|---|---|---|---|---|
| Homepage | 73 (77) → **75 (77)** | 100 → **100** | 100 → **100** | 83 → **100** |
| Store | 78 (87) → **77 (80)** | 98 → **100** | 100 → **100** | 83 → **100** |
| Product page | 76 (84) → **88 (88)** | 97 → **100** | 100 → **100** | 83 → **100** |

| Page | FCP | LCP | TBT | CLS | Page weight |
|---|---|---|---|---|---|
| Homepage | 2.3 s → 2.3 s | 9.0 s → **6.6 s** | 30 → 40 ms | 0.001 → 0.001 | 6,642 → 6,116 KiB |
| Store | 2.3 s → 2.3 s | 5.4 s → 5.7 s | 10 → 20 ms | 0.001 → 0.001 | 3,471 → 3,467 KiB |
| Product page | 2.3 s → 2.3 s | 6.1 s → **3.6 s** | 0 → 20 ms | 0.001 → 0.001 | 5,962 → **945 KiB** |

- **Accessibility and SEO** are now 100 on all three pages.
- **Product page performance** rose from 76 to 88, and its page weight fell by 84%.
- **Homepage performance** rose slightly.
- **Store performance** is unchanged within run-to-run noise: its better run dropped from 87 to 80, and its LCP is in the same range.

## What was fixed

### Accessibility

| Finding | Fix |
|---|---|
| **Accessible name doesn't match the visible text** (all pages). The logo link was labelled "Eneba Marketplace home", but shows "eneba DEMO", so voice-control users couldn't say what they see. | The `aria-label` was removed; the name is now the visible text plus a visually hidden "Marketplace home". |
| **Headings skip a level** (store). The page went from `<h1>` straight to the cards' `<h3>` titles. | A visually hidden `<h2>Results</h2>` above the grid. |
| **Low contrast** (product page). Muted text (3.7:1) and the green link (3.6:1) sat on the purple buy box. | Those spots use the lighter `soft` and `highlight` colours (5.9:1 and 5.2:1). The contrast of every text colour on every background was checked. The warning pink fails on purple (2.7:1), so the cart summary and the checkout payment panel moved to the darker card background, where every colour passes. |

### SEO

| Finding | Fix |
|---|---|
| **No meta description** | A description in `index.html`. |
| **`robots.txt` invalid (15 errors).** The SPA fallback served `index.html` at `/robots.txt`. | A real `public/robots.txt` that keeps cart, checkout, order, auth and dev pages out of search results. |

### Performance

| Finding | Fix | Effect |
|---|---|---|
| **Oversized product screenshots.** The six thumbnails on the product page (about 125 px wide) downloaded Steam's 1920×1080 files, about 850 KB each. | Thumbnails use Steam's 600×338 version (about 100 KB) with a `srcset`; the lightbox keeps the full size. | Product page weight 5,962 → 945 KiB. |
| **The LCP image wasn't prioritised** (product page). | The cover has `fetchpriority="high"`, explicit `width`/`height`, and a 300/600 px `srcset`. | Product LCP 6.1 → 3.6 s. |
| **All four hero slides downloaded at once** (homepage). The slides are stacked on top of each other, so `loading="lazy"` treated every one as visible. | Only the current and the next slide load; the others load just before they're shown. The first slide has `fetchpriority="high"`. | About 860 KB fewer hero bytes on first load. |
| **The LCP image was lazy-loaded** (store). | The first cover loads eagerly with high priority. Making the whole first row eager was tried and was slower, because four large images competed for bandwidth. | "Largest Contentful Paint image was lazily loaded" no longer fails. |
| **Data requests started late.** The page's first API calls only began after the whole 130 KB JS bundle had downloaded and run, and the LCP image waits on that data. | `public/prefetch.js`, a tiny deferred same-origin script, starts the landing page's API requests (home, unfiltered store, product page and the header's filters) while the bundle downloads. `lib/api/client.ts` reuses those responses once, by exact URL, within 10 seconds. | Measured: API requests start about 40 ms into the load instead of about 160 ms. Lighthouse's simulation barely credits this, because it still ties the LCP image to the bundle that inserts it. |
| **Duplicate filter request** (store). The header and the store asked for `/catalog/filters` with different parameters. | The header uses the same parameters as an unfiltered store page, so they share one request. | One request fewer on every page load. |
| **No preconnect** to the API or the Steam image CDN (about 310 ms estimated). | `<link rel="preconnect">` for both in `index.html`. | The audit passes. |
| **Unused JavaScript on landing pages.** | The login, register, checkout and order pages load on demand. | The main bundle dropped from 458 KB to about 440 KB, split into two files. The rest is shared library code (React, React Router, TanStack Query). |
| **Cover sizes.** | Covers use Steam's 300 px file with a `srcset` that offers the 600 px version, and a `sizes` that matches each card's real width. | Desktop and low-density screens now download about 65 KB per cover instead of about 200 KB. |

## Still open, and why

- **Covers on high-density phones.** On a 412 px phone a store card is about 178 px wide. At 1.75× that needs 311 device pixels, so the browser rightly picks the 600 px file, and Lighthouse still reports about 2.4 MB of "properly size images" savings on the store and 4.5 MB on the homepage. Telling the browser the cards are narrower than they are would raise the score but blur covers on real phones, so `sizes` stays truthful. The real fix is an image resizer serving exact widths, for example a Vercel image-optimisation route in front of the Steam CDN.
- **No WebP or AVIF.** Images are hotlinked from Steam's CDN, which only serves JPEG. The same image resizer would fix this, with an estimated 2 to 3.5 MB saving per page.
- **The homepage hero is a 1920×620 JPEG.** Steam offers no smaller hero artwork. The mobile alternatives (capsule images) have the game's logo baked in, which would clash with our own title over the image.
- **FCP is about 2.3 s.** This is a client-rendered SPA: nothing meaningful paints until the bundle runs. Server-side rendering or pre-rendering the landing pages would be the next step, and it's out of scope for this project.

## Also checked (not Lighthouse)

A headless-Chrome script went through every route at 390 px wide, signed out and signed in, including 404s, an empty search, the cart, checkout, order history, order detail and the store's filter drawer. It checks for:

- **Horizontal overflow.** None found.
- **Tap targets under 24 px.**
  - Fixed: the header's Log in and Register links, the user-menu button, "Read more", and the hero's carousel dots (4 px tall bars, which now have a 24 px hit area).
  - Fixed along the way: the category bar's links could shrink and overlap.
- **Visible keyboard focus.** It tabs through each page and flags any element whose outline, shadow, border, background, colour or underline doesn't change on focus. Product cards now show a 2 px accent focus ring instead of a faint 1 px outline.
- **Page titles.** Every route sets a specific `document.title`.
