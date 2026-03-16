<?php
require_once '../auth/auth.php';
require_once '../connection/database.php';
require_once '../utils/functions.php';

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Guide - Disposal System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <link href="../styles/supervisor.css" rel="stylesheet">
    <style>
        /* ======= GUIDE PAGE STYLES ======= */
        .guide-header {
            position: relative;
            background: linear-gradient(135deg, #181a1f 0%, #0f1117 100%);
            color: white;
            padding: 2rem 2.5rem;
            border-radius: 20px;
            margin-bottom: 1.25rem;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        .guide-header::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -5%;
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, rgba(209,244,58,0.12) 0%, transparent 65%);
            border-radius: 50%;
            pointer-events: none;
        }
        .guide-header::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: 10%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(59,130,246,0.08) 0%, transparent 65%);
            border-radius: 50%;
            pointer-events: none;
        }

        /* Sidebar Navigation */
        .doc-sidebar {
            width: 250px;
            background: #ffffff;
            padding: 1.25rem 0.75rem;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
        }
        .doc-sidebar .nav-label {
            font-size: 0.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #94a3b8;
            padding: 0 1rem;
            margin-bottom: 0.5rem;
            margin-top: 1rem;
        }
        .doc-sidebar .nav-label:first-child { margin-top: 0; }
        .doc-sidebar a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.7rem 1rem;
            color: #64748b;
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 2px;
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.2s ease;
        }
        .doc-sidebar a:hover {
            color: #1e293b;
            background: #f1f5f9;
        }
        .doc-sidebar a.active {
            background: #181a1f;
            color: #fff;
            box-shadow: 0 4px 12px rgba(24,26,31,0.2);
        }
        .doc-sidebar a ion-icon {
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .doc-section {
            background: #ffffff;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.03);
            display: none;
            animation: fadeSlideIn 0.35s ease forwards;
            flex: 1;
            min-height: 0;
            overflow-y: auto;
        }
        .doc-section.active-section { display: block; }

        @keyframes fadeSlideIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .doc-section h2 {
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #0f172a;
            font-size: 1.4rem;
        }

        /* Icon Badges */
        .icon-badge {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .icon-badge ion-icon { font-size: 1.4rem; }
        .icon-badge.blue { background: #eff6ff; color: #3b82f6; }
        .icon-badge.green { background: #f0fdf4; color: #22c55e; }
        .icon-badge.yellow { background: #fefce8; color: #eab308; }
        .icon-badge.purple { background: #f5f3ff; color: #8b5cf6; }
        .icon-badge.red { background: #fef2f2; color: #ef4444; }
        .icon-badge.teal { background: #f0fdfa; color: #14b8a6; }

        /* Step Cards */
        .step-card {
            background: #f8fafc;
            border: 1px solid transparent;
            padding: 0.85rem 1.15rem;
            border-radius: 14px;
            margin-bottom: 0.5rem;
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            transition: all 0.25s ease;
        }
        .step-card:hover {
            background: #fff;
            border-color: #e2e8f0;
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
            transform: translateY(-1px);
        }
        .step-number {
            background: linear-gradient(135deg, var(--accent-yellow) 0%, #a3e635 100%);
            color: #181a1f;
            font-weight: 800;
            font-size: 0.95rem;
            min-width: 30px;
            height: 30px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(163,230,53,0.3);
        }

        /* Callout */
        .callout {
            padding: 0.85rem 1.15rem;
            border-radius: 14px;
            margin: 0.75rem 0;
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
        }
        .callout.info { background: #eff6ff; border: 1px solid #dbeafe; }
        .callout.info .callout-icon { color: #3b82f6; }
        .callout.tip { background: #f0fdf4; border: 1px solid #bbf7d0; }
        .callout.tip .callout-icon { color: #22c55e; }
        .callout.warn { background: #fffbeb; border: 1px solid #fef3c7; }
        .callout.warn .callout-icon { color: #f59e0b; }
        .callout.danger { background: #fef2f2; border: 1px solid #fecaca; }
        .callout.danger .callout-icon { color: #ef4444; }
        .callout .callout-icon { font-size: 1.3rem; flex-shrink: 0; margin-top: 1px; }

        /* Flow Diagram */
        .flow-chain {
            display: flex;
            align-items: center;
            gap: 0;
            flex-wrap: nowrap;
            margin: 1rem 0;
            justify-content: center;
        }
        .flow-node {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.5rem 0.6rem;
            text-align: center;
            font-weight: 700;
            font-size: 0.68rem;
            color: #334155;
            min-width: 75px;
            transition: all 0.2s;
        }
        .flow-node:hover {
            border-color: var(--accent-yellow);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        .flow-node small {
            display: block;
            font-weight: 500;
            color: #94a3b8;
            font-size: 0.55rem;
            margin-top: 2px;
        }
        .flow-node ion-icon { font-size: 1rem; }
        .flow-arrow {
            color: #cbd5e1;
            font-size: 0.9rem;
            padding: 0 0.2rem;
            flex-shrink: 0;
        }

        /* Status Badges */
        .status-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.7rem 1rem;
            border-radius: 12px;
            margin-bottom: 0.4rem;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            font-weight: 700;
            padding: 0.35em 0.85em;
            border-radius: 20px;
            font-size: 0.72rem;
            letter-spacing: 0.5px;
            min-width: 85px;
            justify-content: center;
        }

        /* Feature Grid */
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 0.75rem;
            margin-top: 0.75rem;
        }
        .feature-tile {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 14px;
            padding: 1.25rem;
            transition: all 0.25s ease;
        }
        .feature-tile:hover {
            background: #fff;
            border-color: #e2e8f0;
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
            transform: translateY(-2px);
        }
        .feature-tile h6 {
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 0.3rem;
            color: #1e293b;
        }
        .feature-tile p {
            font-size: 0.78rem;
            color: #64748b;
            margin: 0;
            line-height: 1.45;
        }
        .feature-tile .tile-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.75rem;
        }
        .feature-tile .tile-icon ion-icon { font-size: 1.2rem; }

        /* Scrollbar */
        .doc-section::-webkit-scrollbar { width: 4px; }
        .doc-section::-webkit-scrollbar-track { background: transparent; }
        .doc-section::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

        /* Responsive */
        @media (max-width: 991px) {
            .doc-sidebar { display: none !important; }
            .guide-header { padding: 1.25rem; }
            .guide-header h1 { font-size: 1.5rem !important; }
            .doc-section { padding: 1.5rem; border-radius: 16px; }
        }

        /* Mobile tab nav - replaces sidebar on small screens */
        .mobile-guide-nav {
            display: none;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            gap: 6px;
            padding: 4px;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            margin-bottom: 0.75rem;
            flex-wrap: nowrap;
        }
        .mobile-guide-nav a {
            flex-shrink: 0;
            padding: 0.5rem 0.85rem;
            border-radius: 10px;
            font-size: 0.72rem;
            font-weight: 600;
            color: #64748b;
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .mobile-guide-nav a.active {
            background: #181a1f;
            color: #fff;
        }

        @media (max-width: 991px) {
            .mobile-guide-nav { display: flex; }
        }
        @media (max-width: 768px) {
            .guide-header { padding: 1rem; }
            .guide-header h1 { font-size: 1.3rem !important; }
            .guide-header p { font-size: 0.78rem !important; }
            .doc-section { padding: 1.25rem; border-radius: 14px; }
            .doc-section h2 { font-size: 1.15rem; }
            .feature-grid { grid-template-columns: 1fr; }
            .flow-chain { flex-wrap: wrap; gap: 4px; }
            .flow-node { min-width: 60px; font-size: 0.62rem; padding: 0.4rem 0.5rem; }
            .flow-node ion-icon { font-size: 0.85rem !important; }
            .flow-node small { font-size: 0.5rem; }
            .flow-arrow { font-size: 0.7rem; padding: 0 0.1rem; }
            .callout { flex-direction: column; gap: 0.4rem; }
            .step-card { padding: 0.7rem 0.9rem; }
        }
        @media (max-width: 480px) {
            .guide-header h1 { font-size: 1.1rem !important; }
            .doc-section { padding: 1rem; }
            .doc-section h2 { font-size: 1rem; gap: 8px; }
            .icon-badge { width: 34px; height: 34px; }
            .icon-badge ion-icon { font-size: 1.1rem; }
            .mobile-guide-nav a { font-size: 0.65rem; padding: 0.4rem 0.7rem; }
        }
    </style>
</head>
<body>

<div class="dashboard-wrapper">
    <?php include '../components/sidebar.php'; ?>

    <main class="main-content">
        <?php include '../components/topbar.php'; ?>

        <div class="pe-2 mt-2" style="flex-grow: 1; height: calc(100vh - 80px); display: flex; flex-direction: column;">

            <!-- Header -->
            <div class="guide-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div style="z-index: 1;">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge rounded-pill text-dark" style="background: var(--accent-yellow); padding: 0.4em 0.8em; font-weight: 800; letter-spacing: 1px; font-size: 0.65rem;">USER GUIDE</span>
                    </div>
                    <h1 style="font-size: 2rem; font-weight: 800; letter-spacing: -1px; margin: 0; line-height: 1.1;">User Guidelines</h1>
                    <p style="font-size: 0.88rem; color: #94a3b8; max-width: 500px; margin-top: 6px; margin-bottom: 0;">Everything you need to know about submitting logs, the approval chain, and system settings.</p>
                </div>
            </div>

            <div class="d-flex gap-4" style="flex-grow: 1; min-height: 0; overflow: hidden;">

                <!-- Sidebar Nav (Desktop) -->
                <div style="flex-shrink: 0;" class="d-none d-lg-block">
                    <div class="doc-sidebar h-100">
                        <div class="nav-label">Getting Started</div>
                        <a href="#login" class="active"><ion-icon name="log-in-outline"></ion-icon> Login</a>
                        <a href="#dashboard"><ion-icon name="grid-outline"></ion-icon> Dashboard</a>

                        <div class="nav-label">Core Workflow</div>
                        <a href="#submit"><ion-icon name="add-circle-outline"></ion-icon> Submitting Logs</a>
                        <a href="#approval"><ion-icon name="git-network-outline"></ion-icon> Approval Flow</a>
                        <a href="#statuses"><ion-icon name="pricetag-outline"></ion-icon> Status Guide</a>
                        <a href="#history"><ion-icon name="time-outline"></ion-icon> History Logs</a>

                        <div class="nav-label">Administration</div>
                        <a href="#settings"><ion-icon name="settings-outline"></ion-icon> Settings</a>
                        <a href="#roles"><ion-icon name="people-outline"></ion-icon> Roles & Access</a>
                    </div>
                </div>

                <!-- Content Column (mobile nav + sections) -->
                <div class="flex-grow-1" style="min-height: 0; display: flex; flex-direction: column; max-width: 100%;">
                    <!-- Mobile Tab Nav (shown on tablet/phone only) -->
                    <div class="mobile-guide-nav" id="mobileGuideNav">
                        <a href="#login" class="active">Login</a>
                        <a href="#dashboard">Dashboard</a>
                        <a href="#submit">Submitting</a>
                        <a href="#approval">Approval</a>
                        <a href="#statuses">Status</a>
                        <a href="#history">History</a>
                        <a href="#settings">Settings</a>
                        <a href="#roles">Roles</a>
                    </div>

                    <!-- Content Area -->
                <div class="flex-grow-1" style="max-width: 100%; min-height: 0; display: flex; flex-direction: column;">

                    <!-- =============== LOGIN =============== -->
                    <div id="login" class="doc-section active-section">
                        <h2><div class="icon-badge blue"><ion-icon name="log-in"></ion-icon></div> Logging In</h2>
                        <p class="text-muted mb-3" style="font-size: 0.88rem;">Access the system using your official LRNPH system credentials.</p>

                        <div class="step-card">
                            <div class="step-number">1</div>
                            <div>
                                <h6 class="fw-bold mb-1">Enter your Biometrics ID</h6>
                                <p class="text-muted mb-0" style="font-size: 0.83rem; line-height: 1.5;">At the login screen, enter your official LRNPH Biometrics ID (e.g., <code class="bg-light px-2 py-0 rounded" style="color: #3b82f6; border: 1px solid #bfdbfe;">3096</code>) as your username.</p>
                            </div>
                        </div>
                        <div class="step-card">
                            <div class="step-number">2</div>
                            <div>
                                <h6 class="fw-bold mb-1">Enter your Password</h6>
                                <p class="text-muted mb-0" style="font-size: 0.83rem; line-height: 1.5;">Use the same password you use for the LRNPH portal. Your identity and role are automatically loaded from the system.</p>
                            </div>
                        </div>

                        <div class="callout tip mt-3">
                            <div class="callout-icon"><ion-icon name="finger-print"></ion-icon></div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1" style="font-size: 0.85rem;">Automatic Identity</h6>
                                <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.45;">Your full name, role, phase, and area are fetched automatically from the master list. No manual setup needed.</p>
                            </div>
                        </div>
                    </div>

                    <!-- =============== DASHBOARD =============== -->
                    <div id="dashboard" class="doc-section">
                        <h2><div class="icon-badge green"><ion-icon name="grid"></ion-icon></div> Dashboard Overview</h2>
                        <p class="text-muted mb-3" style="font-size: 0.88rem;">Your central command center for monitoring waste metrics and trends.</p>

                        <div class="feature-grid">
                            <div class="feature-tile">
                                <div class="tile-icon" style="background: #eff6ff; color: #3b82f6;"><ion-icon name="stats-chart"></ion-icon></div>
                                <h6>Waste Analytics</h6>
                                <p>View monthly, weekly, and daily waste trends with interactive charts.</p>
                            </div>
                            <div class="feature-tile">
                                <div class="tile-icon" style="background: #fefce8; color: #eab308;"><ion-icon name="pie-chart"></ion-icon></div>
                                <h6>Category Breakdown</h6>
                                <p>See waste distribution across product categories at a glance.</p>
                            </div>
                            <div class="feature-tile">
                                <div class="tile-icon" style="background: #f0fdf4; color: #22c55e;"><ion-icon name="trending-up"></ion-icon></div>
                                <h6>Real-time KPIs</h6>
                                <p>Track total waste in KG & PCS with comparison to previous periods.</p>
                            </div>
                            <div class="feature-tile">
                                <div class="tile-icon" style="background: #f5f3ff; color: #8b5cf6;"><ion-icon name="calendar"></ion-icon></div>
                                <h6>Time Scale Toggle</h6>
                                <p>Switch between daily, weekly, and monthly views for deeper analysis.</p>
                            </div>
                        </div>
                    </div>

                    <!-- =============== SUBMIT =============== -->
                    <div id="submit" class="doc-section">
                        <h2><div class="icon-badge green"><ion-icon name="add-circle"></ion-icon></div> Submitting a Waste Log</h2>

                        <div class="callout info">
                            <div class="callout-icon"><ion-icon name="bulb"></ion-icon></div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1" style="font-size: 0.85rem;">Dynamic Fields</h6>
                                <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.45;">Dropdown options change dynamically based on your previous selections. Fill them in order from top to bottom.</p>
                            </div>
                        </div>

                        <div class="step-card"><div class="step-number">1</div><div><h6 class="fw-bold mb-1">Select Categories</h6><p class="text-muted mb-0" style="font-size: 0.83rem;">Choose Shift, Area, Phase, Log Type, Category, and Product from the dropdown menus.</p></div></div>
                        <div class="step-card"><div class="step-number">2</div><div><h6 class="fw-bold mb-1">Input Quantities</h6><p class="text-muted mb-0" style="font-size: 0.83rem;">Enter waste in Pieces (PCS), Kilograms (KG), or both depending on the category.</p></div></div>
                        <div class="step-card"><div class="step-number">3</div><div><h6 class="fw-bold mb-1">Specify Reason</h6><p class="text-muted mb-0" style="font-size: 0.83rem;">Type a clear, concise justification for the waste to aid review by approvers.</p></div></div>
                        <div class="step-card"><div class="step-number">4</div><div><h6 class="fw-bold mb-1">Submit</h6><p class="text-muted mb-0" style="font-size: 0.83rem;">Click <strong style="color: var(--accent-yellow); background: #202227; padding: 2px 6px; border-radius: 5px; font-size: 0.78rem;">Submit Daily Log</strong> to forward it into the approval chain.</p></div></div>
                    </div>

                    <!-- =============== APPROVAL FLOW =============== -->
                    <div id="approval" class="doc-section">
                        <h2><div class="icon-badge purple"><ion-icon name="git-network"></ion-icon></div> Approval Workflow</h2>
                        <p class="text-muted mb-2" style="font-size: 0.88rem;">Every waste log follows a strict, multi-tier approval chain before it becomes official.</p>

                        <!-- Flow Diagram -->
                        <div class="flow-chain">
                            <div class="flow-node">
                                <ion-icon name="person-outline" style="font-size: 1.2rem; color: #3b82f6;"></ion-icon><br>
                                Supervisor<small>Submits Log</small>
                            </div>
                            <div class="flow-arrow">→</div>
                            <div class="flow-node">
                                <ion-icon name="briefcase-outline" style="font-size: 1.2rem; color: #f59e0b;"></ion-icon><br>
                                Manager<small>Step 1 (Phase-bound)</small>
                            </div>
                            <div class="flow-arrow">→</div>
                            <div class="flow-node" style="border-color: var(--accent-yellow);">
                                <ion-icon name="eye-outline" style="font-size: 1.2rem; color: #ef4444;"></ion-icon><br>
                                Int. Security<small>Step 2 (Global)</small>
                            </div>
                        </div>

                        <div class="callout warn mt-2">
                            <div class="callout-icon"><ion-icon name="lock-closed"></ion-icon></div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1" style="font-size: 0.85rem;">Phase-Bound Enforcement</h6>
                                <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.45;"><strong>Supervisors</strong> and <strong>Managers</strong> can only approve requests from their own assigned Phase. A Phase 1 Supervisor cannot approve Phase 2 logs.</p>
                            </div>
                        </div>
                        <div class="callout info">
                            <div class="callout-icon"><ion-icon name="earth"></ion-icon></div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1" style="font-size: 0.85rem;">Global Access Roles</h6>
                                <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.45;"><strong>Quality Control</strong>, <strong>Internal Security</strong>, and <strong>Admin</strong> have visibility across all phases and can approve any request that reaches their step.</p>
                            </div>
                        </div>
                    </div>

                    <!-- =============== STATUS GUIDE =============== -->
                    <div id="statuses" class="doc-section">
                        <h2><div class="icon-badge yellow"><ion-icon name="pricetag"></ion-icon></div> Status Guide</h2>
                        <p class="text-muted mb-3" style="font-size: 0.88rem;">Each waste log carries a status that reflects where it is in the approval chain.</p>

                        <div class="status-row" style="background: #fefce8; border: 1px solid #fef3c7;">
                            <span class="status-pill" style="background: var(--accent-yellow); color: #000;">PENDING</span>
                            <span class="text-muted fw-medium" style="font-size: 0.82rem;">Waiting for the next approver in the chain.</span>
                        </div>
                        <div class="status-row" style="background: #eff6ff; border: 1px solid #dbeafe;">
                            <span class="status-pill" style="background: #3b82f6; color: #fff;">IN PROGRESS</span>
                            <span class="text-muted fw-medium" style="font-size: 0.82rem;">Partially approved — still climbing the chain.</span>
                        </div>
                        <div class="status-row" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                            <span class="status-pill" style="background: #22c55e; color: #fff;">APPROVED</span>
                            <span class="text-muted fw-medium" style="font-size: 0.82rem;">Fully approved by both tiers. Officially recorded.</span>
                        </div>
                        <div class="status-row" style="background: #fef2f2; border: 1px solid #fecaca;">
                            <span class="status-pill" style="background: #ef4444; color: #fff;">DECLINED</span>
                            <span class="text-muted fw-medium" style="font-size: 0.82rem;">Rejected by an approver. Will not be counted.</span>
                        </div>
                    </div>

                    <!-- =============== HISTORY =============== -->
                    <div id="history" class="doc-section">
                        <h2><div class="icon-badge yellow"><ion-icon name="time"></ion-icon></div> History Logs</h2>
                        <p class="text-muted mb-3" style="font-size: 0.88rem;">View all past submissions and their current approval status. Available to Supervisors and above.</p>

                        <div class="step-card"><div class="step-number">1</div><div><h6 class="fw-bold mb-1">View All Entries</h6><p class="text-muted mb-0" style="font-size: 0.83rem;">The History page displays a comprehensive table of all waste log entries with full details.</p></div></div>
                        <div class="step-card"><div class="step-number">2</div><div><h6 class="fw-bold mb-1">Filter & Search</h6><p class="text-muted mb-0" style="font-size: 0.83rem;">Use the <strong>Options</strong> button to filter by Date Range, Area, Phase, and Status.</p></div></div>
                        <div class="step-card"><div class="step-number">3</div><div><h6 class="fw-bold mb-1">Track Progress</h6><p class="text-muted mb-0" style="font-size: 0.83rem;">Each entry shows its current step in the approval chain so you can see exactly where a log is in the process.</p></div></div>

                        <div class="callout info mt-2">
                            <div class="callout-icon"><ion-icon name="download-outline"></ion-icon></div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1" style="font-size: 0.85rem;">Export Capability</h6>
                                <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.45;">You can export filtered data for reporting and analysis purposes.</p>
                            </div>
                        </div>
                    </div>

                    <!-- =============== SETTINGS =============== -->
                    <div id="settings" class="doc-section">
                        <h2><div class="icon-badge teal"><ion-icon name="settings"></ion-icon></div> System Settings</h2>
                        <p class="text-muted mb-3" style="font-size: 0.88rem;">Manage master data tables and personnel assignments. Restricted to authorized roles only.</p>

                        <div class="feature-grid">
                            <div class="feature-tile">
                                <div class="tile-icon" style="background: #f5f3ff; color: #8b5cf6;"><ion-icon name="people"></ion-icon></div>
                                <h6>Personnel Assignment</h6>
                                <p>Assign staff to roles, phases, and areas using their Biometrics ID. Full names are auto-fetched.</p>
                            </div>
                            <div class="feature-tile">
                                <div class="tile-icon" style="background: #fefce8; color: #eab308;"><ion-icon name="pricetags"></ion-icon></div>
                                <h6>Product Categories</h6>
                                <p>Add or remove product categories and descriptions used in the waste log form.</p>
                            </div>
                            <div class="feature-tile">
                                <div class="tile-icon" style="background: #f0fdfa; color: #14b8a6;"><ion-icon name="layers"></ion-icon></div>
                                <h6>Phases & Areas</h6>
                                <p>Manage production phases and work areas that structure the entire system.</p>
                            </div>
                            <div class="feature-tile">
                                <div class="tile-icon" style="background: #eff6ff; color: #3b82f6;"><ion-icon name="id-card"></ion-icon></div>
                                <h6>Roles & Shifts</h6>
                                <p>Configure roles for the approval chain and shift schedules for logging.</p>
                            </div>
                        </div>

                        <div class="callout warn mt-3">
                            <div class="callout-icon"><ion-icon name="warning"></ion-icon></div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1" style="font-size: 0.85rem;">Handle with Care</h6>
                                <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.45;">Deleting a Phase or Role here will affect the entire approval chain and all users assigned to it. Always coordinate with the IT team first.</p>
                            </div>
                        </div>
                    </div>

                    <!-- =============== ROLES =============== -->
                    <div id="roles" class="doc-section">
                        <h2><div class="icon-badge red"><ion-icon name="people"></ion-icon></div> Roles & Access Levels</h2>
                        <p class="text-muted mb-3" style="font-size: 0.88rem;">Each user is assigned a single role that determines what they can see and do in the system.</p>

                        <div class="table-responsive">
                            <table class="table table-hover mb-0" style="font-size: 0.83rem;">
                                <thead>
                                    <tr style="background: #f8fafc;">
                                        <th class="fw-bold text-muted border-0 py-2 px-3" style="font-size: 0.72rem; letter-spacing: 0.5px;">ROLE</th>
                                        <th class="fw-bold text-muted border-0 py-2 px-3" style="font-size: 0.72rem; letter-spacing: 0.5px;">SCOPE</th>
                                        <th class="fw-bold text-muted border-0 py-2 px-3" style="font-size: 0.72rem; letter-spacing: 0.5px;">PERMISSIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="px-3 py-2 fw-bold">Supervisor / TL</td>
                                        <td class="px-3 py-2"><span class="badge bg-primary bg-opacity-10 text-primary">Own Phase</span></td>
                                        <td class="px-3 py-2 text-muted">Create/Submit waste logs, track own status</td>
                                    </tr>
                                    <tr>
                                        <td class="px-3 py-2 fw-bold">Manager</td>
                                        <td class="px-3 py-2"><span class="badge bg-primary bg-opacity-10 text-primary">Own Phase</span></td>
                                        <td class="px-3 py-2 text-muted">Step 1 Approval for respective phase</td>
                                    </tr>
                                    <tr style="background: #fefce8;">
                                        <td class="px-3 py-2 fw-bold">Internal Security</td>
                                        <td class="px-3 py-2"><span class="badge bg-success bg-opacity-10 text-success">Global</span></td>
                                        <td class="px-3 py-2 text-muted">Step 2 Final Approval across all phases</td>
                                    </tr>
                                    <tr style="background: #f8fafc;">
                                        <td class="px-3 py-2 fw-bold">Super Admin / IT</td>
                                        <td class="px-3 py-2"><span class="badge bg-warning bg-opacity-25 text-dark">Global</span></td>
                                        <td class="px-3 py-2 text-muted">Full system access and settings management</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="callout danger mt-3">
                            <div class="callout-icon"><ion-icon name="shield-checkmark"></ion-icon></div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1" style="font-size: 0.85rem;">Security Note</h6>
                                <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.45;">All access control is enforced server-side. Even if a page URL is accessed directly, the backend will reject unauthorized requests.</p>
                            </div>
                        </div>
                    </div>

                </div>
                </div> <!-- end content column wrapper -->
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const desktopLinks = document.querySelectorAll('.doc-sidebar a');
    const mobileLinks = document.querySelectorAll('.mobile-guide-nav a');
    const sections = document.querySelectorAll('.doc-section');
    const allLinks = [...desktopLinks, ...mobileLinks];

    function switchSection(targetId) {
        // Update desktop sidebar
        desktopLinks.forEach(n => n.classList.remove('active'));
        desktopLinks.forEach(n => {
            if (n.getAttribute('href') === '#' + targetId) n.classList.add('active');
        });
        // Update mobile nav
        mobileLinks.forEach(n => n.classList.remove('active'));
        mobileLinks.forEach(n => {
            if (n.getAttribute('href') === '#' + targetId) n.classList.add('active');
        });
        // Switch section
        sections.forEach(s => {
            s.classList.remove('active-section');
            if (s.id === targetId) {
                setTimeout(() => s.classList.add('active-section'), 10);
            }
        });
    }

    allLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            switchSection(targetId);
        });
    });
});
</script>
</body>
</html>
