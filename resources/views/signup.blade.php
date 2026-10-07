<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign Up</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
</head>
<body>
    <section class="view active" id="view-signup">
        <div class="auth-wrap">
            <aside class="auth-side">
                <div class="d-flex align-items-center gap-2 fs-4 fw-bold"><span class="logo-dot"><i class="bi bi-grid-1x2-fill"></i></span> Promoter Hub</div>
                <div>
                    <h1 class="display-6 fw-bold mb-3">Create your account<br>in under a minute.</h1>
                    <p class="opacity-75 mb-4">Join thousands of users managing everything from one clean panel.</p>
                    <div class="feature-row"><i class="bi bi-lightning-charge"></i>
                        <div><strong>Instant setup</strong>
                            <div class="small opacity-75">No credit card required.</div>
                        </div>
                    </div>
                    <div class="feature-row"><i class="bi bi-palette"></i>
                        <div><strong>Personalised from day one</strong>
                            <div class="small opacity-75">Choose your look in the next step.</div>
                        </div>
                    </div>
                    <div class="feature-row"><i class="bi bi-people"></i>
                        <div><strong>Made for teams & individuals</strong>
                            <div class="small opacity-75">Scale whenever you need.</div>
                        </div>
                    </div>
                </div>
                <small class="opacity-50">© 2026 Promoter Hub</small>
            </aside>
            <div class="auth-form">
                <div class="auth-card">
                    <div class="d-lg-none d-flex align-items-center gap-2 fs-5 fw-bold mb-3"><span class="logo-dot" style="background:var(--brand);color:var(--accent)"><i class="bi bi-grid-1x2-fill"></i></span> Promoter Hub</div>
                    <h3 class="fw-bold mb-1">Sign up</h3>
                    <p class="text-secondary mb-4">Fill in the details below to get started.</p>
                    <form id="signupForm" action="{{ route('signup.post') }}" method="POST">
                        @csrf
                        <div class="row g-3">
                            <div class="col-sm-6"><label class="form-label small fw-semibold">First name</label><input class="form-control" name="first_name" placeholder="Kapil" required></div>
                            <div class="col-sm-6"><label class="form-label small fw-semibold">Last name</label><input class="form-control" name="last_name" placeholder="Sharma" required></div>
                            <div class="col-12"><label class="form-label small fw-semibold">Email</label>
                                <div class="input-icon"><i class="bi bi-envelope"></i><input type="email" class="form-control" name="email" placeholder="you@example.com" required></div>
                            </div>
                            <div class="col-12"><label class="form-label small fw-semibold">Phone</label>
                                <div class="input-icon"><i class="bi bi-telephone"></i><input type="tel" class="form-control" name="phone" placeholder="+91 98765 43210"></div>
                            </div>
                            <div class="col-sm-6"><label class="form-label small fw-semibold">Password</label>
                                <input type="password" class="form-control pw" name="password" placeholder="Min 8 characters" minlength="8" required>
                            </div>
                            <div class="col-sm-6"><label class="form-label small fw-semibold">Confirm password</label>
                                <input type="password" class="form-control pw" name="password_confirmation" placeholder="Repeat password" required>
                            </div>
                            <div class="col-12 my-3">
                                <label class="form-label">Security CAPTCHA</label>
                                <div class="d-flex align-items-center gap-2">
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
                            <div class="col-12">
                                <div class="progress" style="height:6px">
                                    <div class="progress-bar" id="pwbar" style="width:0"></div>
                                </div>
                                <small class="text-secondary" id="pwtxt">Use 8+ characters with numbers & symbols.</small>
                            </div>
                            <div class="col-12">
                                <div class="form-check"><input name="terms" class="form-check-input" type="checkbox" id="terms" required>
                                    <label class="form-check-label small" for="terms">I agree to the <a href="#" class="link-strong">Terms</a> and <a href="#" class="link-strong">Privacy Policy</a></label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div id="signupErrors" class="text-danger small mb-3"></div>
                                <button class="btn btn-brand w-100" type="submit">Create account</button>
                            </div>
                        </div>
                        <p class="text-center small mt-4 mb-0">Already have an account? <a href="{{ route('login') }}" class="link-strong">Log in</a></p>
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
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('assets/js/script.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('#refreshCaptcha').forEach(button => {
                button.addEventListener('click', function() {
                    const container = this.closest('form').querySelector('#captchaImage');
                    const image = container?.querySelector('img');

                    if (image) {
                        image.src = '{{ captcha_src() }}?' + Date.now();
                    }

                    const captchaInput = this.closest('form').querySelector('[name="captcha"]');
                    if (captchaInput) captchaInput.value = '';

                    const error = this.closest('form').querySelector('#captchaError');
                    if (error) error.textContent = '';
                });
            });

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