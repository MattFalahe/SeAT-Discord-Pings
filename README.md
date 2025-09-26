# SeAT Discord Pings

A comprehensive Discord ping and broadcast management plugin for [SeAT](https://github.com/eveseat/seat) - Send fleet notifications to Discord channels with rich embeds, role mentions, channel links, and advanced scheduling features.

[![Latest Version](https://img.shields.io/github/v/release/MattFalahe/seat-discord-pings)](https://github.com/MattFalahe/seat-discord-pings/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0-blue.svg)](https://github.com/MattFalahe/seat-discord-pings/blob/master/LICENSE)
[![SeAT 5.0](https://img.shields.io/badge/SeAT-5.0-blue)](https://github.com/eveseat/seat)

## Features

### Core Features
- 📢 **Fleet Pings** - Send formatted fleet broadcasts to Discord with rich embeds
- 🎯 **Multiple Webhooks** - Manage unlimited Discord webhooks for different channels
- 📝 **Templates** - Quick templates for common ping types (CTA, Mining, Strategic, etc.)
- 📊 **Rich Embeds** - Beautiful Discord embeds with customizable colors and fields
- ⏰ **EVE Time** - Automatic EVE time stamps in broadcasts

### Discord Integration
- 👥 **Discord Roles** - Manage and mention Discord roles in your pings
- 💬 **Discord Channels** - Link to specific Discord channels in messages
- 🎨 **Custom Mentions** - Support for @everyone, @here, role mentions, and custom mentions
- 🔗 **Channel Links** - Add clickable channel references to guide users

### Fleet Information Fields
- FC Name
- Formup Location  
- PAP Type (Strategic/Peacetime/CTA)
- Comms Information
- Doctrine Details
- Custom Messages
- Discord Channel Links

### Advanced Features
- 📅 **Scheduled Pings** - Schedule pings for future times with recurring options
- 📜 **History Tracking** - Complete history of all sent pings with resend capability
- 👥 **Role Restrictions** - Limit webhook access to specific SeAT roles
- 🔄 **Multiple Recipients** - Send to multiple Discord channels simultaneously
- 🎨 **Custom Colors** - Per-webhook and per-ping embed color customization
- 🧪 **Webhook Testing** - Test webhooks before using them
- 🗑️ **Automatic Cleanup** - Scheduled cleanup of old ping history
- ⚙️ **Discord Configuration** - Unified interface for managing webhooks, roles, and channels

## Requirements

- SeAT 5.0 or higher
- PHP 8.0 or higher
- Laravel 10.0 or higher
- Discord webhook URLs
- Queue worker running for scheduled pings

## Installation

### 1. Install the Package

```bash
cd /var/www/seat
composer require mattfalahe/seat-discord-pings
```

### 2. Run Migrations

```bash
php artisan migrate
```

### 3. Setup Permissions (Optional but Recommended)

Grant all Discord Pings permissions to your admin role:

```bash
php artisan discordpings:setup --grant-admin
```

Or manually set up permissions without granting to admin:

```bash
php artisan discordpings:setup
```

### 4. Clear and Rebuild Caches

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 5. Ensure Queue Worker is Running

```bash
php artisan queue:work
```

### 6. Verify Scheduler is Running

Add to your crontab if not already configured:

```bash
* * * * * cd /var/www/seat && php artisan schedule:run >> /dev/null 2>&1
```

## Configuration

### Discord Setup

#### Setting Up Discord Webhooks

1. In Discord, right-click on your channel and select **Edit Channel**
2. Navigate to **Integrations** → **Webhooks**
3. Click **New Webhook**
4. Give it a name (e.g., "SeAT Fleet Pings")
5. Click **Copy Webhook URL**
6. In SeAT, navigate to **Discord Pings** → **Discord Config** → **Webhooks tab**
7. Click **Add Webhook** and paste your URL

#### Adding Discord Roles

1. Navigate to **Discord Pings** → **Discord Config** → **Discord Roles tab**
2. Click **Add Role**
3. Enter a friendly name for the role
4. Enter the Discord Role ID (right-click role in Discord with Developer Mode enabled → Copy ID)
5. Optionally set a color for visual identification
6. Click **Add Role**

#### Adding Discord Channels

1. Navigate to **Discord Pings** → **Discord Config** → **Discord Channels tab**
2. Click **Add Channel**
3. Enter a friendly name for the channel
4. Paste the Discord channel URL (right-click channel → Copy Link)
5. Select the channel type (text, voice, announcement, etc.)
6. Click **Add Channel**

### Permissions

Configure permissions through SeAT's Access Management system:

| Permission | Description |
|------------|-------------|
| `discordpings.view` | Access to Discord Pings plugin menu |
| `discordpings.send` | Send pings to Discord |
| `discordpings.send_multiple` | Send to multiple webhooks at once |
| `discordpings.manage_webhooks` | Manage webhooks, roles, and channels |
| `discordpings.view_history` | View own ping history |
| `discordpings.view_all_history` | View all users' ping history |
| `discordpings.manage_scheduled` | Create and manage scheduled pings |

### Configuration File

After installation, publish the configuration file:

```bash
php artisan vendor:publish --tag=seat --force
```

Edit `config/discordpings.php` to customize:
- Default embed colors
- Rate limiting settings
- History retention period (default: 90 days)
- Maximum scheduled pings per user (default: 50)
- Default templates

## Usage

### Sending a Quick Ping

1. Navigate to **Discord Pings** → **Send Ping**
2. Select your target webhook
3. Enter your message
4. Choose mention type (optional):
   - No Mention
   - @everyone
   - @here
   - Discord Role (select from configured roles)
   - Custom mention
5. Select a Discord channel to link (optional)
6. Fill in fleet details (FC, location, doctrine, etc.)
7. Click **Send Ping**

### Using Templates

Click any template button to quickly fill the message field:
- **Standard CTA** - General call to arms
- **Emergency** - Urgent hostile response
- **Mining Op** - Mining fleet formation
- **Roam Fleet** - PvP roam announcement
- **Strategic Op** - Important strategic operations

### Scheduling Pings

1. Fill out your ping details on the Send Ping page
2. Click **Schedule** instead of Send
3. Set date, time, and recurrence options:
   - One-time
   - Hourly
   - Daily
   - Weekly
   - Monthly
4. Optionally set an end date for recurring pings
5. Pings will be sent automatically at the scheduled time

### Bulk Sending

1. Click "Multiple Webhooks" button on the Send Ping page
2. Check all desired webhooks
3. Fill in your message and details
4. Send once to reach multiple Discord channels

### Managing Discord Configuration

Access **Discord Pings** → **Discord Config** to manage:

- **Webhooks Tab**: Add, edit, test, and delete webhook configurations
- **Discord Roles Tab**: Configure Discord roles for mentions
- **Discord Channels Tab**: Add Discord channels for quick linking

## Discord Embed Format

Pings are sent as rich Discord embeds with:
- **Title**: 📢 Fleet Broadcast
- **Message**: Your custom message
- **Fields**: 
  - 👤 FC Name
  - 📍 Formup Location
  - 🎯 PAP Type
  - 🎧 Comms
  - 🚀 Doctrine
  - 💬 Channel (with clickable link)
- **Footer**: "This was a coord broadcast from [username] to discord at [timestamp] EVE"
- **Color**: Customizable per webhook or per ping
- **Mentions**: Configurable @everyone, @here, or specific roles

## Scheduled Jobs

The plugin includes two automated jobs:

1. **Process Scheduled Pings** - Runs every minute to send due pings
2. **Cleanup History** - Runs daily at 2 AM to remove old ping history

These are automatically registered in SeAT's schedule when you run migrations.

## Console Commands

### Setup Permissions

```bash
# Basic setup
php artisan discordpings:setup

# Reset and recreate all permissions
php artisan discordpings:setup --reset

# Grant all permissions to admin role
php artisan discordpings:setup --grant-admin

# Full reset and grant to admin
php artisan discordpings:setup --reset --grant-admin
```

### Manual Job Execution (for testing)

```bash
# Process scheduled pings manually
php artisan discordpings:process-scheduled

# Clean up old history manually
php artisan discordpings:cleanup-history
```

## Troubleshooting

### Webhook Test Fails
- Verify the webhook URL is correct and starts with `https://discord.com/api/webhooks/`
- Check if the Discord channel still exists
- Ensure the webhook wasn't deleted in Discord

### Pings Not Sending
- Check if Laravel queue is running: `php artisan queue:work`
- Verify cron job is configured correctly
- Check SeAT logs in `storage/logs/`

### Scheduled Pings Not Working
- Ensure the scheduler is running (check crontab)
- Verify queue workers are processing jobs
- Check if the scheduled ping is marked as active

### Permission Issues
- Run `php artisan discordpings:setup --grant-admin` to grant admin permissions
- Ensure users have the `discordpings.view` permission to see the menu
- Check role restrictions on webhooks
- Verify webhook is active

### Missing Discord Roles/Channels in Dropdown
- Ensure roles and channels are added in Discord Config
- Check that they are marked as active
- Verify the Discord IDs are correct

### Database Errors
- Run `php artisan migrate` to ensure all tables are created
- Clear caches with `php artisan cache:clear`

### Rate Limiting
- Discord has rate limits on webhooks (30 requests per minute)
- Space out bulk broadcasts
- Use scheduled pings for better timing

## Upgrading

When upgrading to a new version:

```bash
# Update the package
composer update mattfalahe/seat-discord-pings

# Run new migrations
php artisan migrate

# Clear and rebuild caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Support

- **Issues**: [GitHub Issues](https://github.com/MattFalahe/seat-discord-pings/issues)
- **Discord**: Join the [SeAT Discord server](https://discord.gg/seat)
- **Documentation**: [SeAT Docs](https://docs.eveseat.net/)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the GNU General Public License v2.0 - see the [LICENSE](LICENSE) file for details.

## Credits

- Created by [Matt Falahe](https://github.com/MattFalahe)
- Built for the [SeAT](https://github.com/eveseat/seat) Alliance / Corporation Management Platform
- Inspired by the EVE Online community's need for better fleet communication tools

## Changelog

### Version 1.0.1 (2025-09-26)
- Added Discord role management and mentions
- Added Discord channel linking functionality
- Implemented unified Discord Configuration interface
- Added scheduled ping recurring options
- Added automatic history cleanup job
- Improved permission setup command with --grant-admin option
- Added role-based webhook restrictions
- Enhanced UI with role and channel dropdowns
- Added webhook testing functionality
- Fixed database migration issues
- Improved error handling and logging

### Version 1.0.0 (2025-09-01)
- Initial release
- Core ping functionality
- Webhook management
- Scheduled pings
- History tracking
- Role-based access control
