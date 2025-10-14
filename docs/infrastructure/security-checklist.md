# Security Checklist - Pre-Commit Verification

This document verifies that no sensitive information will be committed to the repository.

## ✅ Files Protected by .gitignore

The following sensitive files are properly excluded:

### Terraform Secrets
- ✅ `terraform.tfvars` - Contains database password and OpenAI API key
- ✅ `.terraform/` - Terraform working directory
- ✅ `*.tfstate` - Terraform state files (may contain secrets)
- ✅ `tfplan` - Terraform plan files (may contain secrets)
- ✅ `.terraform.lock.hcl` - Lock file (included for reproducibility)

### AWS Credentials
- ✅ `.aws/` - AWS credentials directory
- ✅ `*.pem` - Private key files
- ✅ `*.key` - Key files (including docker/certs/*.key)
- ✅ `*.crt` - Certificate files

### Other Secrets
- ✅ `*secrets.json`, `*secrets.txt`, `*secrets.yml` - Secret data files
- ✅ `*password*.txt`, `*password*.json` - Password files
- ✅ `*credentials*.json`, `*credentials*.txt` - Credential files

## ✅ Safe Template Files Being Committed

These files contain NO real secrets - only placeholders:

1. **terraform.tfvars.budget**
   - Database password: `CHANGE_ME_STRONG_PASSWORD_32_CHARS_MIN`
   - OpenAI key: `sk-proj-...`

2. **terraform.tfvars.example**
   - Database password: `CHANGEME_GENERATE_SECURE_PASSWORD`
   - OpenAI key: `CHANGEME_YOUR_OPENAI_API_KEY`

## ✅ Documentation Files Being Committed

Safe documentation about security (no actual secrets):

- `docs/infrastructure/github-secrets-setup.md` - Instructions for adding secrets
- `docs/infrastructure/github-environments-setup.md` - GitHub Environments guide
- `docs/infrastructure/porkbun-dns-setup.md` - DNS configuration guide
- `docs/infrastructure/security-checklist.md` - This file

## ✅ Infrastructure Code Being Committed

All Terraform modules and configurations (no secrets):

- Terraform modules (8 modules)
- Terraform environment configs (variables, outputs, main.tf, backend.tf)
- GitHub Actions workflows
- Bootstrap scripts

## 🔒 Actual Secrets (NOT COMMITTED)

These files exist locally but are **properly excluded from git**:

| File | Location | Contains |
|------|----------|----------|
| `terraform.tfvars` | `terraform/environments/prod/` | Database password, OpenAI API key |
| `.terraform/` | `terraform/environments/prod/` | Terraform working directory |
| `tfplan` | `terraform/environments/prod/` | Terraform execution plan |

## Pre-Push Verification

Run these commands to verify no secrets will be pushed:

```bash
# 1. Verify terraform.tfvars is ignored
git check-ignore -v terraform/environments/prod/terraform.tfvars
# Expected output: .gitignore:35:terraform.tfvars

# 2. Search for any real OpenAI keys in staged files (should find none)
git diff --cached | grep -E "sk-proj-[a-zA-Z0-9]{20,}" | grep -v "sk-proj-\.\.\."
# Expected output: (empty)

# 3. Search for any real passwords in staged files (should find placeholders only)
git diff --cached | grep -E "database_password.*=" | grep -v "CHANGE"
# Expected output: (empty)

# 4. Check that actual secrets file is not staged
git status --short | grep "terraform.tfvars$"
# Expected output: (empty)
```

## Post-Push Security

After pushing to GitHub, verify:

1. **GitHub repository**: Browse files on GitHub, verify `terraform.tfvars` doesn't exist
2. **GitHub secrets**: Check that secrets are in Environment, not visible in code
3. **Commit history**: Use `git log -p | grep "sk-proj"` to verify no keys in history

## Emergency: Committed Secrets

If you accidentally commit secrets:

### Option 1: Remove from Latest Commit (Before Push)

```bash
# Remove sensitive file
git rm --cached terraform/environments/prod/terraform.tfvars

# Amend the commit
git commit --amend --no-edit
```

### Option 2: Remove from History (After Push)

**⚠️ This requires force-pushing and may disrupt collaborators**

```bash
# Use git-filter-repo (recommended) or BFG Repo-Cleaner
# Install: brew install git-filter-repo

# Remove file from all history
git filter-repo --path terraform/environments/prod/terraform.tfvars --invert-paths

# Force push
git push origin main --force

# IMMEDIATELY rotate all exposed secrets:
# 1. Generate new database password
# 2. Create new OpenAI API key
# 3. Update terraform.tfvars with new values
# 4. Update GitHub Environment secrets
# 5. Apply terraform changes
```

### Option 3: Rotate Secrets (Safest)

If secrets were pushed:

1. **Assume compromised**: Treat all exposed secrets as compromised
2. **Rotate immediately**:
   ```bash
   # Generate new database password
   openssl rand -base64 48 | head -c 32

   # Create new OpenAI API key
   # Go to: https://platform.openai.com/api-keys
   # Revoke old key, create new one

   # Update RDS password
   aws rds modify-db-instance \
     --db-instance-identifier mind-the-wait-prod \
     --master-user-password "new-password" \
     --apply-immediately \
     --profile mind-the-wait

   # Update ECS environment variables
   # (Will require task definition update and service restart)
   ```

3. **Clean git history**: Use Option 2 above
4. **Monitor for abuse**: Check AWS CloudTrail, OpenAI usage logs

## Security Best Practices

1. ✅ **Never commit .tfvars files**: Always use templates with placeholders
2. ✅ **Use environment secrets**: GitHub Environments for production secrets
3. ✅ **Rotate regularly**: Update secrets every 90 days
4. ✅ **Enable 2FA**: GitHub account, AWS root account, IAM users
5. ✅ **Monitor access**: AWS CloudTrail, GitHub audit log
6. ✅ **Principle of least privilege**: IAM policies with minimal permissions
7. ✅ **Review .gitignore**: Verify patterns before committing
8. ✅ **Use git hooks**: Pre-commit hooks to scan for secrets
9. ✅ **Audit dependencies**: Regular security scans of packages
10. ✅ **Backup secrets securely**: Use 1Password, AWS Secrets Manager, etc.

## Files to Commit

Total files being added: 48 files

**Categories:**
- GitHub Actions workflows: 3 files
- Documentation: 10 files
- Terraform modules: 24 files
- Terraform configs: 6 files
- Bootstrap scripts: 1 file
- Configuration: 4 files (.gitignore, release-please-config.json, etc.)

**Verification:**
- ✅ No terraform.tfvars (contains secrets)
- ✅ Only template files (.tfvars.example, .tfvars.budget)
- ✅ No .terraform/ directories
- ✅ No tfplan files
- ✅ No .pem or .key files
- ✅ No actual secret values (only placeholders like CHANGEME)

## Ready to Push

All security checks passed! You can safely run:

```bash
git remote add origin https://github.com/samuelwilk/mind-the-wait.git
git branch -M main
git push -u origin main
```

## After Pushing

1. **Verify on GitHub**: Check that terraform.tfvars doesn't appear in repository
2. **Set up GitHub Environment**: Follow `docs/infrastructure/github-environments-setup.md`
3. **Add secrets**: Add 6 secrets to production environment
4. **Test workflow**: Create a test PR to verify CI works
5. **Deploy infrastructure**: Run `terraform apply`
