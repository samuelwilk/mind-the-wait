# CI/CD Workflow with GitHub Actions and Release Please

## Overview

This document outlines the complete CI/CD pipeline for automated deployments using:
- **Release Please**: Automated release management and changelog generation
- **GitHub Actions**: CI/CD automation
- **AWS ECR**: Container registry
- **AWS ECS**: Deployment target

## Workflow Diagram

```
Developer Push → main branch
         ↓
    GitHub Actions: Test & Lint
         ↓
    Release Please Bot → Creates/Updates Release PR
         ↓
    Developer Reviews & Merges Release PR
         ↓
    Release Please → Creates GitHub Release + Git Tag
         ↓
    GitHub Actions: Build & Deploy
         ↓
    Build Docker Images (php, pyparser)
         ↓
    Push to AWS ECR with tags (:latest, :v1.2.3)
         ↓
    Update ECS Task Definitions
         ↓
    Deploy to ECS with Rolling Update
         ↓
    Health Check Verification
         ↓
    Notify on Success/Failure (Slack, Email)
```

## GitHub Secrets Configuration

### Required Secrets (Settings → Secrets → Actions)

```yaml
# AWS Credentials
AWS_ACCESS_KEY_ID: AKIA... (IAM user with ECR + ECS permissions)
AWS_SECRET_ACCESS_KEY: (secret key)
AWS_REGION: us-east-1

# AWS Resource Names
ECR_REPOSITORY_PHP: mind-the-wait/php
ECR_REPOSITORY_PYPARSER: mind-the-wait/pyparser
ECS_CLUSTER: mind-the-wait-prod
ECS_SERVICE_PHP: mind-the-wait-prod-php
ECS_SERVICE_PYPARSER: mind-the-wait-prod-pyparser
ECS_SERVICE_SCHEDULER: mind-the-wait-prod-scheduler

# Application Secrets (for deployment)
DATABASE_URL: postgresql://user:pass@host:5432/db
REDIS_URL: redis://host:6379
OPENAI_API_KEY: sk-...
GTFS_STATIC_URL: https://...

# Notifications (optional)
SLACK_WEBHOOK_URL: https://hooks.slack.com/...
```

### IAM User Permissions

Create IAM user `github-actions-deploy` with policy:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "ecr:GetAuthorizationToken",
        "ecr:BatchCheckLayerAvailability",
        "ecr:GetDownloadUrlForLayer",
        "ecr:BatchGetImage",
        "ecr:PutImage",
        "ecr:InitiateLayerUpload",
        "ecr:UploadLayerPart",
        "ecr:CompleteLayerUpload"
      ],
      "Resource": "*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "ecs:UpdateService",
        "ecs:DescribeServices",
        "ecs:DescribeTaskDefinition",
        "ecs:RegisterTaskDefinition",
        "ecs:ListTasks",
        "ecs:DescribeTasks"
      ],
      "Resource": "*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "iam:PassRole"
      ],
      "Resource": "arn:aws:iam::*:role/ecsTaskExecutionRole"
    }
  ]
}
```

## Release Please Configuration

**.github/release-please-config.json**
```json
{
  "release-type": "simple",
  "packages": {
    ".": {
      "release-type": "simple",
      "changelog-sections": [
        { "type": "feat", "section": "Features", "hidden": false },
        { "type": "fix", "section": "Bug Fixes", "hidden": false },
        { "type": "perf", "section": "Performance Improvements", "hidden": false },
        { "type": "revert", "section": "Reverts", "hidden": false },
        { "type": "docs", "section": "Documentation", "hidden": false },
        { "type": "style", "section": "Styles", "hidden": true },
        { "type": "chore", "section": "Miscellaneous", "hidden": true },
        { "type": "refactor", "section": "Code Refactoring", "hidden": false },
        { "type": "test", "section": "Tests", "hidden": true },
        { "type": "build", "section": "Build System", "hidden": true },
        { "type": "ci", "section": "CI/CD", "hidden": true }
      ],
      "extra-files": [
        "composer.json"
      ]
    }
  }
}
```

**.github/release-please-manifest.json**
```json
{
  ".": "0.0.0"
}
```

## GitHub Actions Workflows

### 1. Release Please Workflow

**.github/workflows/release-please.yml**
```yaml
name: Release Please

