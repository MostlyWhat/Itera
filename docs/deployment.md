# Deployment

Itera runs on [Wasmer Edge](https://docs.wasmer.io/edge/). GitHub Actions builds the application and deploys it with the Wasmer CLI.

## Architecture

- **Build:** GitHub Actions uses native PHP 8.5, Composer, Node.js 24, and pnpm.
- **Package:** `scripts/wasmer/stage.sh` copies the runtime files into `deploy/`. The Wasmer package in `wasmer.toml` mounts `deploy/` at `/app`.
- **Runtime:** The community WASIX package [`shira/php@8.5.10`](https://wasmer.io/shira/php) runs PHP's built-in web server. `deploy/router.php` serves static files and sends other requests to Laravel.
- **Application:** `app.yaml` configures the Wasmer Edge app, the PostgreSQL database, and the health check.
- **Migrations:** `scripts/wasmer/migrate.sh` runs `php artisan migrate --force` with native PHP in CI, one time before each deployment. It reads the database credentials from the Wasmer app secrets. The app never runs migrations when it starts.

## CI/CD

Two workflows run the pipeline:

| Workflow | File                           | Runs on                                                   | Jobs                                  |
| -------- | ------------------------------ | --------------------------------------------------------- | ------------------------------------- |
| Tests    | `.github/workflows/tests.yml`  | Each pull request, a manual run, and a call from Deploy   | `Tests`                               |
| Deploy   | `.github/workflows/deploy.yml` | A push to `main` (a merged pull request) and a manual run | `Tests`, then `Deploy to Wasmer Edge` |

The `Deploy to Wasmer Edge` job starts after the tests pass. It never runs for a pull request. One production deployment runs at a time.

### Branch ruleset

A ruleset on `main` requires a pull request and the `Tests` check before a merge. To set it up, see [Set up the branch ruleset](#set-up-the-branch-ruleset).

The job does these steps:

1. Install the production Composer packages (`composer install --no-dev`).
2. Run `composer run build:production` (package discovery and asset publishing).
3. Run `pnpm install --frozen-lockfile` and `pnpm run build`. Wayfinder generates the routes during the build.
4. Run `scripts/wasmer/stage.sh` to make the `deploy/` directory.
5. Run `scripts/wasmer/migrate.sh`, then `wasmer deploy --non-interactive --publish-package --bump`, then check `/up`.

The `--bump` flag sets the package version to the latest registry version plus one patch. Thus each run publishes a new version, although `wasmer.toml` keeps `0.1.0`.

To skip jobs, see [commit markers](development.md#commit-markers).

## GitHub secrets

| Name           | Value                                                                                            |
| -------------- | ------------------------------------------------------------------------------------------------ |
| `WASMER_TOKEN` | A Wasmer API token from <https://wasmer.io/settings/access-tokens> for the `mostlywhat` account. |

The workflow uses the `production` GitHub environment. GitHub makes it on the first run.

## Wasmer settings

`app.yaml` sets the non-secret production values: `APP_ENV=production`, `APP_DEBUG=false`, the HTTPS `APP_URL`, database sessions and cache, and the `sync` queue. Set the application key as a Wasmer secret. Never commit it.

```bash
php artisan key:generate --show
wasmer app secret create APP_KEY "base64:..." --app mostlywhat/itera
```

If Wasmer gives the app a different URL, change `APP_URL` in `app.yaml` and the URLs in the workflow.

## PostgreSQL

`app.yaml` requests a managed PostgreSQL database (`capabilities.database.engine: postgres`). Wasmer gives these variables to the app: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USERNAME`, and `DB_PASSWORD`. Laravel uses `DB_DATABASE`, so `config/database.php` reads `DB_NAME` when `DB_DATABASE` is not set. `app.yaml` sets `DB_CONNECTION=pgsql`.

## Email

Itera sends email through [Resend](https://resend.com) with Laravel's `smtp` mailer. Email verification, password reset, and team invitations need it. `app.yaml` sets the host (`smtp.resend.com`), the port (`587`, STARTTLS), and the username (`resend`). Set the API key and the sender address as Wasmer secrets:

```bash
wasmer app secret create MAIL_PASSWORD "re_..." --app mostlywhat/itera
wasmer app secret create MAIL_FROM_ADDRESS "no-reply@your-domain.example" --app mostlywhat/itera
```

The sender address must use a domain that you verified in Resend. Redeploy after you change a secret.

## First deployment

The `itera` app already exists (`app_id` in `app.yaml`). For a new app, do these steps:

1. Install the Wasmer CLI and run `wasmer login`.
2. Build the application and run `bash scripts/wasmer/stage.sh` on Linux or in WSL.
3. Run `wasmer deploy --publish-package --bump`. The CLI makes the app and writes `app_id` into `app.yaml`.
4. Create the `APP_KEY` secret (see above). Run `bash scripts/wasmer/migrate.sh` from `deploy/`, then deploy again.
5. Commit `app.yaml` with the `app_id`, and add `WASMER_TOKEN` to the GitHub secrets.

## Manual redeploy

Open **Actions > tests > Run workflow** on GitHub and select `main`. Or run `wasmer deploy --publish-package --bump` from a staged local copy.

## Logs

```bash
wasmer app logs mostlywhat/itera
wasmer app logs mostlywhat/itera --watch
```

The app sends Laravel logs to stderr (`LOG_CHANNEL=stderr`), so they show in these logs.

## Rollback

```bash
wasmer app version list mostlywhat/itera
wasmer app version activate <VERSION_ID>
```

A rollback does not undo migrations. Write migrations that work with the previous release.

## Known limitations of the PHP runtime

- `shira/php` is a community package, not an official Wasmer package. Pin the version and test each upgrade.
- PHP's built-in web server handles one request at a time. `app.yaml` uses `scaling.mode: single_concurrency`, so Wasmer Edge adds instances for more traffic.
- The local file system of an instance is not persistent. Keep sessions, cache, and data in PostgreSQL. Do not keep user uploads on the local disk.
- The router moves Laravel storage to the temporary directory if the package storage is read-only.
- There is no queue worker. Queued jobs run synchronously (`QUEUE_CONNECTION=sync`).
- Wasmer CLI 7.5 reads a health check only in the YAML tag form (`- !http`). The CLI also rewrites `app.yaml` on each deploy.
- A Wasmer `pre-deployment` job with `execute` did not run in tests, so CI runs the migrations. The database must accept connections from GitHub Actions.
- `wasmer run` with this runtime does not work on Windows (`ProcessCputimeId` is not implemented). Test the package on Linux, in WSL, or in Docker.

## Set up the branch ruleset

Do these steps one time, as a repository admin, after `tests.yml` is on GitHub.

1. Open the repository on GitHub. Go to **Settings > Rules > Rulesets**.
2. Click **New ruleset > New branch ruleset**.
3. Set **Ruleset name** to `Protect main` and **Enforcement status** to **Active**.
4. Under **Target branches**, click **Add target > Include default branch**.
5. Select **Restrict deletions** and **Block force pushes**.
6. Select **Require a pull request before merging**. Set **Required approvals** to `0`, so you can merge your own pull request.
7. Select **Require status checks to pass**. Click **Add checks**, type `Tests`, and select the check from **GitHub Actions**.
8. Select **Require branches to be up to date before merging**.
9. Click **Create**.

The `Tests` check shows in the list only after it ran one time. Open a pull request first if the search finds nothing.
