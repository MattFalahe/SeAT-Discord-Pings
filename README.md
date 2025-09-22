# SeAT Discord Pings

A comprehensive Discord ping and broadcast management plugin for [SeAT](https://github.com/eveseat/seat) - Send fleet notifications to Discord channels with rich embeds and advanced features.

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

### Fleet Information Fields
- FC Name
- Formup Location  
- PAP Type (Strategic/Peacetime/CTA)
- Comms Information
- Doctrine Details
- Custom Messages

### Advanced Features
- 📅 **Scheduled Pings** - Schedule pings for future times with recurring options
- 📜 **History Tracking** - Complete history of all sent pings with resend capability
- 👥 **Role Restrictions** - Limit webhook access to specific SeAT roles
- 🔄 **Multiple Recipients** - Send to multiple Discord channels simultaneously
- 🏷️ **@ Mentions** - Support for @everyone, @here, and custom role mentions
- 🎨 **Custom Colors** - Per-webhook and per-ping embed color customization
- 🧪 **Webhook Testing** - Test webhooks before using them

## Requirements

- SeAT 5.0 or higher
- PHP 8.0 or higher
- Discord webhook URLs

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

### 3. Clear Caches

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4. Set Up Scheduler (if not already configured)

Add to your crontab:
```bash
* * * * * cd /var/www/seat && php artisan schedule:run >> /dev/null 2>&1
```

## Configuration

### Setting Up Discord Webhooks

1. In Discord, right-click on your channel and select **Edit Channel**
2. Navigate to **Integrations** → **Webhooks**
3. Click **New Webhook**
4. Give it a name (e.g., "SeAT Fleet Pings")
5. Click **Copy Webhook URL**
6. In SeAT, navigate to **Discord Pings** → **Webhooks**
7. Click **Add Webhook** and paste your URL

### Permissions

Configure permissions through SeAT's permission system:

| Permission | Description |
|------------|-------------|
| `discord.pings.send` | Send pings to Discord |
| `discord.pings.send.multiple` | Send to multiple webhooks at once |
| `discord.pings.scheduled.view` | View scheduled pings |
| `discord.pings.scheduled.create` | Create scheduled pings |
| `discord.pings.scheduled.delete` | Delete scheduled pings |
| `discord.pings.history.view` | View own ping history |
| `discord.pings.history.view.all` | View all users' ping history |
| `discord.pings.webhooks.manage` | Create, edit, and delete webhooks |

## Usage

### Sending a Quick Ping

1. Navigate to **Discord Pings** → **Send Ping**
2. Select your target webhook
3. Enter your message
4. Optionally fill in fleet details (FC, location, doctrine, etc.)
5. Click **Send Ping**

### Using Templates

Click any template button to quickly fill the message field:
- **Standard CTA** - General call to arms
- **Emergency** - Urgent hostile response
- **Mining Op** - Mining fleet formation
- **Roam Fleet** - PvP roam announcement
- **Strategic Op** - Important strategic operations

### Scheduling Pings

1. Fill out your ping details
2. Click **Schedule** instead of Send
3. Set date, time, and recurrence options
4. Pings will be sent automatically at the scheduled time

### Bulk Sending

1. Click "Send to multiple webhooks"
2. Select all desired channels
3. Send once to reach multiple Discord channels

## Discord Embed Format

Pings are sent as rich Discord embeds with:
- **Title**: 📢 Fleet Broadcast
- **Message**: Your custom message
- **Fields**: FC, Location, PAP Type, Comms, Doctrine (if provided)
- **Footer**: "This was a coord broadcast from [username] to discord at [timestamp] EVE"
- **Color**: Customizable per webhook or per ping

## Troubleshooting

### Webhook Test Fails
- Verify the webhook URL is correct and starts with `https://discord.com/api/webhooks/`
- Check if the Discord channel still exists
- Ensure the webhook wasn't deleted in Discord

### Pings Not Sending
- Check if Laravel queue is running: `php artisan queue:work`
- Verify cron job is configured correctly
- Check SeAT logs in `storage/logs/`

### Permission Issues
- Ensure users have the `discord.pings.send` permission
- Check role restrictions on webhooks
- Verify webhook is active

### Rate Limiting
- Discord has rate limits on webhooks (30 requests per minute)
- Space out bulk broadcasts
- Use scheduled pings for better timing

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

### Version 1.0.0 (Planned)
- Initial release
- Core ping functionality
- Webhook management
- Scheduled pings
- History tracking
- Role-based access control
