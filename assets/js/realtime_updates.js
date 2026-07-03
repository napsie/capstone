document.addEventListener('DOMContentLoaded', function() {
    const updateInterval = 5000; // Update every 5 seconds

    function fetchRealtimeData() {
        fetch('../api/get_realtime_data.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    updateDashboardStats(data.data);
                    updateNotifications(data.data.notifications);
                } else {
                    console.error('Error fetching real-time data:', data.message);
                }
            })
            .catch(error => {
                console.error('Network error fetching real-time data:', error);
            });
    }

    function updateDashboardStats(data) {
        // Example: Update total applications stat
        const totalApplicationsElement = document.querySelector('.stat-card h3'); // Adjust selector as needed
        if (totalApplicationsElement) {
            totalApplicationsElement.textContent = data.total_applications;
        }
        // You would add more specific updates here for other stats
        // For example, if you have elements with IDs like 'pending-count', 'verified-count'
        // document.getElementById('pending-count').textContent = data.pending_applications;
    }

    function updateNotifications(notifications) {
        const notificationsList = document.querySelector('.notifications-list'); // Adjust selector as needed
        if (notificationsList) {
            notificationsList.innerHTML = ''; // Clear existing notifications
            notifications.forEach(notification => {
                const notificationItem = document.createElement('div');
                notificationItem.classList.add('notification-item', `notification-${notification.type}`);
                notificationItem.innerHTML = `
                    <div class="notification-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="notification-info">
                        <div class="notification-title">${notification.message}</div>
                        <div class="notification-time">${notification.time}</div>
                    </div>
                `;
                notificationsList.appendChild(notificationItem);
            });
        }
    }

    // Start polling
    setInterval(fetchRealtimeData, updateInterval);
    // Fetch data immediately on load
    fetchRealtimeData();
});