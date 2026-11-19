# Eneba Marketplace

A digital game key marketplace in the style of [eneba.com](https://www.eneba.com), built for the Eneba internship assignment. Payments are simulated: no real money moves, and the keys are generated test data.

## Try it

| | |
|---|---|
| **App** | https://eneba-marketplace-coral.vercel.app |
| **API** | https://eneba-marketplace-api-b54c.onrender.com ([health check](https://eneba-marketplace-api-b54c.onrender.com/api/v1/ping)) |
| **Demo account** | `demo@example.com` / `password` (or **Use demo account** on the login page) |
| **Test cards** | `4242 4242 4242 4242` succeeds · `4000 0000 0000 0002` is declined · `4000 0000 0000 9995` has insufficient funds. Any future expiry date and any 3-digit CVC |

> **The first request may take up to a minute.** The API runs on Render's free tier, which sleeps after about 15 minutes without traffic, and the Neon database suspends too. If a page shows "The server is taking too long to respond", press **Try again**.

The demo account already has paid orders, so order history has something to show. It's **shared by every reviewer**, so its cart and pending orders can change while you use it. Register your own account (any email, no verification) to get a private one.

**A 5-minute tour:**
1. Search `wichter` from the header. It's corrected to "witcher", both in the suggestions and on the results page.
2. Filter the store by platform, region, genre and price, then sort it.
3. Open a product and compare the sellers' offers.
4. Add a key to the cart as a guest, then register. The guest cart is merged into the new account.
5. Check out. Pay with the declined card first, then with `4242…`. The key is revealed and stays in **My orders**.

## What was built

| Part | What it does |
|---|---|
| **Store** | All ~700 products, with platform, region and genre filters, a price range, an in-stock toggle, 7 sort orders and pagination. Facet counts update with the other filters. The URL holds all filter state, so Back, reload and shared links work |
| **Search** | Available from the header on every page, with live suggestions. Typos and partial names work (`wichter` → The Witcher 3, `eldn ring` → ELDEN RING, `cyber` → Cyberpunk 2077), as do abbreviations (`gta 5`, `rdr2`). The API returns the corrected query, so the UI can say "Showing results for …" |
| **Homepage** | Assembled from blocks the API returns: a hero banner, best sellers, deals, new releases and a genre rail. Each block has its own backend provider class, and the frontend has no hardcoded content. A slow or failing provider is skipped (each runs under its own Postgres `statement_timeout`), and the rest of the page still renders |
| **Product page** | Game details, screenshots with a lightbox, an edition and platform switcher, and every seller's offer (price, rating, stock), with the best offer featured |
| **Cart** | A guest cart in `localStorage` and a server cart once signed in. Signing in merges the two, and a retried merge never doubles quantities: the client sends a merge id and the server replays the first result. Prices are always rechecked against the server, with notices for price changes and low stock |
| **Checkout** | Keys are reserved for 15 minutes with `FOR UPDATE SKIP LOCKED`, so two buyers can never get the same key. An Idempotency-Key makes a double click create one order. Payment goes through a `PaymentGateway` interface (simulated, with Stripe-style test cards), and a declined card keeps the reservation so the buyer can retry. A paid order reveals its keys with a copy button |
| **Auth** | Register, log in and log out with Sanctum Bearer tokens. Login locks out after 5 failures per email and IP, and there are per-IP limits on top. Cart, checkout and order history need an account, and after login the user goes back to where they were heading. The `?redirect=` target is checked so it can't leave the site |
| **Order history** | Paginated list of the user's orders with status badges. The order page shows the keys again |
| **Eneba styling** | Colours, type scale, spacing and component shapes taken from eneba.com's own CSS (Metropolis font, purple palette, yellow call-to-action). Desktop first, and checked at 390 px on every route |

Lighthouse (mobile): accessibility, best practices and SEO score **100** on the homepage, store and product page. Performance is 75–88. The before and after numbers, and what was fixed, are in [docs/LIGHTHOUSE.md](docs/LIGHTHOUSE.md).

## Architecture

```
Browser ──> React 19 + TypeScript + Vite (Vercel)
              │  HTTPS, JSON, Authorization: Bearer <token>
              ▼
            Laravel 12 REST API /api/v1 (Docker on Render)
              │
              ▼
            PostgreSQL 16 + pg_trgm (Neon)
```

- **Backend:** controllers stay thin. Form Requests validate input, services hold the domain logic (`ProductCatalogQuery`, `CheckoutService`, `OrderPaymentService`, `CartService`, the homepage block providers), and API Resources shape every response. Money is integer cents in EUR.
- **Frontend:** TanStack Query for server state, React Router with lazy routes, a small set of UI components (`/dev/components` shows them all), and the store filters live in the URL.
- **Catalog data:** real titles, descriptions and artwork from the public Steam Store API, fetched once by `php artisan catalog:fetch-steam` into a committed JSON file. Seeding and deploys never call Steam. Products exist only on the platforms a game is really on. Sellers, offers, prices and keys are seeded.

**[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)** is the full design. It covers the data model (ER diagram), indexes, search ranking, checkout and key reservation, how a real payment provider would plug in, auth and token storage (including the XSS trade-off), the complete endpoint list, and deployment notes.

## Scope decisions

The brief says that a few features done in depth beat many minimal ones, so I chose where to go deep.

**Deep:**
- **Correct checkout.**
  - Keys are reserved row by row with skip-locked selection, and each reservation has a deadline.
  - Payment locks the order and its keys, so a late payment can never take keys someone else has claimed.
  - Checkout, merge and payment are all idempotent.
  - Expired reservations are released without a scheduler, because Render's free tier has none. The sweep runs during checkout and, at most once a minute, after successful catalog reads.
  - All of this is covered by tests, including a test that runs two real Postgres sessions against each other for the last key.
- **Search that copes with typos.** Postgres trigrams alone failed on real queries (`wichter` matched Monster Hunter, and `gta 5` matched GTFO), so search works in three layers:
  - normalised search columns with acronyms and Roman numerals;
  - per-word typo correction against the catalog's vocabulary;
  - tiered ranking (exact, prefix, word match, then `pg_trgm` fuzzy).

  The thresholds were measured on the real catalog.
- **A homepage that tolerates failures.** Providers are discovered automatically, so a new block type means one new class plus a database row. Every provider has its own timeout.
- **Cart merge.** It's idempotent and adjusts for stock and the per-line limit of 10, and tells the user exactly what changed.

**Kept minimal:**
- **Sellers are seed data.** They have no accounts or dashboards.
- **One currency (EUR) and English only.**
- **Payments are simulated** behind an interface. ARCHITECTURE.md explains how Stripe (a payment intent plus a webhook) would replace it without changing checkout.
- **Desktop first**, with a responsive layout that works down to 390 px.

**Left out on purpose:**
- **Email verification, password reset and any email delivery.** These need a mail provider and add little to what's being assessed. Keys are shown in the UI instead.
- **Admin UI, reviews, wishlists, i18n.** They're outside the purchase flow in the brief.
- **Server-side rendering.** It would help first paint and SEO, but it means a second runtime to host. The SPA scores 100 for SEO and accessibility without it.
- **An image resizing proxy.** The images come straight from Steam's CDN (see the limitations below).

## How I used AI

I built this with **Claude Code** (Anthropic's agentic coding CLI) in VS Code, with the Laravel Boost MCP server for version-specific Laravel docs and database access. The full conversations, with every prompt and response, are in **[ai-logs/](ai-logs/README.md)**.

How I worked with it:
- **Plan first, then build.** For each feature I wrote the requirements, asked for a plan, and corrected it before any code was written. Some examples:
  - making `/cart/merge` idempotent so a retry doesn't double quantities;
  - returning the corrected search query in the response;
  - giving each homepage provider its own statement timeout;
  - using real platforms per game instead of invented console editions.
- **Decisions stayed mine.** The stack, the deployment targets, the scope cuts, the catalog source (Steam, after RAWG turned out to be unavailable) and the trade-offs it proposed, all of which I accepted or overrode explicitly.
- **Checking the output.** I asked it to explain the code before committing, for example the migration order and the exact SQL behind the facet counts. I questioned contradictions, such as conflicting claims about the font licence. Every feature shipped with tests, and the final session was a full code review with fixes, followed by a smoke test against production.

## Known limitations

- **Free-tier wake-up.** The first request after about 15 idle minutes takes 30–60 s. Each API request gives up after 20 s, page data is retried automatically twice, and if it still fails the UI explains that the server is waking up and offers **Try again**.
- **Image sizes.** Covers and screenshots are hotlinked from Steam's CDN, which only serves fixed-size JPEGs. High-density phones download larger files than they need, and there's no WebP or AVIF. An image resizer in front of the CDN would fix both (see [LIGHTHOUSE.md](docs/LIGHTHOUSE.md#still-open-and-why)).
- **Platform words in search.** `elden ring xbox` still finds ELDEN RING, but only through the fuzzy tier: words like "xbox" aren't mapped onto the platform filter. Use the filters for that.
- **Shared demo account.** Anyone can change its cart. Repeated wrong passwords lock it for a minute, but only for the IP that sent them.
- **Registration reveals whether an email is registered.** This is a deliberate trade-off without email delivery (see ARCHITECTURE.md, "Authentication").
- **The auth token is in `localStorage`.** This avoids third-party cookie problems between Vercel and Render. The XSS risk is reduced by a strict CSP, React's escaping and short-lived tokens (see ARCHITECTURE.md).

## Run locally

Prerequisites: PHP 8.2 with `pdo_pgsql`, Composer, Node 22+ and Docker.

```bash
# PostgreSQL 16: creates `eneba` (dev) and, on a fresh volume, `eneba_test`
docker compose up -d

# Backend → http://localhost:8000
cd backend
composer install
cp .env.example .env
# In .env set: DB_DATABASE=eneba, DB_USERNAME=eneba, DB_PASSWORD=secret,
#              FRONTEND_URL=http://localhost:5173
php artisan key:generate
php artisan migrate --seed   # 129 games, ~700 products, ~2,500 offers, the demo account
php artisan serve

# Frontend → http://localhost:5173 (in a second terminal)
cd frontend
npm install
cp .env.example .env.local   # VITE_API_URL=http://localhost:8000
npm run dev
```

## Tests

```bash
# Backend: 304 Pest tests against a real PostgreSQL database (eneba_test), so pg_trgm search,
# row locks and SKIP LOCKED actually run. The Concurrency suite uses two DB sessions.
cd backend
php artisan test
php artisan test --testsuite=Concurrency   # just the lock-contention tests

# Frontend: 126 Vitest unit tests, plus type checking and lint
cd frontend
npm test
npx tsc -b
npm run lint
```

**End-to-end journey** (`frontend/scripts/e2e.mjs`): the script drives headless Chrome through the whole purchase. It covers:
- the homepage, a store filter and the `wichter` search;
- the product page and a guest add to cart;
- registering and merging the guest cart;
- logging out and back in;
- checkout with a declined card and then a successful one;
- order history and the 404 page.

It has no dependencies (Node 22+ and a local Chrome or Edge), and it registers a throwaway `e2e-…@example.test` account on every run, so it never touches the demo account.

```bash
cd frontend

# Against local dev servers (vite on :5173, API on :8000)
npm run e2e

# Against the live deployment
E2E_APP_URL=https://eneba-marketplace-coral.vercel.app \
E2E_API_URL=https://eneba-marketplace-api-b54c.onrender.com \
npm run e2e
```

Optional variables: `CHROME_PATH`, if Chrome isn't found automatically, and `E2E_SCREENSHOTS=<dir>` to save a screenshot of each step. On Windows PowerShell, set the variables with `$env:E2E_APP_URL = "…"` before `npm run e2e`.

## Repository layout

```
backend/              Laravel API (Dockerfile, docker/entrypoint.sh)
frontend/             React SPA (vercel.json, scripts/e2e.mjs)
docs/                 ARCHITECTURE.md, LIGHTHOUSE.md
ai-logs/              Claude Code session transcripts
docker-compose.yml    Local PostgreSQL (dev + test databases)
render.yaml           Render Blueprint for the API
```

## Deployment

| Service | Config | Notes |
|---|---|---|
| Render | [render.yaml](render.yaml), [backend/Dockerfile](backend/Dockerfile) | Migrations run on container start under an advisory lock. With `SEED_IF_EMPTY=true`, an empty database is seeded in the background in one transaction over the direct connection (see [ARCHITECTURE.md](docs/ARCHITECTURE.md#seed-data--first-boot)). The app uses Neon's pooled endpoint with emulated prepares ([why](docs/ARCHITECTURE.md#neons-pooler-and-pdo)). Secrets (`APP_KEY`, `DB_URL`, `DB_MIGRATION_URL`, `FRONTEND_URL`) are set in the Render dashboard, never in the repo |
| Vercel | [frontend/vercel.json](frontend/vercel.json) | Root directory `frontend`, env `VITE_API_URL`. Sets the CSP and security headers |
| Neon | — | Pooled connection string for the app, direct connection string for migrations |

Game data and artwork come from the [Steam Store](https://store.steampowered.com). This project is not affiliated with Valve or Eneba.
