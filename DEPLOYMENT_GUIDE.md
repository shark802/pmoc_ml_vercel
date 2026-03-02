# Deployment Guide
**BCPDO System - Production Deployment Checklist**

## Pre-Deployment Checklist

### ✅ Security Configuration

1. **Environment Variables Setup**
   - Copy `.env.example` to `.env`
   - Fill in all required values:
     ```bash
     DB_HOST=srv1322.hstgr.io
     DB_USER=u520834156_userPmoc
     DB_PASSWORD=your_secure_password_here
     DB_NAME=u520834156_DBpmoc25
     ENVIRONMENT=production
     FLASK_ENV=production
     DEBUG_MODE=false
     HTTPS_ENABLED=true
     API_KEY=your_secure_api_key_here
     ```
   - **CRITICAL:** Never commit `.env` file to version control
   - Verify `.env` is in `.gitignore`

2. **Database Security**
   - ✅ Verify no hardcoded credentials in code
   - ✅ All database connections use environment variables
   - ✅ Database user has minimal required permissions

3. **Application Security**
   - ✅ Run security tests: `php tests/security_test.php`
   - ✅ Verify HTTPS is enabled and working
   - ✅ Check security headers are set
   - ✅ Verify CSRF protection is active
   - ✅ Confirm rate limiting is configured

### ✅ Code Quality

1. **Remove Debug Code**
   - ✅ All `DEBUG` log statements removed or conditionally enabled
   - ✅ No `var_dump()`, `print_r()`, or `console.log()` in production
   - ✅ `DEBUG_MODE=false` in `.env`

2. **Error Handling**
   - ✅ `display_errors=0` in production
   - ✅ Error logging enabled
   - ✅ User-friendly error messages (no sensitive info)

### ✅ Testing

1. **Run Tests**
   ```bash
   php tests/security_test.php
   ```
   - All tests must pass before deployment

2. **Manual Testing**
   - Test login/logout
   - Test all CRUD operations
   - Test ML analysis functionality
   - Test certificate generation
   - Test email/SMS notifications

## Deployment Steps

### 1. Backup Current System

```bash
# Backup database
php admin/backup_database.php

# Backup files
tar -czf backup-$(date +%Y%m%d).tar.gz /path/to/application
```

### 2. Update Code

```bash
# Pull latest code
git pull origin main

# Verify no uncommitted changes
git status
```

### 3. Update Environment Variables

```bash
# Update .env file with production values
nano .env

# Verify environment variables are loaded
php -r "require 'includes/env_loader.php'; echo getEnvVar('DB_HOST');"
```

### 4. Run Database Migrations (if any)

```bash
# Check for pending migrations
# Run migrations if needed
```

### 5. Clear Cache

```bash
# Clear application cache
rm -rf cache/*

# Clear rate limit cache
rm -rf cache/rate_limits/*
```

### 6. Set File Permissions

```bash
# Set proper permissions
chmod 755 -R .
chmod 644 includes/*.php
chmod 600 .env
```

### 7. Restart Services

```bash
# Restart PHP-FPM (if applicable)
sudo service php-fpm restart

# Restart web server
sudo service apache2 restart
# OR
sudo service nginx restart

# Restart Flask ML service (if running separately)
# Check ml_model/service.py is running
```

### 8. Verify Deployment

1. **Check Application**
   - Visit homepage
   - Test login
   - Check dashboard loads

2. **Check Logs**
   ```bash
   tail -f /var/log/apache2/error.log
   tail -f /var/log/php_errors.log
   ```

3. **Check Security Headers**
   ```bash
   curl -I https://your-domain.com
   # Verify: X-Frame-Options, X-XSS-Protection, etc.
   ```

4. **Run Security Tests**
   ```bash
   php tests/security_test.php
   ```

## Rollback Procedure

If deployment fails:

1. **Restore Database**
   ```bash
   # Restore from backup
   mysql -u user -p database_name < backup.sql
   ```

2. **Restore Files**
   ```bash
   # Extract backup
   tar -xzf backup-YYYYMMDD.tar.gz -C /path/to/application
   ```

3. **Restore Environment**
   ```bash
   # Restore .env file
   cp .env.backup .env
   ```

4. **Restart Services**
   ```bash
   sudo service apache2 restart
   sudo service php-fpm restart
   ```

## Post-Deployment Monitoring

1. **Monitor Error Logs**
   ```bash
   tail -f /var/log/apache2/error.log
   ```

2. **Monitor Application Logs**
   ```bash
   tail -f /path/to/application/logs/app.log
   ```

3. **Check System Resources**
   ```bash
   top
   df -h
   free -m
   ```

4. **Monitor Database**
   ```bash
   # Check database connections
   mysql -u user -p -e "SHOW PROCESSLIST;"
   ```

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check `.env` file has correct credentials
   - Verify database server is accessible
   - Check firewall rules

2. **500 Internal Server Error**
   - Check PHP error logs
   - Verify file permissions
   - Check `.env` file exists and is readable

3. **ML Service Not Working**
   - Verify Flask service is running
   - Check `ML_SERVICE_URL` in `.env`
   - Check Flask service logs

4. **HTTPS Redirect Loop**
   - Verify `HTTPS_ENABLED=true` in `.env`
   - Check SSL certificate is valid
   - Verify web server SSL configuration

## Security Reminders

- ✅ Never commit `.env` file
- ✅ Use strong passwords for database
- ✅ Enable HTTPS in production
- ✅ Keep dependencies updated
- ✅ Regular security audits
- ✅ Monitor for suspicious activity
- ✅ Keep backups secure

## Support

For deployment issues, contact:
- System Administrator
- Development Team

---

**Last Updated:** 2025-01-XX  
**Version:** 1.0

