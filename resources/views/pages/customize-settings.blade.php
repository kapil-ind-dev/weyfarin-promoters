@extends('layouts.app')
@section('content')
<!-- ============ CUSTOMIZE EXPERIENCE ============ -->
<div class="page" id="page-customize">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0">Customize your experience</h4><small class="text-secondary">Make the panel work the way you do.</small>
        </div>
        <div class="d-flex gap-2"><button class="btn btn-outline-brand btn-sm px-3" id="resetPrefs">Reset</button><button class="btn btn-brand btn-sm px-3" id="savePrefs">Save changes</button></div>
    </div>
    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="panel mb-3">
                <h6 class="fw-bold">Preferences</h6>
                <div class="pref-item"><span class="stat-ico ico-1"><i class="bi bi-moon-stars"></i></span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Dark mode</div><small class="text-secondary">Easier on the eyes at night.</small>
                    </div>
                    <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="optDark"></div>
                </div>
                <div class="pref-item"><span class="stat-ico ico-2"><i class="bi bi-bell"></i></span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Push notifications</div><small class="text-secondary">Alerts for activity and mentions.</small>
                    </div>
                    <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" checked></div>
                </div>
                <div class="pref-item"><span class="stat-ico ico-3"><i class="bi bi-envelope-paper"></i></span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Weekly email digest</div><small class="text-secondary">A summary every Monday morning.</small>
                    </div>
                    <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" checked></div>
                </div>
                <div class="pref-item"><span class="stat-ico ico-4"><i class="bi bi-arrows-collapse"></i></span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Compact density</div><small class="text-secondary">Tighter spacing, more on screen.</small>
                    </div>
                    <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="optCompact"></div>
                </div>
                <div class="pref-item"><span class="stat-ico ico-2"><i class="bi bi-lightning"></i></span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Reduce animations</div><small class="text-secondary">Minimise motion effects.</small>
                    </div>
                    <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox"></div>
                </div>
                <div class="pref-item"><span class="stat-ico ico-1"><i class="bi bi-translate"></i></span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Language</div><small class="text-secondary">Interface language.</small>
                    </div>
                    <select class="form-select form-select-sm w-auto">
                        <option>English</option>
                        <option>हिन्दी</option>
                        <option>ગુજરાતી</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="panel mb-3">
                <h6 class="fw-bold mb-3">Accent colour</h6>
                <div class="d-flex flex-wrap gap-3" id="swatches">
                    <span class="swatch on" data-c="#e0b48a" style="background:#e0b48a" title="Tan"></span>
                    <span class="swatch" data-c="#c9854f" style="background:#c9854f" title="Copper"></span>
                    <span class="swatch" data-c="#9aa86b" style="background:#9aa86b" title="Olive"></span>
                    <span class="swatch" data-c="#7fa6b8" style="background:#7fa6b8" title="Sky"></span>
                    <span class="swatch" data-c="#d98c9a" style="background:#d98c9a" title="Rose"></span>
                </div>
                <small class="text-secondary d-block mt-3">The main theme stays <strong>#352718</strong>; the accent colours highlights and badges.</small>
            </div>
            <div class="panel mb-3">
                <h6 class="fw-bold mb-3">Dashboard layout</h6>
                <div class="row g-2" id="layouts">
                    <div class="col-4">
                        <div class="layout-opt on">
                            <div class="mini"><i></i><i></i></div><small class="fw-semibold">Sidebar</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="layout-opt top">
                            <div class="mini"><i></i><i></i></div><small class="fw-semibold">Top nav</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="layout-opt card">
                            <div class="mini"><i></i><i></i><i></i></div><small class="fw-semibold">Cards</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="panel">
                <h6 class="fw-bold">Dashboard widgets <small class="text-secondary fw-normal">— drag to reorder</small></h6>
                <ul class="sortable-list p-0 mb-0 mt-3" id="widgets">
                    <li draggable="true"><i class="bi bi-grip-vertical grip"></i><i class="bi bi-bar-chart text-secondary"></i><span class="flex-grow-1 fw-semibold">Weekly activity</span>
                        <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" checked></div>
                    </li>
                    <li draggable="true"><i class="bi bi-grip-vertical grip"></i><i class="bi bi-clock-history text-secondary"></i><span class="flex-grow-1 fw-semibold">Recent activity</span>
                        <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" checked></div>
                    </li>
                    <li draggable="true"><i class="bi bi-table text-secondary"></i><span class="flex-grow-1 fw-semibold ms-4">Recent orders</span>
                        <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" checked></div>
                    </li>
                    <li draggable="true"><i class="bi bi-grip-vertical grip"></i><i class="bi bi-calendar-check text-secondary"></i><span class="flex-grow-1 fw-semibold">Upcoming tasks</span>
                        <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox"></div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection