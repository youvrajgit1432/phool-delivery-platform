<?php
$page_title = "Nepali Calendar - Phool Delivery";
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/date/NepaliDateConverter.php';

$pdo = getDBConnection();

// Get current Nepali date
$currentDate = NepaliDateConverter::getCurrentNepaliDate($pdo);

// Get all months
$months = NepaliDateConverter::getAllNepaliMonths();

// Format current date
$formattedDate = NepaliDateConverter::formatNepaliDate(
    $currentDate['year'],
    $currentDate['month'],
    $currentDate['day'],
    $currentDate['weekday']
);
include '../app/views/layouts/header.php';
?>
 
    <style>
        /* Calendar specific styles */
        .calendar-wrapper {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 40px;
            margin-bottom: 30px;
        }
        
        .calendar-wrapper h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-size: 2.2em;
        }
        
        .calendar-wrapper .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1em;
        }
        
        .current-date-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 30px;
            color: white;
            margin-bottom: 40px;
            text-align: center;
        }
        
        .current-date-section h2 {
            font-size: 1.2em;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        
        .nepali-date-display {
            font-size: 3em;
            font-weight: bold;
            margin-bottom: 15px;
            font-family: 'Arial Unicode MS', Arial, sans-serif;
        }
        
        .nepali-date-info {
            font-size: 1.1em;
            line-height: 1.6;
        }
        
        .date-detail {
            display: inline-block;
            margin: 5px 15px;
        }
        
        .update-indicator {
            font-size: 0.9em;
            margin-top: 15px;
            opacity: 0.8;
        }
        
        .update-indicator.live::after {
            content: ' ●';
            color: #4ade80;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .months-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .month-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #667eea;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .month-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        }
        
        .month-card h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 1.3em;
        }
        
        .month-card .month-number {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            margin-right: 10px;
        }
        
        .month-card .month-days {
            color: #666;
            font-size: 1.1em;
            font-weight: 500;
        }
        
        .highlight {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px;
            border-radius: 8px;
        }
        
        .info-section {
            background: #f0f4ff;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            border-left: 4px solid #667eea;
        }
        
        .info-section h3 {
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .info-section p {
            color: #666;
            line-height: 1.6;
        }
        
        .auto-update-badge {
            display: inline-block;
            background: #4ade80;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            margin-top: 15px;
        }
        
        @media (max-width: 768px) {
            .calendar-wrapper {
                padding: 20px;
            }
            
            .calendar-wrapper h1 {
                font-size: 1.8em;
            }
            
            .nepali-date-display {
                font-size: 2em;
            }
            
            .months-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
 
    <div class="calendar-wrapper">
        <h1>नेपाली पञ्चाङ्ग</h1>
        <p class="subtitle">Nepali Calendar (Bikram Sambat)</p>
        
        <div class="current-date-section">
            <h2>Today's Nepali Date</h2>
            <div class="nepali-date-display" id="nepaliDateDisplay">
                <?php echo $currentDate['year'] . '-' . str_pad($currentDate['month'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($currentDate['day'], 2, '0', STR_PAD_LEFT); ?>
            </div>
            <div class="nepali-date-info" id="nepaliDateInfo">
                <div class="date-detail">📅 <strong><?php echo $formattedDate; ?></strong></div>
            </div>
            <div class="update-indicator live" id="updateIndicator">
                Auto-updating daily
            </div>
            <div class="auto-update-badge">✓ Automatically Updated</div>
        </div>
        
        <div class="months-grid">
            <?php foreach ($months as $monthNum => $month): ?>
                <div class="month-card <?php echo $monthNum == $currentDate['month'] ? 'highlight' : ''; ?>">
                    <h3><?php echo $month['name']; ?></h3>
                    <span class="month-number"><?php echo $monthNum; ?></span>
                    <div class="month-days">📆 <?php echo $month['days']; ?> days</div>
                    <?php if ($monthNum == $currentDate['month']): ?>
                        <div style="margin-top: 10px; font-size: 0.9em;">Current month</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="info-section">
            <h3>📋 Nepali Calendar Information</h3>
            <p>
                <strong>Year:</strong> <?php echo $currentDate['year']; ?> BS (Bikram Sambat)<br>
                <strong>Current Month:</strong> <?php echo $currentDate['month_name']; ?> (Month <?php echo $currentDate['month']; ?>)<br>
                <strong>Total Days in Year:</strong> 365 days<br>
                <strong>Today's Weekday:</strong> <?php echo NepaliDateConverter::getWeekdayName($currentDate['weekday']); ?>
            </p>
        </div>
        
        <div class="info-section" style="margin-top: 20px;">
            <h3>⚙️ Auto-Update System</h3>
            <p>
                This calendar automatically updates every day at midnight. The Nepali date is synchronized with the English calendar system and maintains accurate calculations based on the Nepali calendar rules.
            </p>
        </div>
    </div>
    
    <script>
        // Auto-refresh current date every minute
        setInterval(() => {
            fetch('date/api.php?action=current')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const dateDisplay = document.getElementById('nepaliDateDisplay');
                        const dateInfo = document.getElementById('nepaliDateInfo');
                        
                        const year = data.data.year;
                        const month = String(data.data.month).padStart(2, '0');
                        const day = String(data.data.day).padStart(2, '0');
                        
                        dateDisplay.textContent = `${year}-${month}-${day}`;
                        dateInfo.innerHTML = `<div class="date-detail">📅 <strong>${data.formatted}</strong></div>`;
                    }
                })
                .catch(error => console.error('Error updating date:', error));
        }, 60000); // Update every 60 seconds
    </script>
 
 
<?php
include '../app/views/layouts/footer.php';
?>