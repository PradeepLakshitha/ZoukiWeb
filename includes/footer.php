<!-- Toast Container for notifications -->
    <div class="toast-container"></div>
</main>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // DOM Elements
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const overlay = document.getElementById('overlay');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const darkModeToggle = document.getElementById('darkModeToggle');
    const darkModeToggleTop = document.getElementById('darkModeToggleTop');
    const notificationsToggle = document.getElementById('notificationsToggle');
    const userProfileToggle = document.getElementById('userProfileToggle');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    const userDropdown = document.getElementById('userDropdown');
    const markAllReadBtn = document.getElementById('markAllReadBtn');

    // Sidebar Toggle for mobile - Fix for collapse/expand functionality
    document.addEventListener('DOMContentLoaded', () => {
        const isSmallScreen = window.innerWidth < 992;

        if (isSmallScreen) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }
        
        // Add horizontal overflow prevention
        document.body.style.overflowX = 'hidden';
        
        // Fix icon visibility
        if (sidebar.classList.contains('collapsed')) {
            document.querySelectorAll('.nav-link i').forEach(icon => {
                icon.style.display = 'inline-block';
            });
        }
        
        // Initialize count-up animation if it exists
        if (typeof animateCountUp === 'function') {
            animateCountUp();
        }
        
        // Initialize any page-specific functions if they exist
        if (typeof initPageFunctions === 'function') {
            initPageFunctions();
        }
    });

    // Sidebar Toggle with improved handling
    sidebarToggle.addEventListener('click', (e) => {
        e.preventDefault();
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        
        // Fix icon visibility and scrolling issues
        if (sidebar.classList.contains('collapsed')) {
            setTimeout(() => {
                document.querySelectorAll('.nav-link i').forEach(icon => {
                    icon.style.display = 'inline-block';
                });
            }, 300);
        }
    });

    // Mobile Menu Toggle
    mobileMenuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('mobile-show');
        overlay.classList.toggle('show');
    });
    
    // Close mobile menu when clicking on overlay
    overlay.addEventListener('click', () => {
        sidebar.classList.remove('mobile-show');
        overlay.classList.remove('show');
    });

    // Dark Mode Toggle
    function toggleDarkMode() {
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-bs-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

        html.setAttribute('data-bs-theme', newTheme);

        // Update icons
        const darkModeIcon = darkModeToggle.querySelector('i');
        const darkModeIconTop = darkModeToggleTop.querySelector('i');

        if (newTheme === 'dark') {
            darkModeIcon.classList.replace('bi-moon', 'bi-sun');
            darkModeIconTop.classList.replace('bi-moon-fill', 'bi-sun-fill');
            darkModeToggle.querySelector('.footer-text').textContent = 'Light Mode';
        } else {
            darkModeIcon.classList.replace('bi-sun', 'bi-moon');
            darkModeIconTop.classList.replace('bi-sun-fill', 'bi-moon-fill');
            darkModeToggle.querySelector('.footer-text').textContent = 'Dark Mode';
        }

        // Save preference to localStorage
        localStorage.setItem('theme', newTheme);
    }

    darkModeToggle.addEventListener('click', toggleDarkMode);
    darkModeToggleTop.addEventListener('click', toggleDarkMode);

    // Check for saved theme preference
    document.addEventListener('DOMContentLoaded', () => {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-bs-theme', savedTheme);

            if (savedTheme === 'dark') {
                darkModeToggle.querySelector('i').classList.replace('bi-moon', 'bi-sun');
                darkModeToggleTop.querySelector('i').classList.replace('bi-moon-fill', 'bi-sun-fill');
                darkModeToggle.querySelector('.footer-text').textContent = 'Light Mode';
            }
        }
    });

    // Notifications dropdown
    notificationsToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        notificationsDropdown.classList.toggle('show');
        userDropdown.classList.remove('show');
    });

    // User profile dropdown
    userProfileToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        userDropdown.classList.toggle('show');
        notificationsDropdown.classList.remove('show');
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        if (!notificationsDropdown.contains(e.target) && !notificationsToggle.contains(e.target)) {
            notificationsDropdown.classList.remove('show');
        }

        if (!userDropdown.contains(e.target) && !userProfileToggle.contains(e.target)) {
            userDropdown.classList.remove('show');
        }
    });

    // Mark all notifications as read
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();

            // Send AJAX request
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_all_read'
            })
                .then(response => {
                    // First check if the response is OK
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.success) {
                        // Mark all as read visually
                        document.querySelectorAll('.recent-item:not(.read)').forEach(item => {
                            item.classList.add('read');
                        });

                        // Hide notification badge
                        const badge = document.querySelector('.notification-badge');
                        if (badge) {
                            badge.style.display = 'none';
                        }

                        // Hide sidebar badge
                        const sidebarBadge = document.querySelector('.sidebar .badge');
                        if (sidebarBadge) {
                            sidebarBadge.style.display = 'none';
                        }

                        // Show a toast notification
                        showToast('All notifications marked as read', 'success');
                    } else {
                        showToast(data && data.message ? data.message : 'Failed to mark notifications as read', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Still update UI for better UX
                    document.querySelectorAll('.recent-item:not(.read)').forEach(item => {
                        item.classList.add('read');
                    });
                    
                    // Hide notification badges anyway
                    document.querySelectorAll('.notification-badge, .sidebar .badge').forEach(badge => {
                        badge.style.display = 'none';
                    });
                    
                    showToast('Notifications marked as read', 'success');
                });
        });
    }

    // Helper function to show toast notifications
    function showToast(message, type = 'success') {
        // Create the toast container if it doesn't exist
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }

        const toastId = 'toast-' + Date.now();
        const toastHTML = `
                <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;

        toastContainer.insertAdjacentHTML('beforeend', toastHTML);

        const toast = new bootstrap.Toast(document.getElementById(toastId), {
            autohide: true,
            delay: 3000
        });

        toast.show();

        // Remove the toast from DOM after it's hidden
        document.getElementById(toastId).addEventListener('hidden.bs.toast', function () {
            this.remove();
        });
    }
</script>

<?php if (isset($page_scripts) && !empty($page_scripts)): ?>
    <!-- Page-specific scripts -->
    <script>
        <?php echo $page_scripts; ?>
    </script>
<?php endif; ?>
<script src="js/dashboard-enhanced.js"></script>
</body>
</html>