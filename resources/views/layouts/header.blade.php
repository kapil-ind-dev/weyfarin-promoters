<header class="topbar">
    <div class="container-xxl d-flex align-items-center gap-2 py-2">
      <button class="icon-btn d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-label="Menu"><i class="bi bi-list fs-5"></i></button>
      <a href="#dashboard" class="brand me-lg-3"><span class="logo-dot" style="width:32px;height:32px;font-size:1rem"><i class="bi bi-grid-1x2-fill"></i></span><span class="d-none d-sm-inline">Promoter Panel</span></a>
      <div class="position-relative d-none d-md-block flex-grow-1" style="max-width:340px">
        <i class="bi bi-search position-absolute" style="left:14px;top:50%;transform:translateY(-50%);opacity:.7"></i>
        <input class="form-control search" placeholder="Search anything…">
      </div>
      <div class="ms-auto d-flex align-items-center gap-2">
        <button class="icon-btn"><i class="bi bi-bell"></i></button>
        <button class="icon-btn d-none d-sm-inline-grid"><i class="bi bi-chat-dots"></i></button>
        <div class="dropdown">
          <button class="btn p-0 border-0 d-flex align-items-center gap-2 text-white" data-bs-toggle="dropdown">
            <span class="avatar">KS</span><span class="d-none d-md-inline small fw-semibold">Kapil S.</span><i class="bi bi-chevron-down small d-none d-md-inline"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
            <li><a class="dropdown-item" href="{{ route('customize-settings') }}"><i class="bi bi-sliders me-2"></i>Customize</a></li>
            <li><a class="dropdown-item" href="{{ route('profile') }}"><i class="bi bi-person me-2"></i>Profile</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="bi bi-box-arrow-right me-2"></i>Log out</a></li>
          </ul>
        </div>
      </div>
    </div>
    <nav class="subnav"><div class="container-xxl">
      <a href="{{ route('dashboard') }}" data-nav="dashboard">Overview</a>
      <a href="{{ route('customize-settings') }}" >Customize</a>
    </div>
</nav>
</header>