# Microsoft & Google Calendar Sync Application

A PHP application for synchronizing free/busy time between Microsoft and Google calendars using their respective APIs.

## Features

- **Cross-Platform Sync**: Sync between Microsoft and Google calendars
- **Bidirectional Sync**: Sync calendar events between two calendars
- **Flexible Configuration**: Support for source-to-target, target-to-source, or bidirectional syncing
- **Web Interface**: Easy-to-use web interface for managing users and sync configurations
- **Automated Syncing**: Cron job support for automated synchronization
- **Comprehensive Logging**: Detailed logging of sync operations and errors
- **Database Storage**: MySQL/PostgreSQL support for storing sync data and configurations

## Requirements

- PHP 8.1 or higher
- Composer
- MySQL/MariaDB or PostgreSQL database
- Microsoft Azure AD application registration
- Google Cloud Console project with Calendar API enabled
- Web server (Apache/Nginx)

## Installation

1. **Clone the repository**:
   ```bash
   git clone <repository-url>
   cd cal-sync
   ```

2. **Install dependencies**:
   ```bash
   composer install
   ```

3. **Configure environment**:
   ```bash
   cp .env.example .env
   # Edit .env with your configuration
   ```

4. **Set up the database**:
   ```bash
   php scripts/setup.php
   ```

5. **Configure Microsoft Graph API**:
   - Register an application in Azure Active Directory
   - Add the following API permissions:
     - `Calendars.Read`
     - `Calendars.ReadWrite`
     - `User.Read.All` (for accessing other users' calendars)
   - Get your Tenant ID, Client ID, and Client Secret
   - Update the `.env` file with these credentials

6. **Configure Google Calendar API**:
   - Create a project in Google Cloud Console
   - Enable the Google Calendar API
   - Create credentials (Service Account)
   - Download the JSON credentials file
   - Place the credentials file in `storage/google-credentials.json`
   - Share your Google Calendar with the service account email

7. **Set up cron job**:
   ```bash
   # Edit your crontab
   crontab -e
   
   # Add this line (adjust path as needed):
   */15 * * * * /usr/bin/php /path/to/cal-sync/scripts/sync.php >> /path/to/cal-sync/storage/logs/cron.log 2>&1
   ```

## Configuration

### Environment Variables

Edit the `.env` file with your configuration:

```env
# Microsoft Graph API Configuration
MICROSOFT_TENANT_ID=your_tenant_id_here
MICROSOFT_CLIENT_ID=your_client_id_here
MICROSOFT_CLIENT_SECRET=your_client_secret_here

# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=cal_sync
DB_USER=your_db_user
DB_PASSWORD=your_db_password
DB_TYPE=mysql

# Application Configuration
APP_URL=https://yourdomain.com
APP_ENV=production
LOG_LEVEL=info

# Sync Configuration
SYNC_INTERVAL_MINUTES=15
DEFAULT_TIMEZONE=UTC
MAX_SYNC_RANGE_DAYS=30

# Google Calendar API Configuration
GOOGLE_CREDENTIALS_PATH=storage/google-credentials.json
```

### Microsoft Graph API Setup

1. Go to [Azure Portal](https://portal.azure.com/)
2. Navigate to "Azure Active Directory" > "App registrations"
3. Click "New registration"
4. Fill in the application details
5. Go to "API permissions" and add:
   - `Calendars.Read`
   - `Calendars.ReadWrite`
   - `User.Read.All`
6. Go to "Certificates & secrets" and create a new client secret
7. Note down the Tenant ID, Client ID, and Client Secret

### Google Calendar API Setup

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the Google Calendar API:
   - Go to "APIs & Services" > "Library"
   - Search for "Google Calendar API"
   - Click "Enable"
4. Create credentials:
   - Go to "APIs & Services" > "Credentials"
   - Click "Create Credentials" > "Service Account"
   - Fill in the service account details
   - Go to the service account and create a key (JSON format)
   - Download the JSON file
5. Place the credentials file in `storage/google-credentials.json`
6. Share your Google Calendar with the service account:
   - Open your Google Calendar
   - Go to calendar settings
   - Add the service account email with "Make changes to events" permission

## Usage

### Web Interface

Access the web interface at `web/index.php` to:

- Add users
- Configure sync relationships between calendars (Microsoft ↔ Google)
- View sync status and logs
- Enable/disable sync configurations

### Calendar Configuration Examples

**Google → Microsoft Sync:**
- Source Email: `your-email@gmail.com` or calendar ID
- Source Type: Google Calendar
- Target Email: `user@company.com`
- Target Type: Microsoft Calendar
- Direction: Source → Target

**Microsoft → Google Sync:**
- Source Email: `user@company.com`
- Source Type: Microsoft Calendar
- Target Email: `your-email@gmail.com` or calendar ID
- Target Type: Google Calendar
- Direction: Source → Target

**Bidirectional Sync:**
- Source Email: `user1@company.com`
- Source Type: Microsoft Calendar
- Target Email: `user2@gmail.com`
- Target Type: Google Calendar
- Direction: Bidirectional

### Manual Sync

Run a manual sync:

```bash
php scripts/sync.php
```

### Sync Configuration

The application supports three sync directions:

- **Bidirectional**: Events sync both ways between calendars
- **Source to Target**: Events only sync from source to target calendar
- **Target to Source**: Events only sync from target to source calendar

## Database Schema

The application uses the following main tables:

- `users`: Store user information
- `sync_configurations`: Define sync relationships between calendars
- `calendar_events`: Track synced events
- `sync_logs`: Log sync operations and results
- `application_settings`: Store application configuration

## Logging

Logs are stored in `storage/logs/` directory:

- `sync_YYYY-MM-DD.log`: Daily sync operation logs
- `cron.log`: Cron job execution logs

## Security Considerations

- Store sensitive credentials in environment variables
- Use HTTPS in production
- Regularly rotate client secrets
- Monitor sync logs for errors
- Implement proper access controls for the web interface

## Troubleshooting

### Common Issues

1. **Authentication Errors**: Verify your Microsoft Graph API credentials
2. **Database Connection**: Check database configuration and connectivity
3. **Permission Errors**: Ensure the Azure AD app has proper permissions
4. **Sync Failures**: Check logs for detailed error messages

### Debug Mode

Enable debug logging by setting `LOG_LEVEL=debug` in your `.env` file.

## License

This project is licensed under the MIT License.

## Support

For issues and questions, please check the logs first and then create an issue in the repository.
