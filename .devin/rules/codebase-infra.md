---
description: "External configuration of the project, such as Docker conf, Redis and others."
trigger: always_on
---
<frontend-boost-guidelines>
# INFRASTRUCTURE

Descriptive document of the actual file structure and Docker infrastructure configuration for development and production environments.

Contains no assumptions, only actual configurations present in the files.

---

# 1. Infrastructure file structure

## Root

- docker-compose.yml
- docker-compose-dev.yml
- docker-compose-prod.yml
- Dockerfile
- entrypoint.sh
- apache.conf
- redis.conf
- Makefile
- .env
- .env.backend
- .env.frontend
- secrets/

---

# 2. Docker Compose

## 2.1 docker-compose.yml (common base)

Defined services:

- database (MySQL 8.4)
- session (Redis 7)
- webserver (Laravel backend)
- laravel-worker
- laravel-scheduler

Volumes:

- database-data
- session-data

Secrets:

- laravel_app_key
- mysql_root_pwd
- mysql_pwd
- redis_pwd
- backpack_admin_pwd
- user_rays_pwd
- master_rays_pwd
- humanbit
- known_hosts

Notes:

- This file is common-first.
- Dev and Prod are activated via override files.
- The MySQL service is always defined here.
- In production the MySQL container is abandoned operationally, but remains defined in the common compose.

---

## 2.2 docker-compose-dev.yml

Development override.

Main changes:

webserver:
- env_file: .env.backend, .env.frontend
- Ports:
  - 127.0.0.1:8080 -> 80 (backend)
  - 127.0.0.1:3000 -> 3000 (Next dev)
- Bind mount:
  - ./codebase -> ${CODEBASE}
  - ~/.pnpm-store -> /home/codeuser/.pnpm-store

laravel-worker:
- Bind mount codebase

laravel-scheduler:
- Bind mount codebase

Additional service:
- mail (Mailpit)
  - 127.0.0.1:1025
  - 127.0.0.1:8025

---

## 2.3 docker-compose-prod.yml

Production override.

webserver:
- image: webserver:${TAG_NAME}
- env_file: .env.backend
- Port:
  - 127.0.0.1:${PROD_BACKEND_PORT} -> 80
- Named volumes:
  - webserver-backend-cache
  - webserver-backend-import
  - webserver-backend-storage
  - webserver-frontend-lang

laravel-worker:
- image: webserver:${TAG_NAME}
- uses named backend volumes

laravel-scheduler:
- image: webserver:${TAG_NAME}
- uses named backend volumes

nextjs:
- build target: front-prod
- image: frontend:${TAG_NAME}
- Port:
  - 127.0.0.1:${PROD_FRONTEND_PORT} -> 3000
- Volume:
  - webserver-frontend-lang (read-only)

Production volumes:

- webserver-backend-cache
- webserver-backend-import
- webserver-backend-storage
- webserver-frontend-lang

---

# 3. Dockerfile

Multi-stage build.

Stages:

1) base
- php:8.4-apache
- PHP extensions: pdo, pdo_mysql, mysqli, intl, pcntl, curl, redis
- Composer installed globally
- GitHub CLI installed
- Python optional (controlled by INSTALL_PYTHON_TOOLS)

2) core-dev
- Node 20
- pnpm
- Sass
- Apache configured with apache.conf
- User ${CODEUSER}

3) core-prod
- Copy backend only
- composer install --no-dev
- php artisan storage:link
- php artisan backpack:refresh
- Source file permission hardening

4) front-prod
- node:20-bookworm-slim
- pnpm via corepack
- Build Next.js
- Build-time variables from .env.frontend

---

# 4. entrypoint.sh

Responsibilities:

- Symlink $HOME/codebase -> $CODEBASE
- SSH runtime initialization (/run/ssh)
- Apache log initialization
- Backend runtime directory creation
- Laravel cache:
  - optimize:clear
  - config:cache
  - route:cache
  - event:cache
  - view:cache
- Dev mode:
  - composer install automatic if needed
  - pnpm install automatic if needed
- Final privilege drop with gosu

---

# 5. apache.conf

VirtualHost:

- DocumentRoot: ${CODEBASE}/backend/public
- AllowOverride All
- Require all granted

---

# 6. redis.conf

Configuration:

- bind 0.0.0.0
- protected-mode yes
- requirepass enabled via runtime command
- No persistence:
  - save ""
  - appendonly no
- maxmemory 8gb
- maxmemory-policy noeviction
- Disabled commands:
  - FLUSHALL
  - FLUSHDB
  - SHUTDOWN
  - DEBUG
  - KEYS
  - CONFIG

---

# 7. Environment variables

## 7.1 .env (compose-level)

Contains:

- COMPOSE_PROFILES (dev/prod)
- TAG_NAME
- PHP_APACHE_IMAGE
- MYSQL_IMAGE
- REDIS_IMAGE
- Production ports
- CODEBASE
- UID / GID

## 7.2 .env.backend

Contains:

- Laravel config (APP_ENV, APP_DEBUG, APP_URL)
- DB config (DB_HOST=database)
- Redis config (REDIS_HOST=session)
- Session driver redis
- Sanctum domains
- SMTP config
- Pulse config
- SSH remote config

## 7.3 .env.frontend

Contains:

- NEXT_PUBLIC_BACKEND_URL_CLIENT
- NEXT_BACKEND_URL_SERVER
- NEXT_APP_URL
- NEXT_PUBLIC_* client variables

---

# 8. Infrastructure services

## MySQL (database)

- Image: mysql:8.4
- Volume: database-data
- Credentials via Docker secrets
- Exposed internally only (3306)
- Defined in common compose
- In production not used operationally but not removed from file

## Redis (session)

- Image: redis:7
- Volume: session-data
- Password via Docker secret
- No host port publishing

## Backend (webserver)

- Apache + Laravel
- Internal port 80
- Bind on 127.0.0.1 on host

## Worker

- Runs Horizon + Pulse worker

## Scheduler

- Runs schedule:work

## Frontend (prod)

- Separate Next runtime
- Internal port 3000

---

# 9. Data persistence

Persistent:

- MySQL (database-data)
- Redis (session-data, runtime memory only)
- Backend storage (named prod volumes)
- Shared frontend translations

Non-persistent:

- Container filesystem
- Laravel cache (regenerated at startup)

---

# 10. Operational modes

Development:

- make up
- Bind mount codebase
- Next dev integrated

Production:

- COMPOSE_PROFILES=prod
- make up
- Backend and frontend on configured ports
- MySQL container remains in common but is not used operationally

---

# 11. Agent conventions

## Running commands inside containers

- Always run app-level commands as the app user:
  `docker compose exec -u codeuser webserver <cmd>`
- Root execs write root-owned files into the bind-mounted codebase
  (Laravel caches, composer, pnpm, files under `/home/codeuser`) and break
  later runs as `codeuser` — this has already happened with artisan caches
  and the pnpm store.
- Reserve root for paths only root can write (system dirs, package installs).
- The same user rule applies however the agent runs: on the host via
  `docker compose exec -u codeuser`, or inside the container shell itself
  (where commands already run as `codeuser` by default).
- The repo `Makefile` orchestrates the stack from the host and is not
  mounted inside containers. The `make` binary itself ships with the
  Debian base image, so a separate set of container-side targets may
  exist independently of it.

</frontend-boost-guidelines>
