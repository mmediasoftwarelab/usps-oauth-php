# Publishing to Packagist

This guide walks through publishing the USPS OAuth PHP library to Packagist for public distribution via Composer.

## Prerequisites

- [x] GitHub repository created and public
- [x] All tests passing (`composer run check`)
- [x] Version tagged in git
- [x] MIT license in place
- [x] Documentation complete

## Step 1: Prepare Your Repository

### 1.1 Ensure Quality Standards

```bash
# Run all quality checks
composer run check

# Should show:
# ✓ 33/33 tests passing
# ✓ PHPStan level 8: no errors
# ✓ PSR-12: no violations
```

### 1.2 Tag Your Release

```bash
# Create and push version tag
git tag -a v1.0.0 -m "Release v1.0.0: Initial stable release"
git push origin v1.0.0

# Verify tag
git tag -l
```

### 1.3 Create GitHub Release

1. Go to your repository: `https://github.com/YOUR-USERNAME/usps-oauth-php`
2. Click "Releases" → "Create a new release"
3. Select tag: `v1.0.0`
4. Title: `v1.0.0 - Initial Release`
5. Description: Copy from [CHANGELOG.md](../CHANGELOG.md)
6. Click "Publish release"

## Step 2: Register on Packagist

### 2.1 Create Packagist Account

1. Go to [packagist.org](https://packagist.org/)
2. Click "Sign in with GitHub" (recommended)
3. Authorize Packagist to access your GitHub account

### 2.2 Submit Your Package

1. Click "Submit" in the top navigation
2. Enter your repository URL: `https://github.com/YOUR-USERNAME/usps-oauth-php`
3. Click "Check"
4. Review the package information
5. Click "Submit"

**Your package is now live at:**

```
https://packagist.org/packages/mmedia/usps-oauth-php
```

## Step 3: Configure Auto-Updates

### 3.1 Set Up GitHub Webhook

Packagist can automatically update when you push new releases.

1. In Packagist, go to your package page
2. Click your username → "Your packages"
3. Click on "mmedia/usps-oauth-php"
4. Copy the webhook URL shown (something like `https://packagist.org/api/github?username=...`)

### 3.2 Add Webhook to GitHub

1. Go to your GitHub repository
2. Settings → Webhooks → Add webhook
3. Payload URL: Paste the Packagist webhook URL
4. Content type: `application/json`
5. Secret: Leave blank (or use the token from Packagist if shown)
6. Events: Select "Just the push event"
7. Click "Add webhook"

### 3.3 Verify Webhook

1. Make a small change to README.md
2. Commit and push
3. Check GitHub webhook delivery (should show 200 response)
4. Check Packagist package page (should show updated timestamp)

## Step 4: Announce Your Package

### 4.1 Update README Badges

Your README already includes badge placeholders. Update them with real URLs:

```markdown
[![Latest Version](https://img.shields.io/packagist/v/mmedia/usps-oauth-php.svg)](https://packagist.org/packages/mmedia/usps-oauth-php)
[![Total Downloads](https://img.shields.io/packagist/dt/mmedia/usps-oauth-php.svg)](https://packagist.org/packages/mmedia/usps-oauth-php)
[![License](https://img.shields.io/packagist/l/mmedia/usps-oauth-php.svg)](https://packagist.org/packages/mmedia/usps-oauth-php)
```

### 4.2 Share on Social Media

- **Twitter/X**: "🚀 Just released usps-oauth-php v1.0.0 - Modern PHP library for USPS OAuth 2.0 API. Get shipping rates with clean, type-safe code. #PHP #OpenSource"
- **Reddit**: Post to r/PHP, r/opensource
- **LinkedIn**: Professional announcement
- **Dev.to**: Write a blog post about building the library

### 4.3 Submit to Newsletters

- [PHP Weekly](https://www.phpweekly.com/)
- [Laravel News](https://laravel-news.com/) (mention WordPress/Laravel adapters)
- [Awesome PHP](https://github.com/ziadoz/awesome-php) - Submit a PR

## Step 5: Maintain Your Package

### 5.1 Version Numbering

Follow [Semantic Versioning](https://semver.org/):

- **MAJOR** (2.0.0): Breaking changes
- **MINOR** (1.1.0): New features, backward compatible
- **PATCH** (1.0.1): Bug fixes, backward compatible

### 5.2 Release Workflow

```bash
# 1. Make your changes
git checkout -b feature/new-feature

# 2. Run tests
composer run check

# 3. Update CHANGELOG.md
# Add entry under [Unreleased]

# 4. Merge to main
git checkout main
git merge feature/new-feature

# 5. Update version in CHANGELOG.md
# Move [Unreleased] items to new version section

# 6. Tag and push
git tag -a v1.1.0 -m "Release v1.1.0: Description"
git push origin main --tags

# 7. Create GitHub release
# Packagist auto-updates via webhook
```

### 5.3 Handle Issues

Monitor these regularly:

- GitHub Issues
- Packagist package page (comments)
- Stack Overflow tag `usps-oauth-php` (create it)

### 5.4 Security Updates

If you discover a security vulnerability:

1. **DO NOT** create a public issue
2. Email: security@your-domain.com
3. Fix privately
4. Release patch version immediately
5. Announce after patch is available

## Packagist Statistics

Track your package performance:

- **Downloads**: Daily, monthly, total
- **Dependents**: Packages using your library
- **Stars**: GitHub stars
- **Issues**: Open/closed ratio

## Common Issues

### Package Not Updating

- Check webhook delivery in GitHub
- Manually trigger update on Packagist package page
- Verify GitHub repository is public

### Wrong Version Showing

- Ensure tags are pushed: `git push --tags`
- Check composer.json version matches tag
- Clear Packagist cache

### Installation Fails

- Verify minimum PHP version in composer.json
- Check all dependencies are available
- Test fresh install: `composer create-project mmedia/usps-oauth-php test-install`

## Next Steps

1. **Documentation Site**: Consider GitHub Pages or Read the Docs
2. **Code Coverage**: Add Codecov integration
3. **Continuous Integration**: Your GitHub Actions is already configured
4. **Community**: Respond to issues, accept PRs, build contributors

## Resources

- [Packagist Documentation](https://packagist.org/about)
- [Composer Documentation](https://getcomposer.org/doc/)
- [Semantic Versioning](https://semver.org/)
- [Keep a Changelog](https://keepachangelog.com/)

---

**Congratulations! Your package is now available to PHP developers worldwide via:**

```bash
composer require mmedia/usps-oauth-php
```
