# Vue (SFC) Build Setup

This project now uses Vite + Vue 3 for the frontend in `src/`.

## Install

```bash
npm install
```

## Run

```bash
npm run dev
```

The app will run at `http://localhost:5173/`.

## API Base URL

Configure the backend base URL in `.env`:

```
VITE_API_BASE_URL=http://localhost/BOTS_PROJECT
```

## Routes

- `/` Home
- `/blog` Blog list
- `/blog/:slug` Single blog
- `/register?role=user|agent|admin` Registration flow

## Notes

Legacy CDN-based pages remain in `public/` but are now superseded by the Vue app.
Consider removing `public/index.php` and related JS once fully migrated.
