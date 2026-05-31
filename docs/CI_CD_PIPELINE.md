# CI/CD Pipeline Documentation

## Overview

This project uses **GitHub Actions** for continuous integration and deployment, and **Docker DevContainers** for consistent development environments.

## Pipeline Architecture

```
PR opened/updated
    ├── Lint (Laravel Pint code style)
    ├── Security Audit (composer audit)
    └── Dockerfile Lint (hadolint)
         │
         ▼
    Tests (PHPUnit with PostgreSQL)
         │
         ▼ (main branch only)
    Build Docker Image → Push to GHCR
         │
         ▼
    Deploy to Render (production environment)
```

## Workflows

### 1. CI/CD Pipeline (`.github/workflows/ci.yml`)

Triggered on:
- Push to `main` or `develop`
- Pull requests targeting `main`

**Jobs:**

| Job | Purpose | Runs On |
|-----|---------|---------|
| `lint` | Code style check with Laravel Pint | Every push/PR |
| `test` | PHPUnit tests against PostgreSQL 16 | After lint passes |
| `build` | Build & push Docker image to GHCR | Main branch only |
| `deploy` | Trigger Render deployment | After successful build |

### 2. PR Checks (`.github/workflows/pr-checks.yml`)

Additional checks for pull requests:
- **Security Audit**: Checks for known vulnerabilities in dependencies
- **Docker Lint**: Validates Dockerfile best practices

## DevContainer

The `.devcontainer/` directory provides a consistent development environment:

- **PHP 8.2** with all required extensions
- **PostgreSQL 16** database
- **Node.js 20** for frontend tooling
- **GitHub CLI** for PR/issue management

### Usage

1. Open the project in VS Code
2. When prompted, click "Reopen in Container"
3. Wait for the container to build (first time only)
4. The environment is ready with all dependencies installed

## Required Secrets

Configure these in GitHub repository settings → Secrets and variables → Actions:

| Secret | Description | Required For |
|--------|-------------|--------------|
| `RENDER_DEPLOY_HOOK_URL` | Render deploy hook URL | Deploy job |

> Note: `GITHUB_TOKEN` is automatically provided by GitHub Actions.

## Required Environments

Create a `production` environment in GitHub repository settings → Environments:
- Add required reviewers for manual approval before deploy
- Add the `RENDER_DEPLOY_HOOK_URL` secret to this environment

## Local Development

### With DevContainer (Recommended)

```bash
# VS Code will prompt to reopen in container
# Or use command palette: "Dev Containers: Reopen in Container"
```

### Without DevContainer

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## Docker Build (Production)

```bash
# Build production image
docker build -f Dockerfile.production -t signature-backend:latest .

# Run production image
docker run -p 8000:8000 --env-file .env signature-backend:latest
```
