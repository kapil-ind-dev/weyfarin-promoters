<?php
namespace App\Http\Controllers;
use App\Models\AdminModel;
use App\Models\Promoter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function login()
    {
        return view('login');
    }

    public function login_post(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'email'],
            'password' => ['required', 'string'],
            'captcha' => ['required', 'captcha'],
        ], [
            'captcha.required' => 'Please enter the CAPTCHA.',
            'captcha.captcha' => 'The CAPTCHA is invalid.',
        ]);

        $key = 'admin-login:' . Str::lower($validated['username']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'status' => false,
                'message' => 'Too many login attempts. Please try again later.',
            ], 429);
        }

        if (Auth::guard('promoter')->attempt([
            'email' => $validated['username'],
            'password' => $validated['password'],
        ])) {
            RateLimiter::clear($key);
            $request->session()->regenerate();

            return response()->json([
                'status' => true,
                'message' => 'User login successful!',
                'redirect' => route('dashboard'),
                'admin' => Auth::guard('promoter')->user(),
            ], 200);
        }

        RateLimiter::hit($key, 60);

        return response()->json([
            'status' => false,
            'message' => 'Invalid credentials!',
        ], 401);
    }

    public function signup()
    {
        return view('signup');
    }

    public function signup_post(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:155'],
            'last_name' => ['required', 'string', 'max:155'],
            'email' => ['required', 'email', 'max:155', 'unique:promoters,email'],
            'phone' => ['required', 'string', 'max:55'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
            'terms' => ['accepted'],
            'captcha' => ['required', 'captcha'],
        ], [
            'email.unique' => 'This email is already registered.',
            'password.confirmed' => 'Password and confirm password do not match.',
            'terms.accepted' => 'Please accept the Terms and Privacy Policy.',
            'captcha.required' => 'Please enter the CAPTCHA.',
            'captcha.captcha' => 'The CAPTCHA is invalid.',
        ]);

        Promoter::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => Str::lower($validated['email']),
            'phone' => $validated['phone'],
            'password' => $validated['password'],
            'status' => '0',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Account created successfully. You can now log in.',
        ], 201);
    }
    public function profile(){
        $user_id = Auth::id();
        $user = Promoter::findOrFail($user_id);
        return view('pages.profile', compact('user'));
    }
   public function profile_update(Request $request)
    {
        $user = Auth::user();
        // dd($user);
        $validated = $request->validate([
            'first_name'   => ['required', 'string', 'max:50'],
            'last_name'    => ['required', 'string', 'max:50'],
            'email'        => ['required', 'email', 'max:255', Rule::unique('promoters', 'email')->ignore($user->id)],
            'country_code' => ['nullable', 'string', 'regex:/^\+\d{1,4}$/'],
            'phone'        => ['nullable', 'digits_between:6,15'],
            'profile'      => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], // 2 MB
        ]);
    
        // Handle profile photo upload
        if ($request->hasFile('profile')) {
            // delete the old photo if there is one
            if ($user->profile && Storage::disk('public')->exists($user->profile)) {
                Storage::disk('public')->delete($user->profile);
            }
    
            $validated['profile'] = $request->file('profile')->store('profiles', 'public');
        } else {
            // no new file: keep the existing photo
            unset($validated['profile']);
        }
    
        $user->update($validated);
    
        return redirect()->back()->with('success', 'Profile updated successfully.');
    }
    
    public function change_password()
    {
        return view('pages.change-password'); // resources/views/change-password.blade.php
    }
    
    public function password_update(Request $request)
    {
        $user = auth()->user();
    
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => [
                'required',
                'string',
                'confirmed',                 // matches new_password_confirmation
                'different:current_password',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ], [
            'new_password.confirmed' => 'The new password confirmation does not match.',
            'new_password.different' => 'The new password must be different from the current password.',
        ]);
    
        // Check the current password manually so we can attach the error to that field
        if (! Hash::check($request->current_password, $user->password)) {
            return back()
                ->withErrors(['current_password' => 'The current password is incorrect.'])
                ->withInput();
        }
    
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);
    
        return redirect()->route('change-password.show')
            ->with('success', 'Password updated successfully.');
    }
    
    public function logout(Request $request)
    {
        Auth::guard('promoter')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Logged out successfully.');
    }

}