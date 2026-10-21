/**
 * StateManager - Zero-Database Client-Side Workspace State Manager
 * Handles Groups, Traces, Custom Tags, LocalStorage Persistence, and Project Export/Import (.otdrproj)
 */

class StateManager {
    constructor() {
        this.STORAGE_KEY = 'OTDR_WORKSPACE_STATE_V1';
        this.state = this.loadState();
        this.listeners = [];
    }

    getDefaultState() {
        return {
            version: '1.0',
            projectName: 'Proyek Pengukuran OTDR',
            createdAt: new Date().toISOString(),
            activeTraceId: null,
            distanceUnit: 'km', // 'km' atau 'm'
            compareMode: false,
            selectedTraceIds: [],
            txPower: 7.0,
            rxSensitivity: -27.0,
            groups: [],
            traces: []
        };
    }

    loadState() {
        try {
            const raw = localStorage.getItem(this.STORAGE_KEY);
            if (!raw) return this.getDefaultState();
            const parsed = JSON.parse(raw);
            if (!Array.isArray(parsed.traces) || !Array.isArray(parsed.groups)) return this.getDefaultState();
            parsed.distanceUnit = parsed.distanceUnit === 'm' ? 'm' : 'km';
            parsed.txPower = Number.isFinite(parsed.txPower) ? parsed.txPower : 7.0;
            parsed.rxSensitivity = Number.isFinite(parsed.rxSensitivity) ? parsed.rxSensitivity : -27.0;

            // Migrasi otomatis: ubah 'grp-default' ("Grup Utama") menjadi null ("Tanpa Grup")
            parsed.groups = parsed.groups.filter(g => g.id !== 'grp-default');
            parsed.traces.forEach(t => {
                if (t.groupId === 'grp-default') {
                    t.groupId = null;
                }
            });

            return parsed;
        } catch (e) {
            console.error('Gagal memuat state dari LocalStorage:', e);
            return this.getDefaultState();
        }
    }

