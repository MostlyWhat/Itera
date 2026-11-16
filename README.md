# Itera

Itera is a personal Agile planner for college students that can also be used for work and everyday life. Users organize their work as Areas, Projects, Tasks, and Subtasks, give tasks story points, and plan a Monday-to-Sunday sprint.

Live app: <https://itera.wasmer.app>

## Tech stack

- Laravel 13 (PHP 8.5), Fortify, Wayfinder
- Inertia 3, Svelte 5, TypeScript
- Tailwind CSS 4, shadcn-svelte, Vite+
- PostgreSQL in production, SQLite for local development
- Pest for tests, pnpm for JavaScript packages
- Wasmer Edge for hosting, GitHub Actions for CI/CD

## Quick start

### With Docker

You need [Docker](https://docs.docker.com/get-docker/) only.

```bash
docker build -t itera-dev .
docker run --rm -p 8000:8000 -p 5173:5173 itera-dev
```

Then open <http://localhost:8000>. See [Development](docs/development.md#docker) for details.

### On your computer

You need PHP 8.5, Composer, Node.js 24, and pnpm.

```bash
composer setup
php artisan db:seed
composer run dev
```

Both ways add a local admin account. See [Development](docs/development.md#local-admin-account) for the login.

## Documentation

| Document                           | Contents                                                                 |
| ---------------------------------- | ------------------------------------------------------------------------ |
| [Development](docs/development.md) | Local setup, Docker, admin login, commands, commit hooks, commit markers |
| [Deployment](docs/deployment.md)   | Wasmer Edge, CI/CD, secrets, database, email, logs, rollback             |
