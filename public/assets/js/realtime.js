class RealtimeUpdater {
    constructor() {
        this.eventSource = null;
        this.isConnected = false;
    }

    connect() {
        if (this.eventSource) return;
        this.eventSource = new EventSource('../../app/api/realtime_updates.php');
        this.isConnected = true;

        this.eventSource.onmessage = e => {
            if (!e.data) return;
            try {
                const data = JSON.parse(e.data);
                if (data.type === 'update') this.handleUpdate(data.data);
            } catch (err) {}
        };

        this.eventSource.onerror = () => {
            this.isConnected = false;
            this.eventSource?.close();
            this.eventSource = null;
            setTimeout(() => this.connect(), 3000);
        };
    }

    handleUpdate(data) {
        this.updateStats(data);
        this.updateRecent(data.recentLogs || []);
        if (location.pathname.includes('audit_trail.php')) this.fetchAllLogs();
    }

    updateStats(data) {
        if (!location.pathname.includes('dashboard.php')) return;
        const animate = (sel, val) => {
            const el = document.querySelector(sel);
            if (!el || val === undefined) return;
            let start = parseInt(el.textContent.replace(/,/g, '')) || 0;
            let end = val;
            if (start === end) { el.textContent = end.toLocaleString(); return; }
            let diff = end - start;
            let current = start;
            const timer = setInterval(() => {
                current += diff / 30;
                if ((diff > 0 && current >= end) || (diff < 0 && current <= end)) {
                    clearInterval(timer);
                    current = end;
                }
                el.textContent = Math.floor(current).toLocaleString();
            }, 20);
        };
        animate('.dashboard-card:nth-child(1) .card-value', data.totalUsers);
        animate('.dashboard-card:nth-child(2) .card-value', data.totalLogs);
        animate('.dashboard-card:nth-child(3) .card-value', data.activeSessions);
    }

    updateRecent(logs) {
        if (!location.pathname.includes('dashboard.php')) return;
        const tbody = document.getElementById('recent-activity-tbody') || document.querySelector('.table-container tbody');
        if (!tbody) return;

        tbody.querySelectorAll('tr:not(.empty-state-row)').forEach(r => r.remove());

        if (!logs.length) {
            tbody.innerHTML = '<tr class="empty-state-row"><td colspan="5"><div class="empty-state"><i class="fas fa-inbox"></i><h3>No Activity Yet</h3></div></td></tr>';
            return;
        }

        logs.slice(0, 10).forEach(log => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escape(log.email)}</td>
                <td>${log.time_in}</td>
                <td>${log.time_out}</td>
                <td>${log.duration}</td>
                <td><span class="status-badge ${log.is_active ? 'active' : 'inactive'}">${log.status}</span></td>
            `;
            tbody.appendChild(tr);
        });
    }

    fetchAllLogs() {
        fetch('../../app/api/get_all_logs.php', { credentials: 'include' })
            .then(r => r.json())
            .then(d => {
                if (!d.success || !d.logs) return;
                const tbody = document.querySelector('.table-container tbody');
                if (!tbody) return;
                tbody.innerHTML = '';
                d.logs.forEach((log, idx) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td>#${idx+1}</td><td>${escape(log.email)}</td><td>${escape(log.role)}</td><td>${log.time_in}</td><td>${log.time_out}</td><td>${log.duration}</td><td><span class="status-badge ${log.is_active ? 'active' : 'inactive'}">${log.status}</span></td>`;
                    tbody.appendChild(tr);
                });
                const span = document.querySelector('.table-header span');
                if (span) span.textContent = `Total Records: ${d.count}`;
            });
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        this.isConnected = false;
    }
}

function escape(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// Initialize + INSTANT LOGOUT
document.addEventListener('DOMContentLoaded', () => {
    if (!location.pathname.includes('dashboard.php') && !location.pathname.includes('audit_trail.php')) return;

    const updater = new RealtimeUpdater();
    window.realtimeUpdater = updater;
    updater.connect();

    // INSTANT LOGOUT — 100% working
    document.querySelectorAll('[data-logout]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();

            // Instant visual feedback
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging out...';
            this.style.pointerEvents = 'none';

            // 1. Close EventSource
            updater.disconnect();

            // 2. Tell realtime_updates.php to exit immediately
            fetch('../../app/api/realtime_updates.php?logout=1', { 
                method: 'GET', 
                credentials: 'include',
                cache: 'no-store'
            }).finally(() => {
                // 3. Redirect to logout — happens in < 100ms
                window.location.href = '../../app/api/logout.php';
            });
        });
    });

    // Normal page navigation
    document.querySelectorAll('a.internal-link').forEach(a => {
        a.addEventListener('click', () => updater.disconnect());
    });
});