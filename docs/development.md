# Development

## Local setup

1. Install PHP 8.5, Composer, Node.js 24, and pnpm. [Laravel Herd](https://herd.laravel.com) gives you PHP, Composer, and a local HTTPS site (`https://itera.test`).
2. Run `composer setup`. It installs the packages, copies `.env.example` to `.env`, makes the application key, runs the migrations, and builds the frontend.
3. Run `php artisan db:seed` to add the local admin account.
4. Run `composer run dev` to start the Vite dev server with hot reload.

The local database is SQLite (`database/database.sqlite`).

## Docker

The `Dockerfile` makes a local development image. It is not for production (production runs on Wasmer Edge, see [Deployment](deployment.md)). The image has Node.js 24, pnpm, and PHP 8.5 with Composer from [php.new](https://php.new). It runs the same steps as the local setup above.

1. Build the image:

    ```bash
    docker build -t itera-dev .
    ```

2. Start the development server. Laravel uses port 8000 and Vite uses port 5173:

    ```bash
    docker run --rm -p 8000:8000 -p 5173:5173 itera-dev
    ```

3. Open <http://localhost:8000> and sign in with the [local admin account](#local-admin-account).

4. To run a command in the image, put it after the image name:

    ```bash
    docker run --rm itera-dev php artisan test
    ```

The image copies the project at build time. To see code changes, build the image again. `.dockerignore` keeps out `.env`, `vendor`, `node_modules`, local databases, and build output, so the image never contains your local secrets or data. Each container starts with a new database.

## Local admin account

`php artisan db:seed` creates a verified account with a personal team. The email and password are in [`database/seeders/DatabaseSeeder.php`](../database/seeders/DatabaseSeeder.php) (`LOCAL_ADMIN_EMAIL` and `LOCAL_ADMIN_PASSWORD`).

- The seeder runs only when `APP_ENV=local`. In other environments it does nothing.
- You can run it again. It does not make a second account.

## Email verification in local

When `APP_ENV=local`, the app marks each new account as verified at registration (`AppServiceProvider::configureLocalDevelopment()`). You do not need the verification email. All other environments, tests included, keep normal email verification.

Local email uses the `log` mailer. To read an email, look in `storage/logs/laravel.log`.

## Commands

| Command                | What it does                                     |
| ---------------------- | ------------------------------------------------ |
| `composer run dev`     | Starts the Vite dev server and the Laravel tools |
| `composer test`        | Runs Pint (check), PHPStan, and Pest             |
| `composer lint`        | Formats PHP with Pint                            |
| `pnpm run check`       | Checks frontend format and lint (Vite+)          |
| `pnpm run check:fix`   | Fixes frontend format and lint                   |
| `pnpm run types:check` | Runs svelte-check                                |
| `pnpm run build`       | Builds the frontend for production               |
| `composer ci:check`    | Runs all the checks that CI runs                 |

## Commit hooks

A pre-commit hook formats the staged files:

| Staged files                                    | Task                  |
| ----------------------------------------------- | --------------------- |
| `*.{js,mjs,cjs,ts,svelte,css,json,md,yml,yaml}` | `vp check --fix`      |
| `*.php`                                         | `php vendor/bin/pint` |

`.npmrc` sets `ignore-scripts=true`, so `pnpm install` does not install the hook. Run this command one time in each new clone:

```bash
pnpm exec vp config --hooks --no-agent
```

To skip the hook for one commit, use `git commit --no-verify`.

## Commit markers

Put a marker in the commit message to skip CI jobs. For a push, GitHub reads only the last commit. For a pull request, put the marker in the title.

| Marker          | Tests | Deploy          |
| --------------- | ----- | --------------- |
| `[skip ci]`     | No    | No              |
| `[skip tests]`  | No    | Yes (on `main`) |
| `[skip deploy]` | Yes   | No              |

Example: `docs: fix typo in README [skip deploy]`. Use `[skip tests]` only for changes that cannot break the build.
