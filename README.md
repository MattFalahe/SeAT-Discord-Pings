# SeAT Discord Pings

A comprehensive Discord ping and broadcast management plugin for [SeAT](https://github.com/eveseat/seat) - Send fleet notifications to Discord channels with rich embeds, role mentions, channel links, staging locations, and advanced scheduling features.

[![Latest Version](https://img.shields.io/github/v/release/MattFalahe/seat-discord-pings)](https://github.com/MattFalahe/seat-discord-pings/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0-blue.svg)](https://github.com/MattFalahe/seat-discord-pings/blob/master/LICENSE)
[![SeAT 5.0](https://img.shields.io/badge/SeAT-5.0-blue)](https://github.com/eveseat/seat)

## Features

### Core Features
- 📢 **Fleet Pings** - Send formatted fleet broadcasts to Discord with rich embeds
- 🎯 **Multiple Webhooks** - Manage unlimited Discord webhooks for different channels
- 📝 **Templates** - Quick templates for common ping types (CTA, Mining, Strategic, etc.)
- 📊 **Rich Embeds** - Beautiful Discord embeds with customizable colors and fields
- ⏰ **EVE Time** - Automatic EVE time stamps with local time display

### Discord Integration
- 👥 **Discord Roles** - Manage and mention Discord roles in your pings
- 💬 **Discord Channels** - Link to specific Discord channels in messages
- 🎨 **Custom Mentions** - Support for @everyone, @here, role mentions, and custom mentions
- 🔗 **Channel Links** - Add clickable channel references to guide users
- 📍 **Staging Locations** - Pre-configure staging locations with quick dropdown selection

### Fleet Information Fields
- FC Name
- Formup Location (with staging dropdown)
- PAP Type (Strategic/Peacetime/CTA)
- Comms Information
- Doctrine Details (with seat-fitting integration)
- Discord Channel Links
- Custom Messages

### Advanced Features
- 📅 **Scheduled Pings** - Schedule pings with EVE time and recurring options
- 🚀 **Doctrine Integration** - Automatic integration with seat-fitting plugins
- 📍 **Staging Management** - Configure and manage staging locations
- 📜 **History Tracking** - Complete history of all sent pings with resend capability
- 👥 **Role Restrictions** - Limit webhook access to specific SeAT roles
- 🔄 **Multiple Recipients** - Send to multiple Discord channels simultaneously
- 🎨 **Custom Colors** - Per-webhook and per-ping embed color customization
- 🧪 **Webhook Testing** - Test webhooks before using them
- 🗑️ **Automatic Cleanup** - Scheduled cleanup of old ping history
- ⚙️ **Discord Configuration** - Unified interface for managing webhooks, roles, channels, and stagings

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

#### Configuring Staging Locations

1. Navigate to **Discord Pings** → **Discord Config** → **Staging Locations tab**
2. Click **Add Staging**
3. Enter a name for the staging (e.g., "Home Staging")
4. Enter the system name (e.g., "Jita")
5. Optionally enter the structure name (e.g., "4-4 CNAP")
6. Optionally set as default staging
7. Click **Add Staging**

### Permissions

Configure permissions through SeAT's Access Management system:

| Permission | Description |
|------------|-------------|
| `discordpings.view` | Access to Discord Pings plugin menu |
| `discordpings.send` | Send pings to Discord |
| `discordpings.send_multiple` | Send to multiple webhooks at once |
| `discordpings.manage_webhooks` | Manage webhooks, roles, channels, and stagings |
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
- Seat-fitting integration settings

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
6. Select formup location from staging dropdown or type manually
7. Select doctrine from dropdown (if seat-fitting is installed) or type manually
8. Fill in fleet details (FC, PAP type, comms, etc.)
9. Click **Send Ping**

### Using Templates

Click any template button to quickly fill the message field:
- **Standard CTA** - General call to arms
- **Emergency** - Urgent hostile response
- **Mining Op** - Mining fleet formation
- **Roam Fleet** - PvP roam announcement
- **Strategic Op** - Important strategic operations

### Using Staging Locations

1. Click the dropdown arrow next to Formup Location
2. Select from pre-configured staging locations
3. Or type a custom location manually
4. Set default staging in Discord Config for quick access

### Scheduling Pings

1. Fill out your ping details on the Send Ping page
2. Click **Schedule** instead of Send
3. Set date and time in EVE time (UTC)
4. View both EVE time and your local time for reference
5. Set recurrence options:
   - One-time
   - Hourly
   - Daily
   - Weekly
   - Monthly
6. Optionally set an end date for recurring pings
7. Pings will be sent automatically at the scheduled EVE time

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
- **Staging Locations Tab**: Manage staging locations for quick selection

## Integration with Seat-Fitting

The plugin automatically detects and integrates with seat-fitting plugins (CryptaTech or Denngarr versions):

- Doctrine dropdown appears when seat-fitting is installed
- Creates clickable doctrine links in Discord
- Falls back to plain text if doctrine viewing route is not available
- Works with both scheduled and immediate pings

## Discord Embed Format

Pings are sent as rich Discord embeds with:
- **Title**:  
   - :loudspeaker: Fleet Broadcast (for fleet operations)
   - :mega: Announcement (for general announcements)
   - :speech_balloon: Message (for simple messages)
- **Message**: Your custom message
- **Fields**: 
  - 👤 FC Name
  - 📍 Formup Location
  - 🎯 PAP Type
  - 🚀 Doctrine (with clickable link if using seat-fitting)
  - 🎧 Comms / Channel (combined field)
- **Footer**: "This was a coord broadcast from [username] to discord at [timestamp] EVE"
- **Color**: Customizable per webhook or per ping
- **Mentions**: Configurable @everyone, @here, or specific roles

## Time Zones

- All times in the interface are displayed in EVE time (UTC)
- Your local time is shown for reference when scheduling
- Scheduled pings execute based on EVE time
- History timestamps are in EVE time

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
- Verify times are being set correctly in EVE time

### Permission Issues
- Run `php artisan discordpings:setup --grant-admin` to grant admin permissions
- Ensure users have the `discordpings.view` permission to see the menu
- Check role restrictions on webhooks
- Verify webhook is active

### Missing Discord Roles/Channels in Dropdown
- Ensure roles and channels are added in Discord Config
- Check that they are marked as active
- Verify the Discord IDs are correct

### Doctrine Dropdown Not Showing
- Ensure seat-fitting plugin is installed (CryptaTech or Denngarr version)
- Create at least one doctrine in the fitting plugin
- Check logs for any loading errors

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

### Version 1.0.4 (2025-09-29)
- Rename the plugin** from "Discord Pings" to "SeAT Broadcast" throughout the interface
- **Add broadcast type selector** with three options:
   - :loudspeaker: Fleet Broadcast (for fleet operations)
   - :mega: Announcement (for general announcements)
   - :speech_balloon: Message (for simple messages)
- Update Discord embeds to show the appropriate title based on the selected type
- Make the plugin more versatile for different types of communications

The plugin will now be more universal and can be used for any type of broadcast, not just fleet pings. The Discord embed will clearly show what type of message it is with the appropriate icon and title.

### Version 1.0.3 (2025-09-29)
- Reworked time handling:
  - EVE time is now the primary input.
  - Added a timezone selector to confirm local time.

### Version 1.0.2 (2025-09-27)
- Added staging locations management with quick dropdown selection
- Fixed embed color not being applied correctly
- Fixed doctrine links not showing in Discord embeds
- Combined Comms and Channel fields into single embed field
- Added seat-fitting integration for both CryptaTech and Denngarr versions
- Enhanced scheduled pings with all features from send ping (roles, channels, doctrines, stagings)
- Added EVE time display with local time reference
- Fixed webhook testing functionality
- Added working preview modal for Discord embeds
- Improved error handling and logging
- Updated Discord Configuration interface with tabbed layout
- Added support for default staging locations

### Version 1.0.1 (2025-09-26)
- Added Discord role management and mentions
- Added Discord channel linking functionality
- Implemented unified Discord Configuration interface
- Added scheduled ping recurring options
- Added automatic history cleanup job
- Improved permission setup command with --grant-admin option
- Added role-based webhook restrictions
- Enhanced UI with role and channel dropdowns
- Fixed database migration issues
- Improved error handling and logging

### Version 1.0.0 (2025-09-01)
- Initial release
- Core ping functionality
- Webhook management
- Scheduled pings
- History tracking
- Role-based access control
