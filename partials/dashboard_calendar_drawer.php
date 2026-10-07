<link rel="stylesheet" href="../assets/css/dashboard-calendar-drawer.css?v=1">
<div class="dashboard-calendar-backdrop" id="dashboardCalendarBackdrop" hidden></div>
<aside class="dashboard-calendar-drawer" id="dashboardCalendarDrawer" role="dialog" aria-modal="true" aria-labelledby="dashboardCalendarTitle" aria-hidden="true">
    <header class="dashboard-calendar-drawer__header">
        <div><span>Quick tool</span><h2 id="dashboardCalendarTitle"><i class="fas fa-calendar-days" aria-hidden="true"></i> Calendar</h2><p>Check dates without leaving the dashboard.</p></div>
        <button id="dashboardCalendarClose" type="button" aria-label="Close calendar"><i class="fas fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="dashboard-calendar-drawer__body">
        <div class="calendar-card">
            <div class="calendar-body">
                <div class="calendar-header">
                    <button class="calendar-nav" id="prev-month" type="button" aria-label="Show previous month"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                    <span class="month-year" id="month-year" aria-live="polite"></span>
                    <button class="calendar-nav" id="next-month" type="button" aria-label="Show next month"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                </div>
                <div class="calendar-toolbar"><button class="calendar-today-button" id="calendar-today" type="button"><i class="fas fa-location-crosshairs" aria-hidden="true"></i> Today</button><span class="calendar-clock" id="current-time" aria-live="polite"></span></div>
                <table class="calendar-table">
                    <thead><tr><th scope="col">Sun</th><th scope="col">Mon</th><th scope="col">Tue</th><th scope="col">Wed</th><th scope="col">Thu</th><th scope="col">Fri</th><th scope="col">Sat</th></tr></thead>
                    <tbody id="calendar-days"></tbody>
                </table>
                <p class="calendar-selection" id="calendar-selection" aria-live="polite"></p>
            </div>
        </div>
    </div>
</aside>
<script src="../assets/js/dashboard-calendar-drawer.js?v=1" defer></script>
