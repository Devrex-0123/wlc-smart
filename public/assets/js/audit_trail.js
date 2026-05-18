const ITEMS_PER_PAGE = 10;
let allLogged = [];
let allActivity = [];
let loggedPage = 1;
let activityPage = 1;
let activeView = "logged";

function parseDateValue(value) {
  if (!value || value === "Active Session") return null;
  const dt = new Date(value);
  return Number.isNaN(dt.getTime()) ? null : dt;
}

function dateRangeMatch(dateValue, from, to) {
  if (!from && !to) return true;
  const dt = parseDateValue(dateValue);
  if (!dt) return false;
  const start = from ? new Date(`${from}T00:00:00`) : null;
  const end = to ? new Date(`${to}T23:59:59`) : null;
  if (start && dt < start) return false;
  if (end && dt > end) return false;
  return true;
}

function getFilters() {
  return {
    search: (document.getElementById("globalSearch")?.value || "").toLowerCase().trim(),
    from: document.getElementById("dateFrom")?.value || "",
    to: document.getElementById("dateTo")?.value || "",
    action: (document.getElementById("actionTypeFilter")?.value || "all").toLowerCase()
  };
}

function matchesAction(text, action) {
  if (!action || action === "all") return true;
  const normalized = String(text || "").toLowerCase();
  if (action === "other") {
    return !["add", "edit", "update", "delete", "login", "logout", "approve", "reject"].some((token) => normalized.includes(token));
  }
  return normalized.includes(action);
}

function renderLogged() {
  const tbody = document.getElementById("loggedBody");
  const { search, from, to, action } = getFilters();
  const filtered = allLogged.filter((row) => {
    const searchHit = !search || [row.email, row.role, row.status, row.time_in, row.time_out].some((v) => String(v || "").toLowerCase().includes(search));
    const actionText = `${row.status || ""} ${row.duration || ""}`;
    const actionHit = matchesAction(actionText, action);
    return searchHit && actionHit && dateRangeMatch(row.time_in, from, to);
  });

  const totalPages = Math.max(1, Math.ceil(filtered.length / ITEMS_PER_PAGE));
  loggedPage = Math.min(loggedPage, totalPages);
  const start = (loggedPage - 1) * ITEMS_PER_PAGE;
  const pageItems = filtered.slice(start, start + ITEMS_PER_PAGE);

  tbody.innerHTML = pageItems.length ? "" : `<tr><td colspan="8" style="text-align:center;padding:36px;color:#64748b;">No logged records found.</td></tr>`;
  pageItems.forEach((log, i) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${start + i + 1}</td>
      <td>${log.log_id || "—"}</td>
      <td>${log.email || "—"}</td>
      <td>${log.role || "—"}</td>
      <td>${log.time_in || "—"}</td>
      <td>${log.time_out || "—"}</td>
      <td>${log.duration || "—"}</td>
      <td>${log.status || "—"}</td>
    `;
    tbody.appendChild(tr);
  });

  document.getElementById("loggedPageInfo").textContent = `Page ${loggedPage} of ${totalPages}`;
  document.getElementById("prevLoggedBtn").disabled = loggedPage === 1;
  document.getElementById("nextLoggedBtn").disabled = loggedPage === totalPages;
  document.getElementById("loggedCount").textContent = String(filtered.length);
}

function renderActivity() {
  const tbody = document.getElementById("activityBody");
  const { search, from, to, action } = getFilters();
  const filtered = allActivity.filter((row) => {
    const searchHit = !search || [row.user, row.role, row.type, row.description, row.time].some((v) => String(v || "").toLowerCase().includes(search));
    const actionText = `${row.type || ""} ${row.description || ""}`;
    const actionHit = matchesAction(actionText, action);
    return searchHit && actionHit && dateRangeMatch(row.time, from, to);
  });

  const totalPages = Math.max(1, Math.ceil(filtered.length / ITEMS_PER_PAGE));
  activityPage = Math.min(activityPage, totalPages);
  const start = (activityPage - 1) * ITEMS_PER_PAGE;
  const pageItems = filtered.slice(start, start + ITEMS_PER_PAGE);

  tbody.innerHTML = pageItems.length ? "" : `<tr><td colspan="7" style="text-align:center;padding:36px;color:#64748b;">No activity records found.</td></tr>`;
  pageItems.forEach((act, i) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${start + i + 1}</td>
      <td>${act.activity_id || "—"}</td>
      <td>${act.user || "—"}</td>
      <td>${act.role || "—"}</td>
      <td>${act.type || "—"}</td>
      <td>${act.description || "—"}</td>
      <td>${act.time || "—"}</td>
    `;
    tbody.appendChild(tr);
  });

  document.getElementById("activityPageInfo").textContent = `Page ${activityPage} of ${totalPages}`;
  document.getElementById("prevActivityBtn").disabled = activityPage === 1;
  document.getElementById("nextActivityBtn").disabled = activityPage === totalPages;
  document.getElementById("activityCount").textContent = String(filtered.length);
}