    saveState() {
        try {
            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(this.state));
        } catch (e) {
            console.warn('LocalStorage penuh, menyimpan versi ringkas (compact state):', e);
            try {
                // Jika quota 5MB browser terlampaui, simpan tanpa membebani points mentah di localStorage
                const compactState = {
                    ...this.state,
                    traces: this.state.traces.map(t => ({
                        ...t,
                        dataPoints: {
                            totalPoints: t.dataPoints?.totalPoints || 0,
                            scaleFactor: t.dataPoints?.scaleFactor || 1000,
                            spacingKm: t.dataPoints?.spacingKm || 0,
                            points: (t.dataPoints?.points || []).filter((_, idx) => idx % 4 === 0)
                        }
                    }))
                };
                localStorage.setItem(this.STORAGE_KEY, JSON.stringify(compactState));
            } catch (err2) {
                console.warn('Gagal menyimpan ke LocalStorage:', err2);
            }
        }
        // Selalu panggil notify agar UI terupdate tanpa terhalang error storage
        this.notify();
    }

    subscribe(callback) {
        this.listeners.push(callback);
    }

    notify() {
        this.listeners.forEach(cb => cb(this.state));
    }

    getState() {
        return this.state;
    }

    // --- PROJECT PERSISTENCE ---

    setProjectName(name) {
        this.state.projectName = name;
        this.saveState();
    }

    setDistanceUnit(unit) {
        this.state.distanceUnit = unit === 'm' ? 'm' : 'km';
        this.saveState();
    }

    getDistanceUnit() {
        return this.state.distanceUnit || 'km';
    }

    getTxPower() {
        return Number.isFinite(this.state.txPower) ? this.state.txPower : 0;
    }

    setTxPower(val) {
        const parsed = typeof val === 'number' || (typeof val === 'string' && val.trim() !== '') ? Number(val) : NaN;
        this.state.txPower = Number.isFinite(parsed) ? parsed : null;
        this.saveState();
    }

    getRxSensitivity() {
        return Number.isFinite(this.state.rxSensitivity) ? this.state.rxSensitivity : 0;
    }

    setRxSensitivity(val) {
        const parsed = typeof val === 'number' || (typeof val === 'string' && val.trim() !== '') ? Number(val) : NaN;
        this.state.rxSensitivity = Number.isFinite(parsed) ? parsed : null;
        this.saveState();
    }

    hasValidPowerBudget() {
        return Number.isFinite(this.state.txPower) && Number.isFinite(this.state.rxSensitivity);
    }

    formatDistance(distKm, showUnit = true) {
        const d = parseFloat(distKm) || 0;
        const unit = this.getDistanceUnit();
        if (unit === 'm') {
            const val = Math.round(d * 1000 * 10) / 10;
            return showUnit ? `${val} m` : val;
        }
        const val = Math.round(d * 10000) / 10000;
        return showUnit ? `${val} km` : val;
    }

    resetWorkspace() {
        this.state = this.getDefaultState();
        this.saveState();
    }

    exportProject() {
        const payload = JSON.stringify(this.state, null, 2);
        const blob = new Blob([payload], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        const safeName = (this.state.projectName || 'otdr-project').replace(/[^a-zA-Z0-9_-]/g, '_');
        a.href = url;
        a.download = `${safeName}.otdrproj`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    importProject(jsonString) {
        try {
            const data = JSON.parse(jsonString);
            if (!Array.isArray(data.traces)) {
                throw new Error('Format project tidak valid: atribut traces tidak ditemukan.');
            }
            data.groups = Array.isArray(data.groups) ? data.groups : [];
            data.distanceUnit = data.distanceUnit === 'm' ? 'm' : 'km';
            data.txPower = typeof data.txPower === 'number' && !isNaN(data.txPower) ? data.txPower : 7.0;
            data.rxSensitivity = typeof data.rxSensitivity === 'number' && !isNaN(data.rxSensitivity) ? data.rxSensitivity : -27.0;
            data.selectedTraceIds = Array.isArray(data.selectedTraceIds) ? data.selectedTraceIds : [];
            data.compareMode = data.compareMode === true;
            data.traces = data.traces.filter(trace => trace && typeof trace === 'object').map(trace => ({
                ...trace,
                events: Array.isArray(trace.events) ? trace.events : [],
                groups: Array.isArray(trace.groups) ? trace.groups : [],
                dataPoints: trace.dataPoints && typeof trace.dataPoints === 'object' ? trace.dataPoints : { points: [] },
                customTags: Array.isArray(trace.customTags) ? trace.customTags : []
            }));
            this.state = { ...this.getDefaultState(), ...data };
            if (!this.state.activeTraceId && this.state.traces.length > 0) {
                this.state.activeTraceId = this.state.traces[0].id;
            }
            this.saveState();
            return { success: true, count: this.state.traces.length };
        } catch (e) {
            return { success: false, error: e.message };
        }
    }

    // --- GROUPS MANAGEMENT ---

    createGroup(name, description = '') {
        const id = 'grp-' + Date.now() + '-' + Math.random().toString(36).substr(2, 5);
        const group = { id, name: name.trim() || 'Grup Baru', description };
        this.state.groups.push(group);
        this.saveState();
        return group;
    }

    updateGroup(groupId, name, description = '') {
        const grp = this.state.groups.find(g => g.id === groupId);
        if (grp) {
            grp.name = name.trim() || grp.name;
            grp.description = description;
            this.saveState();
        }
    }

    deleteGroup(groupId) {
        // Move traces from deleted group to null (Tanpa Grup)
        this.state.traces.forEach(t => {
            if (t.groupId === groupId) {
                t.groupId = null;
            }
        });
        this.state.groups = this.state.groups.filter(g => g.id !== groupId);
        this.saveState();
    }

    // --- TRACES MANAGEMENT ---

    addTrace(parsedData, targetGroupId = null) {
        const id = 'trc-' + Date.now() + '-' + Math.random().toString(36).substr(2, 5);
        // Default groupId is null (Tanpa Grup)
        const groupId = targetGroupId || null;

        const trace = {
            id,
            groupId,
            fileName: parsedData.fileName || 'trace.sor',
            fileSize: parsedData.fileSize || 0,
            summary: parsedData.summary || {},
            metadata: parsedData.metadata || {},
            events: (parsedData.events || []).map((ev, idx) => ({
                ...ev,
                eventIndex: idx,
                customTag: ev.customTag || '',
                notes: ev.notes || ''
            })),
            dataPoints: parsedData.dataPoints || { points: [] },
            customTags: [], // user-defined markers along distance
            addedAt: new Date().toISOString()
        };

        this.state.traces.push(trace);
        // Otomatis aktifkan trace yang baru saja ditambahkan
        this.state.activeTraceId = id;
        this.saveState();
        return trace;
    }

    removeTrace(traceId) {
        this.state.traces = this.state.traces.filter(t => t.id !== traceId);
        this.state.selectedTraceIds = this.state.selectedTraceIds.filter(id => id !== traceId);
        if (this.state.activeTraceId === traceId) {
            this.state.activeTraceId = this.state.traces[0]?.id || null;
        }
        this.saveState();
    }

    moveTrace(traceId, newGroupId) {
        const trace = this.state.traces.find(t => t.id === traceId);
        if (trace) {
            trace.groupId = newGroupId || null;
            this.saveState();
        }
    }

    setActiveTrace(traceId) {
        this.state.activeTraceId = traceId;
        this.saveState();
    }

    getActiveTrace() {
        return this.state.traces.find(t => t.id === this.state.activeTraceId) || null;
    }

    getTracesByGroup(groupId) {
        return this.state.traces.filter(t => t.groupId === groupId);
    }

    getUngroupedTraces() {
        return this.state.traces.filter(t => !t.groupId);
    }

    // --- MULTI-TRACE COMPARE MODE ---

    setCompareMode(enabled) {
        this.state.compareMode = !!enabled;
        if (this.state.compareMode && this.state.selectedTraceIds.length === 0 && this.state.activeTraceId) {
            this.state.selectedTraceIds = [this.state.activeTraceId];
        }
        this.saveState();
    }

    toggleTraceSelection(traceId) {
        const idx = this.state.selectedTraceIds.indexOf(traceId);
        if (idx >= 0) {
            this.state.selectedTraceIds.splice(idx, 1);
        } else {
            this.state.selectedTraceIds.push(traceId);
        }
        this.saveState();
    }

    // --- CUSTOM TAGGING & MARKERS ---

    addCustomTag(traceId, { distanceKm, label, notes = '', type = 'odp' }) {
        const trace = this.state.traces.find(t => t.id === traceId);
        if (!trace) return null;

        const tag = {
            id: 'tag-' + Date.now() + '-' + Math.random().toString(36).substr(2, 4),
            distanceKm: parseFloat(distanceKm) || 0,
            label: label.trim() || 'Marker',
            notes: notes.trim(),
            type: type || 'odp', // 'odp', 'joint_closure', 'otb', 'slack', 'bending', 'other'
            createdAt: new Date().toISOString()
        };

        trace.customTags = trace.customTags || [];
        trace.customTags.push(tag);
        this.saveState();
        return tag;
    }

    removeCustomTag(traceId, tagId) {
        const trace = this.state.traces.find(t => t.id === traceId);
        if (trace && trace.customTags) {
            trace.customTags = trace.customTags.filter(tg => tg.id !== tagId);
            this.saveState();
        }
    }

    updateEventTag(traceId, eventIndex, customTag, notes = '') {
        const trace = this.state.traces.find(t => t.id === traceId);
        if (trace && trace.events && trace.events[eventIndex]) {
            trace.events[eventIndex].customTag = customTag.trim();
            if (notes) {
                trace.events[eventIndex].notes = notes.trim();
            }
            this.saveState();
        }
    }
}

window.StateManager = StateManager;
