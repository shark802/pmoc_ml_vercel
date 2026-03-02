<?php
// Email Configuration for BCPDO System
// Modify these settings according to your email provider

// SMTP Configuration
define('SMTP_HOST', 'smtp.hostinger.com');           // Your SMTP server (e.g., smtp.gmail.com, smtp.outlook.com)
define('SMTP_PORT', 587);                        // SMTP port (587 for TLS, 465 for SSL)
define('SMTP_SECURE', 'tls');                    // Security type: 'tls' or 'ssl'
define('SMTP_AUTH', true);                       // Enable SMTP authentication

// Email Account Settings
define('SMTP_USERNAME', 'pmoc_info@bccbsis.com'); // Your email address
define('SMTP_PASSWORD', 'f#7x0Hks=Nb5');    // Your email password or app password

// From Email Settings
define('FROM_EMAIL', 'pmoc_info@bccbsis.com');   // From email address

define('FROM_NAME', 'BCPDO System');             // From name

// Email Templates Settings
define('SITE_NAME', 'BCPDO');                    // Your site name
define('SITE_URL', 'https://pmoc.bccbsis.com');    // Your site URL (production domain)

// Email Features
define('ENABLE_EMAIL_NOTIFICATIONS', true);      // Enable/disable email notifications
define('ENABLE_SCHEDULE_CONFIRMATIONS', true);   // Enable schedule confirmation emails
define('ENABLE_SCHEDULE_REMINDERS', true);       // Enable schedule reminder emails
define('ENABLE_SCHEDULE_CANCELLATIONS', false);  // Disable automatic schedule cancellation emails

// Debug Settings
define('EMAIL_DEBUG', true);                    // Enable email debugging (set to true for troubleshooting)

// Common SMTP Providers Configuration Examples:

/*
Gmail Configuration:
- SMTP_HOST: 'smtp.gmail.com'
- SMTP_PORT: 587
- SMTP_SECURE: 'tls'
- SMTP_USERNAME: 'your-email@gmail.com'
- SMTP_PASSWORD: 'your-app-password' (generate from Google Account settings)

Outlook/Hotmail Configuration:
- SMTP_HOST: 'smtp-mail.outlook.com'
- SMTP_PORT: 587
- SMTP_SECURE: 'tls'
- SMTP_USERNAME: 'your-email@outlook.com'
- SMTP_PASSWORD: 'your-password'

Yahoo Configuration:
- SMTP_HOST: 'smtp.mail.yahoo.com'
- SMTP_PORT: 587
- SMTP_SECURE: 'tls'
- SMTP_USERNAME: 'your-email@yahoo.com'
- SMTP_PASSWORD: 'your-app-password'

Custom SMTP Server:
- SMTP_HOST: 'your-smtp-server.com'
- SMTP_PORT: 587 (or your server's port)
- SMTP_SECURE: 'tls' (or 'ssl')
- SMTP_USERNAME: 'your-username'
- SMTP_PASSWORD: 'your-password'
*/

// Instructions for Gmail App Password:
/*
1. Go to your Google Account settings
2. Navigate to Security
3. Enable 2-Step Verification if not already enabled
4. Go to App passwords
5. Generate a new app password for "Mail"
6. Use this generated password as SMTP_PASSWORD
*/

// Instructions for Testing:
/*
1. Update the SMTP settings above with your email provider details
2. Set EMAIL_DEBUG to true for troubleshooting
3. Test the email functionality using the test page
4. Check the error logs if emails are not sending
*/
?> 