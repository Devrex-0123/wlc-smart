    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supplier & Requisition UI</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f6f7;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 900px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .tab-nav {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .tab-nav button {
            flex: 1;
            padding: 0.75rem;
            background: #16a34a;
            border: none;
            color: white;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .tab-nav button.active,
        .tab-nav button:hover {
            background: #15803d;
        }

        .section {
            display: none;
        }

        .section.active {
            display: block;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.25rem;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .btn {
            padding: 0.5rem 1rem;
            background: #16a34a;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #15803d;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        table th,
        table td {
            padding: 0.5rem;
            border: 1px solid #ddd;
            text-align: left;
        }

        table th {
            background: #f0f4f8;
        }

        .status {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            color: white;
            font-size: 0.85rem;
        }

        .status.Pending { background: #f59e0b; }
        .status.Approved { background: #16a34a; }
        .status.Denied { background: #dc2626; }
        .status.Delivered { background: #2563eb; }
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <?php require __DIR__ . '/partials/sidebar_brand_header.php'; ?>
        <nav>
            <ul class="sidebar-nav">
                <li><a href="#" class="active"><i class="fas fa-truck"></i> Suppliers</a></li>
                <li><a href="#"><i class="fas fa-file-contract"></i> Requisition</a></li>
                <li><a href="#"><i class="fas fa-chart-line"></i> Monitoring</a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="user-avatar">
                    <div class="user-avatar-initials">SU</div>
                </div>
                <div class="user-details">
                    <h4>Sample User</h4>
                    <p>Admin</p>
                </div>
            </div>
            <button id="logoutBtn" class="btn-logout-sidebar">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
    </aside>

    <main class="main-content">
    <div class="container">
        <h1>Supplier / Requisition / Monitoring (Sample UI)</h1>
        <div class="tab-nav">
            <button id="tab-suppliers" onclick="showSection('suppliers')" class="active">Suppliers</button>
            <button id="tab-requisitions" onclick="showSection('requisitions')">Requisition</button>
            <button id="tab-monitoring" onclick="showSection('monitoring')">Monitoring</button>
        </div>

        <div id="suppliers" class="section active">
            <h2>Manage Suppliers</h2>
            <div class="form-group">
                <label for="supplier-name">Name</label>
                <input type="text" id="supplier-name" placeholder="e.g. Acme Corp" />
            </div>
            <button class="btn" onclick="addSupplier()">Add Supplier</button>

            <table id="supplier-table">
                <thead>
                    <tr><th>#</th><th>Name</th></tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div id="requisitions" class="section">
            <h2>Submit Requisition</h2>
            <div class="form-group">
                <label for="req-item">Item</label>
                <input type="text" id="req-item" placeholder="e.g. Computer" />
            </div>
            <button class="btn" onclick="submitRequisition()">Request Item</button>

            <table id="requisition-table">
                <thead>
                    <tr><th>#</th><th>Item</th><th>Status</th></tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div id="monitoring" class="section">
            <h2>Monitor Requests</h2>
            <table id="monitor-table">
                <thead>
                    <tr><th>#</th><th>Item</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
    </main>

    <script>
        let suppliers = [];
        let requisitions = [];
        let nextSupplierId = 1;
        let nextReqId = 1;

        function showSection(id) {
            document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
            document.getElementById(id).classList.add('active');
            document.querySelectorAll('.tab-nav button').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + id).classList.add('active');
            renderAll();
        }

        function addSupplier() {
            const nameEl = document.getElementById('supplier-name');
            const name = nameEl.value.trim();
            if (!name) return alert('Please enter a supplier name');
            suppliers.push({ id: nextSupplierId++, name });
            nameEl.value = '';
            renderSuppliers();
        }

        function renderSuppliers() {
            const tbody = document.querySelector('#supplier-table tbody');
            tbody.innerHTML = '';
            suppliers.forEach((s, i) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${i + 1}</td><td>${s.name}</td>`;
                tbody.appendChild(tr);
            });
        }

        function submitRequisition() {
            const itemEl = document.getElementById('req-item');
            const item = itemEl.value.trim();
            if (!item) return alert('Please enter an item name');
            requisitions.push({ id: nextReqId++, item, status: 'Pending' });
            itemEl.value = '';
            renderRequisitions();
            renderMonitoring();
        }

        function renderRequisitions() {
            const tbody = document.querySelector('#requisition-table tbody');
            tbody.innerHTML = '';
            requisitions.forEach((r, i) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${i + 1}</td><td>${r.item}</td><td><span class=\"status ${r.status}\">${r.status}</span></td>`;
                tbody.appendChild(tr);
            });
        }

        function renderMonitoring() {
            const tbody = document.querySelector('#monitor-table tbody');
            tbody.innerHTML = '';
            requisitions.forEach((r, i) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${i + 1}</td>
                    <td>${r.item}</td>
                    <td><span class=\"status ${r.status}\">${r.status}</span></td>
                    <td>
                        <select onchange=\"changeStatus(${r.id},this.value)\">
                            <option value=\"Pending\"${r.status==='Pending'?' selected':''}>Pending</option>
                            <option value=\"Approved\"${r.status==='Approved'?' selected':''}>Approved</option>
                            <option value=\"Denied\"${r.status==='Denied'?' selected':''}>Denied</option>
                            <option value=\"Delivered\"${r.status==='Delivered'?' selected':''}>Delivered</option>
                        </select>
                    </td>`;
                tbody.appendChild(tr);
            });
        }

        function changeStatus(id, status) {
            const req = requisitions.find(r => r.id === id);
            if (!req) return;
            req.status = status;
            renderRequisitions();
            renderMonitoring();
        }

        function renderAll() {
            renderSuppliers();
            renderRequisitions();
            renderMonitoring();
        }

        renderAll();
    </script>
    <script src="../assets/js/logout.js?v=wlc1"></script>
</body>
</html>