// Admin JavaScript
console.log('Admin panel loaded');

// Auto-refresh functionality
if (window.location.pathname.includes('orders.php')) {
    setInterval(() => {
        location.reload();
    }, 5000); // Refresh every 5 seconds
}
