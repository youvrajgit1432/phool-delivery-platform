# Nepali Date Auto-Update System Setup Guide

## Overview
This system automatically updates the current Nepali date in the database every day at midnight. The UI will display the current Nepali date, and the database will be synchronized automatically.

---

## Components

### 1. **Database Table: `current_nepali_date`**
- Stores the current English and Nepali date
- Updated automatically every day
- Fields:
  - `english_date`: Current English/Gregorian date (YYYY-MM-DD)
  - `year`: Nepali year (Bikram Sambat)
  - `month`: Nepali month (1-12)
  - `day`: Nepali day
  - `weekday`: Day of week (0=Sunday, 6=Saturday)
  - `month_name`: Nepali month name (Baishakh, Jestha, etc)
  - `weekday_name`: Day name (Sunday, Monday, etc)
  - `last_updated`: Timestamp of last update

### 2. **Auto-Update Script**
Location: `/admin/public/date/auto-update.php`
- Runs daily at midnight (00:00)
- Converts current English date to Nepali date
- Updates/inserts the `current_nepali_date` table
- Logs all updates to `nepali_date_update.log`

### 3. **API Endpoint**
Location: `/admin/public/date/api.php`
- Action: `?action=current` - Returns current Nepali date in JSON format
- Used by the UI to display the live date

### 4. **Calendar UI**
Location: `/admin/public/nepali_calendar.php`
- Displays current Nepali date
- Auto-refreshes every 60 seconds
- Shows all 12 Nepali months with day counts

---

## Setup Instructions

### Step 1: Create the Database Table

Run this SQL script on your database:

```bash
mysql -u root -p phooldelivery < database/CREATE-CURRENT-NEPALI-DATE-TABLE.sql
```

Or import it manually through phpMyAdmin.

### Step 2: Set Up Automatic Daily Updates

#### **Option A: Windows (XAMPP on Windows)**

**Step A1: Run as Administrator**
1. Open Command Prompt as Administrator
2. Navigate to the auto-update script directory:
   ```
   cd C:\xampp\htdocs\phool-delivery\admin\public\date
   ```

**Step A2: Create Task Scheduler Entry**

Run the setup batch file:
```bash
setup-scheduler.bat
```

OR manually create the task:
```bash
schtasks /create /tn "Phool Delivery\Nepali Date Auto-Update" ^
    /tr "C:\xampp\php\php.exe C:\xampp\htdocs\phool-delivery\admin\public\date\auto-update.php" ^
    /sc daily /st 00:00 /f
```

**Step A3: Verify**
1. Open Task Scheduler (taskschd.msc)
2. Look for "Phool Delivery" folder
3. Find "Nepali Date Auto-Update" task
4. Right-click and select "Run" to test

#### **Option B: Linux/Unix Servers**

**Step B1: Open Crontab**
```bash
crontab -e
```

**Step B2: Add Cron Job**
```
0 0 * * * cd /var/www/html/phool-delivery/admin/public && php date/auto-update.php
```

Or:
```
0 0 * * * /usr/bin/php /var/www/html/phool-delivery/admin/public/date/auto-update.php
```

**Step B3: Verify**
```bash
crontab -l
```

---

## Testing

### Test 1: Manual Update
1. Open a terminal/command prompt
2. Navigate to: `C:\xampp\htdocs\phool-delivery\admin\public\date`
3. Run: `php auto-update.php`
4. You should see output:
   ```
   2026-01-03 10:30:45 - Updated Nepali date to: Poush 20, 2082 (Poush 20, 2082 BS)
   ```

### Test 2: Check Database
```sql
SELECT * FROM current_nepali_date;
```

You should see a record with today's date converted to Nepali format.

### Test 3: Check API
Visit: `http://localhost/phool-delivery/admin/public/date/api.php?action=current`

You should see JSON response:
```json
{
  "status": "success",
  "data": {
    "id": "1",
    "english_date": "2026-01-03",
    "year": "2082",
    "month": "9",
    "day": "20",
    "weekday": "5",
    "month_name": "Poush",
    "weekday_name": "Friday",
    "last_updated": "2026-01-03 00:00:00"
  },
  "formatted": "Poush 20, 2082"
}
```

### Test 4: Check UI
Visit: `http://localhost/phool-delivery/admin/public/nepali_calendar.php`

You should see:
- Current Nepali date displayed at the top
- Auto-updating every 60 seconds
- All 12 months shown with their day counts
- Current month highlighted

### Test 5: Check Log File
View: `/admin/public/date/nepali_date_update.log`

Should contain entries like:
```
2026-01-03 10:30:45 - Updated Nepali date to: Poush 20, 2082 (Poush 20, 2082 BS)
2026-01-02 00:00:00 - Updated Nepali date to: Poush 19, 2082 (Poush 19, 2082 BS)
```

---

## Troubleshooting

### Issue: Task doesn't run automatically

**Solution:**
1. Check Task Scheduler > View > Refresh (F5)
2. Right-click the task > Properties
3. Check "Run whether user is logged in or not"
4. Click OK and re-enable the task

### Issue: PHP error in log file

**Solution:**
1. Check that `bootstrap/app.php` exists and has database connection
2. Verify XAMPP is running
3. Check database credentials in `config/Database.php`

### Issue: Date not updating in database

**Solution:**
1. Manually test: `php auto-update.php`
2. Check error log: `nepali_date_update.log`
3. Verify database connectivity: `php check-db-simple.php`
4. Check permissions on log file directory

### Issue: Windows Task Scheduler not found

**Solution:**
1. Open Command Prompt as Administrator
2. Navigate to: `C:\xampp\htdocs\phool-delivery\admin\public\date`
3. Run: `setup-scheduler.bat`

---

## File Locations

```
phool-delivery/
├── database/
│   └── CREATE-CURRENT-NEPALI-DATE-TABLE.sql
├── admin/
│   └── public/
│       ├── nepali_calendar.php (UI - displays current date)
│       ├── nepali_calendar_update.php (legacy update script)
│       └── date/
│           ├── auto-update.php (automatic daily update)
│           ├── api.php (JSON API endpoint)
│           ├── NepaliDateConverter.php (conversion logic)
│           ├── setup-scheduler.bat (Windows Task Scheduler setup)
│           ├── nepali_date_update.log (update log)
│           └── check-db-simple.php (database test)
```

---

## How It Works

1. **Midnight (00:00)**: Task Scheduler or Cron job triggers `auto-update.php`
2. **Conversion**: Gets today's English date and converts to Nepali using `NepaliDateConverter`
3. **Database Update**: Inserts or updates record in `current_nepali_date` table
4. **Logging**: Writes result to `nepali_date_update.log`
5. **UI Display**: When user visits `nepali_calendar.php`, it:
   - Fetches current date from database
   - Displays it on the page
   - Auto-refreshes every 60 seconds via JavaScript AJAX call to `api.php`

---

## Key Features

✓ Automatic daily updates at midnight  
✓ Database synchronized with English dates  
✓ Live UI with auto-refresh every 60 seconds  
✓ Full Nepali calendar display (all 12 months)  
✓ Comprehensive logging  
✓ Easy setup on Windows and Linux  
✓ JSON API for integration with other systems  

---

## Maintenance

- **Check logs monthly**: `/admin/public/date/nepali_date_update.log`
- **Monitor database**: Ensure `current_nepali_date` table is being updated
- **Task Scheduler**: Verify task is still enabled in Windows Task Scheduler
- **Cron logs**: Check system cron logs on Linux servers

---

## Support

For issues or questions:
1. Check the log file first
2. Test the PHP script manually
3. Verify database connection
4. Check Task Scheduler/Cron job status
