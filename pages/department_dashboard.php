<?php
session_start();
require_once '../includes/db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centralized Profiling and Record Authentication System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/department-sidebar.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --gray: #95a5a6;
            --light-gray: #f8f9fa;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            height: 100vh;
            overflow: auto;
        }
        
        /* Sidebar styles are handled by department-sidebar.css */
        
        /* Main Content Styles */
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .header-content {
            display: flex;
            flex-direction: column;
        }
        
        .welcome-message {
            font-size: 1.2rem;
            color: var(--gray);
            margin-bottom: 5px;
        }
        
        .header h1 {
            color: var(--primary);
            font-size: 1.8rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-info h3 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .stat-info p {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .bg-primary { background: var(--primary); }
        .bg-success { background: var(--success); }
        .bg-warning { background: var(--warning); }
        .bg-danger { background: var(--danger); }
        
        /* Dashboard Panels Layout */
        .dashboard-panels {
            display: flex;
            gap: 20px; /* Space between left and right panels */
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
        }

        .left-panel,
        .right-panel {
            flex: 1; /* Allow panels to grow and shrink */
            min-width: 300px; /* Minimum width before wrapping */
        }

        .left-panel {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .charts-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .chart-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .chart-card h3 {
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-wrapper {
            position: relative;
            height: 300px; /* Fixed height for charts */
            width: 100%;
        }

        .right-panel {
            display: flex;
            flex-direction: column;
            gap: 20px; /* Space between calendar and notifications */
        }

        .calendar-card,
        .notifications-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .calendar-card h2 {
            text-align: center;
            margin-bottom: 10px;
            color: var(--primary);
        }

        .calendar-card h3 {
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .calendar-header button {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: var(--secondary);
        }

        .calendar-table {
            width: 100%;
            text-align: center;
        }

        .calendar-table th,
        .calendar-table td {
            padding: 8px;
            border: none;
        }

        .calendar-table th {
            color: var(--gray);
            font-weight: normal;
        }

        .calendar-table td {
            cursor: pointer;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .calendar-table td:hover:not(.inactive) {
            background-color: var(--light-gray);
        }

        .calendar-table td.today {
            background-color: var(--secondary);
            color: white;
            font-weight: bold;
        }

        .calendar-table td.inactive {
            color: #ccc;
            cursor: default;
        }

        .notifications-card h3 {
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notification-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-icon {
            font-size: 1.2rem;
            color: var(--secondary);
        }

        .notification-title {
            font-weight: bold;
            color: var(--dark);
        }

        .notification-message {
            font-size: 0.9rem;
            color: var(--gray);
        }

        .notification-time {
            font-size: 0.8rem;
            color: #aaa;
        }
        
        .notifications-list {
            max-height: 300px; /* Adjust as needed */
            overflow-y: auto;
            padding-right: 10px; /* To prevent scrollbar from overlapping content */
        }
        
        /* Dashboard Sections */
        .dashboard-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .section-header h2 {
            color: var(--primary);
            font-size: 1.4rem;
        }
        
        .section-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--secondary);
            color: white;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: var(--light-gray);
            color: var(--dark);
            font-weight: 600;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-verified {
            background: #d1edff;
            color: #0c5460;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Records Section */
        .records-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .record-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        
        .record-card:hover {
            transform: translateY(-5px);
        }
        
        .record-card h3 {
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .record-card ul {
            list-style: none;
            margin-left: 10px;
        }
        
        .record-card li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .record-card li:last-child {
            border-bottom: none;
        }
        
        .record-card i {
            color: var(--secondary);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .records-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .user-info {
                align-self: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../partials/department_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div class="header-content">
                    <div class="welcome-message" data-first-name="<?php echo htmlspecialchars($_SESSION['first_name']); ?>" data-last-name="<?php echo htmlspecialchars($_SESSION['last_name']); ?>" data-role="<?php echo htmlspecialchars($_SESSION['role']); ?>"></div>
                    <h1>Centralized Profiling Dashboard</h1>
                </div>
                <div class="header-actions">        
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php
                                $profilePic = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default.jpg';
                                $profilePicPath = '../images/profile_pictures/' . $profilePic;
                                if (!file_exists($profilePicPath) || is_dir($profilePicPath)) {
                                    $profilePicPath = '../images/profile_pictures/default.jpg'; // Fallback to default if file doesn't exist
                                }
                            ?>
                            <img src="<?php echo $profilePicPath; ?>" alt="Profile Picture" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                        </div>
                        <div class="user-details">
                            <h2><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h2>
                            <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_SESSION['role']))); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon bg-primary">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>142</h3>
                        <p>Verified Applications</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3>89</h3>
                        <p>Senior Citizen Records</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bg-warning">
                        <i class="fas fa-wheelchair"></i>
                    </div>
                    <div class="stat-info">
                        <h3>67</h3>
                        <p>PWD Records</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bg-danger">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3>1,284</h3>
                        <p>Total Processed</p>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-panels">
                        <div class="left-panel">
                            <h2 style="color: var(--primary);">Application Statistics</h2>
                            <div class="charts-container">
                                <div class="chart-card">
                                    <h3><i class="fas fa-chart-bar"></i> Barangay Records Chart</h3>
                                    <div class="chart-wrapper"><canvas id="barangayRecordsChart"></canvas></div>
                                </div>
                                <div class="chart-card">
                                    <h3><i class="fas fa-chart-bar"></i> Yearly Records Chart</h3>
                                    <div class="chart-wrapper"><canvas id="yearlyRecordsChart"></canvas></div>
                                </div>
                            </div>
                        </div>
        
                        <div class="right-panel">
                            <div class="calendar-card">
                                <h2 id="current-time"></h2>
                                <h3><i class="fas fa-calendar-alt"></i> Calendar</h3>
                                <div class="calendar-body">
                                    <div class="calendar-header">
                                        <button id="prev-month"><i class="fas fa-chevron-left"></i></button>
                                        <span id="month-year"></span>
                                        <button id="next-month"><i class="fas fa-chevron-right"></i></button>
                                    </div>
                                    <table class="calendar-table">
                                        <thead><tr><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th></tr></thead>
                                        <tbody id="calendar-days"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="notifications-card">
                                <h3><i class="fas fa-bell"></i> Recent Applications</h3>
                                <div class="notifications-list" id="realtime-notifications-list">
                                    <p>Loading notifications...</p>
                                </div>
                            </div>
                        </div>
                    </div>
        
                    <div class="footer">                <p>Centralized Profiling and Record Authentication System | Department Admin &copy; 2024</p>
            </div>
        </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/sidebar-toggle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializeWelcomeMessage();
            initializeCalendar();
            updateTime();
            setInterval(updateTime, 1000);

            // Fetch dynamic data for charts and notifications
            fetch('../api/get_realtime_data.php')
                .then(response => response.json())
                .then(result => {
                    if (result.status === 'success') {
                        renderNotifications(result.data.notifications);
                        initializeDepartmentCharts(result.data);
                        updateStatCards(result.data); // Call new function to update stat cards
                    } else {
                        console.error('Failed to load dashboard data:', result.message);
                        document.getElementById('realtime-notifications-list').innerHTML = '<p>Could not load notifications.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching dashboard data:', error);
                    document.getElementById('realtime-notifications-list').innerHTML = '<p>Error loading notifications.</p>';
                });
        });

        function updateStatCards(data) {
            document.querySelector('.stat-card:nth-child(1) h3').textContent = data.verified_applications;
            document.querySelector('.stat-card:nth-child(2) h3').textContent = data.senior_citizen_records;
            document.querySelector('.stat-card:nth-child(3) h3').textContent = data.pwd_records;
            document.querySelector('.stat-card:nth-child(4) h3').textContent = data.total_processed;
        }

        function updateTime() {
            const timeEl = document.getElementById('current-time');
            if (timeEl) {
                timeEl.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }
        }

        function initializeWelcomeMessage() {
            const welcomeMessage = document.querySelector('.welcome-message');
            const firstName = welcomeMessage.dataset.firstName;
            const lastName = welcomeMessage.dataset.lastName;
            const hour = new Date().getHours();
            let greeting = (hour < 12) ? "Good morning" : (hour < 18) ? "Good afternoon" : "Good evening";
            welcomeMessage.innerHTML = `${greeting}, <strong>${firstName} ${lastName}</strong>!`;
        }

        function initializeCalendar() {
            const monthYearEl = document.getElementById('month-year');
            const calendarDaysEl = document.getElementById('calendar-days');
            const prevMonthBtn = document.getElementById('prev-month');
            const nextMonthBtn = document.getElementById('next-month');
            if (!monthYearEl || !calendarDaysEl || !prevMonthBtn || !nextMonthBtn) return;
            let currentDate = new Date();
            function renderCalendar() {
                const year = currentDate.getFullYear(), month = currentDate.getMonth();
                const today = new Date();
                const firstDayOfMonth = new Date(year, month, 1), lastDayOfMonth = new Date(year, month + 1, 0);
                const firstDayOfWeek = firstDayOfMonth.getDay(), totalDays = lastDayOfMonth.getDate();
                const prevMonthDays = new Date(year, month, 0).getDate();
                monthYearEl.textContent = `${firstDayOfMonth.toLocaleString('default', { month: 'long' })} ${year}`;
                calendarDaysEl.innerHTML = '';
                let date = 1, nextMonthDate = 1;
                for (let i = 0; i < 6; i++) {
                    const row = document.createElement('tr');
                    let weekHasDays = false;
                    for (let j = 0; j < 7; j++) {
                        const cell = document.createElement('td');
                        if (i === 0 && j < firstDayOfWeek) {
                            cell.textContent = prevMonthDays - firstDayOfWeek + j + 1;
                            cell.classList.add('inactive');
                        } else if (date > totalDays) {
                            cell.textContent = nextMonthDate++;
                            cell.classList.add('inactive');
                        } else {
                            cell.textContent = date;
                            if (date === today.getDate() && month === today.getMonth() && year === today.getFullYear()) cell.classList.add('today');
                            date++;
                            weekHasDays = true;
                        }
                        row.appendChild(cell);
                    }
                    if (weekHasDays || i === 0) calendarDaysEl.appendChild(row);
                }
            }
            prevMonthBtn.addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() - 1); renderCalendar(); });
            nextMonthBtn.addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() + 1); renderCalendar(); });
            renderCalendar();
        }

        function renderNotifications(notifications) {
            const listEl = document.getElementById('realtime-notifications-list');
            if (!listEl) return;
            if (notifications.length === 0) {
                listEl.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--gray);">No recent applications.</p>';
                return;
            }
            listEl.innerHTML = '';
            notifications.forEach(notif => {
                const item = document.createElement('div');
                const statusClass = notif.status.toLowerCase().replace(' ', '-');
                const iconClass = { pending: 'fa-clock', verified: 'fa-check', rejected: 'fa-times', approved: 'fa-check-double' }[statusClass] || 'fa-info-circle';
                
                const dateObj = new Date(notif.date_submitted.replace(' ', 'T')); // Parse with 'T' for reliability
                const year = dateObj.getFullYear();
                const month = (dateObj.getMonth() + 1).toString().padStart(2, '0'); // Months are 0-indexed
                const day = dateObj.getDate().toString().padStart(2, '0');
                const hours = dateObj.getHours().toString().padStart(2, '0');
                const minutes = dateObj.getMinutes().toString().padStart(2, '0');
                const seconds = dateObj.getSeconds().toString().padStart(2, '0');

                const formattedDateTime = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;

                item.className = `notification-item status-${statusClass}`;
                item.innerHTML = `
                    <div class="notification-icon"><i class="fas ${iconClass}"></i></div>
                    <div class="notification-info">
                        <div class="notification-title">${notif.full_name}</div>
                        <div class="notification-message">${notif.application_type} - Status: ${notif.status} - Barangay: ${notif.barangay}</div>
                        <div class="notification-time">${formattedDateTime}</div>
                    </div>
                `;
                listEl.appendChild(item);
            });
        }

        function initializeDepartmentCharts(data) {
            const { barangay_records, yearly_records } = data;

            // Barangay Records Chart (Horizontal Bar)
            const barangayRecordsCtx = document.getElementById('barangayRecordsChart')?.getContext('2d');
            if (barangayRecordsCtx && barangay_records) {
                const labels = barangay_records.map(record => record.barangay);
                const counts = barangay_records.map(record => record.count);

                new Chart(barangayRecordsCtx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Applications',
                            data: counts,
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.6)',
                                'rgba(54, 162, 235, 0.6)',
                                'rgba(255, 206, 86, 0.6)',
                                'rgba(75, 192, 192, 0.6)',
                                'rgba(153, 102, 255, 0.6)'
                            ],
                            borderColor: [
                                'rgba(255, 99, 132, 1)',
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 206, 86, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(153, 102, 255, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        indexAxis: 'y', // Makes it a horizontal bar chart
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: { beginAtZero: true }
                        }
                    }
                });
            }

            // Yearly Records Chart (Bar)
            const yearlyRecordsCtx = document.getElementById('yearlyRecordsChart')?.getContext('2d');
            if (yearlyRecordsCtx && yearly_records) {
                const labels = yearly_records.map(record => record.year);
                const counts = yearly_records.map(record => record.count);

                new Chart(yearlyRecordsCtx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Total Applications',
                            data: counts,
                            backgroundColor: 'rgba(75, 192, 192, 0.6)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }
        }
    </script>
</body>
</html>