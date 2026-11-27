@extends('admin.layouts.app')
@section('title','Admin Dashboard - FIX-MOTO')
@section('page_title','Dashboard')
@section('content')
<div class="admin-page" id="admin-dashboard" data-geography-url="{{ route('admin.dashboard.geography') }}">
    <div class="admin-page-header">
        <div><p class="live-kicker">LIVE OPERATIONS · CALAPAN CITY</p><h1>Admin dashboard</h1><p>Monitor mechanic shop availability and coverage across Calapan City.</p></div>
    </div>

    <section class="admin-stats">
        <article class="admin-stat"><div class="stat-copy"><span>Registered shops</span><strong>{{ $shopCount }}</strong><small>{{ $verifiedShopCount }} verified · Across {{ $coveredBarangays }} barangays</small></div><div class="admin-stat-icon green"><i data-lucide="building-2"></i></div></article>
        <article class="admin-stat"><div class="stat-copy"><span>Shops online</span><strong>{{ $onlineShopCount }}<em>/{{ $shopCount }}</em></strong><small>{{ $coveragePercent }}% currently online</small></div><div class="admin-stat-icon amber"><i data-lucide="wrench"></i></div></article>
        <article class="admin-stat"><div class="stat-copy"><span>Avg. dispatch time</span><strong>{{ $avgDispatch }}</strong><small>{{ $avgDispatch === 'No data' ? 'Awaiting response records' : 'Average accepted response time' }}</small></div><div class="admin-stat-icon blue"><i data-lucide="clock-3"></i></div></article>
        <article class="admin-stat barangay-coverage-stat">
            <div class="stat-copy barangay-coverage-copy">
                <span>Barangays Covered</span>
                <strong><b id="covered-barangay-count">{{ $coveredBarangays }}</b><em> / <span id="total-barangay-count">{{ $totalBarangays }}</span></em></strong>
                <small><b id="coverage-percentage">{{ $barangayCoveragePercent }}</b>% coverage · <span id="selected-shop-summary">{{ $shopCount }} registered shops in All Barangays</span></small>
                <div class="coverage-progress" role="progressbar" aria-label="Barangay coverage" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $barangayCoveragePercent }}">
                    <span id="coverage-progress-fill" style="width:{{ $barangayCoveragePercent }}%"></span>
                </div>
            </div>
            <div class="admin-stat-icon neutral"><i data-lucide="map-pin"></i></div>
            <div class="barangay-picker" id="barangay-picker">
                <button type="button" class="barangay-picker-trigger" id="barangay-picker-trigger" role="combobox" aria-label="Filter by barangay" aria-haspopup="listbox" aria-expanded="false" aria-controls="barangay-picker-menu" disabled>
                    <i data-lucide="map-pin"></i>
                    <span class="barangay-picker-name" id="selected-barangay-name">All Barangays</span>
                    <span class="barangay-picker-count" id="selected-barangay-count">{{ $shopCount }}</span>
                    <i data-lucide="chevron-down"></i>
                </button>
                <div class="barangay-picker-menu" id="barangay-picker-menu" aria-hidden="true">
                    <label class="barangay-picker-search">
                        <i data-lucide="search"></i>
                        <input type="search" id="barangay-picker-search" placeholder="Search barangays" autocomplete="off" aria-label="Search barangays">
                    </label>
                    <div class="barangay-picker-options" id="barangay-picker-options" role="listbox" aria-label="Calapan barangays"></div>
                </div>
            </div>
            <small class="barangay-picker-status" id="barangay-picker-status" role="status">Loading official barangay boundaries…</small>
        </article>
    </section>

    <section class="distribution-card barangay-distribution-card">
        <div class="distribution-header">
            <div><p>MECHANIC SHOP DISTRIBUTION</p><h2 id="distribution-title">Shops per Barangay</h2></div>
        </div>
        <div class="coverage-chart-scroll" id="coverage-chart-scroll">
            <div class="coverage-chart-canvas"><canvas id="mechanicDistribution" aria-label="Registered mechanic shops by barangay"></canvas></div>
        </div>
        <div class="admin-empty barangay-chart-empty" id="barangay-chart-empty" hidden>No registered mechanic shops in this barangay.</div>
        <p class="barangay-picker-status" id="geography-load-status" role="status" aria-live="polite"></p>
    </section>

    <section class="admin-bottom-grid">
        <article class="map-card">
            <div class="panel-header">
                <div><h2>Barangay coverage &amp; live dispatch map</h2><p><span class="live-dot"></span> GPS last updated {{ now()->diffForHumans() }}</p></div>
                <div class="map-legend"><span><i class="legend-dot green"></i>Online shop</span><span><i class="legend-dot amber"></i>Offline shop</span><span><i class="legend-dot red"></i>Needs review / request</span></div>
            </div>
            <div id="adminDispatchMap" aria-label="Calapan City barangay boundaries and live shop locations"></div>
            <small class="map-data-attribution">Barangay boundaries: PSA PSGC 3Q 2026 / NAMRIA source shapes, via faeldon/philippines-json-maps.</small>
        </article>
        <article class="attention-card">
            <div class="panel-header"><div><h2>Needs attention</h2></div><span class="attention-count" id="attention-count">{{ $unassignedRequests->count() + $pendingVerifications->count() + $needsGeographyReview }}</span></div>
            @if($unassignedRequests->count())
                <div class="attention-item"><span class="attention-icon amber"><i data-lucide="siren"></i></span><div><strong>Unassigned requests</strong><p>{{ $unassignedRequests->count() }} service request(s) are waiting.</p><small>{{ $unassignedRequests->first()?->created_at?->diffForHumans() }}</small></div><a href="{{ route('admin.live-dispatch') }}">Reassign</a></div>
            @endif
            @if($pendingVerifications->count())
                <div class="attention-item"><span class="attention-icon green"><i data-lucide="badge-check"></i></span><div><strong>Pending shop verifications</strong><p>{{ $pendingVerifications->count() }} mechanic shop application(s) require review.</p><small>{{ $pendingVerifications->first()?->created_at?->diffForHumans() }}</small></div><a href="{{ route('admin.verification') }}">Review</a></div>
            @endif
            <div class="attention-item geography-review-item" id="geography-review-item" @if($needsGeographyReview === 0) hidden @endif>
                <span class="attention-icon amber"><i data-lucide="map-pin"></i></span>
                <div><strong>Shop locations need review</strong><p><span id="geography-review-count">{{ $needsGeographyReview }}</span> location(s) are unmatched or ambiguous.</p><small>Check GPS pins against barangay boundaries.</small></div>
            </div>
            @if(!$unassignedRequests->count() && !$pendingVerifications->count() && $needsGeographyReview === 0)
                <div class="admin-empty compact" id="admin-empty-attention">No unresolved items.</div>
            @endif
        </article>
    </section>
