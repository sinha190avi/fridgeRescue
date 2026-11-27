/**
 * FridgeRescue — Interactive Client Application
 */

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initFaqAccordion();
    initMobileNav();
});

/* ==========================================================
   Theme Management (Light / Dark Mode)
   ========================================================== */
function initTheme() {
    const savedTheme = localStorage.getItem('fridgerescue_theme') || 
                       document.documentElement.getAttribute('data-theme') || 
                       'light';
    setTheme(savedTheme, false);

    const themeToggles = document.querySelectorAll('.theme-toggle-btn');
    themeToggles.forEach(btn => {
        btn.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-theme') || 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            setTheme(next, true);
        });
    });
}

function setTheme(theme, persistServer = false) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('fridgerescue_theme', theme);

    // Update toggle icons
    document.querySelectorAll('.theme-toggle-btn').forEach(btn => {
        btn.innerHTML = theme === 'dark' ? '☀️' : '🌙';
        btn.setAttribute('title', theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode');
    });

    if (persistServer && window.CSRF_TOKEN) {
        const url = (window.APP_BASE_URL || '') + '/api/update_theme.php';
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.CSRF_TOKEN
            },
            body: JSON.stringify({ theme })
        }).catch(() => {});
    }
}

/* ==========================================================
   FAQ Accordion
   ========================================================== */
function initFaqAccordion() {
    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(item => {
        const header = item.querySelector('.faq-question');
        if (header) {
            header.addEventListener('click', () => {
                const isOpen = item.classList.contains('active');
                // Close all
                faqItems.forEach(i => i.classList.remove('active'));
                if (!isOpen) {
                    item.classList.add('active');
                }
            });
        }
    });
}

/* ==========================================================
   Mobile Bottom Navigation Active State
   ========================================================== */
function initMobileNav() {
    const currentPath = window.location.pathname.split('/').pop() || 'index.php';
    document.querySelectorAll('.mobile-nav-item').forEach(item => {
        const href = item.getAttribute('href');
        if (href && href.includes(currentPath)) {
            item.classList.add('active');
        }
    });
}

/* ==========================================================
   Toast Notification Utility
   ========================================================== */
function showToast(message, type = 'success', duration = 4000) {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    const iconMap = {
        success: '✅',
        error: '⚠️',
        warning: '⚡',
        info: 'ℹ️'
    };

    toast.innerHTML = `
        <span style="font-size: 18px;">${iconMap[type] || '🔔'}</span>
        <div style="flex: 1;">${message}</div>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: var(--text-secondary); font-size: 16px;">&times;</button>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(50px)';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

/* ==========================================================
   Modal Dialog Utilities
   ========================================================== */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('modal-open');
        document.body.style.overflow = '';
    }
}

// Close modals when clicking overlay
window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-backdrop')) {
        e.target.classList.remove('modal-open');
        document.body.style.overflow = '';
    }
});
