# Deploying PMOC ML Service on Vercel

This Flask app is configured to run on Vercel as a serverless function (Fluid compute).

## Prerequisites

- **Pre-trained models**: Commit `risk_model.pkl`, `category_model.pkl`, and `risk_encoder.pkl` in the project root so `/analyze` works. If they are missing, train locally (or on Heroku), then add and commit the three files.
- **Environment variables** (in Vercel Project Settings → Environment Variables):
  - `DB_HOST` – MySQL host (e.g. `srv1322.hstgr.io` or `localhost` for dev)
  - `DB_USER` – MySQL user
  - `DB_PASSWORD` – MySQL password
  - `DB_NAME` – Database name (e.g. `u520834156_DBpmoc25`)
  - Optional: `FLASK_ENV`, `ENVIRONMENT` for production vs local DB detection

## Deploy

1. **CLI**
   ```bash
   cd pmoc_ml
   npx vercel
   ```
   Or link and deploy:
   ```bash
   npx vercel link
   npx vercel deploy --prod
   ```

2. **Git**
   - Push the repo and connect the project in [Vercel](https://vercel.com/new). Vercel will detect Flask via `pyproject.toml` and use `service:app`.

## Local dev with Vercel

```bash
python -m venv .venv
.venv\Scripts\activate   # Windows
pip install -r requirements.txt
vercel dev
```

## Behavior on Vercel

- **Entry point**: `pyproject.toml` defines `app = "service:app"`, so the Flask app in `service.py` is used.
- **Read-only filesystem**: The deployment bundle is read-only. Model files must be committed (or loaded from `/tmp`; see below).
- **`/analyze`**: Works if the three `.pkl` files are in the repo. Models are loaded from the project directory at cold start.
- **`/train`**: Runs in the background and saves models to `/tmp`. **Models are not persisted** across invocations. The response includes a `vercel_note` when running on Vercel. For production, train elsewhere and commit the `.pkl` files.
- **Database**: Set `DB_*` env vars so the app can load MEAI categories and questions from MySQL.

## Endpoints

- `GET /health` – Health check  
- `GET /status` – Service status and model availability  
- `POST /analyze` – Couple analysis and recommendations (main API)  
- `POST /train` – Start training (on Vercel: in-memory only, not persisted)  
- `GET /training_status` – Training progress  

After deploy, use: `https://pmoc-ml-vercel.vercel.app/health`
