(function () {
    var root = document.getElementById('aboutPreview');
    if (!root) return;

    var lowStockEmpty = document.getElementById('previewLowStockEmpty');
    var lowStockList = document.getElementById('previewLowStockList');

    root.addEventListener('click', function (event) {
        var actionEl = event.target.closest('[data-preview-action]');
        if (!actionEl || !root.contains(actionEl)) return;

        var action = actionEl.getAttribute('data-preview-action');

        if (action === 'view-requisitions') {
            var reqCard = root.querySelector('.preview-req-list');
            if (reqCard) {
                reqCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            return;
        }

        if (action === 'view-low-stock') {
            if (lowStockEmpty && lowStockList) {
                lowStockEmpty.hidden = true;
                lowStockList.hidden = false;
            }
            return;
        }

        if (action === 'kpi') {
            root.querySelectorAll('.preview-kpi').forEach(function (kpi) {
                kpi.classList.remove('is-highlight');
            });
            actionEl.classList.add('is-highlight');
        }
    });

    root.querySelectorAll('[data-preview-req]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            root.querySelectorAll('.preview-req-item').forEach(function (item) {
                item.classList.remove('is-selected');
            });
            btn.classList.add('is-selected');
        });
    });
})();
