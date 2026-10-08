@extends('layouts.app')
@section('content')
@php
    $user = $user ?? auth()->user();
    $initials = strtoupper(substr($user->first_name, 0, 1) . substr($user->last_name, 0, 1));
    $countryCode = old('country_code', $user->country_code ?? '+91');
    $isActive = (string) $user->status === '1'; // adjust if your status mapping is different
@endphp

<style>
    .profile-cover { height: 110px; border-radius: 14px 14px 0 0; background: linear-gradient(135deg, #352718, var(--brand, #e0b48a)); }
    .profile-avatar-wrap { position: relative; width: 112px; height: 112px; margin: -56px auto 0; }
    .profile-avatar { width: 112px; height: 112px; border-radius: 50%; object-fit: cover; border: 4px solid var(--bs-body-bg, #fff); background: #352718; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; }
    .profile-avatar-edit { position: absolute; right: 2px; bottom: 2px; width: 34px; height: 34px; border-radius: 50%; border: 3px solid var(--bs-body-bg, #fff); background: var(--brand, #e0b48a); color: #352718; display: flex; align-items: center; justify-content: center; cursor: pointer; }
    .acc-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: .6rem; }
    .acc-box { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; padding: .7rem .4rem; display: flex; flex-direction: column; align-items: center; gap: .15rem; background: rgba(224,180,138,.08); }
    .acc-val { font-weight: 700; font-size: .85rem; line-height: 1.2; }
    .acc-box small { font-size: .7rem; }
</style>

<!-- ============ PROFILE ============ -->
<div class="page" id="page-profile">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0">My profile</h4>
            <small class="text-secondary">View and update your personal information.</small>
        </div>
        <div class="d-flex gap-2">
            <button type="reset" form="profileForm" class="btn btn-outline-brand btn-sm px-3" id="resetProfile">Reset</button>
            <button type="submit" form="profileForm" class="btn btn-brand btn-sm px-3">Save changes</button>
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

    <form id="profileForm" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <!-- ===== LEFT: summary card ===== -->
            <div class="col-12 col-xl-4">
                <div class="panel p-0 overflow-hidden mb-3">
                    <div class="profile-cover"></div>
                    <div class="px-3 pb-4 text-center">
                        <div class="profile-avatar-wrap">
                            @if ($user->profile)
                                <img id="avatarPreview" class="profile-avatar" src="{{ asset('storage/' . $user->profile) }}" alt="Profile photo">
                                <div id="avatarInitials" class="profile-avatar d-none">{{ $initials }}</div>
                            @else
                                <img id="avatarPreview" class="profile-avatar d-none" src="" alt="Profile photo">
                                <div id="avatarInitials" class="profile-avatar">{{ $initials }}</div>
                            @endif
                            <label for="profileInput" class="profile-avatar-edit" title="Change photo"><i class="bi bi-camera"></i></label>
                            <input type="file" id="profileInput" name="profile" accept="image/png,image/jpeg,image/webp" class="d-none">
                        </div>

                        <h5 class="fw-bold mt-3 mb-0" id="displayName">{{ $user->first_name }} {{ $user->last_name }}</h5>
                        <small class="text-secondary d-block">{{ $user->email }}</small>
                        <span class="badge mt-2 {{ $isActive ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $isActive ? 'Active' : 'Inactive' }}
                        </span>
                        <small class="text-secondary d-block mt-3">JPG, PNG or WEBP. Max 2 MB.</small>

                        <!-- Account details as boxes -->
                        <div class="acc-grid mt-3">
                            <div class="acc-box">
                                <span class="stat-ico ico-1"><i class="bi bi-hash"></i></span>
                                <div class="acc-val">#{{ $user->id }}</div>
                                <small class="text-secondary">User ID</small>
                            </div>
                            <div class="acc-box">
                                <span class="stat-ico ico-2"><i class="bi bi-calendar-plus"></i></span>
                                <div class="acc-val" title="{{ \Carbon\Carbon::parse($user->created_at)->format('d M Y, h:i A') }}">{{ \Carbon\Carbon::parse($user->created_at)->format('d M Y') }}</div>
                                <small class="text-secondary">Member since</small>
                            </div>
                            <div class="acc-box">
                                <span class="stat-ico ico-3"><i class="bi bi-arrow-repeat"></i></span>
                                <div class="acc-val" title="{{ \Carbon\Carbon::parse($user->updated_at)->format('d M Y, h:i A') }}">{{ \Carbon\Carbon::parse($user->updated_at)->diffForHumans(null, true, true) }}</div>
                                <small class="text-secondary">Last updated</small>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ===== RIGHT: single edit panel ===== -->
            <div class="col-12 col-xl-8">
                <div class="panel mb-3">
                    <h6 class="fw-bold mb-3">Profile information</h6>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="first_name" class="form-label small fw-semibold">First name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" id="first_name" name="first_name" class="form-control @error('first_name') is-invalid @enderror"
                                       value="{{ old('first_name', $user->first_name) }}" required>
                                @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="last_name" class="form-label small fw-semibold">Last name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" id="last_name" name="last_name" class="form-control @error('last_name') is-invalid @enderror"
                                       value="{{ old('last_name', $user->last_name) }}" required>
                                @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="email" class="form-label small fw-semibold">Email address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                       value="{{ old('email', $user->email) }}" required>
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="phone" class="form-label small fw-semibold">Phone number</label>
                            <div class="input-group">
                                <select name="country_code" id="country_code" class="form-select @error('country_code') is-invalid @enderror" style="max-width: 120px;">
                                    @foreach (['+91' => 'IN +91', '+1' => 'US +1', '+44' => 'UK +44', '+971' => 'AE +971', '+61' => 'AU +61', '+65' => 'SG +65', '+49' => 'DE +49'] as $code => $label)
                                        <option value="{{ $code }}" @selected($countryCode === $code)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input type="tel" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                       value="{{ old('phone', $user->phone) }}" inputmode="numeric" maxlength="15" placeholder="9999988888">
                                @error('country_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    (function () {
        const input    = document.getElementById('profileInput');
        const preview  = document.getElementById('avatarPreview');
        const initials = document.getElementById('avatarInitials');
        const first    = document.getElementById('first_name');
        const last     = document.getElementById('last_name');
        const nameEl   = document.getElementById('displayName');
        const original = preview.getAttribute('src');
        const hadImage = !preview.classList.contains('d-none');

        input.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            if (file.size > 2 * 1024 * 1024) {
                alert('Image must be 2 MB or smaller.');
                this.value = '';
                return;
            }
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('d-none');
            initials.classList.add('d-none');
        });

        function syncName() {
            nameEl.textContent = (first.value + ' ' + last.value).trim();
            initials.textContent = ((first.value[0] || '') + (last.value[0] || '')).toUpperCase();
        }
        first.addEventListener('input', syncName);
        last.addEventListener('input', syncName);

        document.getElementById('profileForm').addEventListener('reset', function () {
            setTimeout(function () {
                if (hadImage) { preview.src = original; }
                else { preview.classList.add('d-none'); initials.classList.remove('d-none'); }
                syncName();
            }, 0);
        });
    })();
</script>
@endsection