on:
  push:
    branches:
      - main

permissions:
  contents: write
  pull-requests: write

jobs:
  release-please:
    runs-on: ubuntu-latest
    outputs:
      release_created: ${{ steps.release.outputs.release_created }}
      tag_name: ${{ steps.release.outputs.tag_name }}
      version: ${{ steps.release.outputs.major }}.${{ steps.release.outputs.minor }}.${{ steps.release.outputs.patch }}

    steps:
      - name: Run Release Please
        id: release
        uses: google-github-actions/release-please-action@v4
        with:
          release-type: simple
          package-name: mind-the-wait
          token: ${{ secrets.GITHUB_TOKEN }}
```

### 2. Test & Lint Workflow

**.github/workflows/test.yml**
```yaml
name: Test & Lint

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

jobs:
  php-cs-fixer:
    name: PHP CS Fixer
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pdo, pdo_pgsql, redis

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run PHP CS Fixer (dry run)
        run: vendor/bin/php-cs-fixer fix --dry-run --diff

  phpunit:
    name: PHPUnit Tests
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_USER: test
          POSTGRES_PASSWORD: test
          POSTGRES_DB: mindthewait_test
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
        ports:
          - 5432:5432

      redis:
        image: redis:7
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
        ports:
          - 6379:6379

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pdo, pdo_pgsql, redis
          coverage: pcov

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run database migrations
        run: php bin/console doctrine:migrations:migrate --no-interaction
        env:
          DATABASE_URL: postgresql://test:test@localhost:5432/mindthewait_test

      - name: Run PHPUnit tests
        run: vendor/bin/phpunit --coverage-text
        env:
          DATABASE_URL: postgresql://test:test@localhost:5432/mindthewait_test
          REDIS_URL: redis://localhost:6379
          OPENAI_API_KEY: test-key

  docker-build-test:
    name: Docker Build Test
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Build PHP image
        uses: docker/build-push-action@v5
        with:
          context: .
          file: ./Dockerfile
          target: php
          push: false
          cache-from: type=gha
          cache-to: type=gha,mode=max

      - name: Build Python parser image
        uses: docker/build-push-action@v5
        with:
          context: ./pyparser
          file: ./pyparser/Dockerfile
          push: false
          cache-from: type=gha
          cache-to: type=gha,mode=max
```

### 3. Deploy to Production Workflow

**.github/workflows/deploy-production.yml**
```yaml
name: Deploy to Production

on:
  release:
    types: [published]

env:
  AWS_REGION: ${{ secrets.AWS_REGION }}
  ECR_REPOSITORY_PHP: ${{ secrets.ECR_REPOSITORY_PHP }}
  ECR_REPOSITORY_PYPARSER: ${{ secrets.ECR_REPOSITORY_PYPARSER }}
  ECS_CLUSTER: ${{ secrets.ECS_CLUSTER }}
  ECS_SERVICE_PHP: ${{ secrets.ECS_SERVICE_PHP }}
  ECS_SERVICE_PYPARSER: ${{ secrets.ECS_SERVICE_PYPARSER }}
  ECS_SERVICE_SCHEDULER: ${{ secrets.ECS_SERVICE_SCHEDULER }}

