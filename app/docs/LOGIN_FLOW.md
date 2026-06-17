# Login Flow - Complete Guide

Step-by-step explanation of how user authentication and login works in LD Expert Bird.

---

## Overview Diagram

```
User visits app
        ↓
Redirects to /login
        ↓
User enters credentials
        ↓
Form submits POST /login
        ↓
Backend validates credentials
        ↓
Role-based redirect
        ↓
Session established
        ↓
User on dashboard
```

---

## Step-by-Step Flow

### Step 1: User Visits App (Any URL)

```
User visits: http://localhost:8000/
        ↓
App checks: Is user authenticated?
        ↓
No → Redirect to /login
        ↓
Yes → Load requested page
```

**Code Location:** `app/Http/Middleware/RedirectIfAuthenticated.php`

---

### Step 2: Login Page Loads

**URL:** `GET /login`

```
Browser requests: http://localhost:8000/login
        ↓
Laravel routes to: LoginController@create
        ↓
Returns: resources/views/auth/login.blade.php
        ↓
Form is displayed:
├─ Username field (input[name="username"])
├─ Password field (input[name="password"])
├─ Remember me checkbox
├─ Login button (@dusk="login-button")
└─ Forgot password link
```

**Controller:** `app/Http/Controllers/Auth/LoginController.php`

**View:** `resources/views/auth/login.blade.php`

```php
<form method="POST" action="{{ route('login') }}">
    @csrf
    
    <!-- Username -->
    <x-ui::input 
        id="username" 
        name="username" 
        type="text" 
        required 
        autofocus 
    />
    
    <!-- Password -->
    <x-ui::input 
        id="password" 
        name="password" 
        type="password" 
        required 
    />
    
    <!-- Remember Me -->
    <x-ui::checkbox 
        id="remember_me" 
        name="remember" 
    />
    
    <!-- Submit Button -->
    <x-ui::button type="submit" dusk="login-button">
        {{ __('Log in') }}
    </x-ui::button>
</form>
```

---

### Step 3: User Enters Credentials

**Input Fields:**

```
Username: develop.ldexpert@gmail.com
Password: Password123!
Remember: [checked/unchecked]

User clicks: "Log in" button
```

---

### Step 4: Form Submission

**Request:**

```
POST /login
Content-Type: application/x-www-form-urlencoded

_token=xxxxx
username=develop.ldexpert@gmail.com
password=Password123!
remember=on
```

**Route:** `POST /login` → `LoginController@store`

```php
// routes/web.php
Route::post('/login', [LoginController::class, 'store'])
    ->middleware('guest');
```

---

### Step 5: Backend Validation

**LoginController@store:**

```php
public function store(LoginRequest $request): RedirectResponse
{
    // 1. Validate credentials using LoginRequest
    $validated = $request->validate();
    
    // 2. Authenticate user
    if (Auth::attempt($validated, $request->boolean('remember'))) {
        // Success - continue to step 6
        return redirect()->intended(route('dashboard'));
    }
    
    // 3. Failed - redirect back with error
    return back()
        ->withInput($request->only('username'))
        ->withErrors(['username' => 'Invalid credentials']);
}
```

**LoginRequest (Form Request):**

```php
// app/Http/Requests/LoginRequest.php
public function rules(): array
{
    return [
        'username' => ['required', 'string'],
        'password' => ['required', 'string'],
    ];
}
```

---

### Step 6: Credential Verification

**Database Lookup:**

```sql
SELECT * FROM users 
WHERE email = 'develop.ldexpert@gmail.com'
OR username = 'develop.ldexpert@gmail.com'
LIMIT 1;
```

**Password Check:**

```php
// Laravel Auth Guard
if (Hash::check($plainPassword, $user->password)) {
    // ✅ Password matches
    $user authenticated = true;
} else {
    // ❌ Password incorrect
    return error;
}
```

---

### Step 7: Session Created

**Success Path:**

