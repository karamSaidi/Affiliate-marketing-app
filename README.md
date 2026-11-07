# Affiliate Catalog

Laravel application for an Arabic and English affiliate product catalog. The public site uses Laravel Blade. The administration interface is prepared for Inertia 3, Vue 3, TypeScript, and Bootstrap 5. Production uses MySQL. Local development uses SQLite.

## Local setup

Prerequisites: PHP 8.4, Composer, Node.js, and npm.

Run these commands from the project root in PowerShell:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
npm ci
npm run build
php artisan serve
```

The local environment uses SQLite, file sessions, file cache, and the database queue. Keep `.env` private. It is ignored by Git.

## Frontend assets

Build JavaScript and CSS on the development machine with `npm run build`. Commit the generated `public/build` assets with each release so cPanel does not need a persistent Node.js process.

## Architecture

Read the [architecture decision](docs/specs/0001-affiliate-catalog-architecture/index.md) and [project scope](docs/scope/scope.md) before extending the application. Production hosting requirements and external service settings are recorded in the architecture decision.
