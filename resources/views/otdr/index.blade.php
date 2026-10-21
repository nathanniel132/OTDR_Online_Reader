<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Web OTDR Viewer & Analyzer (.SOR Reader) - Telcordia SR-4731</title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Plotly.js -->
    <script src="https://cdn.plot.ly/plotly-2.35.2.min.js"></script>
    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="/css/style.css">
    <script>
        window.OTDR_API_BASE = '{{ url("") }}';
    </script>
</head>
<body>

    <!-- Print Only Header -->
    <div class="print-only-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="mb-0 fw-bold">LAPORAN HASIL PENGUKURAN OTDR</h3>
                <small class="text-muted">Standar Bellcore / Telcordia SR-4731 (GR-196)</small>
            </div>
            <div class="text-end">
                <div class="fw-semibold" id="printProjectName">Proyek Pengukuran OTDR</div>
                <small class="text-muted">Tanggal Cetak: {{ date('d-m-Y H:i') }}</small>
            </div>
        </div>
    </div>

    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3 shadow-sm no-print">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center fw-bold" href="#">
                <i class="bi bi-activity text-info me-2 fs-4"></i>
                <span>OTDR Viewer & Analyzer</span>
            </a>

            <!-- Project Title & Quick Actions -->
            <div class="d-flex align-items-center ms-auto">


                <button id="btnExportProject" class="btn btn-sm btn-outline-info me-2" title="Simpan seluruh state ke file .otdrproj">
                    <i class="bi bi-download me-1"></i> Ekspor Project
                </button>
                <button id="btnImportProject" class="btn btn-sm btn-outline-light me-2" title="Buka file project (.otdrproj)">
                    <i class="bi bi-upload me-1"></i> Impor Project
                </button>
                <button id="btnResetWorkspace" class="btn btn-sm btn-outline-danger" title="Kosongkan workspace">
                    <i class="bi bi-trash me-1"></i> Reset
                </button>
                <input type="file" id="projectFileInput" accept=".otdrproj,.json" class="d-none">
            </div>
        </div>
    </nav>

    <!-- Main Workspace Container -->
    <div class="container-fluid">
        <div class="row">

            <!-- Left Sidebar: Upload, Groups & Traces -->
            <div class="col-lg-3 col-md-4 p-3 sidebar no-print">

                <!-- Quick Load Sample Button -->
                <button id="btnLoadSample" class="btn btn-primary w-100 mb-3 shadow-sm fw-semibold">
                    <i class="bi bi-file-earmark-play-fill text-warning me-1"></i> Muat Sampel (Doc/kabel1 cbd c1.sor)
                </button>

                <!-- Drag and Drop Upload Zone -->
                <div id="dropZone" class="drop-zone p-3 text-center mb-3" style="cursor: pointer;" onclick="if (!event.target.closest('#btnBrowseFile')) document.getElementById('fileInput').click()">
                    <i class="bi bi-cloud-arrow-up text-primary fs-2"></i>
                    <div class="fw-semibold small mt-1">Tarik & Lepas File .SOR</div>
                    <div class="extra-small text-muted mb-2">Mendukung multi-file batch upload (atau klik di sini)</div>
                    <button id="btnBrowseFile" class="btn btn-sm btn-outline-primary px-3" type="button" onclick="document.getElementById('fileInput').click()">
                        <i class="bi bi-folder-plus me-1"></i> Pilih File
                    </button>
                    <input type="file" id="fileInput" multiple accept=".sor,.SOR,*/*" class="d-none">
                </div>

                <!-- Upload Progress Indicator -->
                <div id="uploadProgress" class="progress mb-3 d-none" style="height: 6px;">
                    <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-info" style="width: 0%"></div>
                </div>

                <!-- Compare Mode Toggle -->
                <div class="card mb-3 border shadow-sm">
                    <div class="card-body p-2 d-flex align-items-center justify-content-between">
                        <span class="small fw-semibold">
                            <i class="bi bi-layers-half text-primary me-1"></i> Multi-Trace Compare
                        </span>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" id="compareModeSwitch">
                        </div>
                    </div>
                </div>

                <!-- Groups Header -->
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-bold text-secondary extra-small">
                        Kelompok Trace (<span id="totalTracesBadge">0</span>)
                    </span>
                    <button id="btnAddGroup" class="btn btn-sm btn-link text-primary p-0 text-decoration-none extra-small" type="button">
                        <i class="bi bi-plus-circle"></i> Tambah Grup
                    </button>
                </div>

                <!-- Groups & Traces Accordion/List -->
                <div id="groupListContainer" class="overflow-auto" style="max-height: calc(100vh - 360px);">
                    <!-- Dynamic rendering by app.js -->
                </div>

            </div>

            <!-- Main Content Area -->
            <div class="col-lg-9 col-md-8 p-3 main-content">

                <!-- Top Metrics Overview Bar -->
                <div class="row g-2 mb-3">
                    <div class="col-sm-2 col-4">
                        <div class="card border-0 shadow-sm p-2 bg-white h-100 metric-card d-flex flex-column justify-content-between">
                            <div class="text-muted extra-small text-uppercase metric-label">Jarak Span</div>
                            <div id="metricDistance" class="fs-5 fw-bold text-primary metric-value">-</div>
                        </div>
                    </div>
                    <div class="col-sm-2 col-4">
                        <div class="card border-0 shadow-sm p-2 bg-white h-100 metric-card d-flex flex-column justify-content-between">
                            <div class="text-muted extra-small text-uppercase metric-label">Panjang Gelombang</div>
                            <div id="metricWavelength" class="fs-5 fw-bold text-info metric-value">-</div>
                        </div>
                    </div>
                    <div class="col-sm-2 col-4">
                        <div class="card border-0 shadow-sm p-2 bg-white h-100 metric-card d-flex flex-column justify-content-between">
                            <div class="text-muted extra-small text-uppercase metric-label">Pulse Width</div>
                            <div id="metricPulseWidth" class="fs-5 fw-bold text-secondary metric-value">-</div>
                        </div>
                    </div>
                    <div class="col-sm-2 col-4">
                        <div class="card border-0 shadow-sm p-2 bg-white h-100 metric-card d-flex flex-column justify-content-between">
                            <div class="text-muted extra-small text-uppercase metric-label">Indeks Bias (IOR)</div>
                            <div id="metricIOR" class="fs-5 fw-bold text-dark metric-value">-</div>
                        </div>
                    </div>
                    <div class="col-sm-2 col-4">
                        <div class="card border-0 shadow-sm p-2 bg-white h-100 metric-card d-flex flex-column justify-content-between">
                            <div class="text-muted extra-small text-uppercase metric-label">Jumlah Event</div>
                            <div id="metricEventsCount" class="fs-5 fw-bold text-warning metric-value">-</div>
                        </div>
                    </div>
                    <div class="col-sm-2 col-4">
                        <div class="card border-0 shadow-sm p-2 bg-white h-100 metric-card d-flex flex-column justify-content-between">
                            <div class="text-muted extra-small text-uppercase metric-label">Perangkat OTDR</div>
                            <div id="metricDevice" class="small fw-bold text-truncate text-success metric-value" title="">-</div>
                        </div>
                    </div>
                </div>

                <!-- Optical Power Budget -->
                <div class="card border-0 shadow-sm mb-3 no-print">
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label for="txPowerInput" class="form-label small fw-semibold mb-1">Daya Kirim / TX Power (dBm)</label>
                                <input type="number" step="0.1" class="form-control form-control-sm" id="txPowerInput" value="7.0">
                            </div>
                            <div class="col-md-3">
                                <label for="rxSensitivityInput" class="form-label small fw-semibold mb-1">Sensitivitas Penerima (dBm)</label>
                                <input type="number" step="0.1" class="form-control form-control-sm" id="rxSensitivityInput" value="-27.0">
                            </div>
                            <div class="col"><div class="text-muted extra-small">Total Span Loss</div><span id="powerTotalLoss" class="badge bg-secondary">- dB</span></div>
                            <div class="col"><div class="text-muted extra-small">Sisa Daya Akhir / P<sub>rx</sub></div><span id="powerRx" class="badge bg-primary">- dBm</span></div>
                            <div class="col"><div class="text-muted extra-small">Margin</div><span id="powerMargin" class="badge bg-info text-dark">- dB</span></div>
                            <div class="col-auto"><span id="powerStatus" class="badge bg-secondary">-</span></div>
                        </div>
                    </div>
                </div>

                <!-- Trace Chart Card -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2 py-2 border-bottom chart-header-bar">
                        <div class="d-flex align-items-center flex-wrap gap-2 overflow-hidden chart-title-group">
                            <i class="bi bi-graph-up-arrow text-primary"></i>
                            <span id="activeTraceFileName" class="fw-bold text-truncate" style="max-width: 400px;">
                                Belum Ada Trace Terpilih
                            </span>
                            <span id="chartLegendNotice" class="badge bg-warning text-dark d-none text-nowrap">
                                Mode Komparasi Aktif
                            </span>
                        </div>
                        <div class="d-flex align-items-center flex-wrap gap-2 no-print chart-toolbar">
                            <!-- Distance Unit Toggle (km / meter) -->
                            <div class="btn-group btn-group-sm shadow-sm" role="group" id="unitToggleGroup" title="Ubah satuan jarak antara kilometer dan meter">
                                <button type="button" class="btn btn-primary fw-semibold text-nowrap d-inline-flex align-items-center justify-content-center" id="btnUnitKm">km</button>
                                <button type="button" class="btn btn-outline-primary fw-semibold text-nowrap d-inline-flex align-items-center justify-content-center" id="btnUnitM">meter</button>
                            </div>
                            <button id="btnResetZoom" class="btn btn-sm btn-outline-secondary text-nowrap d-inline-flex align-items-center justify-content-center" title="Reset Zoom Sumbu X & Y">
                                <i class="bi bi-arrows-angle-contract me-1"></i> Reset Zoom
                            </button>
                            <button id="btnExportChartPng" class="btn btn-sm btn-outline-primary text-nowrap d-inline-flex align-items-center justify-content-center" title="Ekspor Grafik PNG">
                                <i class="bi bi-image me-1"></i> Unduh PNG
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-2 position-relative" style="min-height: 480px;">
                        <!-- Placeholder: shown only when no trace available -->
                        <div id="chartPlaceholder" class="h-100 flex-column align-items-center justify-content-center text-muted py-5" style="min-height: 450px;">
                            <i class="bi bi-graph-up text-secondary mb-3" style="font-size: 3.5rem;"></i>
                            <h5 class="fw-semibold">Belum Ada Trace OTDR Terpilih</h5>
                            <p class="small text-secondary mb-0">Silakan unggah file .SOR atau klik tombol "Muat Sampel" untuk memulai visualisasi.</p>
                        </div>
                        <!-- Plotly container: must never receive custom HTML -->
                        <div id="traceChartContainer" class="d-none" style="min-height: 480px; width: 100%;"></div>
                    </div>
                </div>

                <!-- Custom Marker & Penanda Bar -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-2 d-flex flex-wrap align-items-center justify-content-between">
                        <div class="d-flex align-items-center me-2 mb-1">
                            <span class="fw-bold small me-2">
                                <i class="bi bi-geo-alt-fill text-danger"></i> Penanda Titik (Custom Tags):
                            </span>
                            <button id="btnAddCustomTag" class="btn btn-sm btn-outline-success py-0 px-2" title="Tambah Penanda Jarak Kustom">
                                <i class="bi bi-plus-lg"></i> Tambah Marker Titik
                            </button>
                        </div>
                        <div id="customTagsContainer" class="d-flex flex-wrap align-items-center flex-grow-1">
                            <!-- Dynamic chips rendered by app.js -->
                        </div>
                    </div>
                </div>

                <!-- Tabbed Details: Key Events & Detailed Metadata -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white p-0 border-bottom">
                        <ul class="nav nav-tabs card-header-tabs m-0 px-3" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active fw-semibold" data-bs-toggle="tab" data-bs-target="#tabEvents" type="button">
                                    <i class="bi bi-list-check me-1"></i> Tabel Event OTDR
                                    <span id="eventsCountBadge" class="badge bg-primary ms-1">0</span>
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#tabMetadata" type="button">
                                    <i class="bi bi-info-circle me-1"></i> Metadata & Parameter Teknis
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="card-body p-3">
                        <div class="tab-content">

                            <!-- Tab 1: Key Events Table -->
                            <div class="tab-pane fade show active" id="tabEvents">
                                <div class="d-flex align-items-center justify-content-between mb-2 no-print">
                                    <small class="text-muted">
                                        <i class="bi bi-cursor-fill text-primary"></i> Klik baris tabel untuk memfokuskan grafik ke titik event tersebut.
                                    </small>
                                    <div>
                                        <button id="btnExportCsv" class="btn btn-sm btn-outline-secondary me-2">
                                            <i class="bi bi-filetype-csv me-1"></i> Ekspor CSV
                                        </button>
                                        <button id="btnPrintReport" class="btn btn-sm btn-outline-dark">
                                            <i class="bi bi-printer me-1"></i> Cetak Laporan (PDF)
                                        </button>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover table-bordered table-sm align-middle mb-0">
                                        <thead class="table-light">
                                            <tr class="text-center small">
                                                <th style="width: 50px;">No</th>
                                                <th id="thEventDistance">Jarak (km)</th>
                                                <th>Tipe Event</th>
                                                <th>Penanda / Custom Tag</th>
                                                <th>Event Loss</th>
                                                <th>Reflectance</th>
                                                <th>Attenuation Slope</th>
                                                <th>Kumulatif Loss</th>
                                                <th>Sisa Daya (dBm)</th>
                                                <th class="no-print" style="width: 80px;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody id="eventsTableBody" class="small">
                                            <tr>
                                                <td colspan="10" class="text-center text-muted py-4">
                                                    Belum ada data trace yang dimuat.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Tab 2: Detailed Metadata -->
                            <div class="tab-pane fade" id="tabMetadata">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle mb-0">
                                        <tbody id="metaGeneralBody" class="small">
                                            <tr>
                                                <td colspan="2" class="text-center text-muted">Belum ada data</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- Modal Tambah / Edit Grup -->
    <div class="modal fade" id="modalGroup" tabindex="-1" aria-labelledby="modalGroupLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalGroupLabel">Tambah Grup Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="groupEditId" value="">
                    <div class="mb-3">
                        <label for="groupNameInput" class="form-label small fw-semibold">Nama Grup / Ruas Kabel <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="groupNameInput" placeholder="Contoh: Feeder Ruas A-B, Kabel Distribusi 24C">
                    </div>
                    <div class="mb-3">
                        <label for="groupDescInput" class="form-label small fw-semibold">Deskripsi (Opsional)</label>
                        <textarea class="form-control" id="groupDescInput" rows="2" placeholder="Catatan atau keterangan segmen kabel..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="btnSaveGroup">Simpan Grup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Marker Kustom -->
    <div class="modal fade" id="modalCustomTag" tabindex="-1" aria-labelledby="modalCustomTagLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalCustomTagLabel">Tambah Marker Titik (Custom Tag)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="tagDistanceInput" class="form-label small fw-semibold">Jarak Titik (<span id="modalTagUnitLabel">km</span>) <span class="text-danger">*</span></label>
                        <input type="number" step="any" class="form-control" id="tagDistanceInput" placeholder="Contoh: 0.500">
                    </div>
                    <div class="mb-3">
                        <label for="tagLabelInput" class="form-label small fw-semibold">Nama / Label Marker <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="tagLabelInput" placeholder="Contoh: ODP-CBD-01, Joint Closure 2">
                    </div>
                    <div class="mb-3">
                        <label for="tagTypeSelect" class="form-label small fw-semibold">Tipe Titik</label>
                        <select class="form-select" id="tagTypeSelect">
                            <option value="odp">ODP (Optical Distribution Point)</option>
                            <option value="joint_closure">Joint Closure (Sambungan Splice)</option>
                            <option value="otb">OTB / Rak Server (Optical Termination Box)</option>
                            <option value="slack">Slack Cable (Cadangan Kabel)</option>
                            <option value="bending">Potensi Bending / Tekukan</option>
                            <option value="other">Lainnya / Catatan Khusus</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="tagNotesInput" class="form-label small fw-semibold">Catatan Lapangan (Opsional)</label>
                        <textarea class="form-control" id="tagNotesInput" rows="2" placeholder="Detail tiang, no closure, teknisi penanggung jawab..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success" id="btnSaveCustomTag">Simpan Marker</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Edit Tag Event -->
    <div class="modal fade" id="modalEventTag" tabindex="-1" aria-labelledby="modalEventTagLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalEventTagLabel">Beri Label / Tag Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="eventTraceId">
                    <input type="hidden" id="eventIndex">
                    <div class="mb-3">
                        <label for="eventTagLabelInput" class="form-label small fw-semibold">Label / Nama Titik</label>
                        <input type="text" class="form-control" id="eventTagLabelInput" placeholder="Contoh: JC-01, ODP Tiang 24, OTB End">
                    </div>
                    <div class="mb-3">
                        <label for="eventTagNotesInput" class="form-label small fw-semibold">Catatan Event</label>
                        <textarea class="form-control" id="eventTagNotesInput" rows="2" placeholder="Catatan teknis event sambungan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="btnSaveEventTag">Simpan Tag</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- App Scripts -->
    <script src="/js/stateManager.js"></script>
    <script src="/js/traceChart.js"></script>
    <script src="/js/app.js"></script>
</body>
</html>
