<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Promoter Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico')}}">
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
</head>

<body>
    <section class="view active" id="view-app">
        <!-- top bar -->
        @include('layouts.header')
        <!-- mobile sidebar -->
        <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileNav">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title fw-bold">Menu</h5><button class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body" id="mobileNavBody"></div>
        </div>
        <main class="container-xxl py-4">
            <div class="app-layout">
                <!-- sidebar (desktop) -->
                @include('layouts.sidebar')
                <div>
                    @yield('content')
                </div>
            </div>
        </main>
    </section>
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
            <div class="modal-content logout-modal">
            <div class="modal-body text-center p-4 p-sm-5">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="logout-ico mx-auto mb-3"><i class="bi bi-box-arrow-right"></i></div>
                <h5 class="fw-bold mb-2" id="logoutTitle">Log out of Promoter Panel?</h5>
                <p class="text-secondary mb-4">You'll need to sign in again to access your dashboard and preferences.</p>
                <div class="d-flex flex-column flex-sm-row gap-2">
                <button type="button" class="btn btn-outline-brand flex-fill" data-bs-dismiss="modal">Stay logged in</button>
                <button type="button" class="btn btn-brand flex-fill" id="confirmLogout" onclick="window.location.href='{{ route('logout') }}'"><i class="bi bi-box-arrow-right me-1"></i>Yes, log out</button>
                </div>
            </div>
            </div>
        </div>
    </div>
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="toast" class="toast align-items-center text-white border-0" style="background:var(--brand)" role="status">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-check-circle me-2" style="color:var(--accent)"></i>Preferences saved</div>
                <button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('assets/js/script.js') }}"></script>
    <script>
        // document.getElementById('optDark').addEventListener('change', e => {
        //     document.documentElement.style.setProperty('--bg', e.target.checked ? '#1d150d' : '#f3ede6');
        //     document.body.style.color = e.target.checked ? '#f3ede6' : '';
        // });
    </script>
</body>

</html>