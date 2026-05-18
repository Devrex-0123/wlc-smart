<?php
/**
 * Shared dashboard “modern” overview strip: theme-aligned chart visuals (decorative / glanceable).
 * Include once per dashboard main, immediately after .page-header.
 */
$barHeights = [38, 62, 45, 71, 52, 68, 55, 80, 48, 73, 58, 66];
?>
<section class="dashboard-insights" aria-label="Overview">
    <div class="dashboard-insights-inner">
        <div class="insight-panel insight-panel--wide">
            <div class="insight-panel-head">
                <div class="insight-panel-title-wrap">
                    <span class="insight-panel-icon" aria-hidden="true"><i class="fas fa-chart-area"></i></span>
                    <div>
                        <h2 class="insight-panel-title">Activity trend</h2>
                        <p class="insight-panel-sub">Illustrative pulse — requisitions &amp; workflow volume</p>
                    </div>
                </div>
            </div>
            <div class="insight-chart-wrap">
                <svg class="insight-area-svg" viewBox="0 0 520 140" preserveAspectRatio="none" aria-hidden="true">
                    <defs>
                        <linearGradient id="dashInsightFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#22c55e" stop-opacity="0.28"/>
                            <stop offset="100%" stop-color="#22c55e" stop-opacity="0"/>
                        </linearGradient>
                        <linearGradient id="dashInsightStroke" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0%" stop-color="#16a34a"/>
                            <stop offset="100%" stop-color="#059669"/>
                        </linearGradient>
                    </defs>
                    <path class="insight-area-fill" d="M0,105 C40,100 80,75 130,82 C180,88 220,55 270,62 C320,68 360,42 410,48 C460,54 500,28 520,32 L520,140 L0,140 Z" fill="url(#dashInsightFill)"/>
                    <path class="insight-area-line" d="M0,105 C40,100 80,75 130,82 C180,88 220,55 270,62 C320,68 360,42 410,48 C460,54 500,28 520,32" fill="none" stroke="url(#dashInsightStroke)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <div class="insight-bar-chart" aria-hidden="true">
                    <?php foreach ($barHeights as $h) : ?>
                        <span class="insight-bar" style="height: <?php echo (int) $h; ?>%"></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="insight-panel insight-panel--compact">
            <div class="insight-panel-head">
                <div class="insight-panel-title-wrap">
                    <span class="insight-panel-icon insight-panel-icon--cyan" aria-hidden="true"><i class="fas fa-chart-pie"></i></span>
                    <div>
                        <h2 class="insight-panel-title">Balance</h2>
                        <p class="insight-panel-sub">Target mix — pending vs cleared</p>
                    </div>
                </div>
            </div>
            <div class="insight-donut-wrap">
                <div class="insight-donut" role="img" aria-label="Decorative chart: 62 percent primary segment">
                    <div class="insight-donut-inner">
                        <span class="insight-donut-value">62%</span>
                        <span class="insight-donut-label">On track</span>
                    </div>
                </div>
                <ul class="insight-legend">
                    <li><span class="insight-dot insight-dot--primary"></span> Active pipeline</li>
                    <li><span class="insight-dot insight-dot--muted"></span> Cleared / stable</li>
                </ul>
            </div>
        </div>
    </div>
</section>
