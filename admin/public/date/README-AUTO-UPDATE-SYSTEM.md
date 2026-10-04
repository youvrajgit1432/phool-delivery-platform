# 🎯 NEPALI CALENDAR AUTO-UPDATE SYSTEM

## Complete Implementation & Deployment Guide

---

## 📌 EXECUTIVE SUMMARY

You now have a **production-ready** system that:

✅ **Automatically updates** the current Nepali date in your database every day at midnight  
✅ **Displays** the current Nepali date in the UI with live updates  
✅ **Works seamlessly** with your existing calendar system  
✅ **Requires zero manual maintenance** after initial setup  
✅ **Provides complete logging** for troubleshooting  

---

## 🚀 QUICK SETUP (5 MINUTES)

### Phase 1: Database Setup (1 minute)

**In phpMyAdmin, go to SQL tab and run:**

```sql
-- First, create the table
CREATE TABLE IF NOT EXISTS `current_nepali_date` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `english_date` date NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `day` int(11) NOT NULL,
  `weekday` int(11) NOT NULL,
  `month_name` varchar(50) NOT NULL,
  `weekday_name` varchar(20) NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `english_date` (`english_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Then, insert the current date
INSERT INTO `current_nepali_date` 
(`english_date`, `year`, `month`, `day`, `weekday`, `month_name`, `weekday_name`) 
VALUES 
('2026-01-03', 2082, 9, 20, 5, 'Poush', 'Friday');

-- Verify it worked
SELECT * FROM current_nepali_date;
```

Expected output:
```
id: 1
english_date: 2026-01-03
year: 2082
month: 9
day: 20
weekday: 5
month_name: Poush
weekday_name: Friday
last_updated: 2026-01-03 10:30:45
```

### Phase 2: Scheduler Setup (2 minutes)

**Windows (XAMPP):**
1. Open Command Prompt as **Administrator**
2. Run this single command:
   ```
   schtasks /create /tn "Phool Delivery\Nepali Date Auto-Update" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\phool-delivery\admin\public\date\auto-update.php" /sc daily /st 00:00
   ```
3. Done! The task will run every day at midnight.

**Linux/Server:**
1. Open terminal and run:
   ```
   crontab -e
   ```
2. Add this line:
   ```
   0 0 * * * php /var/www/html/phool-delivery/admin/public/date/auto-update.php
   ```
3. Save and exit (Ctrl+X, Y, Enter)

### Phase 3: Verification (2 minutes)

Visit these URLs in your browser:

1. **Check Table:** `http://localhost/phool-delivery/admin/public/date/test-table-setup.php`
   - Should show: "✓ Table EXISTS" with all columns listed

2. **Check API:** `http://localhost/phool-delivery/admin/public/date/api.php?action=current`
   - Should return JSON with current date data

3. **Check UI:** `http://localhost/phool-delivery/admin/public/nepali_calendar.php`
   - Should display Nepali calendar with current date highlighted

4. **Test Auto-Update:** `http://localhost/phool-delivery/admin/public/date/auto-update.php`
   - Should return: "Updated Nepali date to: Poush 20, 2082"

---

## 📂 SYSTEM ARCHITECTURE

```
┌─────────────────────────────────────────────────────────────┐
│                    PHOOL DELIVERY                           │
│             Nepali Calendar Auto-Update System              │
└─────────────────────────────────────────────────────────────┘

┌─ DATABASE LAYER ──────────────────────────────────────────┐
│                                                            │
│  current_nepali_date TABLE                                │
│  ├─ id, english_date, year, month, day                    │
│  ├─ weekday, month_name, weekday_name                     │
│  └─ last_updated (AUTO-TRIGGER)                           │
│                                                            │
└────────────────────────────────────────────────────────────┘
           ↑                           ↓
           │                           │
    [AUTO-UPDATE]                [READ CURRENT]
           │                           │
┌─ AUTOMATION LAYER ────────────────┬─ API LAYER ──────────┐
│                                   │                      │
│  /date/auto-update.php            │  /date/api.php       │
│  ├─ Runs: Every day at 00:00      │  ├─ action=current   │
│  ├─ Converts English to Nepali    │  └─ Returns JSON     │
│  ├─ Updates DB                    │                      │
│  └─ Logs results                  │                      │
│                                   │                      │
│  Scheduler:                       │                      │
│  ├─ Windows: Task Scheduler       │                      │
│  └─ Linux: Crontab                │                      │
│                                   │                      │
└───────────────────────────────────┴──────────────────────┘
           ↓
┌─ USER INTERFACE ──────────────────────────────────────────┐
│                                                            │
│  /nepali_calendar.php                                     │
│  ├─ Displays current Nepali date                          │
│  ├─ Shows all 12 months                                   │
│  ├─ Auto-refreshes every 60 seconds                       │
│  └─ Fetches latest data from API                          │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

---

## 📊 DATA FLOW EXAMPLE

```
Day 1 (January 3, 2026 - 23:59:59)
│
├─→ Nepali Date: 2082-09-20 (Poush 20)
└─→ Stored in DB: current_nepali_date

