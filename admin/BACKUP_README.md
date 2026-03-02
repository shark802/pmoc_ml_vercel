# Database Backup System

## Overview
This system provides automatic and manual database backup functionality for the BCPDO System.

## Features
- **Manual Backups**: Create backups on-demand from the admin panel
- **Automatic Backups**: Schedule automatic backups (daily, weekly, or monthly)
- **Backup Management**: View, download, and delete backup files
- **Automatic Cleanup**: Old backups are automatically deleted based on retention settings

## Setup Instructions

### 1. Automatic Backup Setup (Cron Job)

To enable automatic backups, you need to set up a cron job that runs the `auto_backup.php` script.

#### For Linux/Unix Systems:

1. Open your crontab:
   ```bash
   crontab -e
   ```

2. Add one of the following lines based on your desired frequency:

   **Daily at 2 AM:**
   ```
   0 2 * * * php /path/to/caps2/admin/auto_backup.php
   ```

   **Weekly on Sunday at 2 AM:**
   ```
   0 2 * * 0 php /path/to/caps2/admin/auto_backup.php
   ```

   **Monthly on the 1st at 2 AM:**
   ```
   0 2 1 * * php /path/to/caps2/admin/auto_backup.php
   ```

3. Replace `/path/to/caps2` with your actual project path.

#### For Windows (Task Scheduler):

1. Open Task Scheduler
2. Create a new task
3. Set trigger (daily, weekly, or monthly)
4. Set action to run: `php.exe C:\xampp\htdocs\caps2\admin\auto_backup.php`

### 2. Backup Directory

Backups are stored in the `../backups/` directory (relative to the admin folder).

The directory is automatically created and secured with a `.htaccess` file to prevent web access.

### 3. Database Credentials

The backup scripts use the database credentials from `includes/conn.php`. Make sure these are correct for your environment.

### 4. mysqldump Requirement

The system tries to use `mysqldump` first (faster and more reliable). If `mysqldump` is not available, it falls back to a PHP-based backup method.

To ensure `mysqldump` works:
- Make sure MySQL/MariaDB is in your system PATH
- Or update the script to use the full path to `mysqldump`

## Usage

### Manual Backup
1. Navigate to **Admin Panel > Database Backup**
2. Click **"Create Backup Now"** button
3. Wait for the backup to complete
4. The backup will appear in the backup history

### Automatic Backup Settings
1. Navigate to **Admin Panel > Database Backup**
2. Enable "Automatic Backups"
3. Select backup frequency (Daily, Weekly, or Monthly)
4. Set retention period (days to keep backups)
5. Click **"Save Settings"**

### Download Backup
1. Go to the Backup History section
2. Click the download icon next to the backup file
3. The SQL file will be downloaded to your computer

### Delete Backup
1. Go to the Backup History section
2. Click the delete icon next to the backup file
3. Confirm deletion

## Backup File Format

Backup files are named: `backup_{database_name}_{timestamp}.sql`

Example: `backup_u520834156_DBpmoc25_2025-11-17_21-30-45.sql`

## Security Notes

- Only Super Admin users can access the backup system
- Backup files are stored outside the web root (relative path)
- `.htaccess` file prevents direct web access to backups
- File downloads are validated to only allow `.sql` files

## Troubleshooting

### Backup fails with mysqldump error
- Check if `mysqldump` is installed and in PATH
- Verify database credentials in `includes/conn.php`
- The system will automatically fall back to PHP-based backup

### Automatic backups not running
- Verify cron job is set up correctly
- Check cron logs for errors
- Ensure `auto_backup.php` has execute permissions
- Verify "Automatic Backups" is enabled in settings

### Backup files too large
- Consider excluding certain tables if needed
- Adjust retention period to keep fewer backups
- Check available disk space

## Notes

- Backups include all tables, routines, and triggers
- The system uses `--single-transaction` flag for consistent backups
- Old backups are automatically cleaned based on retention settings

