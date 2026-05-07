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
            background-color: #2196F3;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .code {
            font-size: 36px;
            font-weight: bold;
            color: #2196F3;
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
            <h1>OnlineTaxiServices</h1>
            <p>Password Reset Request</p>
        </div>
        <br>
        <p>Hello <strong>{{ $name }}</strong>,</p>
        <p>We received a request to reset your password. Use the code below:</p>
        <div class="code">{{ $token }}</div>
        <p style="text-align: center; color: #666;">
            This code will expire in <strong>15 minutes</strong>
        </p>
        <p>If you did not request a password reset, please ignore this email.</p>
        <div class="footer">
            <p>OnlineTaxiServices App &copy; 2026</p>
        </div>
    </div>
</body>
</html>