</div>

<script>
const dashboardElement = document.getElementById('admin-dashboard');
const geographyUrl = dashboardElement.dataset.geographyUrl;
const picker = document.getElementById('barangay-picker');
const pickerTrigger = document.getElementById('barangay-picker-trigger');
const pickerMenu = document.getElementById('barangay-picker-menu');
const pickerSearch = document.getElementById('barangay-picker-search');
const pickerOptions = document.getElementById('barangay-picker-options');
const pickerStatus = document.getElementById('barangay-picker-status');
const chartStatus = document.getElementById('geography-load-status');
const chartEmpty = document.getElementById('barangay-chart-empty');
const chartCanvas = document.getElementById('mechanicDistribution');
let barangayData = [];
let totalRegisteredShopCount = 0;
let selectedBarangayId = null;
let chart = null;
let chartBarangayIds = [];
let chartBarangayRows = [];
let boundaryLayer = null;
let cityBoundaryLayer = null;
let markerLayer = null;
let selectedRequestNumber = 0;
const barangayLayers = new Map();
const adminMap = L.map('adminDispatchMap');
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(adminMap);

function setBoundaryStyle(layer) {
    const isSelected = selectedBarangayId !== null && Number(layer.feature.properties.barangay_id) === Number(selectedBarangayId);
    layer.setStyle({
        color: isSelected ? '#FF0000' : '#FF0000',
        weight: isSelected ? 3 : 1,
        fillColor: isSelected ? '#FF0000' : '#FF0000',
        fillOpacity: isSelected ? 0.3 : 0.06,
    });
}