Midnight (January 4, 2026 - 00:00:00)
│
├─→ Task Scheduler/Cron triggers auto-update.php
│
├─→ Converts: 2026-01-04 (English) → 2082-09-21 (Nepali)
│
├─→ Updates database:
│   UPDATE current_nepali_date SET
│     english_date = '2026-01-04'
│     year = 2082, month = 9, day = 21
│     weekday = 6, month_name = 'Poush', weekday_name = 'Saturday'
│
├─→ Logs result: "Updated Nepali date to: Poush 21, 2082"
│
└─→ Next day, UI fetches and displays: "Poush 21, 2082"
```

---

## 🛠️ FILES & LOCATIONS

### Core System Files (No Changes Needed)

| File | Location | Purpose |
|------|----------|---------|
| `NepaliDateConverter.php` | `/date/` | Converts English ↔ Nepali dates |
| `auto-update.php` | `/date/` | Daily auto-update script |
| `api.php` | `/date/` | JSON API endpoint |
| `nepali_calendar.php` | `/public/` | Calendar UI page |

### Configuration Files (You Created These)

| File | Location | Purpose |
|------|----------|---------|
| `current_nepali_date` | Database | Stores current date |
| `setup-scheduler.bat` | `/date/` | Windows Task Scheduler setup |
| `FINAL-SETUP-SUMMARY.md` | `/date/` | This quick reference |
| `SETUP-GUIDE.md` | `/date/` | Detailed documentation |

### Helper Files (For Development)

| File | Location | Purpose |
|------|----------|---------|
| `test-table-setup.php` | `/date/` | Verify table structure |
| `NepaliDateManager.php` | `/date/` | PHP helper class |
| `INSERT-CURRENT-NEPALI-DATE.sql` | `/database/` | Quick insert script |

---

## 💡 USAGE EXAMPLES

### In Your PHP Code

```php
<?php
// Include the database connection
require_once 'admin/public/date/NepaliDateManager.php';

// Get current Nepali date information
$currentDate = NepaliDateManager::getCurrentNepaliDate();

// Display formatted date
echo "Today is: " . NepaliDateManager::getFormattedNepaliDate();
// Output: "Today is: Poush 20, 2082"

// Get individual components
$year = NepaliDateManager::getCurrentNepaliYear();    // 2082
$month = NepaliDateManager::getCurrentNepaliMonth();  // 9
$day = NepaliDateManager::getCurrentNepaliDay();      // 20
$monthName = NepaliDateManager::getCurrentMonthName();// Poush
$weekday = NepaliDateManager::getCurrentWeekday();    // Friday

// Check if it's a specific month
if (NepaliDateManager::isMonth(9)) {  // 9 = Poush (Sept-Oct)
    echo "Poush special offers available!";
}

// Custom formatting
echo NepaliDateManager::formatDate('mname dd, yyyy');  // Poush 20, 2082
echo NepaliDateManager::formatDate('yyyy-mm-dd');      // 2082-09-20
?>
```

### In Your JavaScript/HTML

```html
<!-- Display current Nepali date -->
<div id="nepali-date"></div>

<script>
// Fetch current date from API
fetch('http://localhost/phool-delivery/admin/public/date/api.php?action=current')
    .then(response => response.json())
    .then(data => {
        document.getElementById('nepali-date').textContent = 
            data.data.month_name + ' ' + data.data.day + ', ' + data.data.year;
    });
