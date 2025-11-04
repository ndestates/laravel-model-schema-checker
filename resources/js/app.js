// Laravel Model Schema Checker JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the application
    initializeApp();
});

function initializeApp() {
    // Set up CSRF token for all AJAX requests
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        // Set up default headers for fetch requests
        window.csrfToken = csrfToken.getAttribute('content');
    }

    // Initialize dashboard if present
    if (document.getElementById('checkForm')) {
        initializeDashboard();
    }

    // Initialize results page if present
    if (document.getElementById('issuesList')) {
        initializeResultsPage();
    }

    // Initialize step-by-step page if present
    if (document.getElementById('applyCurrentFixBtn')) {
        initializeStepByStepPage();
    }

    // Initialize history page if present
    if (document.getElementById('historyList')) {
        initializeHistoryPage();
    }
}

function initializeDashboard() {
    const checkForm = document.getElementById('checkForm');
    const runBtn = document.getElementById('runChecksBtn');
    const spinner = document.getElementById('loadingSpinner');
    const progressContainer = document.getElementById('progressContainer');
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    const progressPercent = document.getElementById('progressPercent');

    checkForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        // Show loading state
        runBtn.disabled = true;
        spinner.classList.remove('hidden');
        runBtn.textContent = 'Starting Checks...';
        progressContainer.classList.remove('hidden');

        fetch('/model-schema-checker/run-checks', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': window.csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                check_types: Array.from(formData.getAll('check_types[]')),
                options: {}
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Start polling for progress
                pollProgress(data.job_id, progressFill, progressText, progressPercent, runBtn, spinner);
            } else {
                showError('Error: ' + (data.message || 'Unknown error'));
                resetForm(runBtn, spinner);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('An error occurred while starting the checks');
            resetForm(runBtn, spinner);
        });
    });
}

function initializeResultsPage() {
    // Apply all fixes button
    const applyAllBtn = document.getElementById('applyAllFixesBtn');
    if (applyAllBtn) {
        applyAllBtn.addEventListener('click', function() {
            const resultId = this.dataset.resultId || window.location.pathname.split('/').pop();
            applyAllFixes(resultId, this);
        });
    }
}

function initializeStepByStepPage() {
    const applyBtn = document.getElementById('applyCurrentFixBtn');
    const skipBtn = document.getElementById('skipCurrentFixBtn');

    if (applyBtn) {
        applyBtn.addEventListener('click', function() {
            applyCurrentFix(this);
        });
    }

    if (skipBtn) {
        skipBtn.addEventListener('click', function() {
            skipCurrentFix(this);
        });
    }
}

function initializeHistoryPage() {
    // Filter functionality
    const statusFilter = document.getElementById('statusFilter');
    const dateFilter = document.getElementById('dateFilter');
    const issuesFilter = document.getElementById('issuesFilter');

    if (statusFilter) statusFilter.addEventListener('change', filterResults);
    if (dateFilter) dateFilter.addEventListener('change', filterResults);
    if (issuesFilter) issuesFilter.addEventListener('change', filterResults);
}

// Progress polling function
function pollProgress(jobId, progressFill, progressText, progressPercent, runBtn, spinner) {
    const poll = () => {
        fetch(`/model-schema-checker/check-progress/${jobId}`)
            .then(response => response.json())
            .then(data => {
                progressFill.style.width = data.progress + '%';
                progressText.textContent = data.message || 'Processing...';
                progressPercent.textContent = data.progress + '%';

                if (data.status === 'completed') {
                    progressText.textContent = 'Checks completed! Reloading page...';
                    setTimeout(() => window.location.reload(), 2000);
                } else if (data.status === 'failed') {
                    progressText.textContent = 'Checks failed: ' + (data.message || 'Unknown error');
                    resetForm(runBtn, spinner);
                } else {
                    setTimeout(poll, 1000); // Poll again in 1 second
                }
            })
            .catch(error => {
                console.error('Error polling progress:', error);
                progressText.textContent = 'Error checking progress';
                resetForm(runBtn, spinner);
            });
    };

    poll();
}

