@extends('admin.layouts.app')

@section('title', 'Alo Admin - Account Profile & Security')
@section('page_title', 'My Profile & Account Security')

@section('content')

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
        <!-- Left: Profile Summary Card -->
        <div>
            <div class="card" style="text-align: center;">
                <div class="admin-avatar" style="width: 80px; height: 80px; font-size: 2rem; margin: 0 auto 16px; background: linear-gradient(135deg, #0284c7, #6366f1);">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #0f172a;">{{ $user->name }}</h3>
                <div style="margin-top: 6px;">
                    <span class="badge badge-info">{{ ucwords(str_replace('_', ' ', $user->role)) }}</span>
                    <span class="badge badge-success" style="margin-left: 6px;">{{ ucfirst($user->status) }}</span>
                </div>

                <div style="border-top: 1px solid #e2e8f0; margin-top: 20px; padding-top: 18px; text-align: left; font-size: 0.88rem; line-height: 1.8;">
                    <div><strong>Email:</strong> {{ $user->email }}</div>
                    <div><strong>Phone:</strong> {{ $user->phone ?? 'Not set' }}</div>
                    <div><strong>Account Created:</strong> {{ $user->created_at->format('M d, Y') }}</div>
                </div>
            </div>

            <!-- Two-Factor Security Card -->
            <div class="card">
                <h4 class="card-title" style="margin-bottom: 8px;">Two-Factor Authentication</h4>
                <p style="font-size: 0.85rem; color: #64748b; line-height: 1.5; margin-bottom: 14px;">
                    Add an extra layer of security to your admin account when signing in.
                </p>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <div>
                        <div style="font-weight: 600; font-size: 0.88rem;">2FA Security</div>
                        <div style="font-size: 0.75rem; color: #10b981;">Enabled via Email OTP</div>
                    </div>
                    <span class="badge badge-success">ACTIVE</span>
                </div>
            </div>
        </div>

        <!-- Right: Edit Profile & Password Forms -->
        <div>
            <!-- Update Profile Info -->
            <div class="card" style="margin-bottom: 24px;">
                <h4 class="card-title" style="margin-bottom: 16px;">Update Personal Information</h4>
                <form action="{{ route('admin.profile.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}" placeholder="+91 9876543210">
                    </div>

                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="card">
                <h4 class="card-title" style="margin-bottom: 16px;">Change Password</h4>
                <form action="{{ route('admin.profile.password') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label class="form-label">Current Password *</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">New Password * (Min 6 chars)</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm New Password *</label>
                            <input type="password" name="new_password_confirmation" class="form-control" required minlength="6">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </div>
    </div>

@endsection
