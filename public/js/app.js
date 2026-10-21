/**
 * App - Main Controller for OTDR Viewer & Analyzer
 * Coordinates StateManager, TraceChart, API Communication, and UI Components
 */

document.addEventListener('DOMContentLoaded', () => {
    const stateManager = new StateManager();
    const traceChart = new TraceChart('traceChartContainer', 'chartPlaceholder');

    // UI Elements
    const elements = {
        // Project Header
        projectNameDisplay: document.getElementById('projectNameDisplay'),
        btnEditProjectName: document.getElementById('btnEditProjectName'),
        btnExportProject: document.getElementById('btnExportProject'),
        btnImportProject: document.getElementById('btnImportProject'),
        projectFileInput: document.getElementById('projectFileInput'),
        btnResetWorkspace: document.getElementById('btnResetWorkspace'),

        // Upload & Sample
        fileInput: document.getElementById('fileInput'),
        btnBrowseFile: document.getElementById('btnBrowseFile'),
        btnLoadSample: document.getElementById('btnLoadSample'),
        dropZone: document.getElementById('dropZone'),
        uploadProgress: document.getElementById('uploadProgress'),
        uploadProgressBar: document.getElementById('uploadProgressBar'),

        // Sidebar Groups & Traces
        groupListContainer: document.getElementById('groupListContainer'),
        btnAddGroup: document.getElementById('btnAddGroup'),
        totalTracesBadge: document.getElementById('totalTracesBadge'),
        compareModeSwitch: document.getElementById('compareModeSwitch'),

        // Active Trace Metadata & Metrics
        activeTraceFileName: document.getElementById('activeTraceFileName'),
        metricDistance: document.getElementById('metricDistance'),
        metricWavelength: document.getElementById('metricWavelength'),
        metricPulseWidth: document.getElementById('metricPulseWidth'),
        metricIOR: document.getElementById('metricIOR'),
        metricEventsCount: document.getElementById('metricEventsCount'),
        metricDevice: document.getElementById('metricDevice'),
        metaGeneralBody: document.getElementById('metaGeneralBody'),

        // Chart Controls
        btnResetZoom: document.getElementById('btnResetZoom'),
        btnExportChartPng: document.getElementById('btnExportChartPng'),
        chartLegendNotice: document.getElementById('chartLegendNotice'),
        btnUnitKm: document.getElementById('btnUnitKm'),
        btnUnitM: document.getElementById('btnUnitM'),

        // Events Table & Custom Tags
        eventsTableBody: document.getElementById('eventsTableBody'),
        eventsCountBadge: document.getElementById('eventsCountBadge'),
        thEventDistance: document.getElementById('thEventDistance'),
        btnExportCsv: document.getElementById('btnExportCsv'),
        btnPrintReport: document.getElementById('btnPrintReport'),
        txPowerInput: document.getElementById('txPowerInput'),
        rxSensitivityInput: document.getElementById('rxSensitivityInput'),
        powerTotalLoss: document.getElementById('powerTotalLoss'),
        powerRx: document.getElementById('powerRx'),
        powerMargin: document.getElementById('powerMargin'),
        powerStatus: document.getElementById('powerStatus'),
        btnAddCustomTag: document.getElementById('btnAddCustomTag'),
        customTagsContainer: document.getElementById('customTagsContainer'),

        // Modals & Inputs
        modalGroupEl: document.getElementById('modalGroup'),
        groupEditId: document.getElementById('groupEditId'),
        groupNameInput: document.getElementById('groupNameInput'),
        groupDescInput: document.getElementById('groupDescInput'),
        btnSaveGroup: document.getElementById('btnSaveGroup'),
        modalGroupLabel: document.getElementById('modalGroupLabel'),

        modalCustomTagEl: document.getElementById('modalCustomTag'),
        tagDistanceInput: document.getElementById('tagDistanceInput'),
        tagLabelInput: document.getElementById('tagLabelInput'),
        tagTypeSelect: document.getElementById('tagTypeSelect'),
        tagNotesInput: document.getElementById('tagNotesInput'),
        modalTagUnitLabel: document.getElementById('modalTagUnitLabel'),
        btnSaveCustomTag: document.getElementById('btnSaveCustomTag'),

        modalEventTagEl: document.getElementById('modalEventTag'),
        eventTraceId: document.getElementById('eventTraceId'),
        eventIndex: document.getElementById('eventIndex'),
        eventTagLabelInput: document.getElementById('eventTagLabelInput'),
        eventTagNotesInput: document.getElementById('eventTagNotesInput'),
        btnSaveEventTag: document.getElementById('btnSaveEventTag')
    };

    // Safe Bootstrap Modal Instance Getter
    function getModal(el) {
        if (!el) return null;
        try {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                // getOrCreateInstance keeps a single instance (avoids _isShown desync)
                return bootstrap.Modal.getOrCreateInstance(el);
            }
        } catch (e) {
            console.warn('Bootstrap modal init warning:', e);
        }
        return null;
    }

    // Unlock page when no modal is visible anymore
    function cleanupModalState() {
        if (document.querySelector('.modal.show')) return;
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    }

    // Robust modal close: instance hide -> dismiss button -> force cleanup
    function closeModal(el) {
        if (!el) return;

        try {
            const m = getModal(el);
            if (m) m.hide();
        } catch (e) {
            console.warn('Bootstrap modal hide warning:', e);
        }

        // Wait for the hide transition, then verify
        setTimeout(() => {
            if (!el.classList.contains('show')) {
                cleanupModalState();
                return;
            }

            // Fallback 1: trigger the modal's dismiss button
            const dismissBtn = el.querySelector('[data-bs-dismiss="modal"]');
            if (dismissBtn) {
                try { dismissBtn.click(); } catch (e) { /* noop */ }
            }

            setTimeout(() => {
                if (el.classList.contains('show')) {
                    // Fallback 2: force close and drop the stale instance
                    el.classList.remove('show');
                    el.style.display = 'none';
                    el.setAttribute('aria-hidden', 'true');
                    try {
                        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            bootstrap.Modal.getInstance(el)?.dispose();
                        }
                    } catch (e) { /* noop */ }
                }
                cleanupModalState();
            }, 350);
        }, 350);
    }

    function safeRenderAll() {
        try {
            renderAll();
        } catch (e) {
            console.error('Render error:', e);
        }
    }

    // Subscribe to state changes
    stateManager.subscribe(() => {
        safeRenderAll();
    });

    // --- RENDER FUNCTION ---
    function renderAll() {
        const state = stateManager.getState();
        const activeTrace = stateManager.getActiveTrace();

        // 1. Project Info & Unit Toggle
        if (elements.txPowerInput) {
            elements.txPowerInput.value = Number.isFinite(state.txPower) ? state.txPower.toFixed(1) : '';
            elements.txPowerInput.classList.toggle('is-invalid', !Number.isFinite(state.txPower));
        }
        if (elements.rxSensitivityInput) {
            elements.rxSensitivityInput.value = Number.isFinite(state.rxSensitivity) ? state.rxSensitivity.toFixed(1) : '';
            elements.rxSensitivityInput.classList.toggle('is-invalid', !Number.isFinite(state.rxSensitivity));
        }
        if (elements.projectNameDisplay) {
            elements.projectNameDisplay.innerText = state.projectName || 'Proyek Pengukuran OTDR';
        }
        if (elements.totalTracesBadge) {
            elements.totalTracesBadge.innerText = state.traces.length;
        }
        if (elements.compareModeSwitch) {
            elements.compareModeSwitch.checked = state.compareMode;
        }

        // Update Unit Toggle Buttons State
        const unit = stateManager.getDistanceUnit();
        if (elements.btnUnitKm && elements.btnUnitM) {
            const btnBase = 'btn py-1 px-2 fw-semibold text-nowrap d-inline-flex align-items-center justify-content-center';
            if (unit === 'm') {
                elements.btnUnitM.className = `${btnBase} btn-primary active`;
                elements.btnUnitKm.className = `${btnBase} btn-outline-primary`;
            } else {
                elements.btnUnitKm.className = `${btnBase} btn-primary active`;
                elements.btnUnitM.className = `${btnBase} btn-outline-primary`;
            }
        }
        if (elements.thEventDistance) {
            elements.thEventDistance.innerText = unit === 'm' ? 'Jarak (meter)' : 'Jarak (km)';
        }
        if (elements.modalTagUnitLabel) {
            elements.modalTagUnitLabel.innerText = unit;
        }

        // 2. Render Sidebar Groups & Traces
        renderSidebar(state, activeTrace);

        // 3. Render Top Metrics & Metadata Cards
        renderMetadata(activeTrace);

        // 4. Render Chart
        renderChart(state, activeTrace);

        // 5. Render Events Table
        renderEventsTable(activeTrace);
        updatePowerBudgetSummary(activeTrace);

        // 6. Render Custom Tags Badges
        renderCustomTags(activeTrace);
    }

    // --- SIDEBAR RENDERING ---
    function renderSidebar(state, activeTrace) {
        if (!elements.groupListContainer) return;
        elements.groupListContainer.innerHTML = '';

        const ungroupedTraces = stateManager.getUngroupedTraces();

        if (state.traces.length === 0 && state.groups.length === 0) {
            elements.groupListContainer.innerHTML = `
                <div class="text-center text-muted p-3 small">
                    <i class="bi bi-folder2-open d-block fs-3 mb-2 text-secondary"></i>
                    Belum ada trace. Silakan unggah file .SOR atau klik tombol "Muat Sampel".
                </div>
            `;
            return;
        }

        // Helper untuk render item trace
        const renderTraceItemHtml = (tr) => {
            const isActive = activeTrace && tr.id === activeTrace.id;
            const isSelected = state.selectedTraceIds.includes(tr.id);
            const activeClass = isActive ? 'active-trace' : '';
            const distKm = tr.summary?.totalDistanceKm || 0;
            const distFormatted = stateManager.formatDistance(distKm);
            const wl = tr.summary?.wavelength || 1310;

            return `
                <div class="trace-item ${activeClass} d-flex align-items-center justify-content-between p-2 rounded mb-1"
                     data-trace-id="${tr.id}"
                     style="cursor: pointer;"
                     onclick="window.App.selectTrace('${tr.id}')">
                    <div class="d-flex align-items-center flex-grow-1 overflow-hidden me-2">
                        ${state.compareMode ? `
                            <input type="checkbox" class="form-check-input me-2 trace-checkbox"
                                   ${isSelected ? 'checked' : ''}
                                   onclick="event.stopPropagation(); window.App.toggleTraceCompare('${tr.id}')">
                        ` : ''}
                        <i class="bi bi-activity text-primary me-2 flex-shrink-0"></i>
                        <div class="overflow-hidden">
                            <div class="fw-semibold text-truncate small" title="${tr.fileName}">${tr.fileName}</div>
                            <div class="text-muted extra-small">${distFormatted} • ${wl} nm • ${tr.events?.length || 0} ev</div>
                        </div>
                    </div>
                    <div class="dropdown flex-shrink-0" onclick="event.stopPropagation()">
                        <button class="btn btn-sm btn-link text-secondary p-0" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' onclick="event.stopPropagation()">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><h6 class="dropdown-header">Pindahkan Grup</h6></li>
                            ${tr.groupId ? `
                                <li>
                                    <a class="dropdown-item small" href="#" onclick="window.App.moveTrace('${tr.id}', null); return false;">
                                        <i class="bi bi-inbox me-1"></i> Tanpa Grup (Keluarkan)
                                    </a>
                                </li>
                            ` : ''}
                            ${state.groups.map(g => `
                                <li>
                                    <a class="dropdown-item small ${g.id === tr.groupId ? 'disabled fw-bold' : ''}"
                                       href="#" onclick="window.App.moveTrace('${tr.id}', '${g.id}'); return false;">
                                        <i class="bi bi-folder me-1"></i> ${g.name}
                                    </a>
                                </li>
                            `).join('')}
                            ${state.groups.length === 0 && !tr.groupId ? `
                                <li><span class="dropdown-item small text-muted italic">Buat grup baru dulu</span></li>
                            ` : ''}
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item small text-danger" href="#" onclick="window.App.deleteTrace('${tr.id}'); return false;">
                                    <i class="bi bi-trash"></i> Hapus Trace
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            `;
        };

        // 1. Render Seksi "Tanpa Grup"
        if (ungroupedTraces.length > 0 || state.groups.length > 0) {
            const ungroupedCard = document.createElement('div');
            ungroupedCard.className = 'group-card mb-2';

            let ungroupedHtml = '';
            if (ungroupedTraces.length === 0) {
                ungroupedHtml = `<div class="p-2 text-muted extra-small text-center italic">Tidak ada trace tanpa grup.</div>`;
            } else {
                ungroupedTraces.forEach(tr => {
                    ungroupedHtml += renderTraceItemHtml(tr);
                });
            }

            ungroupedCard.innerHTML = `
                <div class="group-header d-flex align-items-center justify-content-between p-2 rounded bg-light border">
                    <div class="d-flex align-items-center overflow-hidden">
                        <i class="bi bi-inboxes text-secondary me-2"></i>
                        <span class="fw-bold small text-truncate text-secondary">Tanpa Grup</span>
                        <span class="badge bg-secondary ms-2">${ungroupedTraces.length}</span>
                    </div>
                </div>
                <div class="group-body ps-2 pe-1 pt-1">
                    ${ungroupedHtml}
                </div>
            `;
            elements.groupListContainer.appendChild(ungroupedCard);
        }

        // 2. Render Grup Kustom Pengguna
        state.groups.forEach(group => {
            const groupTraces = stateManager.getTracesByGroup(group.id);
            const groupCard = document.createElement('div');
            groupCard.className = 'group-card mb-2';

            let tracesHtml = '';
            if (groupTraces.length === 0) {
                tracesHtml = `<div class="p-2 text-muted extra-small text-center italic">Belum ada trace di grup ini. Pindahkan trace ke sini via menu titik tiga.</div>`;
            } else {
                groupTraces.forEach(tr => {
                    tracesHtml += renderTraceItemHtml(tr);
                });
            }

            groupCard.innerHTML = `
                <div class="group-header d-flex align-items-center justify-content-between p-2 rounded bg-light border">
                    <div class="d-flex align-items-center overflow-hidden">
                        <i class="bi bi-folder-fill text-warning me-2"></i>
                        <span class="fw-bold small text-truncate" title="${group.name}">${group.name}</span>
                        <span class="badge bg-primary ms-2">${groupTraces.length}</span>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-link text-secondary p-0" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}'>
                            <i class="bi bi-gear-fill"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li>
                                <a class="dropdown-item small" href="#" onclick="window.App.editGroup('${group.id}', '${group.name}'); return false;">
                                    <i class="bi bi-pencil"></i> Ubah Nama
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item small text-danger" href="#" onclick="window.App.deleteGroup('${group.id}'); return false;">
                                    <i class="bi bi-trash"></i> Hapus Grup
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="group-body ps-2 pe-1 pt-1">
                    ${tracesHtml}
                </div>
            `;

            elements.groupListContainer.appendChild(groupCard);
        });
    }

    // --- METADATA & METRICS RENDERING ---
    function renderMetadata(activeTrace) {
        if (!activeTrace) {
            if (elements.activeTraceFileName) elements.activeTraceFileName.innerText = 'Tidak Ada File Aktif';
            if (elements.metricDistance) elements.metricDistance.innerText = '-';
            if (elements.metricWavelength) elements.metricWavelength.innerText = '-';
            if (elements.metricPulseWidth) elements.metricPulseWidth.innerText = '-';
            if (elements.metricIOR) elements.metricIOR.innerText = '-';
            if (elements.metricEventsCount) elements.metricEventsCount.innerText = '-';
            if (elements.metricDevice) elements.metricDevice.innerText = '-';
            if (elements.metaGeneralBody) elements.metaGeneralBody.innerHTML = '<tr><td colspan="2" class="text-center text-muted">Belum ada data</td></tr>';
            return;
        }

        const sum = activeTrace.summary || {};
        const meta = activeTrace.metadata || {};

        if (elements.activeTraceFileName) elements.activeTraceFileName.innerText = activeTrace.fileName;
        if (elements.metricDistance) elements.metricDistance.innerText = stateManager.formatDistance(sum.totalDistanceKm || 0);
        if (elements.metricWavelength) elements.metricWavelength.innerText = `${sum.wavelength || '-'} nm`;
        if (elements.metricPulseWidth) elements.metricPulseWidth.innerText = `${sum.pulseWidth || '-'} ns`;
        if (elements.metricIOR) elements.metricIOR.innerText = sum.ior || '-';
        if (elements.metricEventsCount) elements.metricEventsCount.innerText = activeTrace.events?.length || 0;
        if (elements.metricDevice) elements.metricDevice.innerText = `${sum.supplier || ''} ${sum.model || ''}`;

        // Detailed Metadata Tab
        if (elements.metaGeneralBody) {
            const gen = meta.general || {};
            const sup = meta.supplier || {};
            const fxd = meta.fixed || {};

            elements.metaGeneralBody.innerHTML = `
                <tr><th class="w-50 text-secondary">Nama File</th><td>${activeTrace.fileName}</td></tr>
                <tr><th class="text-secondary">Waktu Uji</th><td>${fxd.dateTimeFormatted || sum.dateTime || '-'}</td></tr>
                <tr><th class="text-secondary">Perangkat / OTDR</th><td>${sup.supplier || '-'} ${sup.model || '-'} (SN: ${sup.serialNumber || '-'})</td></tr>
                <tr><th class="text-secondary">Versi Software OTDR</th><td>${sup.softwareVersion || '-'}</td></tr>
                <tr><th class="text-secondary">Kabel ID / Core</th><td>${gen.cableId || '-'} / ${gen.fiberId || '-'}</td></tr>
                <tr><th class="text-secondary">Lokasi Awal - Akhir</th><td>${gen.origin || '-'} → ${gen.destination || '-'}</td></tr>
                <tr><th class="text-secondary">Panjang Gelombang</th><td>${sum.wavelength || '-'} nm</td></tr>
                <tr><th class="text-secondary">Pulse Width</th><td>${sum.pulseWidth || '-'} ns</td></tr>
                <tr><th class="text-secondary">Indeks Bias (IOR)</th><td>${sum.ior || '-'}</td></tr>
                <tr><th class="text-secondary">Sample Spacing</th><td>${fxd.sampleSpacingMeters || '-'} m (${fxd.sampleSpacingRaw || '-'} raw)</td></tr>
                <tr><th class="text-secondary">Total Titik Data</th><td>${fxd.totalDataPts || activeTrace.dataPoints?.totalPoints || 0} titik</td></tr>
                <tr><th class="text-secondary">Backscatter Coeff</th><td>${fxd.backscatter || '-'} dB</td></tr>
            `;
        }
    }

    // --- CHART RENDERING ---
    function renderChart(state, activeTrace) {
        const unit = stateManager.getDistanceUnit();
        if (state.compareMode && state.selectedTraceIds.length > 0) {
            const compareTraces = state.traces.filter(t => state.selectedTraceIds.includes(t.id));
            traceChart.render(activeTrace, compareTraces, unit, stateManager.getTxPower());
            if (elements.chartLegendNotice) {
                elements.chartLegendNotice.classList.remove('d-none');
                elements.chartLegendNotice.innerText = `Mode Komparasi Aktif (${compareTraces.length} trace dipilih)`;
            }
        } else {
            traceChart.render(activeTrace, [], unit, stateManager.getTxPower());
            if (elements.chartLegendNotice) {
                elements.chartLegendNotice.classList.add('d-none');
            }
        }
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
    }

    // --- EVENTS TABLE RENDERING ---
    function renderEventsTable(activeTrace) {
        if (!elements.eventsTableBody) return;
        elements.eventsTableBody.innerHTML = '';
        const events = activeTrace?.events || [];
        if (elements.eventsCountBadge) elements.eventsCountBadge.innerText = events.length;
        if (events.length === 0) {
            elements.eventsTableBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Tidak ada data event pada trace ini.</td></tr>';
            return;
        }
        const txPower = stateManager.getTxPower();
        const rxSensitivity = stateManager.getRxSensitivity();
        const unit = stateManager.getDistanceUnit();
        events.forEach((ev, idx) => {
            const type = ev.type || '';
            const isEnd = type.includes('End');
            const remaining = txPower - (ev.cumulativeLossDb || 0);
            const tr = document.createElement('tr');
            tr.className = isEnd ? 'table-danger-light' : '';
            tr.style.cursor = 'pointer';
            tr.innerHTML = `<td class="fw-bold text-center">${escapeHtml(ev.eventNumber)}</td><td class="fw-semibold text-primary">${escapeHtml(stateManager.formatDistance(ev.distanceKm))}</td><td><span class="badge ${isEnd ? 'bg-danger' : (type.includes('Launch') ? 'bg-info text-dark' : 'bg-primary')}">${escapeHtml(type)}</span> <span class="extra-small text-muted">(${escapeHtml(ev.eventCode)})</span></td><td>${ev.customTag ? `<span class="badge bg-success-subtle text-success border border-success-subtle">📍 ${escapeHtml(ev.customTag)}</span>` : '<span class="text-muted extra-small italic">Belum ditandai</span>'}</td><td class="text-end">${ev.eventLossDb !== 0 ? escapeHtml(ev.eventLossDb) + ' dB' : '-'}</td><td class="text-end">${ev.reflectanceDb !== 0 ? escapeHtml(ev.reflectanceDb) + ' dB' : '-'}</td><td class="text-end">${ev.slopeDbKm !== 0 ? escapeHtml(ev.slopeDbKm) + ' dB/km' : '-'}</td><td class="text-end fw-semibold">${escapeHtml(ev.cumulativeLossDb || 0)} dB</td><td class="text-end fw-bold ${remaining >= rxSensitivity ? 'text-success' : 'text-danger'}">${remaining.toFixed(3)} dBm</td><td class="text-center"></td>`;
            const actionCell = tr.lastElementChild;
            const button = document.createElement('button');
            button.className = 'btn btn-sm btn-outline-secondary py-0 px-1';
            button.title = 'Beri Label / Tag Titik';
            button.innerHTML = '<i class="bi bi-tag-fill"></i> Tag';
            button.addEventListener('click', event => {
                event.stopPropagation();
                window.App.openEventTagModal(activeTrace.id, idx, ev.customTag || '', ev.notes || '');
            });
            actionCell.appendChild(button);
            tr.addEventListener('click', () => traceChart.focusAt(ev.distanceKm, unit));
            elements.eventsTableBody.appendChild(tr);
        });
    }

    function updatePowerBudgetSummary(activeTrace) {
        const validInputs = stateManager.hasValidPowerBudget();
        if (!activeTrace || !validInputs) {
            [elements.powerTotalLoss, elements.powerRx, elements.powerMargin].forEach(el => { if (el) el.textContent = '-'; });
            if (elements.powerStatus) { elements.powerStatus.textContent = validInputs ? 'No Trace' : 'INVALID'; elements.powerStatus.className = 'badge bg-secondary'; }
            return;
        }
        const summaryLoss = activeTrace.summary?.totalLossDb;
        const eventLosses = Array.isArray(activeTrace.events)
            ? activeTrace.events.map(event => event?.cumulativeLossDb).filter(loss => typeof loss === 'number' && Number.isFinite(loss))
            : [];
        const totalLoss = typeof summaryLoss === 'number' && Number.isFinite(summaryLoss)
            ? summaryLoss
            : eventLosses.length > 0 ? eventLosses[eventLosses.length - 1] : null;
        if (totalLoss === null) {
            [elements.powerTotalLoss, elements.powerRx, elements.powerMargin].forEach(el => { if (el) el.textContent = '-'; });
            if (elements.powerStatus) { elements.powerStatus.textContent = 'TIDAK TERSEDIA'; elements.powerStatus.className = 'badge bg-secondary'; }
            return;
        }
        const rx = stateManager.getTxPower() - totalLoss;
        const margin = rx - stateManager.getRxSensitivity();
        if (elements.powerTotalLoss) elements.powerTotalLoss.textContent = `${totalLoss.toFixed(3)} dB`;
        if (elements.powerRx) elements.powerRx.textContent = `${rx.toFixed(3)} dBm`;
        if (elements.powerMargin) elements.powerMargin.textContent = `${margin.toFixed(3)} dB`;
        if (elements.powerStatus) { elements.powerStatus.textContent = margin >= 0 ? 'PASS' : 'FAIL'; elements.powerStatus.className = `badge ${margin >= 0 ? 'bg-success' : 'bg-danger'}`; }
    }

    // --- CUSTOM TAGS RENDERING ---
    function renderCustomTags(activeTrace) {
        if (!elements.customTagsContainer) return;
        elements.customTagsContainer.innerHTML = '';

        if (!activeTrace || !activeTrace.customTags || activeTrace.customTags.length === 0) {
            elements.customTagsContainer.innerHTML = `
                <div class="text-muted small italic p-2">Belum ada custom marker pada trace ini. Klik "Tambah Marker Titik" untuk menandai ODP, Sambungan, Slack, dll.</div>
            `;
            return;
        }

        activeTrace.customTags.forEach(tag => {
            const badge = document.createElement('div');
            badge.className = 'custom-marker-chip d-inline-flex align-items-center bg-white border rounded shadow-sm px-2 py-1 me-2 mb-2';

            const typeIcons = {
                'odp': 'bi-hdd-network text-success',
                'joint_closure': 'bi-tools text-primary',
                'otb': 'bi-server text-purple',
                'slack': 'bi-arrow-repeat text-warning',
                'bending': 'bi-exclamation-triangle text-danger',
                'other': 'bi-geo-alt text-secondary'
            };
            const iconClass = typeIcons[tag.type] || 'bi-geo-alt text-secondary';

            const icon = document.createElement('i');
            icon.className = `bi ${iconClass} me-1`;
            icon.style.fontSize = '1rem';
            const details = document.createElement('div');
            details.className = 'me-2';
            details.setAttribute('role', 'button');
            details.tabIndex = 0;
            const label = document.createElement('span');
            label.className = 'fw-bold small';
            label.textContent = tag.label ?? '';
            const distance = document.createElement('span');
            distance.className = 'badge bg-light text-dark border ms-1 extra-small';
            distance.textContent = stateManager.formatDistance(tag.distanceKm);
            details.append(label, distance);
            details.addEventListener('click', () => window.App.focusChart(tag.distanceKm));
            details.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    window.App.focusChart(tag.distanceKm);
                }
            });
            const deleteButton = document.createElement('button');
            deleteButton.className = 'btn btn-sm btn-link text-danger p-0 ms-1';
            deleteButton.title = 'Hapus marker';
            deleteButton.innerHTML = '<i class="bi bi-x-circle"></i>';
            deleteButton.addEventListener('click', () => window.App.removeCustomTag(activeTrace.id, tag.id));
            badge.append(icon, details, deleteButton);
            elements.customTagsContainer.appendChild(badge);
        });
    }

    // --- FILE UPLOAD LOGIC ---
    function uploadFiles(fileList) {
        if (!fileList || fileList.length === 0) return;

        const formData = new FormData();
        let validCount = 0;

        for (let i = 0; i < fileList.length; i++) {
            const file = fileList[i];
            if (file.name.toLowerCase().endsWith('.sor')) {
                formData.append('files[]', file);
                validCount++;
            }
        }

        if (validCount === 0) {
            alert('Hanya file dengan ekstensi biner .sor yang diperbolehkan.');
            return;
        }

        // Show progress bar
        if (elements.uploadProgress) elements.uploadProgress.classList.remove('d-none');
        if (elements.uploadProgressBar) elements.uploadProgressBar.style.width = '45%';

        fetch('/api/otdr/parse', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (elements.uploadProgressBar) elements.uploadProgressBar.style.width = '100%';
            setTimeout(() => {
                if (elements.uploadProgress) elements.uploadProgress.classList.add('d-none');
                if (elements.uploadProgressBar) elements.uploadProgressBar.style.width = '0%';
            }, 500);

            if (res.success && res.traces && res.traces.length > 0) {
                let lastTrace = null;
                res.traces.forEach(traceData => {
                    lastTrace = stateManager.addTrace(traceData);
                });

                // Ensure the newest trace is active and the curve draws immediately
                // (first upload happens on an empty workspace, no F5 needed)
                if (lastTrace && lastTrace.id) {
                    stateManager.setActiveTrace(lastTrace.id);
                }
                safeRenderAll();

                if (res.warnings && res.warnings.length > 0) {
                    alert('Sebagian file diproses dengan catatan:\n' + res.warnings.join('\n'));
                }
            } else {
                alert('Gagal memproses file: ' + (res.message || 'Format tidak valid.'));
            }
        })
        .catch(err => {
            if (elements.uploadProgress) elements.uploadProgress.classList.add('d-none');
            alert('Terjadi kesalahan jaringan saat parsing file OTDR: ' + err.message);
        });
    }

    // --- EVENT LISTENERS ---

    // File input trigger
    if (elements.btnBrowseFile) {
        elements.btnBrowseFile.addEventListener('click', () => {
            elements.fileInput?.click();
        });
    }

    if (elements.fileInput) {
        elements.fileInput.addEventListener('change', (e) => {
            uploadFiles(e.target.files);
            e.target.value = ''; // reset
        });
    }

    // Drag and Drop
    if (elements.dropZone) {
        ['dragenter', 'dragover'].forEach(eventName => {
            elements.dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                elements.dropZone.classList.add('drag-active');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            elements.dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                elements.dropZone.classList.remove('drag-active');
            }, false);
        });

        elements.dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            uploadFiles(files);
        });
    }

    // Load Sample Button
    if (elements.btnLoadSample) {
        elements.btnLoadSample.addEventListener('click', () => {
            elements.btnLoadSample.disabled = true;
            elements.btnLoadSample.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memuat...';

            fetch('/api/otdr/sample')
                .then(res => res.json())
                .then(res => {
                    elements.btnLoadSample.disabled = false;
                    elements.btnLoadSample.innerHTML = '<i class="bi bi-file-earmark-play-fill text-warning me-1"></i> Muat Sampel (Doc/kabel1 cbd c1.sor)';

                    if (res.success && res.trace) {
                        stateManager.addTrace(res.trace);
                    } else {
                        alert(res.message || 'Gagal memuat sampel.');
                    }
                })
                .catch(err => {
                    elements.btnLoadSample.disabled = false;
                    elements.btnLoadSample.innerHTML = '<i class="bi bi-file-earmark-play-fill text-warning me-1"></i> Muat Sampel (Doc/kabel1 cbd c1.sor)';
                    alert('Gagal mengambil file sampel: ' + err.message);
                });
        });
    }

    // Optical Power Budget Inputs
    if (elements.txPowerInput) {
        elements.txPowerInput.addEventListener('input', (e) => stateManager.setTxPower(e.target.value));
    }
    if (elements.rxSensitivityInput) {
        elements.rxSensitivityInput.addEventListener('input', (e) => stateManager.setRxSensitivity(e.target.value));
    }

    // Distance Unit Toggle Handlers (km / meter)
    if (elements.btnUnitKm) {
        elements.btnUnitKm.addEventListener('click', () => {
            stateManager.setDistanceUnit('km');
        });
    }

    if (elements.btnUnitM) {
        elements.btnUnitM.addEventListener('click', () => {
            stateManager.setDistanceUnit('m');
        });
    }

    // Chart Controls
    if (elements.btnResetZoom) {
        elements.btnResetZoom.addEventListener('click', () => {
            traceChart.resetZoom(stateManager.getActiveTrace(), stateManager.getDistanceUnit());
            const activeTrace = stateManager.getActiveTrace();
            const state = stateManager.getState();
            const compareTraces = state.compareMode ? state.traces.filter(t => state.selectedTraceIds.includes(t.id)) : [];
            traceChart.render(activeTrace, compareTraces, stateManager.getDistanceUnit(), stateManager.getTxPower());
        });
    }

    if (elements.btnExportChartPng) {
        elements.btnExportChartPng.addEventListener('click', () => {
            const tr = stateManager.getActiveTrace();
            traceChart.exportImage(tr?.fileName || 'otdr_chart.png');
        });
    }

    // Compare Mode Switch
    if (elements.compareModeSwitch) {
        elements.compareModeSwitch.addEventListener('change', (e) => {
            stateManager.setCompareMode(e.target.checked);
        });
    }

    // Project Name Edit
    if (elements.btnEditProjectName) {
        elements.btnEditProjectName.addEventListener('click', () => {
            const current = stateManager.getState().projectName;
            const newName = prompt('Ubah Nama Proyek:', current);
            if (newName && newName.trim()) {
                stateManager.setProjectName(newName.trim());
            }
        });
    }

    // Export Project
    if (elements.btnExportProject) {
        elements.btnExportProject.addEventListener('click', () => {
            stateManager.exportProject();
        });
    }

    // Import Project
    if (elements.btnImportProject) {
        elements.btnImportProject.addEventListener('click', () => {
            elements.projectFileInput?.click();
        });
    }

    if (elements.projectFileInput) {
        elements.projectFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = (event) => {
                const res = stateManager.importProject(event.target.result);
                if (res.success) {
                    alert(`Proyek berhasil dipulihkan! ${res.count} trace dimuat.`);
                } else {
                    alert('Gagal mengimpor file project: ' + res.error);
                }
            };
            reader.readAsText(file);
            e.target.value = '';
        });
    }

    // Reset Workspace
    if (elements.btnResetWorkspace) {
        elements.btnResetWorkspace.addEventListener('click', () => {
            if (confirm('Apakah Anda yakin ingin mengosongkan workspace? Seluruh trace dan tag yang belum diekspor akan terhapus.')) {
                stateManager.resetWorkspace();
            }
        });
    }

    // Add Group Button Modal Trigger
    if (elements.btnAddGroup) {
        elements.btnAddGroup.addEventListener('click', () => {
            if (elements.groupEditId) elements.groupEditId.value = '';
            if (elements.modalGroupLabel) elements.modalGroupLabel.innerText = 'Tambah Grup Baru';
            if (elements.groupNameInput) elements.groupNameInput.value = '';
            if (elements.groupDescInput) elements.groupDescInput.value = '';
            const m = getModal(elements.modalGroupEl);
            if (m) {
                m.show();
                setTimeout(() => elements.groupNameInput?.focus(), 400);
            }
        });
    }

    // Save Group Handler
    if (elements.btnSaveGroup) {
        elements.btnSaveGroup.addEventListener('click', () => {
            const name = elements.groupNameInput?.value?.trim();
            if (!name) {
                alert('Nama grup tidak boleh kosong.');
                elements.groupNameInput?.focus();
                return;
            }
            const desc = elements.groupDescInput?.value?.trim() || '';
            const editId = elements.groupEditId?.value;

            if (editId) {
                stateManager.updateGroup(editId, name, desc);
            } else {
                stateManager.createGroup(name, desc);
            }

            closeModal(elements.modalGroupEl);
        });

        // Enter key to save
        elements.groupNameInput?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                elements.btnSaveGroup.click();
            }
        });
    }

    // Export CSV
    if (elements.btnExportCsv) {
        elements.btnExportCsv.addEventListener('click', () => {
            const tr = stateManager.getActiveTrace();
            if (!tr || !tr.events || tr.events.length === 0) {
                alert('Belum ada trace atau event untuk diekspor ke CSV.');
                return;
            }

            const unit = stateManager.getDistanceUnit();
            const factor = unit === 'm' ? 1000 : 1;
            const txPower = stateManager.getTxPower();
            let csv = `No,Jarak (${unit}),Tipe Event,Event Code,Custom Tag,Event Loss (dB),Reflectance (dB),Slope (dB/km),Kumulatif Loss (dB),Sisa Daya (dBm),Catatan\n`;
            tr.events.forEach(ev => {
                const distVal = Math.round(ev.distanceKm * factor * 100) / 100;
                const remainingPower = txPower - (ev.cumulativeLossDb || 0);
                csv += `"${ev.eventNumber}","${distVal}","${ev.type}","${ev.eventCode}","${ev.customTag || ''}","${ev.eventLossDb}","${ev.reflectanceDb}","${ev.slopeDbKm}","${ev.cumulativeLossDb}","${remainingPower.toFixed(3)}","${ev.notes || ''}"\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${tr.fileName.replace(/\.[^/.]+$/, "")}_events.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    }

    // Print / PDF Report
    if (elements.btnPrintReport) {
        elements.btnPrintReport.addEventListener('click', () => {
            window.print();
        });
    }

    // Add Custom Tag Marker Modal Trigger
    if (elements.btnAddCustomTag) {
        elements.btnAddCustomTag.addEventListener('click', () => {
            const activeTrace = stateManager.getActiveTrace();
            if (!activeTrace) {
                alert('Pilih trace terlebih dahulu untuk menambahkan marker titik.');
                return;
            }

            const unit = stateManager.getDistanceUnit();
            if (elements.modalTagUnitLabel) elements.modalTagUnitLabel.innerText = unit;
            if (elements.tagDistanceInput) {
                elements.tagDistanceInput.value = unit === 'm' ? '500' : '0.500';
            }
            if (elements.tagLabelInput) elements.tagLabelInput.value = '';
            if (elements.tagTypeSelect) elements.tagTypeSelect.value = 'odp';
            if (elements.tagNotesInput) elements.tagNotesInput.value = '';

            const m = getModal(elements.modalCustomTagEl);
            if (m) {
                m.show();
                setTimeout(() => elements.tagLabelInput?.focus(), 400);
            }
        });
    }

    // Save Custom Tag Handler
    if (elements.btnSaveCustomTag) {
        elements.btnSaveCustomTag.addEventListener('click', () => {
            const activeTrace = stateManager.getActiveTrace();
            if (!activeTrace) return;

            const unit = stateManager.getDistanceUnit();
            const rawDist = parseFloat(elements.tagDistanceInput?.value);
            if (isNaN(rawDist) || rawDist < 0) {
                alert(`Jarak titik harus berupa angka positif (${unit}).`);
                elements.tagDistanceInput?.focus();
                return;
            }

            // Selalu simpan basis km secara internal
            const distKm = unit === 'm' ? rawDist / 1000 : rawDist;

            const label = elements.tagLabelInput?.value?.trim();
            if (!label) {
                alert('Nama / label marker tidak boleh kosong.');
                elements.tagLabelInput?.focus();
                return;
            }

            const type = elements.tagTypeSelect?.value || 'odp';
            const notes = elements.tagNotesInput?.value?.trim() || '';

            stateManager.addCustomTag(activeTrace.id, {
                distanceKm: distKm,
                label: label,
                notes: notes,
                type: type
            });

            closeModal(elements.modalCustomTagEl);
        });
    }

    // Save Event Tag Handler
    if (elements.btnSaveEventTag) {
        elements.btnSaveEventTag.addEventListener('click', () => {
            const traceId = elements.eventTraceId?.value;
            const eventIndex = parseInt(elements.eventIndex?.value);
            const tag = elements.eventTagLabelInput?.value?.trim() || '';
            const notes = elements.eventTagNotesInput?.value?.trim() || '';

            if (traceId !== null && !isNaN(eventIndex)) {
                stateManager.updateEventTag(traceId, eventIndex, tag, notes);
            }

            closeModal(elements.modalEventTagEl);
        });
    }

    // --- GLOBAL HELPER FOR INLINE ACTIONS ---
    window.App = {
        selectTrace: (traceId) => {
            stateManager.setActiveTrace(traceId);
        },
        toggleTraceCompare: (traceId) => {
            stateManager.toggleTraceSelection(traceId);
        },
        deleteTrace: (traceId) => {
            if (confirm('Hapus trace ini dari workspace?')) {
                stateManager.removeTrace(traceId);
            }
        },
        moveTrace: (traceId, newGroupId) => {
            stateManager.moveTrace(traceId, newGroupId);
        },
        editGroup: (groupId, currentName) => {
            const grp = stateManager.getState().groups.find(g => g.id === groupId);
            if (elements.groupEditId) elements.groupEditId.value = groupId;
            if (elements.modalGroupLabel) elements.modalGroupLabel.innerText = 'Ubah Nama Grup';
            if (elements.groupNameInput) elements.groupNameInput.value = currentName || grp?.name || '';
            if (elements.groupDescInput) elements.groupDescInput.value = grp?.description || '';

            const m = getModal(elements.modalGroupEl);
            if (m) {
                m.show();
                setTimeout(() => elements.groupNameInput?.focus(), 400);
            }
        },
        deleteGroup: (groupId) => {
            if (confirm('Hapus grup ini? Seluruh trace di dalamnya akan dipindahkan ke Tanpa Grup.')) {
                stateManager.deleteGroup(groupId);
            }
        },
        focusChart: (distKm) => {
            traceChart.focusAt(distKm, stateManager.getDistanceUnit());
        },
        removeCustomTag: (traceId, tagId) => {
            if (confirm('Hapus penanda ini?')) {
                stateManager.removeCustomTag(traceId, tagId);
            }
        },
        openEventTagModal: (traceId, eventIndex, currentTag, currentNotes) => {
            if (elements.eventTraceId) elements.eventTraceId.value = traceId;
            if (elements.eventIndex) elements.eventIndex.value = eventIndex;
            if (elements.eventTagLabelInput) elements.eventTagLabelInput.value = currentTag || '';
            if (elements.eventTagNotesInput) elements.eventTagNotesInput.value = currentNotes || '';

            const m = getModal(elements.modalEventTagEl);
            if (m) {
                m.show();
                setTimeout(() => elements.eventTagLabelInput?.focus(), 400);
            }
        }
    };

    // Run Initial Safe Render
    safeRenderAll();
});
