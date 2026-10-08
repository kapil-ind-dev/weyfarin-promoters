<aside class="side-desktop d-none d-lg-block">
    <div class="side-card sidebar-nav" id="sideNav">
        <a class="nav-link @if(request()->routeIs('dashboard')) active @endif" href="{{ route('dashboard') }}" data-nav="dashboard"><i class="bi bi-speedometer2"></i>Dashboard<i class="bi bi-chevron-right chev"></i></a>
        <a class="nav-link @if(request()->routeIs('customize-settings')) active @endif" href="{{ route('customize-settings') }}" data-nav="customize"><i class="bi bi-sliders"></i>Customize<i class="bi bi-chevron-right chev"></i></a>
        <a class="nav-link"><i class="bi bi-folder2"></i>Projects<i class="bi bi-chevron-right chev"></i></a>
        <a class="nav-link"><i class="bi bi-bar-chart"></i>Analytics<i class="bi bi-chevron-right chev"></i></a>
        <a class="nav-link"><i class="bi bi-calendar3"></i>Calendar<i class="bi bi-chevron-right chev"></i></a>
        <a class="nav-link"><i class="bi bi-envelope"></i>Messages<i class="bi bi-chevron-right chev"></i></a>
        <a class="nav-link"><i class="bi bi-credit-card"></i>Billing<i class="bi bi-chevron-right chev"></i></a>
        <a class="nav-link @if(request()->routeIs('profile')) active @endif"  href="{{ route('profile') }}"><i class="bi bi-person-gear" ></i>Profile<i class="bi bi-chevron-right chev"></i></a>
        <a class="nav-link @if(request()->routeIs('change-password.show')) active @endif"  href="{{ route('change-password.show') }}"><i class="bi bi-key" ></i>Change Password<i class="bi bi-chevron-right chev"></i></a>
        <a class="nav-link" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="bi bi-box-arrow-right" ></i>Log out<i class="bi bi-chevron-right chev"></i></a>
    </div>
</aside>