function renderActiveView() {
  if (activeView === "logged") {
    renderLogged();
  } else {
    renderActivity();
  }
  document.getElementById("combinedCount").textContent = String(allLogged.length + allActivity.length);
}

function setView(nextView) {
  activeView = nextView;
  const actionFilter = document.getElementById("actionTypeFilter");
  if (actionFilter) {
    actionFilter.disabled = false;
  }
  document.getElementById("loggedPanel").style.display = nextView === "logged" ? "" : "none";
  document.getElementById("activityPanel").style.display = nextView === "activity" ? "" : "none";
  document.getElementById("showLoggedBtn").classList.toggle("active", nextView === "logged");
  document.getElementById("showActivityBtn").classList.toggle("active", nextView === "activity");
  renderActiveView();
}

async function loadAuditData() {
  try {
    const [logsRes, activityRes] = await Promise.all([
      fetch("../../app/api/get_all_logs.php", { credentials: "include" }),
      fetch("../../app/api/get_activity_logs.php", { credentials: "include" })
    ]);
    const logsData = await logsRes.json();
    const activityData = await activityRes.json();
    allLogged = logsData.success ? (logsData.logs || []) : [];
    allActivity = activityData.success ? (activityData.activities || []) : [];
  } catch (_) {
    allLogged = [];
    allActivity = [];
  }
  renderLogged();
  renderActivity();
  document.getElementById("combinedCount").textContent = String(allLogged.length + allActivity.length);
}

document.getElementById("showLoggedBtn")?.addEventListener("click", () => setView("logged"));
document.getElementById("showActivityBtn")?.addEventListener("click", () => setView("activity"));
document.getElementById("globalSearch")?.addEventListener("input", () => {
  loggedPage = 1;
  activityPage = 1;
  renderActiveView();
});
document.getElementById("dateFrom")?.addEventListener("change", () => {
  loggedPage = 1;
  activityPage = 1;
  renderActiveView();
});
document.getElementById("dateTo")?.addEventListener("change", () => {
  loggedPage = 1;
  activityPage = 1;
  renderActiveView();
});
document.getElementById("actionTypeFilter")?.addEventListener("change", () => {
  loggedPage = 1;
  activityPage = 1;
  renderActiveView();
});
document.getElementById("clearDateBtn")?.addEventListener("click", () => {
  document.getElementById("dateFrom").value = "";
  document.getElementById("dateTo").value = "";
  const actionFilter = document.getElementById("actionTypeFilter");
  if (actionFilter) {
    actionFilter.value = "all";
  }
  loggedPage = 1;
  activityPage = 1;
  renderActiveView();
});
document.getElementById("prevLoggedBtn")?.addEventListener("click", () => {
  if (loggedPage > 1) loggedPage--;
  renderLogged();
});
document.getElementById("nextLoggedBtn")?.addEventListener("click", () => {
  loggedPage++;
  renderLogged();
});
document.getElementById("prevActivityBtn")?.addEventListener("click", () => {
  if (activityPage > 1) activityPage--;
  renderActivity();
});
document.getElementById("nextActivityBtn")?.addEventListener("click", () => {
  activityPage++;
  renderActivity();
});

// Mobile menu
const mobileMenuBtn = document.getElementById("mobileMenuBtn");
const sidebar = document.getElementById("sidebar");
if (window.innerWidth <= 768 && mobileMenuBtn) mobileMenuBtn.style.display = "block";
mobileMenuBtn?.addEventListener("click", () => sidebar?.classList.toggle("open"));
document.addEventListener("click", (e) => {
  if (window.innerWidth <= 768 && sidebar && mobileMenuBtn && !sidebar.contains(e.target) && !mobileMenuBtn.contains(e.target) && sidebar.classList.contains("open")) {
    sidebar.classList.remove("open");
  }
});
window.addEventListener("resize", () => {
  if (!mobileMenuBtn || !sidebar) return;
  mobileMenuBtn.style.display = window.innerWidth > 768 ? "none" : "block";
  if (window.innerWidth > 768) sidebar.classList.remove("open");
});

loadAuditData();
