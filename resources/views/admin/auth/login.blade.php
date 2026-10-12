<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alo Admin - Sign In</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #0f172a;
        }

        .login-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            width: 100%;
            max-width: 440px;
            padding: 40px 36px;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #0284c7, #38bdf8);
        }

        .brand-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-wrap {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: #0284c7;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
            box-shadow: 0 10px 15px -3px rgba(2, 132, 199, 0.3);
        }

        .logo-wrap img {
            width: 36px;
            height: 36px;
            object-fit: contain;
        }

        .brand-header h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.02em;
        }

        .brand-header p {
            font-size: 0.88rem;
            color: #64748b;
            margin-top: 4px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            font-size: 0.92rem;
            color: #0f172a;
            outline: none;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: #0284c7;
            box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.15);
        }

        .btn-submit {
            width: 100%;
            padding: 13px;
            border-radius: 10px;
            background: #0284c7;
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
            margin-top: 10px;
        }

        .btn-submit:hover {
            background: #0369a1;
            box-shadow: 0 6px 20px rgba(2, 132, 199, 0.4);
        }

        .demo-credentials-box {
            margin-top: 24px;
            padding: 14px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            font-size: 0.8rem;
            color: #475569;
        }

        .demo-credentials-box strong {
            color: #0f172a;
        }

        .error-message {
            background: #fef2f2;
            color: #991b1b;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 18px;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="brand-header">
            <div class="logo-wrap">
                <img src="{{ asset('alo_logo.png') }}" alt="Alo Logo">
            </div>
            <h1>Alo Admin Portal</h1>
            <p>Please sign in to access your dashboard.</p>
        </div>

        @if($errors->any())
            <div class="error-message">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('admin.login.submit') }}" method="POST">
            @csrf
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="{{ old('email', 'admin@alo.com') }}" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" value="admin123" required>
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; font-size: 0.82rem;">
                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="checkbox" name="remember" checked>
                    <span>Remember me</span>
                </label>
            </div>

            <button type="submit" class="btn-submit">Sign In to Dashboard</button>
        </form>

        <div class="demo-credentials-box">
            <div style="font-weight: 600; margin-bottom: 4px; color: #0284c7;">Super Admin Access:</div>
            <div>Email: <strong>admin@alo.com</strong></div>
            <div>Password: <strong>admin123</strong></div>
        </div>
    </div>

</body>
</html>
