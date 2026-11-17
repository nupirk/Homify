/**
 * UrbanServe Admin - Global JavaScript Utilities
 * Provides common functions used across components
 */

// Global utility functions
window.UrbanAdmin = {
    // CSRF Token
    getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    },

    // API Helper
    async makeRequest(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.getCsrfToken()
            }
        };

        const response = await fetch(url, { ...defaultOptions, ...options });
        return response.json();
    },

    // Modal Management
    showModal(modalId) {
        document.getElementById(modalId)?.classList.remove('hidden');
    },

    hideModal(modalId) {
        document.getElementById(modalId)?.classList.add('hidden');
    },

    // Notification shortcuts
    showSuccess(message, callback = null) {
        if (typeof showNotificationModal === 'function') {
            showNotificationModal('success', 'Success', message, callback);
        }
    },

    showError(message) {
        if (typeof showNotificationModal === 'function') {
            showNotificationModal('error', 'Error', message);
        }
    },

    showInfo(message) {
        if (typeof showNotificationModal === 'function') {
            showNotificationModal('info', 'Info', message);
        }
    },

    // Form helpers
    serializeForm(form) {
        const formData = new FormData(form);
        const data = {};
        for (let [key, value] of formData.entries()) {
            if (data[key]) {
                if (Array.isArray(data[key])) {
                    data[key].push(value);
                } else {
                    data[key] = [data[key], value];
                }
            } else {
                data[key] = value;
            }
        }
        return data;
    },

    // Loading states
    setButtonLoading(buttonElement, loading = true) {
        if (loading) {
            buttonElement.disabled = true;
            buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
        } else {
            buttonElement.disabled = false;
            // Restore original content would require storing it first
        }
    },

    // URL helpers
    updateUrl(params) {
        const url = new URL(window.location);
        Object.keys(params).forEach(key => {
            if (params[key]) {
                url.searchParams.set(key, params[key]);
            } else {
                url.searchParams.delete(key);
            }
        });
        window.history.pushState({}, '', url);
    }
};

// Initialize global event handlers
document.addEventListener('DOMContentLoaded', function() {
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        // Close all dropdowns that don't contain the clicked element
        document.querySelectorAll('[class*="dropdown-menu"]').forEach(dropdown => {
            if (!dropdown.contains(event.target) && !event.target.closest('[class*="dropdown-btn"]')) {
                dropdown.classList.add('hidden');
            }
        });
    });

    // ESC key to close modals
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.fixed.inset-0:not(.hidden)').forEach(modal => {
                modal.classList.add('hidden');
            });
        }
    });
});

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UrbanAdmin;
}
