function formatCount(value) {
  const n = Number(value) || 0;
  return n.toLocaleString('en-US');
}

function animateCount(el, target, duration = 900) {
  if (!el) return;
  const end = Math.max(0, Number(target) || 0);
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (prefersReduced || end === 0) {
    el.textContent = formatCount(end);
    return;
  }

  const start = performance.now();
  const from = 0;

  function frame(now) {
    const progress = Math.min((now - start) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    const current = Math.round(from + (end - from) * eased);
    el.textContent = formatCount(current);
    if (progress < 1) requestAnimationFrame(frame);
  }

  requestAnimationFrame(frame);
}

function setMeta(id, text) {
  const el = document.getElementById(id);
  if (el) el.textContent = text;
}

function applySummary(summary) {
  animateCount(document.getElementById('totalAssetsCount'), summary.total_assets);
  animateCount(document.getElementById('activeRequestsCount'), summary.active_requests);
  animateCount(document.getElementById('pendingDeliveryCount'), summary.pending_delivery);
  animateCount(document.getElementById('deptsActiveCount'), summary.depts_with_active_requests);

  const deptCount = Number(summary.total_departments) || 0;
  const deptLabel = deptCount === 1 ? 'department' : 'departments';
  setMeta('totalAssetsMeta', `Across ${formatCount(deptCount)} ${deptLabel}`);

  const awaiting = Number(summary.awaiting_validation) || 0;
  setMeta(
    'activeRequestsMeta',
    awaiting === 1 ? '1 awaiting validation' : `${formatCount(awaiting)} awaiting validation`
  );

  const arriving = Number(summary.arriving_this_week) || 0;
  setMeta(
    'pendingDeliveryMeta',
    arriving === 1 ? '1 arriving this week' : `${formatCount(arriving)} arriving this week`
  );
}

function applyPipeline(pipeline = {}) {
  const request = pipeline.request || {};
  const canvass = pipeline.canvass || {};
  const pr = pipeline.pr || {};
  const po = pipeline.po || {};
  const delivery = pipeline.delivery || {};

  animateCount(document.getElementById('pipelineRequestSubmitted'), request.submitted);
  animateCount(document.getElementById('pipelineRequestAwaiting'), request.awaiting);
  animateCount(document.getElementById('pipelineCanvassSubmitted'), canvass.submitted);
  animateCount(document.getElementById('pipelineCanvassAwaiting'), canvass.awaiting);
  animateCount(document.getElementById('pipelinePrSubmitted'), pr.submitted);
  animateCount(document.getElementById('pipelinePrAwaiting'), pr.awaiting);
  animateCount(document.getElementById('pipelinePoSubmitted'), po.submitted);
  animateCount(document.getElementById('pipelinePoAwaiting'), po.awaiting);
  animateCount(document.getElementById('pipelineDeliveryTransit'), delivery.in_transit);
  animateCount(document.getElementById('pipelineDeliveryReceiving'), delivery.pending_receiving);
}

function renderRecentRequisitions(items = []) {
  const list = document.getElementById('recentRequisitionsList');
  if (!list) return;

  if (!items.length) {
    list.innerHTML = '<li class="dashboard-recent__empty">No recent requisitions yet.</li>';
    return;
  }

  list.innerHTML = items
    .map((item) => {
      const tone = String(item.tone || 'request');
      const title = String(item.title || 'Requisition');
      const reference = String(item.reference || '');
      const stage = String(item.stage || '');
      const badge = String(item.badge || 'Request');

      return `
        <li class="dashboard-recent__item">
          <span class="dashboard-recent__icon dashboard-recent__icon--${tone}" aria-hidden="true">
            <i class="fas fa-file-lines"></i>
          </span>
          <div class="dashboard-recent__body">
            <p class="dashboard-recent__title">${escapeHtml(title)}</p>
            <p class="dashboard-recent__meta">${escapeHtml(reference)} · ${escapeHtml(stage)}</p>
          </div>
          <span class="dashboard-recent__badge dashboard-recent__badge--${tone}">${escapeHtml(badge)}</span>
        </li>
      `;
    })
    .join('');
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function formatPoDateTime(value) {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

function renderAwaitingItemReceipt(items = []) {
  const list = document.getElementById('awaitingItemReceiptList');
  if (!list) return;

  if (!items.length) {
    list.innerHTML = '<li class="dashboard-recent__empty">No purchase orders awaiting item receipt.</li>';
    return;
  }

  list.innerHTML = items
    .map((item) => {
      const poNumber = escapeHtml(String(item.po_number || '—'));
      const location = escapeHtml(String(item.location || '—'));
      const released = escapeHtml(formatPoDateTime(item.payment_released_at));
      const requestId = Number(item.requisition_id || 0);
      const progressHref =
        requestId > 0
          ? `requisition_status_progress.php?rid=${encodeURIComponent(String(requestId))}`
          : '#';

      return `
        <li class="dashboard-recent__item dashboard-awaiting-receipt__item">
          <span class="dashboard-recent__icon dashboard-recent__icon--delivery" aria-hidden="true">
            <i class="fas fa-box-open"></i>
          </span>
          <div class="dashboard-recent__body">
            <p class="dashboard-recent__title">${poNumber}</p>
            <p class="dashboard-recent__meta">${location} · Payment released ${released}</p>
          </div>
          <a href="${progressHref}" class="dashboard-awaiting-receipt__view">
            View <i class="fas fa-chevron-right" aria-hidden="true"></i>
          </a>
        </li>
      `;
    })
    .join('');
}

async function loadDashboardSummary() {
  try {
    const res = await fetch('../../app/api/get_dashboard_summary.php', { credentials: 'include' });
    const data = await res.json();
    if (data.success) {
      applySummary(data.summary || {});
      applyPipeline(data.pipeline || {});
      renderRecentRequisitions(data.recent_requisitions || []);
      renderAwaitingItemReceipt(data.awaiting_item_receipt || []);
      return;
    }
  } catch (err) {
    console.error('Dashboard summary failed:', err);
  }

  applySummary({});
  applyPipeline({});
  renderRecentRequisitions([]);
  renderAwaitingItemReceipt([]);
}

document.addEventListener('DOMContentLoaded', loadDashboardSummary);
