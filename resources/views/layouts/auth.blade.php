<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Login') - Support Ticket & SLA System</title>
    <!-- Favicons -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .auth-card {
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-width: 440px;
            width: 100%;
            overflow: hidden;
        }
        .auth-header {
            padding: 2rem 2rem 1rem 2rem;
            text-align: center;
        }
        .auth-body {
            padding: 1rem 2rem 2rem 2rem;
        }
        .portal-badge {
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            font-weight: 700;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            display: inline-block;
            margin-bottom: 0.75rem;
        }
        .portal-customer { background-color: #dbeafe; color: #1e40af; }
        .portal-agent { background-color: #fef3c7; color: #92400e; }
        .portal-admin { background-color: #fee2e2; color: #991b1b; }
        .btn-portal-customer { background-color: #2563eb; color: #ffffff; }
        .btn-portal-customer:hover { background-color: #1d4ed8; color: #ffffff; }
        .btn-portal-agent { background-color: #d97706; color: #ffffff; }
        .btn-portal-agent:hover { background-color: #b45309; color: #ffffff; }
        .btn-portal-admin { background-color: #dc2626; color: #ffffff; }
        .btn-portal-admin:hover { background-color: #b91c1c; color: #ffffff; }
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.15);
            border-color: #2563eb;
        }
        .portal-switchers a {
            font-size: 0.825rem;
            text-decoration: none;
            color: #64748b;
            transition: color 0.15s;
        }
        .portal-switchers a:hover {
            color: #0f172a;
        }
        .demo-credential-box {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 0.5rem;
            padding: 0.75rem;
            font-size: 0.8rem;
            color: #475569;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        @yield('content')
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
