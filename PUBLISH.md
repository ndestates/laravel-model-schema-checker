# Publishing to Packagist - DDEV Guide

## Prerequisites

✅ DDEV is running: `ddev start`  
✅ You have a Packagist account at https://packagist.org  
✅ Your GitHub repository is public  

## Step 1: Validate Package Using DDEV

### 1.1 Check Composer Validation
```bash
ddev composer validate --strict --no-check-all
```

### 1.2 Install Dependencies  
```bash
ddev composer install --no-dev --optimize-autoloader
```

### 1.3 Check Package Structure
```bash
# Verify all required files exist
ls -la src/
ls -la config/
ls -la check/
cat composer.json | head -20
```

## Step 2: Prepare Git Repository

### 2.1 Commit Current Changes
```bash
git status
git add .
git commit -m "Prepare package for Packagist release"
```

### 2.2 Merge to Master Branch
```bash
git checkout master
git merge feature/develop
git push origin master
```

### 2.3 Create Release Tag
```bash
# Create semantic version tag
git tag v1.0.0
git push origin v1.0.0
```

## Step 3: Submit to Packagist

### 3.1 Go to Packagist
1. Open: https://packagist.org/packages/submit
2. Log in with your Packagist account

### 3.2 Submit Repository
1. Enter your GitHub repository URL:
   ```
   https://github.com/ndestates/laravel-model-schema-checker
   ```
2. Click "Check"
3. If validation passes, click "Submit"

## Step 4: Test Installation (Critical!)

### 4.1 Create Test Laravel Project (Outside Current Project)
```bash
# Go to a different directory
cd ~/projects
composer create-project laravel/laravel test-package-install
cd test-package-install
```

### 4.2 Install Your Package
```bash
composer require ndestates/laravel-model-schema-checker --dev
```

### 4.3 Test Package Functionality
```bash
php vendor/ndestates/laravel-model-schema-checker/check.php --help
php artisan vendor:publish --provider="NDEstates\\LaravelModelSchemaChecker\\ModelSchemaCheckerServiceProvider"
```

## Step 5: Set Up Auto-Updates (Optional)

### 5.1 GitHub Webhook
1. Go to your GitHub repository
2. Settings → Webhooks
3. Add webhook: `https://packagist.org/api/github?username=YOUR_PACKAGIST_USERNAME`

## Commands Summary

Here are all the commands in order:

```bash
# 1. Start DDEV and validate
ddev start
ddev composer validate --strict

# 2. Prepare repository
git add .
git commit -m "Ready for Packagist release"
git checkout master
git merge feature/develop
git push origin master

# 3. Create release tag  
git tag v1.0.0
git push origin v1.0.0

# 4. Submit to Packagist (manual step on website)
# Visit: https://packagist.org/packages/submit
# Enter: https://github.com/ndestates/laravel-model-schema-checker

# 5. Test installation (in different directory)
cd ~/projects
composer create-project laravel/laravel test-install
cd test-install
composer require ndestates/laravel-model-schema-checker --dev
php vendor/ndestates/laravel-model-schema-checker/check.php --help
```

## Troubleshooting

### Composer Validation Fails
```bash
ddev composer validate --strict --no-check-all
# Fix any issues shown and re-run
```

### Package Not Found on Packagist
- Wait 5-10 minutes after submission
- Check repository is public
- Verify GitHub URL is correct

### Installation Fails  
- Test in completely fresh Laravel project
- Check Laravel version compatibility
- Ensure all dependencies are correctly specified

## Success Indicators

✅ `ddev composer validate` passes  
✅ Git tag created and pushed  
✅ Packagist shows your package  
✅ Test installation works  
✅ Package commands execute successfully  

Your package is now live on Packagist! 🚀