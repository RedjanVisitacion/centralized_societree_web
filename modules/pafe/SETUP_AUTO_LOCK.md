# Auto-Lock Setup Instructions

## Overview
The auto-lock feature automatically locks event attendance sessions at specified times. This prevents students from marking attendance after the designated cutoff times.

## Features
- **Automatic Locking**: Sessions lock automatically at configured times
- **Flexible Timing**: Set different lock times for morning and afternoon sessions
- **Real-time Checking**: System checks for auto-lock conditions on every page load
- **Cron Job Support**: Optional background processing for precise timing

## Setup Options

### Option 1: Real-time Checking (Default)
The system automatically checks and locks sessions every time someone visits the events page. This works without any additional setup.

**Pros:**
- No server configuration required
- Works on any hosting environment
- Immediate activation

**Cons:**
- Requires page visits to trigger checks
- May have slight delays

### Option 2: Cron Job (Recommended for Production)
Set up a cron job for precise, minute-by-minute checking.

#### Step 1: Locate the Cron Script
The auto-lock script is located at:
```
modules/pafe/auto_lock_cron.php
```

#### Step 2: Set Up Cron Job
Add this line to your server's crontab to run every minute:

```bash
# Edit crontab
crontab -e

# Add this line (replace /path/to/ with your actual path)
* * * * * /usr/bin/php /path/to/modules/pafe/auto_lock_cron.php
```

#### Step 3: Verify Setup
Check your server's error logs to confirm the cron job is running:
```bash
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/nginx/error.log
```

You should see log entries like:
```
Auto-lock cron running at 2024-12-10 14:30:00
Auto-locked morning session for event ID: 5 (Workshop on Teaching Methods)
```

## How to Use

### 1. Create Event with Auto-Lock
1. Click "Create New Event"
2. Fill in event details
3. In the "Automatic Session Locking" section:
   - Check "Enable automatic session locking"
   - Set morning session auto-lock time (optional)
   - Set afternoon session auto-lock time (optional)
4. Click "Create Event"

### 2. Manage Existing Events
1. Find your event in the events list
2. Click "Auto-Lock Settings" button
3. Configure auto-lock times
4. Click "Save Auto-Lock Settings"

### 3. Monitor Auto-Lock Status
- Events with auto-lock enabled show a green status indicator
- Lock times are displayed in the auto-lock settings
- Sessions automatically change to "LOCKED" status at the specified times

## Example Scenarios

### Scenario 1: Workshop with Strict Timing
- **Event**: "Teaching Methods Workshop"
- **Date**: December 15, 2024
- **Morning Session**: 8:00 AM - 12:00 PM (Lock at 8:30 AM)
- **Afternoon Session**: 1:00 PM - 5:00 PM (Lock at 1:30 PM)

**Setup**:
- Enable auto-lock
- Morning auto-lock time: 08:30
- Afternoon auto-lock time: 13:30

### Scenario 2: Flexible Conference
- **Event**: "Education Conference"
- **Date**: December 20, 2024
- **Morning Session**: Open attendance (no auto-lock)
- **Afternoon Session**: Lock at 2:00 PM

**Setup**:
- Enable auto-lock
- Morning auto-lock time: (leave empty)
- Afternoon auto-lock time: 14:00

## Troubleshooting

### Auto-Lock Not Working
1. **Check if auto-lock is enabled** for the event
2. **Verify times are set correctly** (use 24-hour format)
3. **Ensure event date is today** (auto-lock only works on event day)
4. **Check server time zone** matches your local time zone

### Cron Job Issues
1. **Verify cron job is running**:
   ```bash
   sudo service cron status
   ```

2. **Check cron logs**:
   ```bash
   grep CRON /var/log/syslog
   ```

3. **Test script manually**:
   ```bash
   php /path/to/modules/pafe/auto_lock_cron.php
   ```

### Time Zone Problems
Ensure your server's time zone matches your event location:
```bash
# Check current timezone
timedatectl

# Set timezone (example for Philippines)
sudo timedatectl set-timezone Asia/Manila
```

## Security Notes
- Auto-lock times are stored in the database
- Only admins can configure auto-lock settings
- Locked sessions cannot be unlocked automatically (manual admin action required)
- All auto-lock actions are logged for audit purposes

## Support
If you encounter issues with the auto-lock feature:
1. Check the server error logs
2. Verify database connectivity
3. Ensure proper file permissions on the cron script
4. Contact your system administrator for server-level issues