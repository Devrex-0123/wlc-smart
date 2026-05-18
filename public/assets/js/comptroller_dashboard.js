/**
 * Comptroller dashboard — loads aggregate stats from comptroller API.
 */
(function () {
    const API = '../../app/api/comptroller.php?action=dashboard_stats';

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    document.addEventListener('DOMContentLoaded', async () => {
        try {
            const res = await fetch(API, { credentials: 'include' });
            const data = await res.json();
            if (!data.success) {
                setText('compStatPending', '—');
                return;
            }
            setText('compStatPending', String(data.pending ?? '—'));
            setText('compStatOngoing', String(data.ongoing ?? '—'));
            setText('compStatClearedMonth', String(data.cleared_this_month ?? '—'));
            setText('compStatFlagged', String(data.flagged ?? '—'));
            setText('compStatTotalLines', String(data.total_lines ?? '—'));
            setText('compStatCompletedAll', String(data.completed ?? '—'));
        } catch {
            setText('compStatPending', '—');
        }
    });
})();