```php
// Laravel Auth::attempt() does:
1. Find user by email/username
2. Verify password hash
3. Create session:
   ├─ Set auth session guard
   ├─ Set remember-me cookie (if checked)
   └─ Create database session entry
4. Redirect to intended page
```

**Session Storage:**

```
Database Table: sessions
├─ id: session token
├─ user_id: 123 (authenticated user)
├─ ip_address: 192.168.1.1
├─ user_agent: Chrome/...
├─ payload: encrypted session data
├─ last_activity: timestamp
└─ expires_at: timestamp + 2 hours
```

---

### Step 8: Role-Based Redirect

**User Role Check:**

```php
$user = Auth::user(); // Get authenticated user

switch ($user->role) {
    case Role::ADMIN:
        return redirect('/admin/dashboard');
    
    case Role::THERAPIST:
        return redirect('/therapist/dashboard');
    
    case Role::STUDENT:
        return redirect('/student/dashboard');
    
    case Role::PARENT:
        return redirect('/parent/dashboard');
    
    default:
        return redirect('/');
}
```

**Redirect Logic:**

```
POST /login → Authenticated
        ↓
Check user->role
        ↓
    ┌───┬────────┬─────────┬────────┐
    │   │        │         │        │
    ▼   ▼        ▼         ▼        ▼
  ADMIN THERAPIST STUDENT PARENT  OTHER
    │   │        │         │        │
    └───┼────────┼─────────┼────────┘
        │
        ▼
Redirect to role dashboard
```

---

### Step 9: Dashboard Load

**Admin Dashboard Example:**

```
GET /admin/dashboard
        ↓
AdminDashboardController@show
        ↓
Retrieve dashboard data:
├─ Key metrics (schools, therapists, students)
├─ Critical alerts
├─ Recent activity
└─ Chart data
        ↓
Render: resources/views/admin/dashboard.blade.php
        ↓
Display "Welcome back" message + dashboard
```

---

### Step 10: Session Established

**User State:**

```
Auth::user()
├─ id: 1
├─ email: develop.ldexpert@gmail.com
├─ role: ADMIN
├─ timezone: America/Los_Angeles
├─ status: active
└─ ... other fields

Auth::check() = true
Auth::guard() = web (default guard)
Auth::id() = 1
```

---

## Key Components

### 1. Guards

```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',    // Use session driver
        'provider' => 'users',    // User provider
    ],
],
```

**What it does:** Determines HOW users are authenticated (session-based vs token-based)

---

### 2. Providers

```php
// config/auth.php
'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],
],
```

**What it does:** Specifies WHICH model to authenticate against

---

### 3. Passwords

```php
// config/auth.php
'passwords' => [
    'users' => [
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ],
],
```

**What it does:** Password reset configuration

---

### 4. Middleware

**Routes:**

```php
Route::middleware('auth')->group(function () {
    // Only authenticated users can access
    Route::get('/dashboard', ...);
    Route::get('/admin/dashboard', ...);
});

Route::middleware('guest')->group(function () {
    // Only unauthenticated users can access
    Route::get('/login', ...);
    Route::post('/login', ...);
});
```

---

## Data Flow

### Database Tables Involved

```
users table:
├─ id: 1
├─ email: develop.ldexpert@gmail.com
├─ username: develop.ldexpert
├─ password: $2y$12$hash...  (hashed)
├─ role: admin
├─ status: active
├─ timezone: America/Los_Angeles
├─ email_verified_at: timestamp
└─ remember_token: token (for remember-me)

sessions table:
├─ id: session_token
├─ user_id: 1
├─ ip_address: 192.168.1.1
├─ user_agent: Mozilla/5.0...
├─ payload: encrypted_data
├─ last_activity: timestamp
└─ expires_at: timestamp
```

### Session Lifetime

```
Session Created: 2026-06-08 15:00:00 UTC
        ↓
Expires: 2026-06-08 17:00:00 UTC (2 hours)
        ↓
If "Remember Me" checked:
   Cookie lasts: 30 days (configurable)
```

---

## Error Handling

### Invalid Credentials

