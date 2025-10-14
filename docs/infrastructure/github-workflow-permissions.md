# GitHub Workflow Permissions Setup

This guide explains how to configure GitHub repository settings to allow workflows to function properly.

## Release Please Workflow Permissions

The Release Please workflow needs permission to create pull requests. GitHub's default security settings prevent this.

### Steps to Enable

1. Go to your repository on GitHub: `https://github.com/samuelwilk/mind-the-wait`

2. Navigate to **Settings** → **Actions** → **General**

3. Scroll down to **Workflow permissions** section

4. Find the option: **"Allow GitHub Actions to create and approve pull requests"**

5. ✅ Check this box

6. Click **Save**

### Why This Is Needed

Release Please creates pull requests automatically when you push to main. These PRs contain:
- Updated CHANGELOG.md
- Version bumps in package files
- Release notes

Without this permission, you'll see the error:
```
release-please failed: GitHub Actions is not permitted to create or approve pull requests.
```

### Security Note

This permission allows workflows to:
- Create pull requests
- Approve pull requests (if configured)

It does **not** allow workflows to:
- Merge pull requests without approval
- Bypass branch protection rules
- Push directly to protected branches

You still maintain full control over merging releases.

## Other Workflow Permissions

All other workflows (Test & Lint, Deploy to Production) use the default permissions:
- `contents: read` - Read repository contents
- `contents: write` - Write to repository (for releases)
- `id-token: write` - AWS OIDC authentication
- `pull-requests: write` - Comment on PRs with test results

These are configured per-workflow in the YAML files and don't require repository settings changes.
