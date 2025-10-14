# Deployment Guide

## Updating Your Deployed Application

When you have changes to deploy (like the new Google Calendar support), follow these steps:

### **Step 1: Handle Git Conflicts**

If you get conflicts with `composer.json`, you have a few options:

#### **Option A: Force Pull (Recommended)**
```bash
# Backup your current composer.json if needed
cp composer.json composer.json.backup

# Force pull the changes
git fetch origin
git reset --hard origin/main

# Reinstall dependencies
composer install --no-dev --optimize-autoloader
```

#### **Option B: Stash and Pull**
```bash
# Stash local changes
git stash

# Pull changes
git pull origin main

# Apply stashed changes (if needed)
git stash pop

# Install new dependencies
composer install --no-dev --optimize-autoloader
```

### **Step 2: Run Database Migration**

```bash
# Run the migration script
php scripts/migration.php migrate

# Check migration status
php scripts/migration.php status
```

### **Step 3: Update Environment Configuration**

```bash
# Copy new environment template
cp .env.example .env

# Edit .env with your configuration
# Add Google Calendar API settings if needed
```

### **Step 4: Restart Services (if needed)**

```bash
# Restart web server (if using systemd)
sudo systemctl restart apache2
# or
sudo systemctl restart nginx

# Restart cron (if needed)
sudo systemctl restart cron
```

## **Migration Script Usage**

The migration script handles database schema changes safely:

```bash
# Run all pending migrations
php scripts/migration.php migrate

# Check current migration status
php scripts/migration.php status

# Rollback to specific version (if needed)
php scripts/migration.php rollback 1.0.0
```

## **What the Migration Does**

The migration script will:

1. **Create a `schema_migrations` table** to track applied migrations
2. **Add `source_type` and `target_type` columns** to `sync_configurations` table
3. **Set default values** for existing records
4. **Add indexes** for better performance
5. **Track migration history** for future updates

## **Safe Deployment Process**

1. **Test locally first**:
   ```bash
   composer install
   php scripts/migration.php migrate
   php scripts/setup.php
   ```

2. **Deploy to staging** (if you have one)

3. **Deploy to production**:
   ```bash
   git pull origin main
   composer install --no-dev --optimize-autoloader
   php scripts/migration.php migrate
   ```

4. **Verify deployment**:
   - Check web interface loads
   - Test adding a sync configuration
   - Check logs for errors

## **Rollback Plan**

If something goes wrong:

1. **Rollback code**:
   ```bash
   git reset --hard HEAD~1
   composer install --no-dev --optimize-autoloader
   ```

2. **Rollback database** (if needed):
   ```bash
   php scripts/migration.php rollback
   ```

3. **Restart services**:
   ```bash
   sudo systemctl restart apache2
   ```