function initializeMap(data) {
    cityBoundaryLayer = L.geoJSON(data.city_boundary, {
        style: { color: '#0D2F24', weight: 2, fillOpacity: 0 },
        interactive: false,
    }).addTo(adminMap);
    boundaryLayer = L.geoJSON(data.features, {
        style: feature => ({
            color: '#FF0000',
            weight: 1,
            fillColor: '#FF0000',
            fillOpacity: 0.06,
        }),
        onEachFeature: (feature, layer) => {
            const barangayId = Number(feature.properties.barangay_id);
            barangayLayers.set(barangayId, layer);
            layer.bindTooltip(feature.properties.name, {
                permanent: true,
                direction: 'center',
                className: 'barangay-map-label',
                opacity: 0.92,
            });
            layer.on('mouseover', () => layer.setStyle({ weight: 2, fillOpacity: 0.2 }));
            layer.on('mouseout', () => setBoundaryStyle(layer));
            layer.on('click', () => selectBarangay(barangayId));
        },
    }).addTo(adminMap);
    markerLayer = L.layerGroup().addTo(adminMap);
    adminMap.fitBounds(cityBoundaryLayer.getBounds());
    adminMap.setMaxBounds(cityBoundaryLayer.getBounds().pad(0.12));
}

const countLabels = {
    id: 'shopCountLabels',
    afterDatasetsDraw(chartInstance) {
        const context = chartInstance.ctx;
        const dataset = chartInstance.data.datasets[0];
        const meta = chartInstance.getDatasetMeta(0);
        context.save();
        context.font = '600 11px Inter, Segoe UI, sans-serif';
        context.fillStyle = '#FF0000';
        context.textBaseline = 'middle';
        meta.data.forEach((bar, index) => {
            const position = bar.getProps(['x', 'y'], true);
            context.fillText(String(dataset.data[index]), position.x + 8, position.y);
        });
        context.restore();
    },
};

function renderChart(rows, title) {
    document.getElementById('distribution-title').textContent = title;
    chartBarangayRows = [...rows].sort((first, second) =>
        second.shop_count - first.shop_count || first.name.localeCompare(second.name));
    const showEmptyState = selectedBarangayId !== null && rows[0]?.shop_count === 0;
    chartEmpty.hidden = !showEmptyState;
    document.getElementById('coverage-chart-scroll').hidden = showEmptyState;

    if (!showEmptyState) {
        const canvasHeight = Math.max(280, chartBarangayRows.length * 34);
        chartCanvas.parentElement.style.height = `${canvasHeight}px`;
        if (!chart) {
            chart = new Chart(chartCanvas, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: '#FF0000',
                        hoverBackgroundColor: '#FF0000',
                        borderRadius: 6,
                        borderSkipped: false,
                        barThickness: 18,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 380, easing: 'easeOutQuart' },
                    onClick(event, elements) {
                        if (elements.length) {
                            selectBarangay(chartBarangayIds[elements[0].index]);
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: context => `${context.raw} registered shop(s)`,
                                afterLabel: context => {
                                    const row = chartBarangayRows[context.dataIndex];

                                    return [`${row.online_count} online`, `${row.offline_count} offline`];
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            suggestedMax: Math.max(1, ...chartBarangayRows.map(row => row.shop_count)) + 1,
                            grid: { color: '#EEF0ED' },
                            border: { display: false },
                            ticks: { precision: 0, stepSize: 1, color: '#62666D' },
                        },
                        y: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: { color: '#43544B', font: { size: 11 } },
                        },
                    },
                },
                plugins: [countLabels],
            });
        }

        chartBarangayIds = chartBarangayRows.map(row => row.id);
        chart.data.labels = chartBarangayRows.map(row => row.name);
        chart.data.datasets[0].data = chartBarangayRows.map(row => row.shop_count);
        chart.options.scales.x.suggestedMax = Math.max(1, ...chartBarangayRows.map(row => row.shop_count)) + 1;
        chart.update();
    }
}

