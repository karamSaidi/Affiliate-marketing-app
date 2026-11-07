<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

## Stack

- **Language / runtime:** PHP 8.4 and Composer.
- **Framework:** Laravel 13. Public pages use Blade. The administration interface uses Inertia 3, Vue 3, and TypeScript.
- **Key dependencies:** Bootstrap 5.3.8, Vite, and Predis for production Redis.
- **Database:** SQLite locally. MySQL 8.4 LTS or a supported MySQL 8.0 release in production, with utf8mb4.
- **Package managers:** Composer and npm.

## Build approach

Tracer Bullet: build small complete slices through data, behavior, and interface.

## Commands

- Set up: `composer install`, `npm ci`
- Run Laravel: `php artisan serve`
- Run Vite: `npm run dev`
- Build frontend assets: `npm run build`
- Run automated tests: `php artisan test`

## Rules

- Public pages use Blade. The administration interface uses Inertia and Vue. Production does not run a persistent Node server.
- Build frontend assets locally and include `public/build` in cPanel releases.
- Keep service credentials in `.env` or server environment settings. Never commit `.env`.
- Local cache uses files. Production cache uses Upstash Redis over TLS via Predis, with Laravel failover to file cache. Set `CACHE_STORE=failover` in production.
- Follow approved decisions in `docs/specs/` and the delivery order in `docs/scope/scope.md`.
- Declined: Laravel Boost, extra Agent Skills, and MCP connections. Do not install or connect them unless the owner asks. This preference overrides the generic Boost setup note above.

## Specs

Architecture and feature decisions live in `docs/specs/`. The product roadmap lives in `docs/scope/scope.md`.

## Git

- integration: off. This workspace has no initialized `.git` directory.
