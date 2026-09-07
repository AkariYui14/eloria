<?php

session_start();

require_once "../db.php";


/* =========================================
   ADMIN ACCESS PROTECTION
========================================= */

if (!isset($_SESSION["user_id"])) {

    header("Location: ../login.php");
    exit;
}


/* =========================================
   GET CURRENT USER
========================================= */

$user_id = (int) $_SESSION["user_id"];

$stmt = $conn->prepare("
    SELECT
        first_name,
        last_name,
        email,
        is_admin
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

$stmt->close();


/* =========================================
   CHECK ADMIN STATUS
========================================= */

if (!$user || (int) $user["is_admin"] !== 1) {

    header("Location: ../index.php");
    exit;
}


/* =========================================
   DASHBOARD STATISTICS
========================================= */

$total_products = 0;
$active_products = 0;
$total_orders = 0;
$pending_orders = 0;
$total_customers = 0;
$total_sales = 0;


/* =========================================
   TOTAL PRODUCTS
   Only active products are counted
========================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM products
    WHERE is_active = 1
");

if ($result) {

    $row = $result->fetch_assoc();

    $total_products = (int) $row["total"];

    $result->free();
}


/* =========================================
   ACTIVE PRODUCTS
========================================= */

$active_products = $total_products;


/* =========================================
   TOTAL ORDERS
========================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM orders
");

if ($result) {

    $row = $result->fetch_assoc();

    $total_orders = (int) $row["total"];

    $result->free();
}


/* =========================================
   PENDING ORDERS
========================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE status = 'Pending'
");

if ($result) {

    $row = $result->fetch_assoc();

    $pending_orders = (int) $row["total"];

    $result->free();
}


/* =========================================
   TOTAL CUSTOMERS
========================================= */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE is_admin = 0
");

if ($result) {

    $row = $result->fetch_assoc();

    $total_customers = (int) $row["total"];

    $result->free();
}


/* =========================================
   TOTAL SALES
   Only completed orders count as sales
========================================= */

$result = $conn->query("
    SELECT COALESCE(SUM(total_amount), 0) AS total
    FROM orders
    WHERE status = 'Completed'
");

if ($result) {

    $row = $result->fetch_assoc();

    $total_sales = (float) $row["total"];

    $result->free();
}


/* =========================================
   RECENT ORDERS
========================================= */

$recent_orders = [];

$result = $conn->query("
    SELECT
        o.id,
        o.total_amount,
        o.status,
        o.created_at,
        COALESCE(
            NULLIF(o.first_name, ''),
            u.first_name
        ) AS first_name,
        COALESCE(
            NULLIF(o.last_name, ''),
            u.last_name
        ) AS last_name
    FROM orders o
    LEFT JOIN users u
        ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 5
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $recent_orders[] = $row;
    }

    $result->free();
}


/* =========================================
   RECENT ACTIVITY
========================================= */

$recent_activity = [];

$result = $conn->query("
    SELECT
        a.action,
        a.description,
        a.created_at,
        u.first_name,
        u.last_name
    FROM activity_logs a
    LEFT JOIN users u
        ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 5
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $recent_activity[] = $row;
    }

    $result->free();
}


/* =========================================
   PAGE TITLE
========================================= */

