<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') - Support Ticket &amp; SLA Management</title>
    <!-- Favicons -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <!-- Google Fonts: Inter & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --app-font-sans: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            --app-font-display: 'Plus Jakarta Sans', var(--app-font-sans);
            --portal-primary: #4f46e5;
            --portal-primary-hover: #4338ca;
            --portal-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            --portal-light-bg: #eef2ff;
            --portal-border: #e2e8f0;
            --portal-card-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05), 0 8px 10px -6px rgba(15, 23, 42, 0.02);
            --portal-card-shadow-hover: 0 20px 30px -10px rgba(15, 23, 42, 0.1), 0 10px 15px -5px rgba(15, 23, 42, 0.04);
        }

        @if(auth('agent')->check())
        :root {
            --portal-primary: #0d9488;
            --portal-primary-hover: #0f766e;
            --portal-gradient: linear-gradient(135deg, #0d9488 0%, #0284c7 100%);
            --portal-light-bg: #f0fdfa;
        }
        @elseif(auth('admin')->check())
        :root {
            --portal-primary: #e11d48;
            --portal-primary-hover: #be123c;
            --portal-gradient: linear-gradient(135deg, #e11d48 0%, #9333ea 100%);
            --portal-light-bg: #fff1f2;
        }
        @endif

        body {
            font-family: var(--app-font-sans);
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 0% 0%, rgba(79, 70, 229, 0.04) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(14, 165, 233, 0.04) 0px, transparent 50%),
                radial-gradient(at 50% 100%, rgba(244, 63, 94, 0.02) 0px, transparent 50%);
            background-attachment: fixed;
            min-height: 100vh;
            color: #1e293b;
            letter-spacing: -0.011em;
        }

        h1, h2, h3, h4, h5, h6, .display-font {
            font-family: var(--app-font-display);
            letter-spacing: -0.025em;
        }

        /* Modern App Header */
        .app-navbar {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            position: sticky;
            top: 0;
            z-index: 1030;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.2);
        }

        .navbar-brand-badge {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--portal-gradient);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);
        }

        .nav-portal-badge {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .app-nav-link {
            color: #94a3b8 !important;
            font-weight: 500;
            font-size: 0.88rem;
            padding: 0.5rem 0.9rem !important;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .app-nav-link:hover {
            color: #ffffff !important;
            background: rgba(255, 255, 255, 0.08);
        }

        .app-nav-link.active {
            color: #ffffff !important;
            background: rgba(255, 255, 255, 0.14);
            font-weight: 600;
        }

        .user-chip {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 30px;
            padding: 4px 14px 4px 6px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .avatar-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--portal-gradient);
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Modern UI Cards */
        .modern-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            box-shadow: var(--portal-card-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            overflow: hidden;
        }

        .modern-card-hover:hover {
            transform: translateY(-3px);
            box-shadow: var(--portal-card-shadow-hover);
            border-color: #cbd5e1;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1.25rem 1.5rem;
            box-shadow: var(--portal-card-shadow);
            position: relative;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--portal-card-shadow-hover);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }

        /* Buttons & Badges */
        .btn-portal-primary {
            background: var(--portal-gradient);
            color: #ffffff;
            border: none;
            font-weight: 600;
            border-radius: 0.65rem;
            padding: 0.625rem 1.25rem;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
            transition: all 0.2s ease;
        }

        .btn-portal-primary:hover {
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(79, 70, 229, 0.4);
            opacity: 0.95;
        }

        .btn-portal-primary:active {
            transform: translateY(0);
        }

        .badge-soft-primary {
            background-color: #eef2ff;
            color: #4f46e5;
            border: 1px solid rgba(79, 70, 229, 0.2);
            font-weight: 600;
        }

        .badge-soft-success {
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid rgba(5, 150, 105, 0.2);
            font-weight: 600;
        }

        .badge-soft-warning {
            background-color: #fffbeb;
            color: #d97706;
            border: 1px solid rgba(217, 119, 6, 0.2);
            font-weight: 600;
        }

        .badge-soft-danger {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid rgba(220, 38, 38, 0.2);
            font-weight: 600;
        }

        .badge-soft-info {
            background-color: #f0f9ff;
            color: #0284c7;
            border: 1px solid rgba(2, 132, 199, 0.2);
            font-weight: 600;
        }

        .badge-soft-secondary {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid rgba(100, 116, 139, 0.2);
            font-weight: 600;
        }

        /* Priority selector interactive cards */
        .priority-card {
            border: 2px solid #e2e8f0;
            border-radius: 0.85rem;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            background: #ffffff;
            text-align: left;
        }

        .priority-card:hover {
            border-color: #94a3b8;
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(0,0,0,0.04);
        }

        .priority-card.active {
            border-color: var(--portal-primary);
            background: #fafafa;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .priority-card.active-low {
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12);
        }

        .priority-card.active-medium {
            border-color: #0284c7;
            box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.12);
        }

        .priority-card.active-high {
            border-color: #f59e0b;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.12);
        }

        .priority-card.active-critical {
            border-color: #ef4444;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15);
        }

        /* Form Controls */
        .form-control, .form-select {
            border: 1px solid #d1d5db;
            border-radius: 0.65rem;
            padding: 0.65rem 0.95rem;
            font-size: 0.92rem;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--portal-primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
        }

        /* Table Aesthetics */
        .table-modern {
            margin-bottom: 0;
        }

        .table-modern thead th {
            background-color: #f8fafc;
            color: #64748b;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            padding: 0.85rem 1.25rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .table-modern tbody td {
            padding: 1rem 1.25rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 0.9rem;
        }

        .table-modern tbody tr:hover td {
            background-color: #f8fafc;
        }

        .table-modern tbody tr:last-child td {
            border-bottom: none;
        }

        /* Beacon Pulse Animation */
        .live-pulse {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #10b981;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulse-ring 1.8s infinite;
        }

        .live-pulse-danger {
            background-color: #ef4444;
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
            animation: pulse-ring 1.5s infinite;
        }

        @keyframes pulse-ring {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            70% {
                transform: scale(1);
                box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
            }
            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }
    </style>
    @yield('extra_css')
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="app-navbar py-2">
        <div class="container-fluid px-lg-4 px-3">
            <div class="d-flex align-items-center justify-content-between">
                <!-- Brand & Portal Identifier -->
                <div class="d-flex align-items-center gap-3">
                    <a class="text-decoration-none d-flex align-items-center gap-2" href="{{ auth('customer')->check() ? route('customer.dashboard') : (auth('agent')->check() ? route('agent.dashboard') : (auth('admin')->check() ? route('admin.dashboard') : route('home'))) }}">
                        <div class="navbar-brand-badge">
                            <i class="bi bi-shield-check fs-5"></i>
                        </div>
                        <div class="d-flex flex-column">
                            <span class="text-white fw-bold fs-6 lh-1">Helpdesk SLA</span>
                            <span class="nav-portal-badge mt-1">@yield('portal_name', 'Support System')</span>
                        </div>
                    </a>

                    @if(auth('customer')->check())
                        <div class="d-none d-md-flex align-items-center gap-2 ms-4">
                            <a class="app-nav-link {{ request()->routeIs('customer.tickets.index') || request()->routeIs('customer.dashboard') ? 'active' : '' }}" href="{{ route('customer.tickets.index') }}">
                                <i class="bi bi-inbox"></i> My Tickets
                            </a>
                            <a class="app-nav-link {{ request()->routeIs('customer.tickets.create') ? 'active' : '' }}" href="{{ route('customer.tickets.create') }}">
                                <i class="bi bi-plus-circle"></i> Submit Ticket
                            </a>
                        </div>
                    @elseif(auth('agent')->check())
                        <div class="d-none d-md-flex align-items-center gap-2 ms-4">
                            <a class="app-nav-link {{ request()->routeIs('agent.dashboard') ? 'active' : '' }}" href="{{ route('agent.dashboard') }}">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                            <a class="app-nav-link {{ request()->routeIs('agent.tickets.*') ? 'active' : '' }}" href="{{ route('agent.tickets.index') }}">
                                <i class="bi bi-inbox-fill"></i> Ticket Queue
                            </a>
                        </div>
                    @elseif(auth('admin')->check())
                        <div class="d-none d-md-flex align-items-center gap-2 ms-4">
                            <a class="app-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                            <a class="app-nav-link {{ request()->routeIs('admin.tickets.*') ? 'active' : '' }}" href="{{ route('admin.tickets.index') }}">
                                <i class="bi bi-ticket-perforated-fill"></i> All Tickets
                            </a>
                            <a class="app-nav-link {{ request()->routeIs('admin.imports.*') ? 'active' : '' }}" href="{{ route('admin.imports.index') }}">
                                <i class="bi bi-file-earmark-arrow-up-fill"></i> CSV Import
                            </a>
                        </div>
                    @endif
                </div>

                <!-- Right Side Actions & User Profile -->
                <div class="d-flex align-items-center gap-3">
                    <!-- In-App Notification Center -->
                    <div class="dropdown" id="notificationDropdownContainer">
                        <button class="btn btn-sm btn-link text-white text-decoration-none position-relative p-2 rounded-circle" type="button" id="notificationBellBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Notifications" style="background: rgba(255,255,255,0.08); width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center;">
                            <i class="bi bi-bell fs-6"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="notificationBadge" style="font-size: 0.65rem; padding: 0.25em 0.5em;">
                                0
                            </span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-0 rounded-4 mt-2" aria-labelledby="notificationBellBtn" style="width: 360px; max-width: 90vw; max-height: 480px; overflow: hidden; z-index: 1060;">
                            <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-bold small text-dark"><i class="bi bi-bell-fill text-primary me-1"></i> Notifications</span>
                                    <span class="badge bg-primary rounded-pill small" id="notificationCountBadge">0</span>
                                </div>
                                <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 text-muted small fw-medium" id="markAllReadBtn">
                                    Mark all as read
                                </button>
                            </div>
                            <div class="list-group list-group-flush overflow-auto" id="notificationList" style="max-height: 380px;">
                                <div class="text-center py-4 text-muted small" id="notificationEmpty">
                                    <i class="bi bi-bell-slash fs-4 d-block mb-1 text-secondary opacity-50"></i>
                                    No notifications yet
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="user-chip text-white d-none d-sm-inline-flex">
                        <div class="avatar-circle">
                            {{ strtoupper(substr(Auth::guard(auth('admin')->check() ? 'admin' : (auth('agent')->check() ? 'agent' : 'customer'))->user()?->name ?? 'U', 0, 1)) }}
                        </div>
                        <div class="d-flex flex-column text-start pe-2">
                            <span class="small fw-semibold lh-1">@yield('user_name', 'Authenticated User')</span>
                            <span class="text-white-50" style="font-size: 0.7rem;">Active</span>
                        </div>
                    </div>

                    @yield('logout_button')
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Wrapper -->
    <main class="container-fluid px-lg-4 px-3 py-4">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center" role="alert">
                <i class="bi bi-check-circle-fill fs-5 text-success me-2"></i>
                <div class="small fw-medium">{{ session('success') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 text-danger me-2"></i>
                <div class="small fw-medium">{{ session('error') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="container-fluid px-lg-4 px-3 py-4 text-center text-muted small border-top mt-5">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
            <div>
                <span>&copy; {{ date('Y') }} Support Ticket &amp; SLA Management Platform. All rights reserved.</span>
            </div>
            <div class="d-flex align-items-center gap-3 text-secondary">
                <span><span class="live-pulse me-1"></span> SLA Engine Active</span>
                <span>&bull;</span>
                <span>Business Hours: Mon-Fri 09:00 - 18:00</span>
            </div>
        </div>
    </footer>

    <!-- Floating In-App Live Alert Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
        <div id="liveNotificationToast" class="toast align-items-center text-bg-white border-0 shadow-lg rounded-3" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-start gap-2 py-3">
                    <div class="fs-4 text-primary lh-1 mt-1" id="toastIcon">
                        <i class="bi bi-bell-fill"></i>
                    </div>
                    <div>
                        <strong class="d-block mb-1 text-dark" id="toastTitle">Notification</strong>
                        <p class="mb-1 text-muted small" id="toastMessage"></p>
                        <a href="#" class="btn btn-sm btn-outline-primary py-0 px-2 text-decoration-none fw-medium" id="toastActionBtn" style="font-size: 0.75rem;">View</a>
                    </div>
                </div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const bellBadge = document.getElementById('notificationBadge');
            const countBadge = document.getElementById('notificationCountBadge');
            const list = document.getElementById('notificationList');
            const emptyEl = document.getElementById('notificationEmpty');
            const markAllBtn = document.getElementById('markAllReadBtn');
            const toastEl = document.getElementById('liveNotificationToast');
            const toastInstance = toastEl ? new bootstrap.Toast(toastEl, { delay: 6000 }) : null;

            let lastSeenIds = new Set();
            let initialLoadDone = false;

            function fetchNotifications() {
                fetch('{{ route("notifications.index") }}', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => {
                    if (!res.ok) throw new Error('Not authenticated');
                    return res.json();
                })
                .then(data => {
                    const unread = data.unread_count || 0;
                    if (unread > 0) {
                        bellBadge.textContent = unread > 99 ? '99+' : unread;
                        bellBadge.classList.remove('d-none');
                        countBadge.textContent = unread;
                    } else {
                        bellBadge.classList.add('d-none');
                        countBadge.textContent = '0';
                    }

                    if (data.notifications && data.notifications.length > 0) {
                        emptyEl.classList.add('d-none');
                        let html = '';
                        let hasNewUnreadForToast = false;
                        let latestNewItem = null;

                        data.notifications.forEach(item => {
                            if (!item.read && !lastSeenIds.has(item.id)) {
                                if (initialLoadDone) {
                                    hasNewUnreadForToast = true;
                                    latestNewItem = item;
                                }
                            }
                            lastSeenIds.add(item.id);

                            const bgClass = item.read ? 'bg-white' : 'bg-light';
                            const iconClass = item.type.includes('breach') ? 'bi-exclamation-octagon-fill text-danger' : 
                                              (item.type.includes('warning') ? 'bi-exclamation-triangle-fill text-warning' : 'bi-chat-dots-fill text-primary');

                            html += `
                                <a href="${item.url}" class="list-group-item list-group-item-action p-3 ${bgClass} border-bottom position-relative notification-item" data-id="${item.id}">
                                    <div class="d-flex align-items-start gap-2">
                                        <div class="mt-1"><i class="bi ${iconClass} fs-5"></i></div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="fw-semibold text-dark small">${escapeHtml(item.title)}</span>
                                                <span class="text-muted" style="font-size: 0.7rem;">${item.created_at}</span>
                                            </div>
                                            <p class="mb-0 text-secondary small text-truncate" style="max-width: 260px;">${escapeHtml(item.message)}</p>
                                        </div>
                                    </div>
                                </a>
                            `;
                        });
                        list.innerHTML = html;

                        // Trigger floating toast alert if user receives new notification while page is active
                        if (hasNewUnreadForToast && latestNewItem && toastInstance) {
                            document.getElementById('toastTitle').textContent = latestNewItem.title;
                            document.getElementById('toastMessage').textContent = latestNewItem.message;
                            document.getElementById('toastActionBtn').href = latestNewItem.url;
                            toastInstance.show();
                        }
                    } else {
                        list.innerHTML = `
                            <div class="text-center py-4 text-muted small" id="notificationEmpty">
                                <i class="bi bi-bell-slash fs-4 d-block mb-1 text-secondary opacity-50"></i>
                                No notifications yet
                            </div>
                        `;
                    }
                    initialLoadDone = true;
                })
                .catch(err => {
                    // Quiet fail if session expired or guest
                });
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text || '';
                return div.innerHTML;
            }

            // Mark single as read on click
            document.addEventListener('click', function(e) {
                const item = e.target.closest('.notification-item');
                if (item) {
                    const id = item.getAttribute('data-id');
                    fetch(`/notifications/${id}/read`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        }
                    }).catch(() => {});
                }
            });

            // Mark all read button
            if (markAllBtn) {
                markAllBtn.addEventListener('click', function() {
                    fetch('{{ route("notifications.read-all") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(() => {
                        bellBadge.classList.add('d-none');
                        countBadge.textContent = '0';
                        document.querySelectorAll('.notification-item').forEach(el => {
                            el.classList.remove('bg-light');
                            el.classList.add('bg-white');
                        });
                    });
                });
            }

            // Initial fetch + auto-poll every 15 seconds for active users
            fetchNotifications();
            setInterval(fetchNotifications, 15000);
        });
    </script>
    @yield('extra_js')
</body>
</html>
