# GitHub Environment Deployment Rules

## Issue

When creating a release (e.g., v0.1.0), the deployment workflow fails with:

```
Tag "v0.1.0" is not allowed to deploy to production due to environment protection rules.
```

This happens because the `production` environment is configured to only allow deployments from specific branches, not from tags.

## Solution: Allow Tag-Based Deployments

### Steps

1. Go to your repository: `https://github.com/samuelwilk/mind-the-wait`

2. Navigate to **Settings** → **Environments** → **production**

3. Scroll to **Deployment branches and tags** section

4. Change the setting from:
   - ❌ **Selected branches** (only allows main)

   To one of these options:

   **Option A: Allow All Tags (Recommended)**
   - ✅ Select **"Selected branches and tags"**
   - Click **"Add deployment branch or tag rule"**
   - Enter: `refs/tags/v*`
   - This allows any tag starting with `v` (e.g., v0.1.0, v1.2.3)

   **Option B: Allow All Branches and Tags**
   - ✅ Select **"All branches"**
   - This allows deployments from any branch or tag
   - Less secure but simpler

5. Click **Save protection rules**

### How Deployments Work

After fixing the environment rules, here's the deployment flow:

1. **Push to main** → Builds Docker images with commit SHA tag (e.g., `abc1234`)
   - Does NOT update `:latest` tag
   - Does NOT deploy to ECS

2. **Create release** (merge Release Please PR or manually create) → Builds images with `:latest`, `:v0.1.0`, and `:abc1234` tags
   - Updates `:latest` tag
   - Deploys to ECS (force new deployment)
   - Triggers zero-downtime rolling update

3. **Manual deployment** (`workflow_dispatch`) → Deploy specific tag
   - Useful for rollbacks or testing

### Why We Use Tags for Releases

Releases are created from tags (e.g., `v0.1.0`) not from branch pushes. This is the standard practice because:

1. **Semantic versioning** - Tags clearly indicate versions
2. **Immutability** - Tags don't move; branches do
3. **GitHub Releases** - Created from tags, not commits
4. **Rollbacks** - Easy to redeploy a specific version by tag

### Security Note

Allowing tag-based deployments is safe when:
- Only maintainers can create tags (GitHub's default)
- Only maintainers can create releases
- The production environment requires approval (optional)

To add approval requirements:
1. Go to **Settings** → **Environments** → **production**
2. Enable **"Required reviewers"**
3. Add yourself and/or team members
4. Now all deployments require manual approval

## Alternative: Remove Environment Protection

If you want automatic deployments without approval, you can remove the `environment: production` from the workflow entirely. This skips environment protection rules but also loses the benefits:
- Deployment logs organized by environment
- Ability to require approvals
- Environment-specific secrets

**Not recommended for production deployments.**

## Verification

After updating the environment rules:

1. Go to Actions: `https://github.com/samuelwilk/mind-the-wait/actions`
2. Find the failed "Deploy to Production" run for v0.1.0
3. Click **"Re-run failed jobs"**
4. The deployment should now succeed!

Or trigger a new release:
```bash
git commit --allow-empty -m "chore: trigger deployment"
git push
# Merge the Release Please PR that's created
```

## Current Status

✅ Release v0.1.0 created successfully
❌ Deployment blocked by environment rules (needs fix above)
🔧 Once fixed, deployments will work automatically on future releases