```
POST /login with wrong password
        ↓
Laravel: Hash::check() fails
        ↓
Return back with errors
        ↓
Display: "Invalid credentials"
        ↓
Redirect to /login (form repopulated)
```

**Error Response:**

```php
return back()
    ->withInput($request->only('username'))
    ->withErrors(['username' => 'Invalid credentials']);
```

---

### Locked Account

```
User account status = 'inactive'
        ↓
Middleware rejects authentication
        ↓
"Account locked" message
```

---

### Rate Limiting

```
Failed login attempts:
├─ 3 failures → Wait 1 minute
├─ 5 failures → Wait 5 minutes
├─ 10 failures → Wait 15 minutes
```

**Throttle Config:**

```php
// config/auth.php
'throttle' => 60,  // Attempts per 60 minutes
```

---

## Timezone Handling

**After Login:**

```
User authenticated
        ↓
Load user->timezone
        ↓
All timestamps displayed in user's timezone
        ↓
All form submissions converted to UTC
```

**Example:**

```php
// User timezone: America/Los_Angeles
User submits form: "2026-06-08 15:00" (local time)
        ↓
Converted to UTC: "2026-06-08 22:00" (stored in DB)
        ↓
When displaying: Convert back to "2026-06-08 15:00" PT
```

---

## Logout Flow

**User clicks "Logout":**

```
GET /logout or POST /logout
        ↓
Auth::logout()
        ↓
Session deleted from database
        ↓
Session cookie cleared from browser
        ↓
Redirect to /login
```

**Code:**

```php
// routes/web.php
Route::post('/logout', function (Request $request) {
    Auth::logout();
    
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    
    return redirect('/login');
})->middleware('auth');
```

---

## Authentication State During Session

```
Browser has valid session cookie
        ↓
User makes request to /admin/dashboard
        ↓
Laravel middleware 'auth':
├─ Checks session in database
├─ Loads user from users table
├─ Sets Auth::user() = user
└─ Allows request to continue
```

---

## Security Features

```
✅ Password Hashing
   - Algorithm: bcrypt
   - Rounds: 12 (configurable)
   - Passwords never stored in plain text

✅ CSRF Protection
   - Token generated per session
   - Validated on POST requests
   - @csrf directive in forms

✅ Session Security
   - Session tokens are random & long
   - Stored in database (not files)
   - Expires after inactivity
   - IP address tied to session

✅ Remember Me
   - Token stored in database
   - Regenerated on new login
   - Expires after 30 days

✅ Rate Limiting
   - Brute-force protection
   - Failed attempts throttled
   - Account lockout after threshold

✅ Password Reset
   - Token expires after 60 minutes
   - One-time use only
   - Sent via email
```

---

## Complete Login Sequence (Dusk Test)

```php
it('TC-A001 admin can log in with valid credentials', function (): void {
    // Step 1: Visit login page
    $browser->visit('/login')
    
    // Step 2: Form is visible
        ->waitFor('input[name="username"]', 10)
    
    // Step 3: Fill credentials
        ->type('input[name="username"]', 'develop.ldexpert@gmail.com')
        ->type('input[name="password"]', 'Password123!')
    
    // Step 4: Submit form
        ->press('@login-button')
    
    // Step 5: Wait for redirect
        ->waitForLocation('/admin/dashboard', 10)
    
    // Step 6: Verify logged in
        ->assertSee('Welcome back');
});
```

---

## Summary

```
Login Flow Steps:
1. User visits /login
2. Form displayed with username & password fields
3. User enters credentials & clicks login
4. Backend validates against database
5. Password hash verified
6. Session created in database
7. User redirected based on role
8. Dashboard loads
9. User authenticated for session duration
10. Session expires after 2 hours (or remember-me period)
```

**Key Points:**
- ✅ Session-based authentication (not JWT/tokens)
- ✅ Credentials checked against hashed passwords
- ✅ Role determines redirect destination
- ✅ Timezone resolved from user profile
- ✅ Sessions expire after 2 hours
- ✅ Remember-me extends to 30 days