$pageTitle = "Admin Dashboard";

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($pageTitle) ?> | Elora Plants
    </title>


    <style>

        /* =========================================
           RESET
        ========================================= */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        /* =========================================
           BODY
        ========================================= */

        body {

            background: #f5f7f2;

            color: #24352a;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            min-height: 100vh;
        }


        a {
            text-decoration: none;
        }


        /* =========================================
           ADMIN NAVBAR
        ========================================= */

        .admin-navbar {

            background: #ffffff;

            border-bottom:
                1px solid #dfe7df;

            padding:
                20px 40px;
        }


        .admin-navbar-inner {

            max-width: 1400px;

            margin: 0 auto;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;
        }


        /* =========================================
           ADMIN BRAND
        ========================================= */

        .admin-brand {

            display: flex;

            align-items: center;

            gap: 14px;

            color: #183a2a;
        }


        /* =========================================
           ADMIN LOGO
        ========================================= */

        .admin-brand-logo {

            width: 68px;

            height: 68px;

            object-fit: contain;

            display: block;

            flex-shrink: 0;
        }


        /* =========================================
           ADMIN PANEL TEXT
        ========================================= */

        .admin-brand-text span {

            display: block;

            font-size: 13px;

            font-weight: bold;

            letter-spacing: 2px;

            color: #315c3d;
        }


        /* =========================================
           ADMIN USER
        ========================================= */

        .admin-user {

            display: flex;

            align-items: center;

            gap: 16px;
        }


        .admin-user-info {

            text-align: right;
        }


        .admin-user-info strong {

            display: block;

            font-size: 14px;

            color: #183a2a;
        }


        .admin-user-info span {

            display: block;

            font-size: 12px;

            color: #718077;

            margin-top: 3px;
        }


        /* =========================================
           LOGOUT
        ========================================= */

        .logout-button {

            color: #ffffff;

            background: #315c3d;

            padding:
                10px 17px;

            border-radius: 10px;

            font-size: 13px;

            font-weight: bold;

            transition: 0.2s ease;
        }


        .logout-button:hover {

            background: #23452d;

            transform:
                translateY(-1px);
        }


        /* =========================================
           MAIN
        ========================================= */

        .admin-main {

            max-width: 1400px;

            margin: 0 auto;

            padding:
                45px 40px 70px;
        }


        /* =========================================
           PAGE HEADER
        ========================================= */

        .page-header {

            margin-bottom: 35px;
        }


        .page-label {

            display: block;

            color: #718475;

            font-size: 11px;

            font-weight: bold;

            letter-spacing: 2px;

            margin-bottom: 10px;
        }


        .page-header h1 {

            font-family:
                Georgia,
                "Times New Roman",
                serif;

            font-size:
                clamp(32px, 4vw, 52px);

            font-weight: normal;

            color: #183a2a;

            margin-bottom: 10px;
        }


        .page-header p {

            color: #718077;

            font-size: 15px;

            line-height: 1.7;
        }


        /* =========================================
           STATISTICS
        ========================================= */

        .stats-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;

            margin-bottom: 35px;
        }


        .stat-card {

            background: #ffffff;

            border:
                1px solid #edf1ed;

            border-radius: 18px;

            padding: 25px;

            box-shadow:
                0 8px 30px
                rgba(35, 60, 45, 0.07);

            transition: 0.2s ease;
        }


        .stat-card:hover {

            transform:
                translateY(-3px);

            box-shadow:
                0 12px 32px
                rgba(35, 60, 45, 0.10);
        }


        .stat-icon {

            width: 45px;

            height: 45px;

            border-radius: 12px;

            background: #e5efe7;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 21px;

            margin-bottom: 20px;
        }


        .stat-card strong {

            display: block;

            color: #183a2a;

            font-family:
                Georgia,
                "Times New Roman",
                serif;

            font-size: 32px;

            margin-bottom: 5px;
        }


        .stat-card span {

            color: #78857d;

            font-size: 13px;
        }


        .sales-value {

            font-size:
                27px !important;
        }


        /* =========================================
           LOWER DASHBOARD
        ========================================= */

        .lower-grid {

            display: grid;

            grid-template-columns:
                1.2fr 1fr;

            gap: 20px;

            margin-bottom: 35px;
        }


        .dashboard-card {

            background: #ffffff;

            border:
                1px solid #edf1ed;

            border-radius: 18px;

            padding: 25px;

            overflow: hidden;

            box-shadow:
                0 8px 30px
                rgba(35, 60, 45, 0.07);
        }


        .dashboard-card-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            margin-bottom: 20px;
        }


        .dashboard-card-header h2 {

            font-family:
                Georgia,
                "Times New Roman",
                serif;

            font-size: 25px;

            font-weight: normal;

            color: #183a2a;
        }


        .view-link {

            color: #315c3d;

            font-size: 13px;

            font-weight: bold;

            white-space: nowrap;

            transition: 0.2s ease;
        }


        .view-link:hover {

            color: #23452d;

            text-decoration: underline;
        }


        /* =========================================
           RECENT ORDERS
        ========================================= */

        .order-list {

            display: flex;

            flex-direction: column;
        }


        .order-item {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            padding:
                15px 0;

            border-bottom:
                1px solid #edf1ed;
        }


        .order-item:last-child {

            border-bottom: none;
        }


        .order-info {

            min-width: 0;
        }


        .order-id {

            color: #183a2a;

            font-weight: bold;

            font-size: 14px;

            margin-bottom: 4px;
        }


        .order-customer {

            color: #718077;

            font-size: 12px;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;
        }


        .order-right {

            text-align: right;

            flex-shrink: 0;
        }


        .order-total {

            color: #315c3d;

            font-weight: bold;

            font-size: 14px;

            margin-bottom: 5px;
        }


        /* =========================================
           STATUS BADGES
        ========================================= */

        .status-badge {

            display: inline-block;

            padding:
                5px 10px;

            border-radius: 20px;

            background: #e5efe7;

            color: #28563f;

            font-size: 10px;

            font-weight: bold;
        }


        .status-cancelled {

            background: #fff3f1;

            color: #9b3d31;
        }


        .status-processing {

            background: #f0f2ed;

            color: #65736a;
        }


        .status-completed {

            background: #dcebdc;

            color: #28563f;
        }


        /* =========================================
           RECENT ACTIVITY
        ========================================= */

        .activity-list {

            display: flex;

            flex-direction: column;
        }


        .activity-item {

            padding:
                15px 0;

            border-bottom:
                1px solid #edf1ed;
        }


        .activity-item:last-child {

            border-bottom: none;
        }


        .activity-action {

            display: inline-block;

            color: #28563f;

            background: #e5efe7;

            padding:
                5px 9px;

            border-radius: 8px;

            font-size: 10px;

            font-weight: bold;

            letter-spacing: 0.4px;

            margin-bottom: 7px;
        }


        .activity-description {

            color: #39483e;

            font-size: 13px;

            line-height: 1.5;

            margin-bottom: 5px;
        }


        .activity-meta {

            color: #8a938d;

            font-size: 11px;
        }


        /* =========================================
           EMPTY STATE
        ========================================= */

        .small-empty {

            text-align: center;

            padding:
                35px 10px;

            color: #78857d;

            font-size: 13px;
        }


        /* =========================================
           MANAGEMENT CARD
        ========================================= */

        .management-card {

            background: #ffffff;

            border:
                1px solid #edf1ed;

            border-radius: 18px;

            padding: 30px;

            box-shadow:
                0 8px 30px
                rgba(35, 60, 45, 0.07);
        }


        .management-card h2 {

            font-family:
                Georgia,
                "Times New Roman",
                serif;

            font-size: 25px;

            font-weight: normal;

            color: #183a2a;

            margin-bottom: 8px;
        }


        .management-card > p {

            color: #718077;

            font-size: 14px;

            line-height: 1.7;

            margin-bottom: 22px;
        }


        .management-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 12px;
        }


        .management-link {

            display: block;

            background: #f5f7f2;

            border:
                1px solid #dce4dc;

            border-radius: 14px;

            padding: 18px;

            color: #315c3d;

            transition: 0.2s ease;
        }


        .management-link:hover {

            background: #edf3e8;

            border-color: #c9ddca;

            transform:
                translateY(-2px);
        }


        .management-link strong {

            display: block;

            font-size: 14px;

            color: #315c3d;

            margin-bottom: 5px;
        }


        .management-link span {

            color: #78857d;

            font-size: 11px;

            line-height: 1.5;
        }


        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 1000px) {

            .stats-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }


            .lower-grid {

                grid-template-columns: 1fr;
            }


            .management-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }

        }


        @media (max-width: 600px) {

            .admin-navbar {

                padding:
                    15px 20px;
            }


            .admin-navbar-inner {

                align-items:
                    flex-start;
            }


            .admin-brand {

                gap: 10px;
            }


            .admin-brand-logo {

                width: 55px;

                height: 55px;
            }


            .admin-brand-text span {

                font-size: 10px;

                letter-spacing: 1.5px;
            }


            .admin-user-info {

                display: none;
            }


            .admin-main {

                padding:
                    30px 20px 50px;
            }


            .page-header h1 {

                font-size: 34px;
            }


            .stats-grid {

                grid-template-columns: 1fr;
            }


            .stat-card {

                padding: 22px;
            }


            .dashboard-card {

                padding: 20px;
            }


            .management-card {

                padding: 20px;
            }


            .management-grid {

                grid-template-columns: 1fr;
            }


            .order-item {

                align-items:
                    flex-start;
            }


            .dashboard-card-header h2 {

                font-size: 22px;
            }

        }

    </style>