// Fix application functions
function applyFix(issueId) {
    if (!confirm('Are you sure you want to apply this fix?')) return;

    fetch(`/model-schema-checker/apply-fix/${issueId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': window.csrfToken,
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess('Fix applied successfully!');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showError('Error applying fix: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('An error occurred while applying the fix');
    });
}

function applyAllFixes(resultId, button) {
    if (!confirm('Are you sure you want to apply all auto-fixable issues?')) return;

    button.disabled = true;
    button.textContent = 'Applying...';

    fetch(`/model-schema-checker/apply-all-fixes/${resultId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': window.csrfToken,
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess('All fixes applied successfully!');
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showError('Error applying fixes: ' + (data.message || 'Unknown error'));
            button.disabled = false;
            button.textContent = 'Apply All Fixes';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('An error occurred while applying fixes');
        button.disabled = false;
        button.textContent = 'Apply All Fixes';
    });
}

function applyCurrentFix(button) {
    if (!confirm('Are you sure you want to apply this fix?')) return;

    button.disabled = true;
    button.textContent = 'Applying...';

    const issueId = button.dataset.issueId || getIssueIdFromUrl();

    fetch(`/model-schema-checker/apply-fix/${issueId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': window.csrfToken,
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Move to next step
            window.location.reload();
        } else {
            showError('Error applying fix: ' + (data.message || 'Unknown error'));
            button.disabled = false;
            button.textContent = 'Apply This Fix';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('An error occurred while applying the fix');
        button.disabled = false;
        button.textContent = 'Apply This Fix';
    });
}

function skipCurrentFix(button) {
    if (!confirm('Are you sure you want to skip this issue? You can come back to it later.')) return;

    const issueId = button.dataset.issueId || getIssueIdFromUrl();

    fetch(`/model-schema-checker/skip-fix/${issueId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': window.csrfToken,
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            showError('Error skipping fix: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('An error occurred while skipping the fix');
    });
}

function rollbackFix(fixId) {
    if (!confirm('Are you sure you want to rollback this fix? This action cannot be undone.')) return;

    fetch(`/model-schema-checker/rollback-fix/${fixId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': window.csrfToken,
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess('Fix rolled back successfully!');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showError('Error rolling back fix: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('An error occurred while rolling back the fix');
    });
}

// History functions
function filterResults() {
    const statusFilter = document.getElementById('statusFilter').value;
    const dateFilter = document.getElementById('dateFilter').value;
    const issuesFilter = document.getElementById('issuesFilter').value;

    const items = document.querySelectorAll('.history-item');

    items.forEach(item => {
        let show = true;

        // Status filter
        if (statusFilter && item.dataset.status !== statusFilter) {
            show = false;
        }

        // Issues filter
        if (issuesFilter && item.dataset.issues !== issuesFilter) {
            show = false;
        }

        // Date filter
        if (dateFilter) {
            const itemDate = new Date(item.dataset.date);
            const today = new Date();
            const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
            const monthAgo = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate());

            if (dateFilter === 'today' && itemDate.toDateString() !== today.toDateString()) {
                show = false;
            } else if (dateFilter === 'week' && itemDate < weekAgo) {
                show = false;
            } else if (dateFilter === 'month' && itemDate < monthAgo) {
                show = false;
            }
        }

        item.style.display = show ? 'block' : 'none';
    });
}

function deleteResult(resultId) {
    if (!confirm('Are you sure you want to delete this check result? This action cannot be undone.')) return;

    fetch(`/model-schema-checker/results/${resultId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': window.csrfToken,
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the item from the DOM
            const items = document.querySelectorAll('.history-item');
            items.forEach(item => {
                if (item.textContent.includes(`Check #${resultId}`)) {
                    item.remove();
                }
            });

            // If no items left, show empty state
            if (document.querySelectorAll('.history-item').length === 0) {
                location.reload(); // Reload to show empty state properly
            }
        } else {
            showError('Error deleting result: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('An error occurred while deleting the result');
    });
}

// Utility functions
function resetForm(runBtn, spinner) {
    runBtn.disabled = false;
    spinner.classList.add('hidden');
    runBtn.textContent = 'Run Checks';
}

function getIssueIdFromUrl() {
    // Extract issue ID from URL or data attributes
    return window.location.pathname.split('/').pop();
}

function showSuccess(message) {
    showNotification(message, 'success');
}

function showError(message) {
    showNotification(message, 'error');
}

function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-md shadow-lg ${
        type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
    }`;
    notification.textContent = message;

    document.body.appendChild(notification);

    // Remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Export functions for global access
window.applyFix = applyFix;
window.rollbackFix = rollbackFix;
window.deleteResult = deleteResult;