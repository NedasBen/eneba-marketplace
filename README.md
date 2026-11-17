# eneba-marketplace

A digital game key marketplace in the style of [eneba.com](https://www.eneba.com), built as an internship take-home assignment.

## Live

| | URL |
|---|---|
| Frontend (Vercel) | https://eneba-marketplace-coral.vercel.app |
| Backend API (Render) | https://eneba-marketplace-api-b54c.onrender.com |
| API health check | https://eneba-marketplace-api-b54c.onrender.com/api/v1/ping |

> The backend runs on Render's free tier and sleeps after about 15 minutes of inactivity. The first request after that can take 30–60 seconds while it wakes up.

### Demo account

| Email | Password |
|---|---|
| `demo@example.com` | `password` |

The demo account has a few past orders with their keys, so you can check order history without registering. You can also create your own account.

## Stack

- **Frontend:** React + TypeScript + Vite, deployed on Vercel
- **Backend:** Laravel 12 (PHP 8.2) REST API at `/api/v1`, Sanctum token auth, Docker on Render
- **Database:** PostgreSQL 16 with `pg_trgm` for typo-tolerant search, hosted on Neon

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the data model, indexes and the full endpoint list.

## Repository layout

```
backend/              Laravel API (Dockerfile, docker/entrypoint.sh)
frontend/             React SPA (vercel.json)
docs/                 Architecture notes
docker-compose.yml    Local PostgreSQL (dev + test databases)
render.yaml           Render Blueprint for the API
```

## Local development

Prerequisites: PHP 8.2 with `pdo_pgsql`, Composer, Node 20+, Docker.

```bash
# Database (creates `eneba` and, on a fresh volume, `eneba_test`)
docker compose up -d

# Backend → http://localhost:8000
cd backend
composer install
cp .env.example .env        # then set DB_* to match docker-compose.yml
php artisan key:generate
php artisan migrate --seed  # demo catalog: 129 games, ~700 products, ~2,500 offers
php artisan serve

# Frontend → http://localhost:5173
cd frontend
npm install
cp .env.example .env.local  # VITE_API_URL=http://localhost:8000
npm run dev
```

## Tests

Backend tests run against a real PostgreSQL database (`eneba_test`), so the `pg_trgm` search behaviour is actually exercised:

```bash
cd backend
php artisan test
```

## Deployment

| Service | Config | Notes |
|---|---|---|
| Render | [render.yaml](render.yaml), [backend/Dockerfile](backend/Dockerfile) | Migrations run on container start under an advisory lock. With `SEED_IF_EMPTY=true`, an empty database is seeded in the background in one transaction (see [ARCHITECTURE.md](docs/ARCHITECTURE.md#seed-data--first-boot)). Secrets (`APP_KEY`, `DB_URL`, `DB_MIGRATION_URL`, `FRONTEND_URL`) are set in the Render dashboard |
| Vercel | [frontend/vercel.json](frontend/vercel.json) | Root directory `frontend`, env `VITE_API_URL` |
| Neon | — | Pooled connection string for the app, direct connection string for migrations |

## Data sources

Game titles, descriptions, artwork and retail prices come from the public [Steam Store](https://store.steampowered.com), fetched once with `php artisan catalog:fetch-steam` and committed as `backend/database/data/games.json`. The console platforms for each game are hand-curated. Game data and images are © their respective owners. This is a non-commercial demo, not affiliated with Valve or Eneba. Sellers, offers, prices and keys are generated demo data.
