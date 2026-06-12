const ITEMS_PER_PAGE = 10;

let itemsPerPage = ITEMS_PER_PAGE;
let allLogged = [];
let allActivity = [];
let loggedPage = 1;
let activityPage = 1;
let activeView = "activity";

function formatCount(value) {
  const n = Number(value) || 0;
  return n.toLocaleString("en-US");
}

function animateCount(el, target, duration = 900) {
  if (!el) return;
  const end = Math.max(0, Number(target) || 0);
  const prefersReduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  if (prefersReduced || end === 0) {
    el.textContent = formatCount(end);
    return;
  }

  const start = performance.now();

  function frame(now) {
    const progress = Math.min((now - start) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    const current = Math.round(end * eased);
    el.textContent = formatCount(current);
    if (progress < 1) requestAnimationFrame(frame);
  }

  requestAnimationFrame(frame);
}

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
    to: document.getElementById("dateTo")?.value || ""
  };
}

function formatTablePageInfo(total, page, perPage, singular, plural) {
  if (total <= 0) return `Showing 0 to 0 of 0 ${plural}`;
  const start = (page - 1) * perPage + 1;
  const end = Math.min(page * perPage, total);
  const noun = total === 1 ? singular : plural;
  return `Showing ${start} to ${end} of ${total} ${noun}`;
}

function updateAuditPagination({
  pageInfoId,
  pageNumId,
  prevId,
  nextId,
  currentPage,
  totalPages,
  totalRecords,
  singular,
  plural,
}) {
  const pageInfo = document.getElementById(pageInfoId);
  const pageNum = document.getElementById(pageNumId);
  const prev = document.getElementById(prevId);
  const next = document.getElementById(nextId);
  if (!prev || !next) return;

  if (pageInfo) {
    pageInfo.textContent = formatTablePageInfo(totalRecords, currentPage, itemsPerPage, singular, plural);
  }
  if (pageNum) pageNum.textContent = String(currentPage);
  prev.disabled = currentPage <= 1 || totalRecords === 0;
  next.disabled = currentPage >= totalPages || totalRecords === 0;
}

function renderLogged() {
  const tbody = document.getElementById("loggedBody");
  const { search, from, to } = getFilters();
  const filtered = allLogged.filter((row) => {
    const searchHit = !search || [row.email, row.role, row.status, row.time_in, row.time_out].some((v) => String(v || "").toLowerCase().includes(search));
    return searchHit && dateRangeMatch(row.time_in, from, to);
  });

  const totalPages = Math.max(1, Math.ceil(filtered.length / itemsPerPage));
  loggedPage = Math.min(loggedPage, totalPages);
  const start = (loggedPage - 1) * itemsPerPage;
  const pageItems = filtered.slice(start, start + itemsPerPage);

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

  updateAuditPagination({
    pageInfoId: "loggedPageInfo",
    pageNumId: "loggedPageNum",
    prevId: "prevLoggedBtn",
    nextId: "nextLoggedBtn",
    currentPage: loggedPage,
    totalPages,
    totalRecords: filtered.length,
    singular: "session",
    plural: "sessions",
  });
}

function renderActivity() {
  const tbody = document.getElementById("activityBody");
  const { search, from, to } = getFilters();
  const filtered = allActivity.filter((row) => {
    const searchHit = !search || [row.user, row.role, row.type, row.description, row.time].some((v) => String(v || "").toLowerCase().includes(search));
    return searchHit && dateRangeMatch(row.time, from, to);
  });

  const totalPages = Math.max(1, Math.ceil(filtered.length / itemsPerPage));
  activityPage = Math.min(activityPage, totalPages);
  const start = (activityPage - 1) * itemsPerPage;
  const pageItems = filtered.slice(start, start + itemsPerPage);

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

  updateAuditPagination({
    pageInfoId: "activityPageInfo",
    pageNumId: "activityPageNum",
    prevId: "prevActivityBtn",
    nextId: "nextActivityBtn",
    currentPage: activityPage,
    totalPages,
    totalRecords: filtered.length,
    singular: "entry",
    plural: "entries",
  });
}

function renderSummary(summary = {}) {
  animateCount(document.getElementById("todaysActivitiesCount"), summary.todays_activities);
  animateCount(document.getElementById("activeUsersCount"), summary.active_users);
  animateCount(document.getElementById("failedLoginCount"), summary.failed_login_attempts);
  animateCount(document.getElementById("totalAuditRecordsCount"), summary.total_audit_records);
}

function renderActiveView() {
  if (activeView === "logged") {
    renderLogged();
  } else {
    renderActivity();
  }
}

async function loadSummary() {
  try {
    const res = await fetch("../../app/api/get_audit_summary.php", { credentials: "include" });
    const data = await res.json();
    renderSummary(data.success ? (data.summary || {}) : {});
  } catch (_) {
    renderSummary({});
  }
}

function setView(nextView) {
  activeView = nextView;
  document.getElementById("loggedPanel").style.display = nextView === "logged" ? "" : "none";
  document.getElementById("activityPanel").style.display = nextView === "activity" ? "" : "none";
  document.getElementById("showLoggedBtn").classList.toggle("active", nextView === "logged");
  document.getElementById("showActivityBtn").classList.toggle("active", nextView === "activity");
  itemsPerPage = ITEMS_PER_PAGE;
  loggedPage = 1;
  activityPage = 1;
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
  itemsPerPage = ITEMS_PER_PAGE;
  renderLogged();
  renderActivity();
  await loadSummary();
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
document.getElementById("clearDateBtn")?.addEventListener("click", () => {
  document.getElementById("dateFrom").value = "";
  document.getElementById("dateTo").value = "";
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
  if (mobileMenuBtn && sidebar) {
    mobileMenuBtn.style.display = window.innerWidth > 768 ? "none" : "block";
    if (window.innerWidth > 768) sidebar.classList.remove("open");
  }
});

loadAuditData();
