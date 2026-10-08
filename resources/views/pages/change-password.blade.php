@extends('layouts.app')
@section('content')

<style>
    .pw-rule { display: flex; align-items: center; gap: .5rem; font-size: .82rem; color: var(--bs-secondary-color, #6c757d); margin-bottom: .35rem; }
    .pw-rule i { font-size: .9rem; }
    .pw-rule.ok { color: #198754; }
    .pw-meter { height: 6px; border-radius: 6px; background: rgba(0,0,0,.08); overflow: hidden; }
    .pw-meter > span { display: block; height: 100%; width: 0; transition: width .25s, background-color .25s; }
    .pw-toggle { cursor: pointer; }
</style>

<!-- ============ CHANGE PASSWORD ============ -->
<div class="page" id="page-change-password">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0">Change password</h4>
            <small class="text-secondary">Keep your account secure with a strong password.</small>
        </div>
        <div class="d-flex gap-2">
            <button type="reset" form="passwordForm" class="btn btn-outline-brand btn-sm px-3">Reset</button>
            <button type="submit" form="passwordForm" class="btn btn-brand btn-sm px-3" id="pwSubmit">Update password</button>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success py-2 small">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger py-2 small mb-3">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <!-- ===== Form ===== -->
        <div class="col-12 col-xl-7">
            <div class="panel">
                <h6 class="fw-bold mb-3">Password details</h6>

                <form id="passwordForm" method="POST" action="{{ route('password.update') }}" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="current_password" class="form-label small fw-semibold">Current password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" id="current_password" name="current_password"
                                       class="form-control @error('current_password') is-invalid @enderror"
                                       autocomplete="current-password" placeholder="Enter current password">
                                <span class="input-group-text pw-toggle" data-target="current_password"><i class="bi bi-eye"></i></span>
                                <div class="invalid-feedback" data-for="current_password">@error('current_password'){{ $message }}@enderror</div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="new_password" class="form-label small fw-semibold">New password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                <input type="password" id="new_password" name="new_password"
                                       class="form-control @error('new_password') is-invalid @enderror"
                                       autocomplete="new-password" placeholder="Enter new password">
                                <span class="input-group-text pw-toggle" data-target="new_password"><i class="bi bi-eye"></i></span>
                                <div class="invalid-feedback" data-for="new_password">@error('new_password'){{ $message }}@enderror</div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="new_password_confirmation" class="form-label small fw-semibold">Confirm new password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                <input type="password" id="new_password_confirmation" name="new_password_confirmation"
                                       class="form-control" autocomplete="new-password" placeholder="Re-enter new password">
                                <span class="input-group-text pw-toggle" data-target="new_password_confirmation"><i class="bi bi-eye"></i></span>
                                <div class="invalid-feedback" data-for="new_password_confirmation"></div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ===== Strength + rules ===== -->
        <div class="col-12 col-xl-5">
            <div class="panel">
                <h6 class="fw-bold mb-3">Password strength</h6>
                <div class="pw-meter mb-2"><span id="pwBar"></span></div>
                <small class="text-secondary d-block mb-3" id="pwLabel">Start typing a new password.</small>

                <div class="pw-rule" data-rule="len"><i class="bi bi-circle"></i> At least 8 characters</div>
                <div class="pw-rule" data-rule="upper"><i class="bi bi-circle"></i> One uppercase letter</div>
                <div class="pw-rule" data-rule="lower"><i class="bi bi-circle"></i> One lowercase letter</div>
                <div class="pw-rule" data-rule="num"><i class="bi bi-circle"></i> One number</div>
                <div class="pw-rule" data-rule="sym"><i class="bi bi-circle"></i> One special character</div>
                <div class="pw-rule" data-rule="diff"><i class="bi bi-circle"></i> Different from current password</div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const form    = document.getElementById('passwordForm');
        const cur     = document.getElementById('current_password');
        const nw      = document.getElementById('new_password');
        const conf    = document.getElementById('new_password_confirmation');
        const bar     = document.getElementById('pwBar');
        const label   = document.getElementById('pwLabel');
        const rules   = document.querySelectorAll('.pw-rule');

        // ---------- show / hide password ----------
        document.querySelectorAll('.pw-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const input = document.getElementById(this.dataset.target);
                const icon  = this.querySelector('i');
                const show  = input.type === 'password';
                input.type = show ? 'text' : 'password';
                icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
            });
        });

        // ---------- helpers ----------
        function setError(input, msg) {
            const fb = document.querySelector('.invalid-feedback[data-for="' + input.id + '"]');
            if (msg) {
                input.classList.add('is-invalid');
                if (fb) fb.textContent = msg;
            } else {
                input.classList.remove('is-invalid');
                if (fb) fb.textContent = '';
            }
            return !msg;
        }

        function checks(value) {
            return {
                len:   value.length >= 8,
                upper: /[A-Z]/.test(value),
                lower: /[a-z]/.test(value),
                num:   /\d/.test(value),
                sym:   /[^A-Za-z0-9]/.test(value),
                diff:  value.length > 0 && value !== cur.value
            };
        }

        // ---------- live strength meter ----------
        function updateStrength() {
            const c = checks(nw.value);
            let score = 0;
            rules.forEach(function (el) {
                const ok = c[el.dataset.rule];
                el.classList.toggle('ok', ok);
                el.querySelector('i').className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
                if (ok && el.dataset.rule !== 'diff') score++;
            });

            const levels = [
                { w: 0,   c: 'transparent', t: 'Start typing a new password.' },
                { w: 20,  c: '#dc3545',     t: 'Very weak' },
                { w: 40,  c: '#fd7e14',     t: 'Weak' },
                { w: 60,  c: '#ffc107',     t: 'Fair' },
                { w: 80,  c: '#20c997',     t: 'Good' },
                { w: 100, c: '#198754',     t: 'Strong' }
            ];
            const lv = nw.value ? levels[Math.max(score, 1)] : levels[0];
            bar.style.width = lv.w + '%';
            bar.style.backgroundColor = lv.c;
            label.textContent = lv.t;
        }

        // ---------- validators ----------
        function validateCurrent() {
            return setError(cur, cur.value ? '' : 'Current password is required.');
        }

        function validateNew() {
            const v = nw.value;
            const c = checks(v);
            let msg = '';
            if (!v)                     msg = 'New password is required.';
            else if (!c.len)            msg = 'Password must be at least 8 characters.';
            else if (!c.upper || !c.lower) msg = 'Use both uppercase and lowercase letters.';
            else if (!c.num)            msg = 'Include at least one number.';
            else if (!c.sym)            msg = 'Include at least one special character.';
            else if (!c.diff)           msg = 'New password must be different from the current one.';
            return setError(nw, msg);
        }

        function validateConfirm() {
            let msg = '';
            if (!conf.value)                 msg = 'Please confirm your new password.';
            else if (conf.value !== nw.value) msg = 'Passwords do not match.';
            return setError(conf, msg);
        }

        // ---------- events ----------
        nw.addEventListener('input', function () {
            updateStrength();
            if (nw.classList.contains('is-invalid')) validateNew();
            if (conf.value) validateConfirm();
        });
        cur.addEventListener('input', function () {
            if (cur.classList.contains('is-invalid')) validateCurrent();
            updateStrength();
        });
        conf.addEventListener('input', function () {
            if (conf.classList.contains('is-invalid') || conf.value) validateConfirm();
        });
        cur.addEventListener('blur', validateCurrent);
        nw.addEventListener('blur', validateNew);
        conf.addEventListener('blur', validateConfirm);

        form.addEventListener('submit', function (e) {
            const a = validateCurrent();
            const b = validateNew();
            const c = validateConfirm();
            if (!(a && b && c)) {
                e.preventDefault();
                const firstBad = form.querySelector('.is-invalid');
                if (firstBad) firstBad.focus();
            }
        });

        form.addEventListener('reset', function () {
            setTimeout(function () {
                [cur, nw, conf].forEach(function (i) { setError(i, ''); i.type = 'password'; });
                document.querySelectorAll('.pw-toggle i').forEach(function (i) { i.className = 'bi bi-eye'; });
                updateStrength();
            }, 0);
        });

        updateStrength();
    })();
</script>
@endsection
