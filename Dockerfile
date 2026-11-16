# Local development image for Itera. Not for production: production runs on
# Wasmer Edge (see docs/deployment.md).

# Node.js 24, with curl and git already installed
FROM node:24

# The php.new script needs a shell and a terminal type to run
ENV SHELL=/bin/bash
ENV TERM=xterm

# Install PHP 8.5 and Composer with php.new (herd-lite)
RUN /bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"

# Add the php.new tools to the PATH, the same as the php.new shell profile line
ENV PATH="/root/.config/herd-lite/bin:${PATH}"
ENV PHP_INI_SCAN_DIR="/root/.config/herd-lite/bin"

# Turn on pnpm. Corepack uses the version in "packageManager" in package.json.
ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
RUN corepack enable

WORKDIR /app

# Copy the project (.dockerignore keeps out .env, vendor, node_modules, and local data)
COPY . .

# Install the dependencies and build the frontend. The tests and any
# request made before the Vite dev server starts need public/build.
RUN composer install --no-interaction \
    && pnpm install --frozen-lockfile \
    && pnpm run build

# Make the environment file, the SQLite database, and the local admin account
RUN cp .env.example .env \
    && php artisan key:generate --force \
    && touch database/database.sqlite \
    && php artisan migrate --force \
    && php artisan db:seed --force

# Let Laravel and Vite listen outside the container, on their default ports
ENV SERVER_HOST=0.0.0.0
ENV LARAVEL_SAIL=1

# Laravel on 8000, Vite on 5173
EXPOSE 8000 5173

# Start the development server, which runs Laravel, the queue, and Vite
CMD ["composer", "run", "dev"]
