# eneba-marketplace

A digital game key marketplace in the style of [eneba.com](https://www.eneba.com), built as an internship take-home assignment.

## Live

| | URL |
|---|---|
| Frontend (Vercel) | https://eneba-marketplace-coral.vercel.app |
| Backend API (Render) | https://eneba-marketplace-api-b54c.onrender.com |
| API health check | https://eneba-marketplace-api-b54c.onrender.com/api/v1/ping |

> The backend runs on Render's free tier and sleeps after about 15 minutes of inactivity. The first request after that can take 30–60 seconds while it wakes up.

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
php artisan migrate
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
| Render | [render.yaml](render.yaml), [backend/Dockerfile](backend/Dockerfile) | Migrations run on container start. Secrets (`APP_KEY`, `DB_URL`, `DB_MIGRATION_URL`, `FRONTEND_URL`) are set in the Render dashboard |
| Vercel | [frontend/vercel.json](frontend/vercel.json) | Root directory `frontend`, env `VITE_API_URL` |
| Neon | — | Pooled connection string for the app, direct connection string for migrations |
