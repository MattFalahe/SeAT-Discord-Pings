# Changelog

Below is a summary of all major updates, improvements, and fixes made to **SeAT Discord Pings**.
Each version entry lists key changes for easier reference and upgrade planning.

---

## 🆕 Version 1.0.5 — *February 2026*

### 🎉 New Features

**Calendar View**
- Visual calendar for scheduled broadcasts
- Month, week, and list view options
- Click on events to view details
- Click on empty dates to create new scheduled pings
- Color-coded events by webhook
- Large calendar display (1600px height) for better visibility
- Event stacking in week view (up to 5 events side by side)

**Template Management**
- Create and manage personal broadcast templates
- Global templates for organization-wide use (admin permission required)
- Quick save templates directly from Send Broadcast page
- Doctrine dropdown integration (seat-fitting plugin)
- Staging location dropdown for quick selection
- Full field support: FC name, formup, doctrine, PAP type, comms, mentions, embed color
- Template fields automatically populate Send Broadcast form

**Help & Documentation Page**
- Comprehensive documentation with searchable interface
- Plugin info section with GitHub repository links
- Getting started guide with installation steps
- Feature descriptions and usage instructions
- Commands reference with examples
- FAQ and troubleshooting sections
- Pages guide explaining each plugin page

### 🐛 Bug Fixes

- Added DataTables sorting across all plugin tables

---

## Version 1.0.4 — *September 2025*

### 🎉 New Features

**Broadcast Type Selector**
- Added broadcast type selector with three options:
  - 📢 Fleet Broadcast (for fleet operations)
  - 📣 Announcement (for general announcements)
  - 💬 Message (for simple messages)
- Discord embeds now show appropriate title based on selected type
- Plugin is now more versatile for different types of communications

---

## Version 1.0.3 — *September 2025*

### ✨ Improvements

**Time Handling Rework**
- EVE time is now the primary input for scheduling
- Added timezone selector to confirm local time
- Improved time display throughout the interface

---

## Version 1.0.2 — *September 2025*

### 🎉 New Features

- Added staging locations management with quick dropdown selection
- Added seat-fitting integration for both CryptaTech and Denngarr versions
- Enhanced scheduled pings with all features from send ping (roles, channels, doctrines, stagings)
- Added EVE time display with local time reference
- Added working preview modal for Discord embeds
- Updated Discord Configuration interface with tabbed layout
- Added support for default staging locations

### 🐛 Bug Fixes

- Fixed embed color not being applied correctly
- Fixed doctrine links not showing in Discord embeds
- Combined Comms and Channel fields into single embed field
- Fixed webhook testing functionality
- Improved error handling and logging

---

## Version 1.0.1 — *September 2025*

### 🎉 New Features

- Added Discord role management and mentions
- Added Discord channel linking functionality
- Implemented unified Discord Configuration interface
- Added scheduled ping recurring options
- Added automatic history cleanup job
- Improved permission setup command with --grant-admin option
- Added role-based webhook restrictions
- Enhanced UI with role and channel dropdowns

### 🐛 Bug Fixes

- Fixed database migration issues
- Improved error handling and logging

---

## 🚀 Version 1.0.0 — *September 2025*

### Initial Release

- Core ping functionality with rich Discord embeds
- Webhook management with testing capability
- Scheduled pings with recurring options
- History tracking with resend capability
- Role-based access control
- Template system for quick pings
- Multiple webhook support for bulk sending

---

## 📦 Upcoming Features (Planned)

- Calendar event drag-and-drop rescheduling
- Ping analytics and statistics dashboard
- Alliance-wide broadcast support
- Mobile-responsive improvements
- Webhook health monitoring

---

For the latest updates, issues, or feature requests:
👉 [github.com/MattFalahe/SeAT-Discord-Pings](https://github.com/MattFalahe/SeAT-Discord-Pings)
