# GitHub Secrets Configuration

This guide shows you how to configure GitHub Environment secrets for automated CI/CD workflows.

## Using GitHub Environments (Recommended)

**Important**: This project uses **GitHub Environments** for better security and deployment control. Secrets should be added to the **production environment**, not as repository secrets.

See `docs/infrastructure/github-environments-setup.md` for full environment setup instructions.

## Required Secrets

The following secrets must be configured in your GitHub **production environment** for the deployment workflows to function:

| Secret Name | Description | How to Get Value |
|------------|-------------|------------------|
| `AWS_ACCESS_KEY_ID` | AWS IAM access key ID | From IAM user `terraform-mind-the-wait` credentials |
| `AWS_SECRET_ACCESS_KEY` | AWS IAM secret access key | From IAM user `terraform-mind-the-wait` credentials |
| `AWS_ACCOUNT_ID` | Your AWS account ID (12-digit number) | Run: `aws sts get-caller-identity --query Account --output text --profile mind-the-wait` |
| `ECS_SERVICE_PHP` | ECS service name for PHP app | From Terraform output after deployment: `mind-the-wait-prod-php` |
| `ECS_SERVICE_PYPARSER` | ECS service name for Python parser | From Terraform output after deployment: `mind-the-wait-prod-pyparser` |
| `ECS_SERVICE_SCHEDULER` | ECS service name for Scheduler | From Terraform output after deployment: `mind-the-wait-prod-scheduler` |

## Step-by-Step Setup

### 1. Get Your AWS Account ID

```bash
aws sts get-caller-identity --query Account --output text --profile mind-the-wait
```

Save this 12-digit number - you'll need it for the `AWS_ACCOUNT_ID` secret.

### 2. Locate Your AWS Credentials

Your AWS access key credentials were created when you set up the IAM user `terraform-mind-the-wait`. If you saved them during setup:

- **Access Key ID**: Looks like `AKIAIOSFODNN7EXAMPLE`
- **Secret Access Key**: Looks like `wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY`

⚠️ **If you don't have these saved**, you'll need to create new credentials:

```bash
# Delete old access key (if you want to replace it)
aws iam list-access-keys --user-name terraform-mind-the-wait --profile mind-the-wait
aws iam delete-access-key --user-name terraform-mind-the-wait --access-key-id AKIAIOSFODNN7EXAMPLE --profile mind-the-wait

# Create new access key
aws iam create-access-key --user-name terraform-mind-the-wait --profile mind-the-wait
```

### 3. Add Secrets to GitHub Production Environment

1. **Navigate to your repository on GitHub**: https://github.com/samuelwilk/mind-the-wait

2. **Go to Settings → Environments**:
   - Click on your repository
   - Click **Settings** (top right)
   - In the left sidebar, click **Environments**
   - Click **production** environment (create it first if it doesn't exist - see `github-environments-setup.md`)

3. **Scroll to Environment secrets section**:
   - Scroll down to the **Environment secrets** section

4. **Add each secret**:
   - Click **Add secret**
   - Enter the **Name** (exactly as shown in the table above)
   - Enter the **Value**
   - Click **Add secret**

5. **Repeat for all 6 secrets**:
   - `AWS_ACCESS_KEY_ID`
   - `AWS_SECRET_ACCESS_KEY`
   - `AWS_ACCOUNT_ID`
   - `ECS_SERVICE_PHP` (add after Terraform deployment)
   - `ECS_SERVICE_PYPARSER` (add after Terraform deployment)
   - `ECS_SERVICE_SCHEDULER` (add after Terraform deployment)

### 4. Get ECS Service Names After Deployment

The ECS service names will be available after you run `terraform apply`. Get them with:

```bash
cd /Users/sam/Repos/mind-the-wait/terraform/environments/prod
terraform output ecs_services
```

Expected output:
```
{
  "php" = "mind-the-wait-prod-php"
  "pyparser" = "mind-the-wait-prod-pyparser"
  "scheduler" = "mind-the-wait-prod-scheduler"
}
```

Add these three values as secrets in GitHub.

## Verify Secrets Configuration

After adding all secrets, you should see 6 secrets listed in your **production environment**:

1. Go to **Settings → Environments → production**
2. Scroll to **Environment secrets**
3. You should see:

```
AWS_ACCESS_KEY_ID          ••••••••
AWS_SECRET_ACCESS_KEY      ••••••••
AWS_ACCOUNT_ID             ••••••••
ECS_SERVICE_PHP            ••••••••
ECS_SERVICE_PYPARSER       ••••••••
ECS_SERVICE_SCHEDULER      ••••••••
```

## Security Best Practices

1. **Use environment secrets, not repository secrets**: Production secrets should only exist in the production environment
2. **Never commit secrets to Git**: All secrets should only exist in GitHub environment settings
3. **Rotate credentials regularly**: Update AWS access keys every 90 days
4. **Use least-privilege IAM policies**: The `terraform-mind-the-wait` user should only have permissions needed for deployment
5. **Enable branch protection**: Configure environment to only deploy from main branch
6. **Monitor access**: Enable CloudTrail to track API usage

## Workflow Behavior

Once environment and secrets are configured:

- **Push to main/develop**: Runs tests and linting (no secrets required)
- **Create PR**: Runs tests and linting (no secrets required)
- **Publish release**:
  1. Triggers build-and-push job (uses production environment)
  2. Requires approval if configured in environment settings
  3. Builds Docker images, pushes to ECR, deploys to ECS
- **Manual deployment**: Can be triggered via "Actions" tab → Run workflow

## Troubleshooting

### "Error: AWS credentials not found"

**Problem**: GitHub Actions can't authenticate with AWS.

**Solution**: Verify `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` are set correctly:
1. Go to Settings → Secrets and variables → Actions
2. Check both secrets exist and have no leading/trailing spaces
3. If uncertain, delete and recreate them

### "Error: Service not found"

**Problem**: ECS service names are incorrect or not set.

**Solution**: Run `terraform output ecs_services` and verify the secret values match exactly.

### "Error: AccessDenied"

**Problem**: IAM user lacks required permissions.

**Solution**: Verify the `terraform-mind-the-wait` IAM user has these policies:
- `AmazonECS_FullAccess`
- `AmazonEC2ContainerRegistryPowerUser`
- Or a custom policy with `ecs:UpdateService`, `ecs:DescribeServices`, `ecr:*` permissions

## Next Steps

After configuring secrets:

1. **Test the workflows**: Create a test PR to verify linting and tests run
2. **Deploy infrastructure**: Run `terraform apply` to create AWS resources
3. **Add ECS service secrets**: Add the three ECS service name secrets
4. **Create first release**: Release Please will create a release PR automatically
5. **Verify deployment**: After merging the release PR, check GitHub Actions logs

## Reference

- [GitHub Encrypted Secrets Documentation](https://docs.github.com/en/actions/security-guides/encrypted-secrets)
- [AWS IAM Best Practices](https://docs.aws.amazon.com/IAM/latest/UserGuide/best-practices.html)
- [Release Please Action](https://github.com/google-github-actions/release-please-action)
