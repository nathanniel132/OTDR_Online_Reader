/**
 * TraceChart - Plotly.js Interactive OTDR Attenuation Curve Renderer
 * Supports Single-Trace, Multi-Trace Overlay, Event Markers, and Custom Tag Annotations
 */

class TraceChart {
    constructor(containerId, placeholderId = 'chartPlaceholder') {
        this.containerId = containerId;
        this.container = document.getElementById(containerId);
        this.placeholder = document.getElementById(placeholderId || 'chartPlaceholder');
        this.colorPalette = [
            '#0284c7', // Primary Ocean Blue
            '#16a34a', // Emerald Green
            '#dc2626', // Crimson Red
            '#9333ea', // Purple
            '#ea580c', // Orange
            '#0891b2', // Cyan
            '#d97706', // Amber
            '#db2777'  // Pink
        ];
    }

    escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
    }

    render(activeTrace, compareTraces = [], unit = 'km', txPower = 7.0) {
        if (!this.container) return;
        this.currentUnit = unit;
        const factor = unit === 'm' ? 1000 : 1;
        const unitLabel = unit === 'm' ? 'meter' : 'km';
        const parseFiniteNumber = (val, fallback = null) => {
            if (val === null || val === undefined || typeof val === 'boolean') return fallback;
            if (typeof val === 'string' && val.trim() === '') return fallback;
            const num = Number(val);
            return Number.isFinite(num) ? num : fallback;
        };
        const safeTxPower = parseFiniteNumber(txPower, 7.0);

        // If no trace available, show placeholder (never overwrite Plotly container HTML)
        if (!activeTrace && (!compareTraces || compareTraces.length === 0)) {
            try {
                if (this.container.data || this.container.classList.contains('js-plotly-plot')) {
                    Plotly.purge(this.container);
                }
            } catch (e) {
                console.warn('Plotly purge warning:', e);
            }
            this.container.classList.add('d-none');
            this.container.classList.remove('d-block');
            if (this.placeholder) {
                this.placeholder.classList.remove('d-none');
                this.placeholder.classList.add('d-flex');
            }
            return;
        }

        // Show Plotly container and hide placeholder
        if (this.placeholder) {
            this.placeholder.classList.add('d-none');
            this.placeholder.classList.remove('d-flex');
        }
        this.container.classList.remove('d-none');
        this.container.classList.add('d-block');

        // Drop any foreign nodes left in the Plotly container
        if (!this.container.classList.contains('js-plotly-plot')) {
            this.container.innerHTML = '';
        }

        const tracesToRender = compareTraces && compareTraces.length > 0 ? compareTraces : [activeTrace];
        const plotData = [];
        const shapes = [];
        const annotations = [];

        tracesToRender.forEach((trace, idx) => {
            if (!trace || !trace.dataPoints || !trace.dataPoints.points) return;

            const pts = Array.isArray(trace.dataPoints.points) ? trace.dataPoints.points : [];
            const finitePoints = pts.reduce((validPoints, pt) => {
                if (!pt) return validPoints;

                const x = parseFiniteNumber(pt.x);
                const y = parseFiniteNumber(pt.y);
                if (x !== null && y !== null) {
                    validPoints.push({ x, y });
                }

                return validPoints;
            }, []);
            if (finitePoints.length === 0) return;

            const xVals = finitePoints.map(pt => pt.x * factor);
            const yVals = finitePoints.map(pt => pt.y);
            const y0 = yVals[0];

            const color = this.colorPalette[idx % this.colorPalette.length];
            const isPrimaryTrace = trace.id === activeTrace?.id || tracesToRender.length === 1;
            const customdata = yVals.map(value => {
                const loss = y0 - value;
                const remPower = safeTxPower - loss;
                return [loss, remPower];
            });

            // Attenuation curve trace
            const hoverXFormat = unit === 'm' ? '%{x:.1f} m' : '%{x:.4f} km';
            plotData.push({
                x: xVals,
                y: yVals,
                type: 'scatter', // High reliability standard scatter
                mode: 'lines',
                name: trace.fileName || `Trace #${idx + 1}`,
                line: {
                    color: color,
                    width: 2.2
                },
                customdata: isPrimaryTrace ? customdata : undefined,
                hovertemplate: '<b>' + (trace.fileName || 'Trace') + '</b><br>' +
                               'Jarak: ' + hoverXFormat + '<br>' +
                               'Atenuasi: %{y:.3f} dB' +
                               (isPrimaryTrace ? '<br>Loss Relatif: %{customdata[0]:.3f} dB<br>Sisa Daya: %{customdata[1]:.3f} dBm' : '') +
                               '<extra></extra>'
            });

            // If single-trace mode or primary active trace, render markers and annotations
            if (tracesToRender.length === 1 || trace.id === activeTrace?.id) {
                // 1. Render KeyEvents Markers
                if (trace.events && Array.isArray(trace.events)) {
                    trace.events.forEach((ev) => {
                        const distPlot = ev.distanceKm * factor;
                        const isEnd = ev.type && ev.type.includes('End');
                        const lineColor = isEnd ? '#dc2626' : '#2563eb';

                        // Vertical line marker
                        shapes.push({
                            type: 'line',
                            x0: distPlot,
                            x1: distPlot,
                            y0: 0,
                            y1: 1,
                            yref: 'paper',
                            line: {
                                color: lineColor,
                                width: 1.5,
                                dash: 'dot'
                            }
                        });

                        // Annotation badge
                        const tagText = ev.customTag ? ` [${this.escapeHtml(ev.customTag)}]` : '';
                        const distDisplay = unit === 'm' ? `${Math.round(distPlot * 10) / 10} m` : `${ev.distanceKm} km`;
                        annotations.push({
                            x: distPlot,
                            y: 1,
                            yref: 'paper',
                            text: `<b>#${ev.eventNumber}</b>${tagText}<br><span style="font-size:10px">${distDisplay}</span>`,
                            showarrow: true,
                            arrowhead: 2,
                            arrowsize: 0.8,
                            arrowcolor: lineColor,
                            ax: 0,
                            ay: -28 - (ev.eventNumber % 2) * 16,
                            bgcolor: isEnd ? '#fef2f2' : '#eff6ff',
                            bordercolor: lineColor,
                            borderwidth: 1,
                            borderpad: 3,
                            font: { size: 10, color: lineColor }
                        });
                    });
                }

                // 2. Render Custom Tags Markers (ODP, JC, OTB, Bending)
                if (trace.customTags && Array.isArray(trace.customTags)) {
                    trace.customTags.forEach((tag, tIdx) => {
                        const distPlot = tag.distanceKm * factor;
                        const tagColors = {
                            'odp': '#059669', // Emerald
                            'joint_closure': '#0284c7', // Blue
                            'otb': '#7c3aed', // Violet
                            'slack': '#d97706', // Amber
                            'bending': '#ea580c', // Orange
                            'other': '#475569'  // Slate
                        };
                        const tagColor = tagColors[tag.type] || '#059669';

                        shapes.push({
                            type: 'line',
                            x0: distPlot,
                            x1: distPlot,
                            y0: 0,
                            y1: 1,
                            yref: 'paper',
                            line: {
                                color: tagColor,
                                width: 2,
                                dash: 'dash'
                            }
                        });

                        const distDisplay = unit === 'm' ? `${Math.round(distPlot * 10) / 10} m` : `${tag.distanceKm} km`;
                        annotations.push({
                            x: distPlot,
                            y: 0.15 + (tIdx % 3) * 0.15,
                            yref: 'paper',
                            text: `📍 <b>${this.escapeHtml(tag.label)}</b><br><span style="font-size:10px">${this.escapeHtml(distDisplay)}</span>`,
                            showarrow: true,
                            arrowhead: 3,
                            arrowcolor: tagColor,
                            ax: 25,
                            ay: -20,
                            bgcolor: '#f8fafc',
                            bordercolor: tagColor,
                            borderwidth: 1.5,
                            borderpad: 4,
                            font: { size: 11, color: '#0f172a' }
                        });
                    });
                }
            }
        });

        if (plotData.length === 0) {
            this.container.classList.add('d-none');
            if (this.placeholder) {
                this.placeholder.classList.remove('d-none');
                this.placeholder.classList.add('d-flex');
                this.placeholder.querySelector('p')?.replaceChildren(document.createTextNode('Trace tidak memiliki titik data.'));
            }
            return;
        }

        // Determine X-axis maximum
        let maxDistance = 2;
        tracesToRender.forEach(t => {
            const d = t?.summary?.totalDistanceKm || 0;
            if (d > maxDistance) maxDistance = d;
            if (t?.metadata?.fixed?.rangeKm && t.metadata.fixed.rangeKm > maxDistance) {
                maxDistance = Math.min(t.metadata.fixed.rangeKm, (d > 0 ? d * 1.3 : t.metadata.fixed.rangeKm));
            }
        });

        const layout = {
            margin: { t: 45, r: 30, l: 65, b: 50 },
            autosize: true,
            plot_bgcolor: '#ffffff',
            paper_bgcolor: '#ffffff',
            showlegend: tracesToRender.length > 1,
            legend: {
                orientation: 'h',
                x: 0,
                y: 1.12,
                font: { size: 11 }
            },
            hovermode: 'closest',
            xaxis: {
                title: { text: `<b>Jarak (${unitLabel})</b>`, font: { size: 12, color: '#334155' } },
                showgrid: true,
                gridcolor: '#f1f5f9',
                zeroline: true,
                zerolinecolor: '#cbd5e1',
                range: [-0.05 * factor, maxDistance * factor],
                tickfont: { size: 11, color: '#475569' }
            },
            yaxis: {
                title: { text: '<b>Atenuasi / Level Daya (dB)</b>', font: { size: 12, color: '#334155' } },
                showgrid: true,
                gridcolor: '#f1f5f9',
                zeroline: true,
                zerolinecolor: '#cbd5e1',
                tickfont: { size: 11, color: '#475569' }
            },
            shapes: shapes,
            annotations: annotations
        };

        const config = {
            responsive: true,
            displayModeBar: true,
            displaylogo: false,
            modeBarButtonsToRemove: ['lasso2d', 'select2d'],
            toImageButtonOptions: {
                format: 'png',
                filename: (activeTrace?.fileName || 'otdr-trace').replace(/\.[^/.]+$/, "") + '_trace',
                height: 600,
                width: 1100,
                scale: 2
            }
        };

        Plotly.react(this.container, plotData, layout, config);

        // Force a proportional resize once the container becomes visible
        const container = this.container;
        setTimeout(() => {
            try {
                Plotly.Plots.resize(container);
            } catch (e) {
                console.warn('Plotly resize warning:', e);
            }
        }, 50);
    }

    focusAt(distanceKm, unit = 'km') {
        if (!this.container || !this.container.data) return;
        const factor = (unit || this.currentUnit) === 'm' ? 1000 : 1;
        const dist = parseFloat(distanceKm) * factor;
        const windowSize = 0.3 * factor; // 300m window
        const update = {
            'xaxis.range': [Math.max(0, dist - windowSize / 2), dist + windowSize / 2]
        };
        Plotly.relayout(this.container, update);
    }

    resetZoom(activeTrace, unit = 'km') {
        if (!this.container || !this.container.data) return;
        const factor = (unit || this.currentUnit) === 'm' ? 1000 : 1;
        const maxDist = (activeTrace?.summary?.totalDistanceKm || 2) * 1.25 * factor;
        const update = {
            'xaxis.autorange': false,
            'xaxis.range': [-0.05 * factor, maxDist],
            'yaxis.autorange': true
        };
        Plotly.relayout(this.container, update);
    }

    exportImage(fileName = 'otdr_trace.png') {
        if (!this.container) return;
        Plotly.downloadImage(this.container, {
            format: 'png',
            width: 1200,
            height: 650,
            filename: fileName.replace(/\.[^/.]+$/, '')
        });
    }
}

window.TraceChart = TraceChart;
