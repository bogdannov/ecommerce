# Deployment System Documentation

This document describes how to use the deployment system for viva-market.pl.

## Quick Start

### 1. Initial Setup (First Time Only)

**Create your `.env` file:**
```bash
cp .env.example .env
```

**Edit `.env` with your actual server credentials:**
```bash
SSH_USER=your_username                 # Your SSH username
SSH_HOST=your.server.com               # Your server hostname
SSH_PORT=22                             # SSH port (usually 22)
SSH_KEY=/Users/you/.ssh/id_rsa         # Path to your SSH private key
REMOTE_PATH=/var/www/viva-market.pl    # Full path to website root on server
```

**Test SSH connection:**
```bash
ssh -i ~/.ssh/id_rsa -p 22 $SSH_USER@$SSH_HOST
```

### 2. Local Deployment

**Test deployment (dry-run):**
```bash
./deploy.sh push --dry-run
```

**Deploy to production:**
```bash
./deploy.sh push
```

**Pull from production:**
```bash
./deploy.sh pull
```

## GitHub Actions Setup

### 1. Generate SSH Key for GitHub Actions

```bash
ssh-keygen -t rsa -b 4096 -C "github-deploy" -f ~/.ssh/github_deploy
```

### 2. Add Public Key to hosting

1. Copy the public key:
   ```bash
   cat ~/.ssh/github_deploy.pub
   ```

2. Add to server's authorized_keys:
   ```bash
   ssh-copy-id -i ~/.ssh/github_deploy.pub user@your.server.com
   ```
   Or manually add to `~/.ssh/authorized_keys` on the server

### 3. Configure GitHub Secrets

Go to: Repository → Settings → Secrets and variables → Actions → New secret

Add these secrets:

- `SSH_PRIVATE_KEY` - Full private key (from `cat ~/.ssh/github_deploy`)
- `SSH_HOST` - Server hostname (e.g., your.server.com)
- `SSH_USER` - SSH username
- `SSH_PORT` - Port number (usually 22)
- `REMOTE_PATH` - Full path to website root on server (e.g., `/var/www/viva-market.pl`)

### 4. Test GitHub Actions

1. Make a change in a feature branch
2. Commit and push
3. Create PR to main branch
4. Merge PR
5. Check Actions tab for deployment status

## Usage

### Local Deployment Commands

```bash
# Deploy to production (with dry-run first)
./deploy.sh push --dry-run
./deploy.sh push

# Pull from production
./deploy.sh pull
```

### GitHub Actions (Automatic)

**Automatic deployment:**
- Push to `main` branch triggers automatic deployment

**Manual deployment:**
1. Go to Actions tab on GitHub
2. Select "Deploy to Production"
3. Click "Run workflow"
4. Choose branch and run

## What Gets Synced

**Included** (synced to server):
- ✅ WordPress core files
- ✅ Themes and plugins
- ✅ Uploads directory
- ✅ Custom code

**Excluded** (never synced - see `.rsyncignore`):
- ❌ `.git/` and Git files
- ❌ `wp-config.php` (server-specific)
- ❌ `.htaccess` (LiteSpeed config)
- ❌ Log files (`*.log`)
- ❌ IDE files (`.idea/`, `.vscode/`)
- ❌ Cache directories
- ❌ `.env` file
- ❌ Temporary files

## Files Overview

```
project-root/
├── .github/
│   └── workflows/
│       └── deploy.yml           # GitHub Actions workflow
├── .rsyncignore                 # Files to exclude from sync
├── deploy.sh                    # Local deployment script
├── .env                         # SSH credentials (gitignored)
├── .env.example                 # Template for .env
└── DEPLOYMENT.md                # This file
```

## Verification Steps

After setup, verify everything works:

1. **Test SSH connection:**
   ```bash
   ssh $SSH_USER@$SSH_HOST -p $SSH_PORT -i $SSH_KEY
   ```

2. **Test dry-run:**
   ```bash
   ./deploy.sh push --dry-run
   ```

3. **Test actual deployment:**
   ```bash
   ./deploy.sh push
   ```

4. **Verify site:**
   - Visit https://viva-market.pl
   - Check that changes are live

5. **Test GitHub Actions:**
   - Make a small change
   - Commit to feature branch
   - Create PR to main
   - Merge PR
   - Verify workflow runs in Actions tab

## Troubleshooting

### SSH Connection Issues

**Problem:** Cannot connect via SSH

**Solution:**
1. Verify credentials in `.env`
2. Test SSH manually: `ssh -i $SSH_KEY -p $SSH_PORT $SSH_USER@$SSH_HOST`
3. Check that SSH key is added to server's authorized_keys

### rsync Permission Errors

**Problem:** Permission denied during rsync

**Solution:**
1. Verify remote path is correct
2. Check SSH user has write permissions
3. Test SSH connection first

### GitHub Actions Fails

**Problem:** GitHub Actions workflow fails

**Solution:**
1. Verify all secrets are configured correctly
2. Check that SSH public key is added to server's authorized_keys
3. Review workflow logs in Actions tab
4. Ensure `REMOTE_PATH` is correct

### Files Not Syncing

**Problem:** Some files aren't being synced

**Solution:**
1. Check `.rsyncignore` - files might be excluded
2. Run with dry-run to see what would sync: `./deploy.sh push --dry-run`
3. Verify files exist locally

## Safety Features

- **Protected files:** `wp-config.php` and `.htaccess` are never overwritten
- **Dry-run mode:** Test deployments before executing
- **Pull confirmation:** Requires explicit confirmation before overwriting local files
- **Exclusion list:** Clear list of protected files in `.rsyncignore`

## Support

For issues or questions:
- Check this documentation
- Review the plan file in the repository
- Contact the development team

## Notes

- Always use `--dry-run` first when testing
- Never commit `.env` (contains SSH credentials)
- The `.env` file is automatically excluded by `.gitignore`
- LiteSpeed cache is automatically cleared after deployment