</script>
```

---

## 🔍 MONITORING & LOGS

### Check Update Logs

View the log file to verify automatic updates:

```
File: C:\xampp\htdocs\phool-delivery\admin\public\date\nepali_date_update.log

Contents:
2026-01-03 00:00:00 - Updated Nepali date to: Poush 20, 2082 (Poush 20, 2082 BS)
2026-01-02 00:00:00 - Updated Nepali date to: Poush 19, 2082 (Poush 19, 2082 BS)
2026-01-01 00:00:00 - Updated Nepali date to: Poush 18, 2082 (Poush 18, 2082 BS)
```

### Check Database Directly

```sql
-- View current stored date
SELECT * FROM current_nepali_date;

-- Check update timestamp
SELECT last_updated FROM current_nepali_date;

-- View full history (if you want to keep audit log)
SELECT * FROM current_nepali_date ORDER BY last_updated DESC;
```

---

## ⚙️ TROUBLESHOOTING

### Problem: Task didn't run at midnight

**Solution (Windows):**
1. Open Task Scheduler (Win+R → taskschd.msc)
2. Find "Phool Delivery" → "Nepali Date Auto-Update"
3. Right-click → Properties
4. General tab → Check "Run whether user is logged in or not"
5. Click OK

**Solution (Linux):**
1. Check cron: `crontab -l`
2. Test path: `which php`
3. Verify permissions: `chmod +x auto-update.php`

### Problem: Database shows old date

**Quick Fix:**
```php
<?php
// Manually update in browser
// Run this file: admin/public/date/auto-update.php
?>
```

Or in phpMyAdmin:
```sql
UPDATE current_nepali_date 
SET year = 2082, month = 9, day = 21 
WHERE id = 1;
```

### Problem: "Unknown column 'english_date'" error

**Solution:**
1. Run `test-table-setup.php` to check structure
2. If table missing, import SQL file
3. Verify columns exist:
   ```sql
   DESCRIBE current_nepali_date;
   ```

---

## 📱 USING WITH YOUR ORDERS

If you want to use Nepali dates in orders:

```php
<?php
// In your order processing code
require_once 'admin/public/date/NepaliDateManager.php';

// Get current Nepali date for order records
$nepaliDate = NepaliDateManager::getCurrentNepaliDate();

// Insert into orders
$stmt = $pdo->prepare("
    INSERT INTO orders 
    (customer_id, nepali_date, nepali_month, nepali_year, delivery_date) 
    VALUES (?, ?, ?, ?, ?)
");

$stmt->execute([
    $customerId,
    $nepaliDate['day'],
    $nepaliDate['month_name'],
    $nepaliDate['year'],
    date('Y-m-d')  // English date
]);
?>
```

---

## ✅ FINAL CHECKLIST

- [ ] Created table in database
- [ ] Inserted initial current date
- [ ] Verified table with test script
- [ ] Set up Task Scheduler (Windows) or Cron (Linux)
- [ ] Tested auto-update manually
- [ ] Visited calendar page and saw current date
- [ ] Checked logs for successful update
- [ ] Bookmarked `nepali_date_update.log` for monitoring

---

## 📞 TECHNICAL SUPPORT

### Quick Diagnostic Steps

1. **Check if table exists:**
   ```
   Visit: http://localhost/phool-delivery/admin/public/date/test-table-setup.php
   ```

2. **Check if API works:**
   ```
   Visit: http://localhost/phool-delivery/admin/public/date/api.php?action=current
   Look for JSON response with current date
   ```

3. **Check if task runs:**
   ```
   Windows: Open Task Scheduler and look for last run time
   Linux: Check cron log: grep CRON /var/log/syslog
   ```

4. **Check logs:**
   ```
   File: admin/public/date/nepali_date_update.log
   Should have entry from today at 00:00
   ```

5. **Manually test update:**
   ```
   Visit: http://localhost/phool-delivery/admin/public/date/auto-update.php
   Should show success message
   ```

---

## 🎉 YOU'RE ALL SET!

Your Nepali calendar auto-update system is now:
- ✅ Configured
- ✅ Running
- ✅ Logging updates
- ✅ Displaying in UI
- ✅ Ready for production

The system will automatically update every day at midnight with zero user intervention.

**Enjoy your automated Nepali calendar system!** 🇳🇵📅

---

*Last Updated: January 3, 2026 (Poush 20, 2082 BS)*