</head>


<body>


<!-- =========================================
     ADMIN NAVBAR
========================================= -->

<header class="admin-navbar">

    <div class="admin-navbar-inner">


        <a
            href="index.php"
            class="admin-brand"
        >

            <img
                src="../image/logo.png"
                alt="Elora Plants Logo"
                class="admin-brand-logo"
            >


            <div class="admin-brand-text">

                <span>
                    ADMIN PANEL
                </span>

            </div>

        </a>


        <div class="admin-user">

            <div class="admin-user-info">

                <strong>

                    <?= htmlspecialchars(
                        $user["first_name"] .
                        " " .
                        $user["last_name"]
                    ) ?>

                </strong>


                <span>
                    Administrator
                </span>

            </div>


            <a
                href="../logout.php"
                class="logout-button"
            >
                Log Out
            </a>

        </div>

    </div>

</header>



<!-- =========================================
     MAIN CONTENT
========================================= -->

<main class="admin-main">


    <!-- =========================================
         PAGE HEADER
    ========================================== -->

    <div class="page-header">

        <span class="page-label">
            ELORA MANAGEMENT
        </span>


        <h1>
            Admin Dashboard
        </h1>


        <p>
            Welcome back.
            Manage your plants, customers, orders,
            and website activity from here.
        </p>

    </div>



    <!-- =========================================
         STATISTICS
    ========================================== -->

    <section class="stats-grid">


        <!-- TOTAL PRODUCTS -->

        <div class="stat-card">

            <div class="stat-icon">
                📦
            </div>


            <strong>
                <?= $total_products ?>
            </strong>


            <span>
                Total Products
            </span>

        </div>



        <!-- ACTIVE PRODUCTS -->

        <div class="stat-card">

            <div class="stat-icon">
                🌱
            </div>


            <strong>
                <?= $active_products ?>
            </strong>


            <span>
                Active Products
            </span>

        </div>



        <!-- TOTAL ORDERS -->

        <div class="stat-card">

            <div class="stat-icon">
                🛍️
            </div>


            <strong>
                <?= $total_orders ?>
            </strong>


            <span>
                Total Orders
            </span>

        </div>



        <!-- PENDING ORDERS -->

        <div class="stat-card">

            <div class="stat-icon">
                ⏳
            </div>


            <strong>
                <?= $pending_orders ?>
            </strong>


            <span>
                Pending Orders
            </span>

        </div>



        <!-- CUSTOMERS -->

        <div class="stat-card">

            <div class="stat-icon">
                👥
            </div>


            <strong>
                <?= $total_customers ?>
            </strong>


            <span>
                Total Customers
            </span>

        </div>



        <!-- SALES -->

        <div class="stat-card">

            <div class="stat-icon">
                💰
            </div>


            <strong class="sales-value">

                ₱<?= number_format(
                    $total_sales,
                    2
                ) ?>

            </strong>


            <span>
                Total Sales
            </span>

        </div>


    </section>



    <!-- =========================================
         RECENT INFORMATION
    ========================================== -->

    <section class="lower-grid">


        <!-- =====================================
             RECENT ORDERS
        ====================================== -->

        <div class="dashboard-card">


            <div class="dashboard-card-header">

                <h2>
                    Recent Orders
                </h2>


                <a
                    href="orders.php"
                    class="view-link"
                >
                    View All →
                </a>

            </div>


            <div class="order-list">


                <?php if (empty($recent_orders)): ?>


                    <div class="small-empty">

                        No orders have been placed yet.

                    </div>


                <?php else: ?>


                    <?php foreach ($recent_orders as $order): ?>

                        <?php

                        $statusClass = "";

                        if (
                            $order["status"] ===
                            "Cancelled"
                        ) {

                            $statusClass =
                                "status-cancelled";

                        } elseif (
                            $order["status"] ===
                            "Processing"
                        ) {

                            $statusClass =
                                "status-processing";

                        } elseif (
                            $order["status"] ===
                            "Completed"
                        ) {

                            $statusClass =
                                "status-completed";
                        }

                        ?>


                        <div class="order-item">


                            <div class="order-info">

                                <div class="order-id">

                                    Order
                                    #<?= (int) $order["id"] ?>

                                </div>


                                <div class="order-customer">

                                    <?= htmlspecialchars(
                                        ($order["first_name"] ?? "") .
                                        " " .
                                        ($order["last_name"] ?? "")
                                    ) ?>

                                </div>

                            </div>


                            <div class="order-right">

                                <div class="order-total">

                                    ₱<?= number_format(
                                        (float)
                                        $order["total_amount"],
                                        2
                                    ) ?>

                                </div>


                                <span
                                    class="status-badge <?= $statusClass ?>"
                                >

                                    <?= htmlspecialchars(
                                        $order["status"]
                                    ) ?>

                                </span>

                            </div>


                        </div>


                    <?php endforeach; ?>


                <?php endif; ?>


            </div>


        </div>



        <!-- =====================================
             RECENT ACTIVITY
        ====================================== -->

        <div class="dashboard-card">


            <div class="dashboard-card-header">

                <h2>
                    Recent Activity
                </h2>


                <a
                    href="activity.php"
                    class="view-link"
                >
                    View All →
                </a>

            </div>


            <div class="activity-list">


                <?php if (empty($recent_activity)): ?>


                    <div class="small-empty">

                        No activity has been recorded yet.

                    </div>


                <?php else: ?>


                    <?php foreach ($recent_activity as $activity): ?>


                        <div class="activity-item">


                            <div class="activity-action">

                                <?= htmlspecialchars(
                                    $activity["action"]
                                ) ?>

                            </div>


                            <div class="activity-description">

                                <?= htmlspecialchars(
                                    $activity["description"]
                                ) ?>

                            </div>


                            <div class="activity-meta">

                                <?php if (
                                    !empty(
                                        $activity["first_name"]
                                    )
                                ): ?>

                                    <?= htmlspecialchars(
                                        $activity["first_name"] .
                                        " " .
                                        $activity["last_name"]
                                    ) ?>

                                    ·

                                <?php endif; ?>


                                <?= date(
                                    "M d, Y h:i A",
                                    strtotime(
                                        $activity["created_at"]
                                    )
                                ) ?>

                            </div>


                        </div>


                    <?php endforeach; ?>


                <?php endif; ?>


            </div>


        </div>


    </section>



    <!-- =========================================
         MANAGEMENT
    ========================================== -->

    <section class="management-card">


        <h2>
            Quick Management
        </h2>


        <p>
            Quickly access the main sections of
            the Elora administration system.
        </p>


        <div class="management-grid">


            <a
                href="products.php"
                class="management-link"
            >

                <strong>
                    🌱 Products
                </strong>

                <span>
                    Manage plants and inventory.
                </span>

            </a>



            <a
                href="orders.php"
                class="management-link"
            >

                <strong>
                    🛍️ Orders
                </strong>

                <span>
                    View and update customer orders.
                </span>

            </a>



            <a
                href="users.php"
                class="management-link"
            >

                <strong>
                    👥 Users
                </strong>

                <span>
                    View registered customers.
                </span>

            </a>



            <a
                href="activity.php"
                class="management-link"
            >

                <strong>
                    📋 Activity
                </strong>

                <span>
                    Review website activity logs.
                </span>

            </a>


        </div>


    </section>


</main>


</body>

</html>