jobs:
  deploy:
    name: Deploy to AWS ECS
    runs-on: ubuntu-latest
    environment: production

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Configure AWS credentials
        uses: aws-actions/configure-aws-credentials@v4
        with:
          aws-access-key-id: ${{ secrets.AWS_ACCESS_KEY_ID }}
          aws-secret-access-key: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
          aws-region: ${{ env.AWS_REGION }}

      - name: Login to Amazon ECR
        id: login-ecr
        uses: aws-actions/amazon-ecr-login@v2

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Extract version from tag
        id: version
        run: |
          VERSION=${GITHUB_REF#refs/tags/v}
          echo "version=$VERSION" >> $GITHUB_OUTPUT
          echo "Deploying version: $VERSION"

      - name: Build and push PHP image
        id: build-php
        uses: docker/build-push-action@v5
        with:
          context: .
          file: ./Dockerfile
          target: php
          push: true
          tags: |
            ${{ steps.login-ecr.outputs.registry }}/${{ env.ECR_REPOSITORY_PHP }}:latest
            ${{ steps.login-ecr.outputs.registry }}/${{ env.ECR_REPOSITORY_PHP }}:${{ steps.version.outputs.version }}
          cache-from: type=gha
          cache-to: type=gha,mode=max

      - name: Build and push Python parser image
        id: build-pyparser
        uses: docker/build-push-action@v5
        with:
          context: ./pyparser
          file: ./pyparser/Dockerfile
          push: true
          tags: |
            ${{ steps.login-ecr.outputs.registry }}/${{ env.ECR_REPOSITORY_PYPARSER }}:latest
            ${{ steps.login-ecr.outputs.registry }}/${{ env.ECR_REPOSITORY_PYPARSER }}:${{ steps.version.outputs.version }}
          cache-from: type=gha
          cache-to: type=gha,mode=max

      - name: Download current PHP task definition
        id: download-taskdef-php
        run: |
          aws ecs describe-task-definition \
            --task-definition ${{ env.ECS_SERVICE_PHP }} \
            --query taskDefinition > task-definition-php.json
          cat task-definition-php.json

      - name: Update PHP task definition
        id: taskdef-php
        uses: aws-actions/amazon-ecs-render-task-definition@v1
        with:
          task-definition: task-definition-php.json
          container-name: php
          image: ${{ steps.login-ecr.outputs.registry }}/${{ env.ECR_REPOSITORY_PHP }}:${{ steps.version.outputs.version }}

      - name: Deploy PHP service to ECS
        uses: aws-actions/amazon-ecs-deploy-task-definition@v1
        with:
          task-definition: ${{ steps.taskdef-php.outputs.task-definition }}
          service: ${{ env.ECS_SERVICE_PHP }}
          cluster: ${{ env.ECS_CLUSTER }}
          wait-for-service-stability: true
          wait-for-minutes: 10

      - name: Download current pyparser task definition
        id: download-taskdef-pyparser
        run: |
          aws ecs describe-task-definition \
            --task-definition ${{ env.ECS_SERVICE_PYPARSER }} \
            --query taskDefinition > task-definition-pyparser.json

      - name: Update pyparser task definition
        id: taskdef-pyparser
        uses: aws-actions/amazon-ecs-render-task-definition@v1
        with:
          task-definition: task-definition-pyparser.json
          container-name: pyparser
          image: ${{ steps.login-ecr.outputs.registry }}/${{ env.ECR_REPOSITORY_PYPARSER }}:${{ steps.version.outputs.version }}

      - name: Deploy pyparser service to ECS
        uses: aws-actions/amazon-ecs-deploy-task-definition@v1
        with:
          task-definition: ${{ steps.taskdef-pyparser.outputs.task-definition }}
          service: ${{ env.ECS_SERVICE_PYPARSER }}
          cluster: ${{ env.ECS_CLUSTER }}
          wait-for-service-stability: true
          wait-for-minutes: 10

      - name: Download current scheduler task definition
        id: download-taskdef-scheduler
        run: |
          aws ecs describe-task-definition \
            --task-definition ${{ env.ECS_SERVICE_SCHEDULER }} \
            --query taskDefinition > task-definition-scheduler.json

      - name: Update scheduler task definition (uses PHP image)
        id: taskdef-scheduler
        uses: aws-actions/amazon-ecs-render-task-definition@v1
        with:
          task-definition: task-definition-scheduler.json
          container-name: scheduler
          image: ${{ steps.login-ecr.outputs.registry }}/${{ env.ECR_REPOSITORY_PHP }}:${{ steps.version.outputs.version }}

      - name: Deploy scheduler service to ECS
        uses: aws-actions/amazon-ecs-deploy-task-definition@v1
        with:
          task-definition: ${{ steps.taskdef-scheduler.outputs.task-definition }}
          service: ${{ env.ECS_SERVICE_SCHEDULER }}
          cluster: ${{ env.ECS_CLUSTER }}
          wait-for-service-stability: true
          wait-for-minutes: 10

      - name: Verify deployment health
        run: |
          # Wait 30 seconds for ALB health checks
          sleep 30

          # Get ALB DNS from Terraform outputs or hardcode
          ALB_URL="https://yourdomain.com/api/realtime"

          # Health check
          HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" $ALB_URL)

          if [ $HTTP_CODE -eq 200 ]; then
            echo "✅ Health check passed (HTTP $HTTP_CODE)"
          else
            echo "❌ Health check failed (HTTP $HTTP_CODE)"
            exit 1
          fi

      - name: Notify Slack on success
        if: success()
        uses: slackapi/slack-github-action@v1
        with:
          webhook: ${{ secrets.SLACK_WEBHOOK_URL }}
          webhook-type: incoming-webhook
          payload: |
            {
              "text": "🚀 Deployment successful!",
              "blocks": [
                {
                  "type": "section",
                  "text": {
                    "type": "mrkdwn",
                    "text": "*Deployment Successful* ✅\n*Version:* ${{ steps.version.outputs.version }}\n*Environment:* Production\n*Release:* <${{ github.event.release.html_url }}|${{ github.event.release.name }}>"
                  }
                }
              ]
            }

      - name: Notify Slack on failure
        if: failure()
        uses: slackapi/slack-github-action@v1
        with:
          webhook: ${{ secrets.SLACK_WEBHOOK_URL }}
          webhook-type: incoming-webhook
          payload: |
            {
              "text": "❌ Deployment failed!",
              "blocks": [
                {
                  "type": "section",
                  "text": {
                    "type": "mrkdwn",
                    "text": "*Deployment Failed* ❌\n*Version:* ${{ steps.version.outputs.version }}\n*Environment:* Production\n*Logs:* <${{ github.server_url }}/${{ github.repository }}/actions/runs/${{ github.run_id }}|View Logs>"
                  }
                }
              ]
            }

      - name: Rollback on failure
        if: failure()
        run: |
          echo "Deployment failed. ECS will automatically rollback to previous task definition."
          echo "Check ECS console for rollback status."
```

### 4. Manual Rollback Workflow

**.github/workflows/rollback.yml**
```yaml
name: Manual Rollback

on:
  workflow_dispatch:
    inputs:
      version:
        description: 'Version to rollback to (e.g., v1.2.0)'
        required: true
        type: string

env:
  AWS_REGION: ${{ secrets.AWS_REGION }}
  ECR_REPOSITORY_PHP: ${{ secrets.ECR_REPOSITORY_PHP }}
  ECR_REPOSITORY_PYPARSER: ${{ secrets.ECR_REPOSITORY_PYPARSER }}
  ECS_CLUSTER: ${{ secrets.ECS_CLUSTER }}
  ECS_SERVICE_PHP: ${{ secrets.ECS_SERVICE_PHP }}
  ECS_SERVICE_PYPARSER: ${{ secrets.ECS_SERVICE_PYPARSER }}
  ECS_SERVICE_SCHEDULER: ${{ secrets.ECS_SERVICE_SCHEDULER }}

jobs:
  rollback:
    name: Rollback to ${{ inputs.version }}
    runs-on: ubuntu-latest
    environment: production

    steps:
      - name: Checkout code
        uses: actions/checkout@v4
        with:
          ref: ${{ inputs.version }}

      - name: Configure AWS credentials
        uses: aws-actions/configure-aws-credentials@v4
        with:
          aws-access-key-id: ${{ secrets.AWS_ACCESS_KEY_ID }}
          aws-secret-access-key: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
          aws-region: ${{ env.AWS_REGION }}

      - name: Login to Amazon ECR
        id: login-ecr
        uses: aws-actions/amazon-ecr-login@v2

      - name: Extract version
        id: version
        run: |
          VERSION=${{ inputs.version }}
          VERSION=${VERSION#v}
          echo "version=$VERSION" >> $GITHUB_OUTPUT

      - name: Verify image exists
        run: |
          aws ecr describe-images \
            --repository-name ${{ env.ECR_REPOSITORY_PHP }} \
            --image-ids imageTag=${{ steps.version.outputs.version }}

      - name: Update task definitions to rollback version
        run: |
          # Similar steps as deploy but with older version tag
          echo "Rolling back to version: ${{ steps.version.outputs.version }}"
          # ... (task definition update logic)

      - name: Notify Slack
        uses: slackapi/slack-github-action@v1
        with:
          webhook: ${{ secrets.SLACK_WEBHOOK_URL }}
          webhook-type: incoming-webhook
          payload: |
            {
              "text": "⏪ Manual rollback to ${{ inputs.version }} initiated by ${{ github.actor }}"
            }
```

## Commit Message Convention

Release Please uses Conventional Commits to determine version bumps:

```
feat: Add new feature             → Minor version bump (1.0.0 → 1.1.0)
fix: Fix bug                      → Patch version bump (1.0.0 → 1.0.1)
perf: Improve performance         → Patch version bump
docs: Update documentation        → No version bump
chore: Update dependencies        → No version bump
refactor: Refactor code           → No version bump (unless breaking)

feat!: Breaking change            → Major version bump (1.0.0 → 2.0.0)
fix!: Breaking bug fix            → Major version bump
```

### Example Commits

```bash
git commit -m "feat: add AI-generated insights for weather analysis"
# → Minor bump: 1.0.0 → 1.1.0

git commit -m "fix: resolve cache warming race condition"
# → Patch bump: 1.1.0 → 1.1.1

git commit -m "feat!: migrate to PostgreSQL 17

BREAKING CHANGE: Requires PostgreSQL 17. Migration steps in docs/MIGRATION.md"
# → Major bump: 1.1.1 → 2.0.0
```

## Deployment Process

### 1. Normal Development Flow

```bash
# 1. Developer makes changes
git checkout -b feature/new-dashboard
# ... make changes ...
git commit -m "feat: add route comparison dashboard"
git push origin feature/new-dashboard

# 2. Create PR to main
# GitHub Actions runs tests automatically

# 3. Merge PR to main
# Release Please bot opens/updates release PR

# 4. Review Release PR
# Check CHANGELOG.md, version bump

# 5. Merge Release PR
# Release Please creates GitHub Release

# 6. Deploy workflow triggers automatically
# Builds Docker images → Pushes to ECR → Deploys to ECS
```

### 2. Hotfix Flow

```bash
# 1. Create hotfix branch from main
git checkout -b hotfix/critical-bug
git commit -m "fix: resolve database connection leak"
git push

# 2. Create PR to main, merge immediately

# 3. Merge release PR (patch bump)

# 4. Automatic deployment
```

### 3. Manual Deployment

```bash
# Trigger deploy workflow manually
gh workflow run deploy-production.yml
```

### 4. Rollback

```bash
# From GitHub UI or CLI
gh workflow run rollback.yml -f version=v1.2.0
```

## Monitoring Deployments

### CloudWatch Logs

```bash
# View ECS logs
aws logs tail /ecs/mind-the-wait-prod/php --follow

# Filter for errors
aws logs filter-log-events \
  --log-group-name /ecs/mind-the-wait-prod/php \
  --filter-pattern "ERROR"
```

### ECS Service Status

```bash
# Check service health
aws ecs describe-services \
  --cluster mind-the-wait-prod \
  --services mind-the-wait-prod-php

# View running tasks
aws ecs list-tasks \
  --cluster mind-the-wait-prod \
  --service-name mind-the-wait-prod-php
```

### ALB Health Checks

```bash
# Check target health
aws elbv2 describe-target-health \
  --target-group-arn <target-group-arn>
```

## Cost of CI/CD

### GitHub Actions Minutes
- Public repo: **FREE** (unlimited)
- Private repo: 2,000 minutes/month free
  - Average build: ~5 minutes
  - Deployments/month: ~20
  - Total: ~100 minutes/month
  - **Cost: $0** (within free tier)

### ECR Storage
- Image size: ~500 MB per image × 2 images = 1 GB
- Keep last 10 versions: ~10 GB
- Cost: 10 GB × $0.10/GB = **$1/month**

### Data Transfer
- ECR → ECS: **FREE** (within same region)

### Total CI/CD Cost: **~$1/month**

## Security Best Practices

1. **Never commit secrets** to repository
2. **Use GitHub environments** with required reviewers for production
3. **Enable branch protection** on main branch
4. **Require status checks** before merge
5. **Use AWS IAM least-privilege** for GitHub Actions user
6. **Scan Docker images** for vulnerabilities (ECR scanning)
7. **Rotate AWS keys** every 90 days
8. **Use OIDC** instead of long-lived keys (advanced)

## Next Steps

1. Set up GitHub secrets
2. Create IAM user with deployment permissions
3. Add workflow files to repository
4. Test with staging environment first
5. Configure Slack notifications
6. Document runbooks for common issues