function renderMarkers(data) {
    markerLayer.clearLayers();
    data.shops.forEach(shop => {
        if (shop.latitude === null || shop.longitude === null) {
            return;
        }
        if (shop.match_status === 'matched'
            && (selectedBarangayId === null || Number(shop.barangay_id) === Number(selectedBarangayId))) {
            const marker = L.circleMarker([shop.latitude, shop.longitude], {
                radius: 8,
                color: '#FFFFFF',
                weight: 2,
                fillColor: shop.is_online ? '#FF0000' : '#FF0000',
                fillOpacity: 1,
            });
            const details = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = shop.name;
            details.append(title, document.createElement('br'));
            details.append(document.createTextNode(`${shop.is_online ? 'Online' : 'Offline'} · ${shop.active_mechanics} active mechanic(s)`));
            details.append(document.createElement('br'));
            details.append(document.createTextNode(shop.service_address));
            marker.bindPopup(details).addTo(markerLayer);
        } else if (selectedBarangayId === null
            && shop.match_status !== 'matched'
            && window.FixMotoGeography.isInside(data.city_boundary.geometry, shop.latitude, shop.longitude)) {
            const marker = L.circleMarker([shop.latitude, shop.longitude], {
                radius: 8,
                color: '#FFFFFF',
                weight: 2,
                fillColor: '#D75A4A',
                fillOpacity: 1,
            });
            const details = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = shop.name;
            details.append(title, document.createElement('br'));
            details.append(document.createTextNode(`Location needs review: ${shop.match_status}`));
            marker.bindPopup(details).addTo(markerLayer);
        }
    });
    data.requests.forEach(serviceRequest => {
        const marker = L.circleMarker([serviceRequest.latitude, serviceRequest.longitude], {
            radius: 6,
            color: '#FFFFFF',
            weight: 2,
            fillColor: '#D75A4A',
            fillOpacity: 1,
        });
        const details = document.createElement('div');
        details.textContent = `${serviceRequest.reference} · ${serviceRequest.issue}`;
        marker.bindPopup(details).addTo(markerLayer);
    });
}

function updateCoverage(data) {
    document.getElementById('covered-barangay-count').textContent = data.summary.covered_barangays;
    document.getElementById('total-barangay-count').textContent = data.summary.total_barangays;
    document.getElementById('coverage-percentage').textContent = data.summary.coverage_percent;
    document.querySelector('.coverage-progress').setAttribute('aria-valuenow', data.summary.coverage_percent);
    document.getElementById('coverage-progress-fill').style.width = `${data.summary.coverage_percent}%`;
    const selectedShopCount = data.selected?.shop_count ?? data.summary.registered_shops;
    totalRegisteredShopCount = data.summary.registered_shops;
    const selectedName = data.selected?.name ?? 'All Barangays';
    document.getElementById('selected-barangay-name').textContent = selectedName;
    document.getElementById('selected-barangay-count').textContent = selectedShopCount;
    document.getElementById('selected-shop-summary').textContent = `${selectedShopCount} registered shop(s) in ${selectedName}`;
    pickerTrigger.setAttribute('aria-label', `Filter by barangay. Selected: ${selectedName}, ${selectedShopCount} registered shops.`);
    const needsReview = data.summary.needs_review;
    document.getElementById('geography-review-count').textContent = needsReview;
    document.getElementById('geography-review-item').hidden = needsReview === 0;
    document.getElementById('attention-count').textContent = Number(document.getElementById('attention-count').textContent)
        + needsReview - Number(document.getElementById('geography-review-count').dataset.previous ?? needsReview);
    document.getElementById('geography-review-count').dataset.previous = needsReview;
    const selected = data.selected;
    renderChart(data.chart, selected ? `Shops in ${selected.name}` : 'Shops per Barangay');
}

function renderPickerOptions(search = '') {
    const normalizedSearch = search.trim().toLocaleLowerCase();
    pickerOptions.replaceChildren();
    const allOption = document.createElement('button');
    allOption.type = 'button';
    allOption.className = `barangay-picker-option${selectedBarangayId === null ? ' is-selected' : ''}`;
    allOption.setAttribute('role', 'option');
    allOption.setAttribute('aria-selected', String(selectedBarangayId === null));
    allOption.dataset.barangayId = '';
    const allName = document.createElement('span');
    allName.className = 'barangay-option-name';
    allName.textContent = 'All Barangays';
    const allCount = document.createElement('span');
    allCount.className = 'barangay-option-count';
    allCount.textContent = String(totalRegisteredShopCount);
    allOption.append(allName, allCount);
    allOption.addEventListener('click', () => selectBarangay(null));
    if (!normalizedSearch || 'all barangays'.includes(normalizedSearch)) {
        pickerOptions.append(allOption);
    }

    barangayData.filter(barangay => barangay.name.toLocaleLowerCase().includes(normalizedSearch)).forEach(barangay => {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = `barangay-picker-option${Number(selectedBarangayId) === Number(barangay.id) ? ' is-selected' : ''}`;
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', String(Number(selectedBarangayId) === Number(barangay.id)));
        option.dataset.barangayId = String(barangay.id);
        const name = document.createElement('span');
        name.className = 'barangay-option-name';
        name.textContent = barangay.name;
        const count = document.createElement('span');
        count.className = 'barangay-option-count';
        count.textContent = String(barangay.shop_count);
        option.append(name, count);
        option.addEventListener('click', () => selectBarangay(barangay.id));
        pickerOptions.append(option);
    });

    if (!pickerOptions.childElementCount) {
        const empty = document.createElement('p');
        empty.className = 'barangay-picker-no-results';
        empty.textContent = 'No barangays match your search.';
        pickerOptions.append(empty);
    }
}

