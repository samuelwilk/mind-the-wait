# GitHub Environments Setup

This guide shows you how to configure GitHub Environments for secure, controlled deployments to production.

## What are GitHub Environments?

GitHub Environments provide:
- **Environment-specific secrets**: Different secrets for staging vs production
- **Protection rules**: Require approvals before deployment
- **Deployment branches**: Only deploy from specific branches
- **Environment variables**: Configuration per environment
- **Deployment history**: Track what was deployed when

## Why Use Environments?

For mind-the-wait, GitHub Environments provide:
1. **Security**: Production secrets isolated from development
2. **Control**: Optional manual approval before production deployment
3. **Audit trail**: See all production deployments in one place
4. **Branch protection**: Only deploy to production from main branch
5. **Visibility**: Clear deployment status in PRs and commits

## Creating the Production Environment

### Step 1: Navigate to Environment Settings

1. Go to your GitHub repository: `https://github.com/samuelwilk/mind-the-wait`
2. Click **Settings** (top navigation)
3. In the left sidebar, click **Environments**
4. Click **New environment**
5. Name it: `production`
6. Click **Configure environment**

### Step 2: Configure Environment Protection Rules (Optional)

These are optional but recommended for production safety:

**Required reviewers** (optional for solo projects):
- Check "Required reviewers"
- Add yourself or team members
- Deployments will pause until approved

**Wait timer** (optional):
- Set a wait time (e.g., 5 minutes)
- Gives you time to cancel if needed

**Deployment branches**:
- Select "Protected branches only" or "Selected branches"
- Choose `main` branch
- This prevents deploying from feature branches

### Step 3: Add Environment Secrets

Scroll down to **Environment secrets** section and add these 6 secrets:

| Secret Name | Value | How to Get |
|------------|-------|------------|
| `AWS_ACCESS_KEY_ID` | Your IAM access key | From IAM user `terraform-mind-the-wait` |
| `AWS_SECRET_ACCESS_KEY` | Your IAM secret key | From IAM user `terraform-mind-the-wait` |
| `AWS_ACCOUNT_ID` | Your AWS account ID | Run: `aws sts get-caller-identity --query Account --output text --profile mind-the-wait` |
| `ECS_SERVICE_PHP` | `mind-the-wait-prod-php` | From Terraform output after deployment |
| `ECS_SERVICE_PYPARSER` | `mind-the-wait-prod-pyparser` | From Terraform output after deployment |
| `ECS_SERVICE_SCHEDULER` | `mind-the-wait-prod-scheduler` | From Terraform output after deployment |

**To add each secret:**
1. Click **Add secret**
2. Enter **Name** (exactly as shown above)
3. Enter **Value**
4. Click **Add secret**

### Step 4: Verify Configuration

After setup, your production environment should show:
- **Name**: production
- **Protection rules**: (Optional) Reviewers, wait timer, branch restrictions
- **Secrets**: 6 secrets configured
- **Deployment branches**: main (or protected branches)

## Environment vs Repository Secrets

### Repository Secrets (Not Recommended for Production)

❌ Available to all workflows
❌ No deployment controls
❌ No approval workflow
❌ Harder to audit

### Environment Secrets (Recommended for Production)

✅ Only available when deploying to that environment
✅ Can require approvals
✅ Can restrict to specific branches
✅ Clear deployment history
✅ Better security isolation

## Updated Workflow Behavior

With GitHub Environments configured:

```yaml
deploy:
  name: Deploy to ECS
  runs-on: ubuntu-latest
  environment: production  # ← Uses production environment
  needs: build-and-push
```

**What happens:**
1. Tests pass → Build starts
2. Build completes → Deployment job starts
3. **Environment check**: Is this from main branch?
4. **Approval (optional)**: Wait for reviewer approval
5. **Deploy**: Run deployment with environment secrets
6. **History**: Deployment recorded in environment

## Getting Environment Secrets in Workflow

Secrets from the environment are accessed the same way:

```yaml
- name: Configure AWS credentials
  uses: aws-actions/configure-aws-credentials@v4
  with:
    aws-access-key-id: ${{ secrets.AWS_ACCESS_KEY_ID }}
    aws-secret-access-key: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
    aws-region: ca-central-1
```

The difference is these secrets come from the **production environment** not the repository.

## Viewing Deployment History

To see all production deployments:

1. Go to your repository
2. Click **Code** tab
3. On the right sidebar, click **Environments**
4. Click **production**
5. See all deployments with:
   - When deployed
   - Who deployed
   - Commit SHA
   - Status (success/failure)
   - Logs link

## Manual Deployment with Environments

To manually trigger a deployment:

1. Go to **Actions** tab
2. Click **Deploy to Production** workflow
3. Click **Run workflow**
4. Select `main` branch
5. (Optional) Enter image tag
6. Click **Run workflow**
7. If approvals required, approve the deployment

## Multiple Environments (Future)

You can create additional environments later:

**staging** environment:
- Auto-deploy on push to `develop` branch
- No approval required
- Separate AWS account or different ECS cluster

**development** environment:
- Auto-deploy on PR creation
- Short-lived environment
- Lower resource limits

## Costs

GitHub Environments are free for:
- Public repositories
- GitHub Free/Pro/Team/Enterprise

No additional cost for using environments!

## Security Best Practices

1. **Never use repository secrets for production**: Always use environment secrets
2. **Enable branch protection**: Only deploy from main
3. **Require approvals for critical environments**: Add yourself as reviewer
4. **Rotate secrets regularly**: Update environment secrets every 90 days
5. **Monitor deployment logs**: Check CloudWatch after each deployment
6. **Use wait timers**: Give yourself time to cancel if needed

## Troubleshooting

### "Environment not found" error in workflow

**Problem**: Workflow references an environment that doesn't exist.

**Solution**:
1. Check environment name spelling (case-sensitive)
2. Ensure environment is created in repository settings
3. Verify workflow has `environment: production` in job definition

### "Environment secret not found" error

**Problem**: Workflow can't access a secret.

**Solution**:
1. Verify secret exists in the **environment** (not repository)
2. Check secret name spelling (case-sensitive)
3. Ensure deployment is using the correct environment

### Deployment stuck on "Waiting for approval"

**Problem**: Required reviewer hasn't approved.

**Solution**:
1. Check your email for approval notification
2. Go to Actions → Click the workflow run
3. Click **Review deployments**
4. Select **production** and click **Approve and deploy**

### "Branch not allowed to deploy" error

**Problem**: Trying to deploy from non-main branch.

**Solution**:
1. Only deploy from main branch with environments enabled
2. Or update environment deployment branches settings
3. Merge your feature branch to main first

## Example: Adding Staging Environment

If you want to add a staging environment later:

```bash
# 1. Create staging environment in GitHub UI
#    Name: staging
#    No approval required
#    Deploy from: develop branch

# 2. Create separate workflow: .github/workflows/deploy-staging.yml
on:
  push:
    branches: [develop]

jobs:
  deploy:
    environment: staging  # ← Use staging environment
    steps:
      - name: Deploy to staging cluster
        run: |
          aws ecs update-service \
            --cluster mind-the-wait-staging \
            --service php-staging \
            ...
```

## Reference

- [GitHub Environments Documentation](https://docs.github.com/en/actions/deployment/targeting-different-environments/using-environments-for-deployment)
- [Environment Protection Rules](https://docs.github.com/en/actions/deployment/targeting-different-environments/using-environments-for-deployment#environment-protection-rules)
- [Deployment History](https://docs.github.com/en/actions/deployment/managing-your-deployments/viewing-deployment-history)

## Next Steps

After setting up the production environment:

1. ✅ Environment created with protection rules
2. ✅ 6 secrets added to environment
3. ✅ Deployment branches restricted to main
4. ⏳ Push code to GitHub
5. ⏳ Watch first deployment (manual approval if enabled)
6. ⏳ Verify deployment in environment history
