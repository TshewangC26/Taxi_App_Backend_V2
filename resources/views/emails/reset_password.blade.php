<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            margin: 0 auto;
        }
        .header {
            background-color: #F6A800;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .role-badge {
            display: inline-block;
            background-color: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            margin-top: 6px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .code {
            font-size: 36px;
            font-weight: bold;
            color: #F6A800;
            text-align: center;
            padding: 20px;
            letter-spacing: 8px;
        }
        .footer {
            text-align: center;
            color: #999;
            font-size: 12px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Easy Ride</h1>
            <p>Password Reset Request</p>
            {{-- ✅ Show role badge --}}
            <span class="role-badge">
                {{ ucfirst($userType) }} Account
            </span>
        </div>
        <br>
        <p>Hello <strong>{{ $name }}</strong>,</p>
        <p>
            We received a request to reset the password for your
            <strong>{{ ucfirst($userType) }}</strong> account.
            Use the code below:
        </p>
        <div class="code">{{ $token }}</div>
        <p style="text-align: center; color: #666;">
            This code will expire in <strong>5 minutes</strong>
        </p>
        <div class="footer">
            <p>Easy Ride App &copy; 2026</p>
        </div>
    </div>
</body>
</html>