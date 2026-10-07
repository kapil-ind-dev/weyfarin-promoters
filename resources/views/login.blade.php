<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
</head>
<body>
    <section class="view active" id="view-login">
        <div class="auth-wrap">
            <aside class="auth-side">
                <div class="d-flex align-items-center gap-2 fs-4 fw-bold"><span class="logo-dot"><i class="bi bi-grid-1x2-fill"></i></span> Promoter Hub</div>
                <div>
                    <h1 class="display-6 fw-bold mb-3">Welcome back.<br>Your space, your way.</h1>
                    <p class="opacity-75 mb-4">Pick up right where you left off — your dashboard, preferences and activity are waiting.</p>
                    <div class="feature-row"><i class="bi bi-speedometer2"></i>
                        <div><strong>Promoter Hub</strong>
                            <div class="small opacity-75">All your numbers at a glance.</div>
                        </div>
                    </div>
                    <div class="feature-row"><i class="bi bi-sliders"></i>
                        <div><strong>Customize experience</strong>
                            <div class="small opacity-75">Themes, layout and notifications.</div>
                        </div>
                    </div>
                    <div class="feature-row"><i class="bi bi-shield-lock"></i>
                        <div><strong>Secure by default</strong>
                            <div class="small opacity-75">Your data stays protected.</div>
                        </div>
                    </div>
                </div>
                <small class="opacity-50">© 2026 Promoter Hub</small>
            </aside>
            <div class="auth-form">
                <div class="auth-card">
                    <div class="d-lg-none d-flex align-items-center gap-2 fs-5 fw-bold mb-3"><span class="logo-dot" style="background:var(--brand);color:var(--accent)"><i class="bi bi-grid-1x2-fill"></i></span> Promoter Hub</div>
                    <h3 class="fw-bold mb-1">Log in</h3>
                    <p class="text-secondary mb-4">Enter your details to access your account.</p>
                    <form id="loginForm" action="{{ route('login.post') }}" method="POST">
                        @csrf
                        <div class="mb-3"><label class="form-label small fw-semibold">Email</label>
                            <div class="input-icon"><i class="bi bi-envelope"></i><input name="username" type="email" class="form-control" placeholder="you@example.com" required></div>
                        </div>
                        <div class="mb-3"><label class="form-label small fw-semibold">Password</label>
                            <div class="input-icon"><i class="bi bi-lock"></i><input name="password" type="password" class="form-control pw" placeholder="••••••••" required>
                                <button type="button" class="pw-toggle" aria-label="Show password"><i class="bi bi-eye" style="position:static;transform:none"></i></button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Security CAPTCHA</label>
                            <div class="d-flex align-items-center gap-2 captcha">
                                <span id="captchaImage">{!! captcha_img() !!}</span>
                                <button type="button" id="refreshCaptcha"
                                    class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                            <input type="text" name="captcha" class="form-control mt-2"
                                placeholder="Enter CAPTCHA" autocomplete="off" required>
                            <div class="text-danger small" id="captchaError"></div>
                        </div>
                        <!-- <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="rem"><label class="form-check-label small" for="rem">Remember me</label></div>
                            <a href="#" class="small link-strong">Forgot password?</a>
                        </div> -->
                        
                        <div id="loginErrors" class="text-danger small mb-3"></div>
                        <button class="btn btn-brand w-100 mb-3" type="submit">Log in</button>
                        <div class="divider mb-3">or continue with</div>
                        <!-- <div class="row g-2 mb-4">
                            <div class="col-6"><button type="button" class="btn btn-outline-brand w-100"><i class="bi bi-google me-1"></i> Google</button></div>
                            <div class="col-6"><button type="button" class="btn btn-outline-brand w-100"><i class="bi bi-apple me-1"></i> Apple</button></div>
                        </div> -->
                        <p class="text-center small mb-0">New here? <a href="{{ route('signup') }}" class="link-strong">Create an account</a></p>
                    </form>
                </div>
            </div>
        </div>
    </section>
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="toast" class="toast align-items-center text-white border-0" style="background:var(--brand)" role="status">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-check-circle me-2" style="color:var(--accent)"></i>Preferences saved</div>
                <button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('assets/js/script.js') }}"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        $('#refreshCaptcha').click(function () {
            reloadCaptcha();
        });
        function reloadCaptcha(){
            $.ajax({
                type: 'GET',
                url: "{{ route('refresh-captcha') }}",
                success: function (data) {
                    $("#captchaImage").html(data.captcha);
                }
            });
        }
        document.addEventListener('DOMContentLoaded', function() {

            ['signupForm', 'loginForm'].forEach(formId => {
                const form = document.getElementById(formId);
                if (!form) return;

                form.addEventListener('submit', async function(event) {
                    event.preventDefault();

                    const errorBox = form.querySelector(
                        formId === 'signupForm' ? '#signupErrors' : '#loginErrors'
                    );

                    errorBox.textContent = '';

                    form.querySelectorAll('.is-invalid').forEach(input => {
                        input.classList.remove('is-invalid');
                    });

                    if (!form.checkValidity()) {
                        form.reportValidity();
                        return;
                    }

                    const password = form.querySelector('[name="password"]');
                    const confirmation = form.querySelector('[name="password_confirmation"]');

                    if (formId === 'signupForm') {
                        if (password.value.length < 8 ||
                            !/[a-z]/.test(password.value) ||
                            !/[A-Z]/.test(password.value) ||
                            !/\d/.test(password.value) ||
                            !/[^A-Za-z0-9]/.test(password.value)) {
                            errorBox.textContent =
                                'Password must contain 8 characters, uppercase and lowercase letters, a number, and a symbol.';
                            return;
                        }

                        if (password.value !== confirmation.value) {
                            confirmation.classList.add('is-invalid');
                            errorBox.textContent = 'Passwords do not match.';
                            return;
                        }
                    }

                    const submitButton = form.querySelector('[type="submit"]');
                    submitButton.disabled = true;

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: new FormData(form),
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        const data = await response.json();

                        if (response.ok && data.status) {
                            errorBox.classList.remove('text-danger');
                            errorBox.classList.add('text-success');
                            errorBox.textContent = data.message;

                            if (formId === 'signupForm') {
                                form.reset();
                                const bar = document.getElementById('pwbar');
                                if (bar) bar.style.width = '0%';
                            } else if (data.redirect) {
                                window.location.href = data.redirect;
                            }

                            return;
                        }

                        errorBox.classList.remove('text-success');
                        errorBox.classList.add('text-danger');

                        if (data.errors) {
                            errorBox.textContent = Object.values(data.errors)
                                .flat()
                                .join(' ');

                            if (data.errors.captcha) {
                                const captchaError = form.querySelector('#captchaError');
                                if (captchaError) {
                                    captchaError.textContent = data.errors.captcha[0];
                                }
                            }
                        } else {
                            errorBox.textContent = data.message || 'Something went wrong.';
                        }

                        const refreshButton = form.querySelector('#refreshCaptcha');
                        if (refreshButton) refreshButton.click();
                    } catch (error) {
                        errorBox.textContent = 'Unable to process your request. Please try again.';
                    } finally {
                        submitButton.disabled = false;
                    }
                });
            });
        });
    </script>
</body>
</html>