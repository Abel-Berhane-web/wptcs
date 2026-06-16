/**
 * WPTCS - Main JavaScript
 * Web-based Parent Teacher Communication System
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ═══ Sidebar Toggle (Mobile) ═══
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.add('show');
            sidebarOverlay.classList.add('show');
        });
    }
    
    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeSidebar);
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }
    
    function closeSidebar() {
        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
    }
    
    // ═══ Auto-dismiss Alerts ═══
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // ═══ Star Rating Input ═══
    document.querySelectorAll('.star-rating-input').forEach(function(container) {
        const input = container.querySelector('input[type="hidden"]');
        const stars = container.querySelectorAll('.star');
        
        stars.forEach(function(star, index) {
            star.addEventListener('click', function() {
                const value = index + 1;
                input.value = value;
                updateStars(stars, value);
            });
            
            star.addEventListener('mouseenter', function() {
                updateStars(stars, index + 1);
            });
        });
        
        container.addEventListener('mouseleave', function() {
            updateStars(stars, parseInt(input.value) || 0);
        });
    });
    
    function updateStars(stars, value) {
        stars.forEach(function(star, index) {
            if (index < value) {
                star.classList.add('active');
                star.textContent = '★';
            } else {
                star.classList.remove('active');
                star.textContent = '☆';
            }
        });
    }
    
    // ═══ Confirm Delete ═══
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // ═══ AJAX Form Submission for Marks ═══
    const marksForm = document.getElementById('marksForm');
    if (marksForm) {
        marksForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                showAlert('danger', 'An error occurred. Please try again.');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
    
    // ═══ AJAX Attendance Submission ═══
    const attendanceForm = document.getElementById('attendanceForm');
    if (attendanceForm) {
        attendanceForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                showAlert('danger', 'An error occurred. Please try again.');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
    
    // ═══ Real-Time Chat (AJAX polling every 3 seconds) ═══
    const commentForm = document.getElementById('commentForm');
    const commentThread = document.getElementById('commentThread');

    if (commentThread) {
        const pollUrl       = commentThread.dataset.pollUrl;
        const currentUserId = parseInt(commentThread.dataset.currentUser, 10);
        let lastTimestamp   = commentThread.dataset.lastTimestamp || '1970-01-01 00:00:00';
        let isPolling       = true;

        // ── Helper: build a chat bubble element ──
        function buildBubble(msg) {
            const isSent = msg.sender_id === currentUserId;
            const div    = document.createElement('div');
            div.className = 'comment-bubble ' + (isSent ? 'sent' : 'received');
            div.setAttribute('data-comment-id', msg.comment_id);
            div.innerHTML = `
                <div>${escapeHtml(msg.message)}</div>
                <div class="comment-meta">${escapeHtml(msg.sender_name)} • ${escapeHtml(msg.time_ago)}</div>
            `;
            return div;
        }

        // ── Collect IDs of messages already rendered ──
        function getRenderedIds() {
            return new Set(
                Array.from(commentThread.querySelectorAll('[data-comment-id]'))
                     .map(el => el.getAttribute('data-comment-id'))
            );
        }

        // ── Remove "no messages" placeholder if present ──
        function clearPlaceholder() {
            const placeholder = commentThread.querySelector('.text-center.text-muted');
            if (placeholder) placeholder.remove();
        }

        // ── Poll the server for new messages ──
        function pollMessages() {
            if (!isPolling || !pollUrl) return;

            fetch(pollUrl + '&since=' + encodeURIComponent(lastTimestamp))
                .then(r => r.json())
                .then(data => {
                    if (!data.messages || data.messages.length === 0) return;

                    const rendered = getRenderedIds();
                    let appended   = false;

                    data.messages.forEach(msg => {
                        // Skip messages already rendered (our own sent messages)
                        if (rendered.has(String(msg.comment_id))) return;

                        clearPlaceholder();
                        const bubble = buildBubble(msg);
                        commentThread.appendChild(bubble);
                        appended = true;

                        // Update the last known timestamp
                        if (msg.created_at > lastTimestamp) {
                            lastTimestamp = msg.created_at;
                        }
                    });

                    if (appended) {
                        commentThread.scrollTop = commentThread.scrollHeight;
                    }
                })
                .catch(() => {}); // Silently ignore network errors during polling
        }

        // ── Start polling every 3 seconds ──
        const pollInterval = setInterval(pollMessages, 3000);
        // Stop polling when user navigates away
        window.addEventListener('beforeunload', () => {
            isPolling = false;
            clearInterval(pollInterval);
        });

        // ── Send message via AJAX ──
        if (commentForm) {
            commentForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData    = new FormData(this);
                const textarea    = this.querySelector('textarea');
                const submitBtn   = this.querySelector('button[type="submit"]');
                const msgText     = textarea.value.trim();
                if (!msgText) return;

                submitBtn.disabled = true;

                fetch(this.action, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            clearPlaceholder();

                            // Optimistically render the sent bubble immediately
                            const tempBubble = document.createElement('div');
                            tempBubble.className = 'comment-bubble sent';
                            tempBubble.setAttribute('data-comment-id', 'temp-' + Date.now());
                            tempBubble.innerHTML = `
                                <div>${escapeHtml(data.comment.message)}</div>
                                <div class="comment-meta">${escapeHtml(data.comment.time)}</div>
                            `;
                            commentThread.appendChild(tempBubble);
                            commentThread.scrollTop = commentThread.scrollHeight;
                            textarea.value = '';

                            // Update timestamp so next poll won't re-fetch this message
                            // Use server time from response to stay in sync
                            const now = new Date();
                            const y   = now.getFullYear();
                            const mo  = String(now.getMonth() + 1).padStart(2, '0');
                            const d   = String(now.getDate()).padStart(2, '0');
                            const h   = String(now.getHours()).padStart(2, '0');
                            const mi  = String(now.getMinutes()).padStart(2, '0');
                            const s   = String(now.getSeconds()).padStart(2, '0');
                            lastTimestamp = `${y}-${mo}-${d} ${h}:${mi}:${s}`;
                        } else {
                            showAlert('danger', data.message);
                        }
                    })
                    .catch(() => showAlert('danger', 'Failed to send message. Please try again.'))
                    .finally(() => { submitBtn.disabled = false; });
            });
        }
    }

    
    // ═══ Score Validation ═══
    document.querySelectorAll('input[data-max-score]').forEach(function(input) {
        input.addEventListener('input', function() {
            const max = parseFloat(this.getAttribute('data-max-score'));
            const val = parseFloat(this.value);
            if (val > max) this.value = max;
            if (val < 0) this.value = 0;
        });
    });
    
    // ═══ Dynamic Section Loading ═══
    const gradeSelect = document.getElementById('gradeSelect');
    if (gradeSelect) {
        gradeSelect.addEventListener('change', function() {
            const gradeId = this.value;
            const sectionSelect = document.getElementById('sectionSelect');
            
            if (!gradeId) {
                sectionSelect.innerHTML = '<option value="">Select Section</option>';
                return;
            }
            
            fetch(`${window.BASE_URL || ''}/index.php?page=api/sections&grade_id=${gradeId}&ajax=1`)
                .then(response => response.json())
                .then(data => {
                    let options = '<option value="">Select Section</option>';
                    data.forEach(section => {
                        options += `<option value="${section.section_id}">${section.section_name}</option>`;
                    });
                    sectionSelect.innerHTML = options;
                });
        });
    }
    
    // ═══ Print Report Card ═══
    const printBtn = document.getElementById('printReportCard');
    if (printBtn) {
        printBtn.addEventListener('click', function() {
            window.print();
        });
    }
    
    // ═══ Select All Checkbox ═══
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    }
    
    // ═══ Helper: Show Alert ═══
    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.setAttribute('role', 'alert');
        alertDiv.innerHTML = `
            <i class="bi bi-${type === 'success' ? 'check-circle' : 'x-circle'} me-2"></i>
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = document.querySelector('.page-content');
        if (container) {
            container.insertBefore(alertDiv, container.firstChild);
            setTimeout(() => alertDiv.remove(), 5000);
        }
    }
    
    // ═══ Helper: Escape HTML ═══
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // ═══ Attendance Quick Set Buttons ═══
    document.querySelectorAll('.attendance-quick-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const status = this.getAttribute('data-status');
            document.querySelectorAll('.attendance-status').forEach(function(select) {
                select.value = status;
            });
        });
    });
    
    // ═══ Fade In Animation ═══
    const fadeElements = document.querySelectorAll('.fade-in-up');
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    });
    
    // ═══ Input Validation (Names and Phones) ═══
    const nameInputs = document.querySelectorAll('input[name="first_name"], input[name="last_name"], input[name="full_name"]');
    nameInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            // Allow English letters, Amharic Unicode characters, spaces, and hyphens
            this.value = this.value.replace(/[^a-zA-Z\u1200-\u137F\s\-]/g, '');
        });
    });

    const phoneInputs = document.querySelectorAll('input[name="phone"]');
    phoneInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            // Allow numbers, plus, minus, and spaces
            this.value = this.value.replace(/[^0-9\+\-\s]/g, '');
        });
    });
});
