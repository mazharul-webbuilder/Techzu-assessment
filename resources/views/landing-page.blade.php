<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Ticket &amp; SLA Management System | Techzu Assessment</title>
    <!-- Favicons -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #090d16 0%, #0f172a 50%, #1e293b 100%);
            min-height: 100vh;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1rem;
        }
        .portal-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            transition: all 0.25s ease-in-out;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .portal-card:hover {
            transform: translateY(-6px);
            background: rgba(30, 41, 59, 0.95);
            border-color: rgba(255, 255, 255, 0.25);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
            color: inherit;
        }
        .icon-box {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.25rem;
        }
        .icon-customer { background: rgba(37, 99, 235, 0.2); color: #60a5fa; }
        .icon-agent { background: rgba(217, 119, 6, 0.2); color: #fbbf24; }
        .icon-admin { background: rgba(220, 38, 38, 0.2); color: #f87171; }
        .candidate-box {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            display: inline-flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .demo-cred {
            background: rgba(15, 23, 42, 0.75);
            border: 1px dashed rgba(255, 255, 255, 0.15);
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 1rem;
            margin-bottom: 1.25rem;
        }
        .demo-cred code {
            color: #f1f5f9;
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 980px;">

        <!-- Header -->
        <div class="text-center mb-4">
            <span class="badge bg-primary bg-opacity-25 text-info px-3 py-2 rounded-pill mb-3">
                <i class="bi bi-award me-1"></i> Technical Evaluation Assessment &bull; Techzu
            </span>
            <h1 class="fw-bold display-5 mb-2">Support Ticket &amp; SLA System</h1>
            <p class="text-secondary lead fs-6 mb-3">
                Multi-guard isolated authentication, business-hours SLA engine, and audit logging.
            </p>

            <!-- Candidate Metadata -->
            <div class="candidate-box text-start">
                <div>
                    <span class="text-secondary small text-uppercase">Candidate</span>
                    <div class="fw-bold text-white">Mazharul Islam</div>
                </div>
                <div class="border-start border-secondary border-opacity-50 ps-3">
                    <span class="text-secondary small text-uppercase">Email</span>
                    <div class="text-white small">mazharulislam1998r@gmail.com</div>
                </div>
                <div class="border-start border-secondary border-opacity-50 ps-3">
                    <span class="text-secondary small text-uppercase">Company</span>
                    <div class="fw-bold text-info">Techzu</div>
                </div>
            </div>
        </div>

        <!-- 3 Portals -->
        <div class="row g-4 mb-4">
            <!-- Customer Portal -->
            <div class="col-md-4">
                <a href="{{ route('customer.login') }}" class="portal-card p-4">
                    <div class="icon-box icon-customer">
                        <i class="bi bi-person"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Customer Portal</h5>
                    <p class="text-secondary small mb-auto">
                        Submit tickets, track resolution progress, and respond to support agent replies.
                    </p>

                    <div class="demo-cred">
                        <div>Email: <code>customer1@example.com</code></div>
                        <div>Password: <code>password</code></div>
                    </div>

                    <div class="text-primary small fw-semibold">
                        Enter Customer Portal <i class="bi bi-arrow-right ms-1"></i>
                    </div>
                </a>
            </div>

            <!-- Agent Portal -->
            <div class="col-md-4">
                <a href="{{ route('agent.login') }}" class="portal-card p-4">
                    <div class="icon-box icon-agent">
                        <i class="bi bi-headset"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Agent Portal</h5>
                    <p class="text-secondary small mb-auto">
                        Claim unassigned tickets, respond to customer issues, and add internal notes.
                    </p>

                    <div class="demo-cred">
                        <div>Email: <code>agent1@example.com</code></div>
                        <div>Password: <code>password</code></div>
                    </div>

                    <div class="text-warning small fw-semibold">
                        Enter Agent Portal <i class="bi bi-arrow-right ms-1"></i>
                    </div>
                </a>
            </div>

            <!-- Administrator Portal -->
            <div class="col-md-4">
                <a href="{{ route('admin.login') }}" class="portal-card p-4">
                    <div class="icon-box icon-admin">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Admin Portal</h5>
                    <p class="text-secondary small mb-auto">
                        System administration, SLA monitoring, agent management, and CSV data imports.
                    </p>

                    <div class="demo-cred">
                        <div>Email: <code>admin@example.com</code></div>
                        <div>Password: <code>password</code></div>
                    </div>

                    <div class="text-danger small fw-semibold">
                        Enter Admin Portal <i class="bi bi-arrow-right ms-1"></i>
                    </div>
                </a>
            </div>
        </div>

        <div class="text-center text-secondary small pt-3">
            Designed &amp; Developed by <strong>Mazharul Islam</strong> for <strong>Techzu</strong> &bull; Laravel 13
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
