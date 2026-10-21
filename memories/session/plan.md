# Implementation Plan - Web OTDR Viewer & Analyzer (.SOR Reader) - Laravel

## Architecture Pivot: Plain PHP -> Laravel Framework
- Framework: Laravel (PHP 8.3)
- Service: `app/Services/SorParserService.php` (SR-4731 Binary Parser Engine)
- Controller: `app/Http/Controllers/OtdrController.php` (Parse upload, sample loader, view dashboard)
- Routes:
  - `GET /` -> Dashboard web viewer
  - `POST /api/otdr/parse` -> Stateless parser endpoint
  - `GET /api/otdr/sample` -> Sample file loader
- Frontend: Blade view (`resources/views/otdr/index.blade.php`), Plotly.js, Bootstrap 5 / Tailwind CSS, custom JS (`stateManager.js`, `traceChart.js`, `app.js`).
- State: Client-side LocalStorage/IndexedDB + .otdrproj project export/import (Zero DB server dependency).

## Milestones & Tasks
1. [x] Milestone 1: Architectural pivot approved & PRD updated for Laravel.
2. [x] Milestone 2: Scaffold Laravel project in workspace.
3. [x] Milestone 3: Implement `App\Services\SorParserService` (Telcordia SR-4731 parser).
4. [x] Milestone 4: Implement `OtdrController` & API routes (`/api/otdr/parse`, `/api/otdr/sample`).
5. [x] Milestone 5: Build Blade dashboard UI, Plotly.js trace chart, grouping, tagging, export/import.
6. [x] Milestone 6: Verification & QA test against sample `Doc/kabel1 cbd c1.sor`.
7. [x] Milestone 7: Ungrouped trace by default (Tanpa Grup) & auto-migration from default group.
8. [x] Milestone 8: Optical Power Budget & Remaining Power Feature ($P_{tx} - \text{Loss}$)
   - [x] Subtask 8.1 (Coder): Implement Optical Power Budget UI controls (TX Power, RX Sensitivity, Estimated RX Power, Margin).
   - [x] Subtask 8.2 (Coder): Integrate Remaining Power calculation into Plotly traceChart hover & Key Events table.
   - [x] Subtask 8.3 (Coder): Persist power settings in StateManager & support reactive live calculation.
   - [x] Subtask 8.4 (Reviewer): Review & validate implementation, edge cases, and run tests (APPROVED).
9. [x] Milestone 9: Fix dropdown menu clipping in sidebar trace and group actions
   - [x] Subtask 9.1 (Coder): Apply Popper fixed strategy (`data-bs-popper-config='{"strategy":"fixed"}'`) and z-index to sidebar dropdowns.
   - [x] Subtask 9.2 (Reviewer): Review and validate fix (APPROVED).