function openPicker() {
    picker.classList.add('is-open');
    pickerTrigger.setAttribute('aria-expanded', 'true');
    pickerMenu.setAttribute('aria-hidden', 'false');
    pickerSearch.value = '';
    renderPickerOptions();
    pickerSearch.focus();
}

function closePicker(returnFocus = false) {
    picker.classList.remove('is-open');
    pickerTrigger.setAttribute('aria-expanded', 'false');
    pickerMenu.setAttribute('aria-hidden', 'true');
    if (returnFocus) {
        pickerTrigger.focus();
    }
}

async function selectBarangay(barangayId, closeAfterUpdate = true, updateMapView = true) {
    const requestNumber = ++selectedRequestNumber;
    pickerTrigger.disabled = true;
    pickerStatus.textContent = 'Updating barangay coverage…';
    chartStatus.textContent = '';
    try {
        const url = new URL(geographyUrl, window.location.origin);
        if (barangayId !== null) {
            url.searchParams.set('barangay_id', String(barangayId));
        }
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            throw new Error('The barangay coverage request failed.');
        }
        const data = await response.json();
        if (requestNumber !== selectedRequestNumber) {
            return;
        }
        selectedBarangayId = barangayId;
        barangayData = data.barangays;
        if (!boundaryLayer) {
            initializeMap(data);
        }
        updateCoverage(data);
        renderPickerOptions(pickerSearch.value);
        renderMarkers(data);
        barangayLayers.forEach(setBoundaryStyle);
        const selectedLayer = selectedBarangayId === null ? null : barangayLayers.get(Number(selectedBarangayId));
        if (updateMapView) {
            if (selectedLayer) {
                adminMap.fitBounds(selectedLayer.getBounds(), { padding: [24, 24], maxZoom: 15 });
            } else {
                adminMap.fitBounds(cityBoundaryLayer.getBounds());
            }
        }
        pickerStatus.textContent = 'Official Calapan barangay data loaded.';
        if (closeAfterUpdate) {
            closePicker();
        }
    } catch (error) {
        if (requestNumber === selectedRequestNumber) {
            pickerStatus.textContent = 'Barangay data could not be loaded. Please try again.';
            chartStatus.textContent = 'The coverage chart and map are unavailable until the data can be loaded.';
        }
    } finally {
        if (requestNumber === selectedRequestNumber) {
            pickerTrigger.disabled = false;
        }
    }
}

pickerTrigger.addEventListener('click', () => {
    if (picker.classList.contains('is-open')) {
        closePicker();
    } else {
        openPicker();
    }
});
pickerTrigger.addEventListener('keydown', event => {
    if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openPicker();
    }
});
pickerSearch.addEventListener('input', () => renderPickerOptions(pickerSearch.value));
pickerSearch.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        event.preventDefault();
        closePicker(true);
    }
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        pickerOptions.querySelector('[role="option"]')?.focus();
    }
});
pickerOptions.addEventListener('keydown', event => {
    const options = [...pickerOptions.querySelectorAll('[role="option"]')];
    const currentIndex = options.indexOf(document.activeElement);
    if (event.key === 'Escape') {
        event.preventDefault();
        closePicker(true);
    } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const direction = event.key === 'ArrowDown' ? 1 : -1;
        options[(currentIndex + direction + options.length) % options.length]?.focus();
    } else if (event.key === 'Home') {
        event.preventDefault();
        options[0]?.focus();
    } else if (event.key === 'End') {
        event.preventDefault();
        options.at(-1)?.focus();
    } else if (event.key === 'Tab') {
        closePicker();
    }
});
document.addEventListener('click', event => {
    if (!picker.contains(event.target) && picker.classList.contains('is-open')) {
        closePicker();
    }
});

selectBarangay(null);
window.setInterval(() => {
    if (document.visibilityState === 'visible' && !picker.classList.contains('is-open')) {
        selectBarangay(selectedBarangayId, false, false);
    }
}, 60000);
</script>
@endsection
