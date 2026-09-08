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

if (!isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] != 1) {
    header("Location: ../index.php");
    exit;
}


/* =========================================
   STATUS UPDATE
========================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {

    $order_id = isset($_POST["order_id"])
        ? (int) $_POST["order_id"]
        : 0;

    $new_status = trim($_POST["status"] ?? "");


    /* Allowed order statuses */
    $allowed_statuses = [
        "Pending",
        "Processing",
        "Shipping",
        "Delivered",
        "Cancelled"
    ];


    if (
        $order_id > 0 &&
        in_array($new_status, $allowed_statuses, true)
    ) {

        /* =========================================
           GET CURRENT STATUS
        ========================================== */

        $current_status = "";

        $status_stmt = $conn->prepare("
            SELECT status
            FROM orders
            WHERE id = ?
            LIMIT 1
        ");

        if ($status_stmt) {

            $status_stmt->bind_param(
                "i",
                $order_id
            );

            $status_stmt->execute();

            $status_result =
                $status_stmt->get_result();

            if ($status_row =
                $status_result->fetch_assoc()
            ) {

                $current_status =
                    $status_row["status"];
            }

            $status_stmt->close();
        }


        /* =========================================
           DO NOT CHANGE DELIVERED ORDERS
        ========================================== */

        if ($current_status === "Delivered") {

            $query_params = [];

            $filter_names = [
                "from_date",
                "from_time",
                "to_date",
                "to_time",
                "status_filter",
                "user_filter"
            ];

            foreach ($filter_names as $filter_name) {

                if (
                    isset($_POST[$filter_name]) &&
                    $_POST[$filter_name] !== ""
                ) {

                    $query_params[$filter_name] =
                        $_POST[$filter_name];
                }
            }


            $redirect_url = "orders.php";

            if (!empty($query_params)) {

                $redirect_url .= "?" .
                    http_build_query($query_params);
            }

            header("Location: " . $redirect_url);
            exit;
        }


        /* =========================================
           IGNORE SAME STATUS
        ========================================== */

        if ($current_status === $new_status) {

            $query_params = [];

            $filter_names = [
                "from_date",
                "from_time",
                "to_date",
                "to_time",
                "status_filter",
                "user_filter"
            ];

            foreach ($filter_names as $filter_name) {

                if (
                    isset($_POST[$filter_name]) &&
                    $_POST[$filter_name] !== ""
                ) {

                    $query_params[$filter_name] =
                        $_POST[$filter_name];
                }
            }


            $redirect_url = "orders.php";

            if (!empty($query_params)) {

                $redirect_url .= "?" .
                    http_build_query($query_params);
            }

            header("Location: " . $redirect_url);
            exit;
        }


        /* =========================================
           UPDATE ORDER STATUS
        ========================================== */

        $update_stmt = $conn->prepare("
            UPDATE orders
            SET status = ?
            WHERE id = ?
            AND status <> 'Delivered'
        ");

        if ($update_stmt) {

            $update_stmt->bind_param(
                "si",
                $new_status,
                $order_id
            );


            if ($update_stmt->execute()) {

                /* =========================================
                   ACTIVITY LOG
                ========================================== */

                $action = "UPDATE_ORDER";

                $description =
                    "Updated order #" .
                    $order_id .
                    " status from " .
                    (
                        $current_status !== ""
                            ? $current_status
                            : "Unknown"
                    ) .
                    " to " .
                    $new_status .
                    ".";

                $target_type = "order";

                $target_id = $order_id;

                $user_id =
                    (int) $_SESSION["user_id"];


                $log_stmt = $conn->prepare("
                    INSERT INTO activity_logs
                    (
                        user_id,
                        action,
                        description,
                        target_type,
                        target_id
                    )
                    VALUES (?, ?, ?, ?, ?)
                ");


                if ($log_stmt) {

                    $log_stmt->bind_param(
                        "isssi",
                        $user_id,
                        $action,
                        $description,
                        $target_type,
                        $target_id
                    );

                    $log_stmt->execute();

                    $log_stmt->close();
                }


                /* =========================================
                   PRESERVE FILTERS
                ========================================== */

                $query_params = [];

                $filter_names = [
                    "from_date",
                    "from_time",
                    "to_date",
                    "to_time",
                    "status_filter",
                    "user_filter"
                ];


                foreach ($filter_names as $filter_name) {

                    if (
                        isset($_POST[$filter_name]) &&
                        $_POST[$filter_name] !== ""
                    ) {

                        $query_params[$filter_name] =
                            $_POST[$filter_name];
                    }
                }


                $redirect_url = "orders.php";


                if (!empty($query_params)) {

                    $redirect_url .= "?" .
                        http_build_query($query_params);
                }


                header("Location: " . $redirect_url);
                exit;
            }


            $update_stmt->close();
        }
    }
}


/* =========================================
   FILTER VALUES
========================================= */

$from_date =
    trim($_GET["from_date"] ?? "");

$from_time =
    trim($_GET["from_time"] ?? "");

$to_date =
    trim($_GET["to_date"] ?? "");

$to_time =
    trim($_GET["to_time"] ?? "");

$status_filter =
    trim($_GET["status_filter"] ?? "");

$user_filter =
    isset($_GET["user_filter"])
        ? (int) $_GET["user_filter"]
        : 0;


/* =========================================
   DATE/TIME VALIDATION
========================================= */

$filter_error = "";

$from_datetime = "";

$to_datetime = "";


/* =========================================
   FROM DATE/TIME
========================================= */

if ($from_date !== "") {

    if ($from_time === "") {
        $from_time = "00:00";
    }


    $from_datetime =
        $from_date .
        " " .
        $from_time .
        ":00";


    $from_date_object =
        DateTime::createFromFormat(
            "Y-m-d H:i:s",
            $from_datetime
        );


    if (
        !$from_date_object ||
        $from_date_object->format("Y-m-d H:i:s")
        !== $from_datetime
    ) {

        $filter_error =
            "Invalid starting date or time.";
    }
}


/* =========================================
   TO DATE/TIME
========================================= */

if ($to_date !== "") {

    if ($to_time === "") {
        $to_time = "23:59";
    }


    $to_datetime =
        $to_date .
        " " .
        $to_time .
        ":59";


    $to_date_object =
        DateTime::createFromFormat(
            "Y-m-d H:i:s",
            $to_datetime
        );


    if (
        !$to_date_object ||
        $to_date_object->format("Y-m-d H:i:s")
        !== $to_datetime
    ) {

        $filter_error =
            "Invalid ending date or time.";
    }
}


/* =========================================
   CHECK DATE RANGE
========================================= */

if (
    $filter_error === "" &&
    $from_datetime !== "" &&
    $to_datetime !== ""
) {

    if (
        strtotime($from_datetime) >
        strtotime($to_datetime)
    ) {

        $filter_error =
            "The starting date and time cannot be later than the ending date and time.";
    }
}


/* =========================================
   VALID STATUS FILTER
========================================= */

$valid_filter_statuses = [
    "Pending",
    "Processing",
    "Shipping",
    "Delivered",
    "Cancelled"
];


if (
    $status_filter !== "" &&
    !in_array(
        $status_filter,
        $valid_filter_statuses,
        true
    )
) {

    $status_filter = "";
}


/* =========================================
   GET USERS
========================================= */

$users = [];


$user_stmt = $conn->prepare("
    SELECT
        id,
        first_name,
        last_name,
        email
    FROM users
    ORDER BY first_name ASC, last_name ASC
");


if ($user_stmt) {

    $user_stmt->execute();

    $user_result =
        $user_stmt->get_result();


    while (
        $user_row =
        $user_result->fetch_assoc()
    ) {

        $users[] = $user_row;
    }


    $user_stmt->close();
}


/* =========================================
   GET ORDERS
========================================= */

$orders = [];

$where = [];

$params = [];

$types = "";


if ($filter_error === "") {


    /* =========================================
       FROM DATETIME FILTER
    ========================================== */

    if ($from_datetime !== "") {

        $where[] =
            "o.created_at >= ?";

        $params[] =
            $from_datetime;

        $types .= "s";
    }


    /* =========================================
       TO DATETIME FILTER
    ========================================== */

    if ($to_datetime !== "") {

        $where[] =
            "o.created_at <= ?";

        $params[] =
            $to_datetime;

        $types .= "s";
    }


    /* =========================================
       STATUS FILTER
    ========================================== */

    if ($status_filter !== "") {

        $where[] =
            "o.status = ?";

        $params[] =
            $status_filter;

        $types .= "s";
    }


    /* =========================================
       CUSTOMER FILTER
    ========================================== */

    if ($user_filter > 0) {

        $where[] =
            "o.user_id = ?";

        $params[] =
            $user_filter;

        $types .= "i";
    }


    /* =========================================
       ORDER QUERY
    ========================================== */

    $sql = "
        SELECT
            o.id,
            o.user_id,

            o.first_name,
            o.last_name,
            o.email,
            o.phone,

            o.address,
            o.barangay,
            o.city,
            o.province,
            o.postal_code,

            o.delivery_notes,

            o.payment_method,
            o.payment_status,

            o.delivery_fee,
            o.total_amount,

            o.status,
            o.created_at,

            u.first_name AS user_first_name,
            u.last_name AS user_last_name,
            u.email AS user_email,

            (
                SELECT COUNT(*)
                FROM order_items oi
                WHERE oi.order_id = o.id
            ) AS item_count

        FROM orders o

        LEFT JOIN users u
            ON o.user_id = u.id
    ";


    if (!empty($where)) {

        $sql .=
            " WHERE " .
            implode(
                " AND ",
                $where
            );
    }


    $sql .= "
        ORDER BY o.created_at DESC
    ";


    $stmt =
        $conn->prepare($sql);


    if ($stmt) {


        if (!empty($params)) {

            $stmt->bind_param(
                $types,
                ...$params
            );
        }


        $stmt->execute();


        $result =
            $stmt->get_result();


        while (
            $row =
            $result->fetch_assoc()
        ) {

            $orders[] =
                $row;
        }


        $stmt->close();
    }
}


/* =========================================
   FILTER SUMMARY
========================================= */

$active_filters = [];


/* =========================================
   FROM FILTER
========================================= */

if ($from_datetime !== "") {

    $active_filters[] =
        "From: " .
        date(
            "M d, Y h:i A",
            strtotime($from_datetime)
        );
}


/* =========================================
   TO FILTER
========================================= */

if ($to_datetime !== "") {

    $active_filters[] =
        "To: " .
        date(
            "M d, Y h:i A",
            strtotime($to_datetime)
        );
}


/* =========================================
   STATUS FILTER
========================================= */

if ($status_filter !== "") {

    $active_filters[] =
        "Status: " .
        htmlspecialchars(
            $status_filter
        );
}


/* =========================================
   CUSTOMER FILTER
========================================= */

if ($user_filter > 0) {

    foreach ($users as $user) {

        if (
            (int) $user["id"] ===
            $user_filter
        ) {

            $active_filters[] =
                "Customer: " .
                htmlspecialchars(
                    $user["first_name"] .
                    " " .
                    $user["last_name"]
                );

            break;
        }
    }
}

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
        Orders | Elora Admin
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


        body {
            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f5f7f2;

            color: #24352a;
        }


        a {
            text-decoration: none;
            color: inherit;
        }


        /* =========================================
           LAYOUT
        ========================================= */

        .admin-layout {
            min-height: 100vh;
            display: flex;
        }


        /* =========================================
           SIDEBAR
        ========================================= */

        .sidebar {
            width: 250px;

            background: #dcebdc;

            color: #183a2a;

            min-height: 100vh;

            padding: 30px 20px;

            position: fixed;

            left: 0;
            top: 0;
            bottom: 0;

            border-right:
                1px solid #c7dbc8;
        }


        /* =========================================
           BRAND / LOGO
        ========================================= */

        .brand {
            text-align: center;
            margin-bottom: 40px;
        }


        .brand img {
            width: 150px;

            height: auto;

            display: block;

            margin: 0 auto 10px;

            object-fit: contain;
        }


        .brand p {
            font-size: 12px;

            color: #4d6655;

            opacity: 1;

            margin-top: 5px;

            letter-spacing: 1.5px;

            font-weight: 600;
        }


        /* =========================================
           NAVIGATION
        ========================================= */

        .nav {
            display: flex;

            flex-direction: column;

            gap: 8px;
        }


        .nav a {
            padding: 13px 15px;

            border-radius: 10px;

            color: #355642;

            transition:
                background 0.2s ease,
                color 0.2s ease,
                transform 0.2s ease;
        }


        .nav a:hover {
            background: #c9ddca;

            color: #1f442f;

            transform:
                translateX(3px);
        }


        .nav a.active {
            background: #294f37;

            color: #ffffff;

            font-weight: bold;
        }


        /* =========================================
           LOGOUT / BOTTOM NAV
        ========================================= */

        .logout {
            margin-top: 25px;

            border-top:
                1px solid
                rgba(
                    41,
                    79,
                    55,
                    0.18
                );

            padding-top: 25px;
        }


        .logout a {
            color: #355642;
        }


        .logout a:hover {
            background: #c9ddca;

            color: #1f442f;
        }


        /* =========================================
           MAIN CONTENT
        ========================================= */

        .main-content {
            margin-left: 250px;

            width:
                calc(100% - 250px);

            padding: 35px;
        }


        /* =========================================
           PAGE HEADER
        ========================================= */

        .page-header {
            margin-bottom: 30px;
        }


        .page-header h2 {
            font-family:
                Georgia,
                "Times New Roman",
                serif;

            font-size: 34px;

            color: #183a2a;
        }


        .page-header p {
            color: #718077;

            margin-top: 6px;
        }


        /* =========================================
           FILTER CARD
        ========================================= */

        .filter-card {
            background: #ffffff;

            border-radius: 18px;

            padding: 25px;

            box-shadow:
                0 8px 30px
                rgba(
                    35,
                    60,
                    45,
                    0.07
                );

            margin-bottom: 25px;
        }


        .filter-card h3 {
            font-family:
                Georgia,
                "Times New Roman",
                serif;

            font-size: 22px;

            color: #183a2a;

            margin-bottom: 20px;
        }


        .filter-grid {
            display: grid;

            grid-template-columns:
                repeat(6, 1fr);

            gap: 15px;
        }


        .form-group {
            display: flex;

            flex-direction: column;

            gap: 7px;
        }


        .form-group label {
            font-size: 12px;

            font-weight: bold;

            color: #65736a;
        }


        .form-group input,
        .form-group select {
            width: 100%;

            height: 44px;

            padding:
                0 12px;

            border:
                1px solid #dce4dc;

            border-radius: 10px;

            background: #ffffff;

            color: #24352a;

            font-size: 13px;

            outline: none;
        }


        .form-group input:focus,
        .form-group select:focus {
            border-color: #8bab91;

            box-shadow:
                0 0 0 3px
                rgba(
                    139,
                    171,
                    145,
                    0.12
                );
        }


        .filter-actions {
            display: flex;

            align-items: center;

            gap: 10px;
        }


        /* =========================================
           BUTTONS
        ========================================= */

        .btn {
            height: 44px;

            padding:
                0 20px;

            border-radius: 10px;

            font-size: 13px;

            font-weight: bold;

            border: none;

            cursor: pointer;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            transition: 0.2s ease;
        }


        .btn-primary {
            background: #315c3d;

            color: #ffffff;
        }


        .btn-primary:hover {
            background: #23452d;

            transform:
                translateY(-1px);
        }


        .btn-light {
            background: #f5f7f2;

            color: #536359;

            border:
                1px solid #d5ded6;
        }


        .btn-light:hover {
            background: #e9eee8;

            color: #315c3d;
        }


        /* =========================================
           FILTER ERROR
        ========================================= */

        .filter-error {
            background: #fff1f1;

            border:
                1px solid #e7caca;

            color: #9b4545;

            border-radius: 10px;

            padding:
                12px 15px;

            margin-bottom: 18px;

            font-size: 13px;
        }


        /* =========================================
           FILTER SUMMARY
        ========================================= */

        .filter-summary {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            flex-wrap: wrap;

            margin-bottom: 18px;
        }


        .filter-summary-left {
            color: #65736a;

            font-size: 13px;
        }


        .filter-summary-left strong {
            color: #183a2a;
        }


        .filter-tags {
            display: flex;

            gap: 7px;

            flex-wrap: wrap;
        }


        .filter-tag {
            background: #e5efe7;

            color: #355642;

            border-radius: 20px;

            padding:
                7px 11px;

            font-size: 11px;

            font-weight: 600;
        }


        /* =========================================
           TABLE CARD
        ========================================= */

        .table-card {
            background: #ffffff;

            border-radius: 18px;

            padding: 25px;

            box-shadow:
                0 8px 30px
                rgba(
                    35,
                    60,
                    45,
                    0.07
                );

            overflow: hidden;
        }


        .table-wrapper {
            width: 100%;

            overflow-x: auto;
        }


        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 900px;
        }


        th {
            text-align: left;

            padding: 15px;

            font-size: 13px;

            color: #68766d;

            background: #f4f7f2;

            border-bottom:
                1px solid #e2e8e2;
        }


        td {
            padding:
                18px 15px;

            border-bottom:
                1px solid #edf1ed;

            vertical-align: middle;
        }


        tbody tr:hover {
            background: #fafcf9;
        }


        /* =========================================
           ORDER DETAILS
        ========================================= */

        .order-id {
            font-weight: bold;

            color: #183a2a;
        }


        .customer-name {
            font-weight: bold;

            color: #263a2d;

            margin-bottom: 4px;
        }


        .customer-email {
            color: #65736a;

            font-size: 12px;
        }


        .order-date {
            color: #65736a;

            font-size: 13px;

            line-height: 1.5;
        }


        .amount {
            color: #183a2a;

            font-weight: bold;

            white-space: nowrap;
        }


        .item-count {
            display: inline-block;

            padding:
                6px 10px;

            border-radius: 20px;

            background: #e5efe7;

            color: #355642;

            font-size: 11px;

            font-weight: bold;

            white-space: nowrap;
        }


        /* =========================================
           STATUS FORM
        ========================================= */

        .status-form {
            display: flex;

            align-items: center;

            gap: 7px;
        }


        .status-form select {
            height: 36px;

            padding:
                0 9px;

            border:
                1px solid #dce4dc;

            border-radius: 8px;

            background: #ffffff;

            color: #355642;

            font-size: 11px;

            outline: none;
        }


        .status-form button {
            height: 36px;

            padding:
                0 11px;

            border: none;

            border-radius: 8px;

            background: #315c3d;

            color: #ffffff;

            font-size: 11px;

            font-weight: bold;

            cursor: pointer;

            transition: 0.2s ease;
        }


        .status-form button:hover {
            background: #23452d;
        }


        /* =========================================
           DELIVERED STATUS
        ========================================= */

        .delivered-status {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            height: 36px;

            padding:
                0 12px;

            border-radius: 8px;

            background: #e5efe7;

            color: #315c3d;

            font-size: 11px;

            font-weight: bold;

            white-space: nowrap;
        }


        /* =========================================
           EMPTY STATE
        ========================================= */

        .empty-state {
            text-align: center;

            padding: 70px 20px;
        }


        .empty-state h3 {
            font-family:
                Georgia,
                "Times New Roman",
                serif;

            font-size: 25px;

            color: #183a2a;

            margin-bottom: 8px;
        }


        .empty-state p {
            color: #7a867e;

            font-size: 13px;
        }


        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 1100px) {

            .filter-grid {
                grid-template-columns:
                    repeat(3, 1fr);
            }

        }


        @media (max-width: 900px) {

            .sidebar {
                width: 210px;
            }


            .main-content {
                margin-left: 210px;

                width:
                    calc(100% - 210px);

                padding: 25px;
            }

        }


        @media (max-width: 700px) {

            .admin-layout {
                display: block;
            }


            .sidebar {
                position: relative;

                width: 100%;

                min-height: auto;

                padding: 20px;

                border-right: none;

                border-bottom:
                    1px solid #c7dbc8;
            }


            .brand {
                margin-bottom: 20px;
            }


            .brand img {
                width: 130px;
            }


            .nav {
                display: grid;

                grid-template-columns:
                    repeat(2, 1fr);

                gap: 7px;
            }


            .nav a {
                text-align: center;

                padding:
                    10px 7px;
            }


            .logout {
                margin-top: 15px;

                padding-top: 15px;
            }


            .main-content {
                margin-left: 0;

                width: 100%;

                padding: 20px;
            }


            .page-header h2 {
                font-size: 28px;
            }


            .filter-grid {
                grid-template-columns: 1fr;
            }


            .filter-actions {
                align-items: stretch;

                flex-direction: column;
            }


            .filter-actions .btn {
                width: 100%;
            }


            .table-card {
                padding: 15px;
            }


            .filter-summary {
                align-items: flex-start;

                flex-direction: column;
            }

        }


        @media (max-width: 500px) {

            .nav {
                grid-template-columns:
                    repeat(2, 1fr);
            }

        }

    </style>

</head>


<body>


<div class="admin-layout">


    <!-- =========================================
         SIDEBAR
    ========================================== -->

    <aside class="sidebar">


        <div class="brand">

            <img
                src="../image/logo.png"
                alt="Elora Plants"
            >

            <p>
                ADMIN PANEL
            </p>

        </div>


        <nav class="nav">


            <a href="index.php">
                Dashboard
            </a>


            <a href="products.php">
                Products
            </a>


            <a
                href="orders.php"
                class="active"
            >
                Orders
            </a>


            <a href="users.php">
                Users
            </a>


            <a href="activity.php">
                Activity Logs
            </a>


        </nav>


        <div class="nav logout">


            <a href="../shop.php">
                View Shop
            </a>


            <a href="../logout.php">
                Log Out
            </a>


        </div>


    </aside>


    <!-- =========================================
         MAIN CONTENT
    ========================================== -->

    <main class="main-content">


        <div class="page-header">

            <h2>
                Orders
            </h2>

            <p>
                Manage customer orders and update their status.
            </p>

        </div>


        <!-- =====================================
             FILTER CARD
        ====================================== -->

        <div class="filter-card">


            <h3>
                Filter Orders
            </h3>


            <?php if ($filter_error !== ""): ?>

                <div class="filter-error">

                    <?= htmlspecialchars(
                        $filter_error
                    ) ?>

                </div>

            <?php endif; ?>


            <form
                method="GET"
                action="orders.php"
            >


                <div class="filter-grid">


                    <div class="form-group">

                        <label for="from_date">
                            From Date
                        </label>

                        <input
                            type="date"
                            id="from_date"
                            name="from_date"
                            value="<?= htmlspecialchars(
                                $from_date
                            ) ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="from_time">
                            From Time
                        </label>

                        <input
                            type="time"
                            id="from_time"
                            name="from_time"
                            value="<?= htmlspecialchars(
                                $from_time
                            ) ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="to_date">
                            To Date
                        </label>

                        <input
                            type="date"
                            id="to_date"
                            name="to_date"
                            value="<?= htmlspecialchars(
                                $to_date
                            ) ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="to_time">
                            To Time
                        </label>

                        <input
                            type="time"
                            id="to_time"
                            name="to_time"
                            value="<?= htmlspecialchars(
                                $to_time
                            ) ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="status_filter">
                            Status
                        </label>


                        <select
                            id="status_filter"
                            name="status_filter"
                        >


                            <option value="">
                                All Statuses
                            </option>


                            <option
                                value="Pending"
                                <?= $status_filter === "Pending"
                                    ? "selected"
                                    : "" ?>
                            >
                                Pending
                            </option>


                            <option
                                value="Processing"
                                <?= $status_filter === "Processing"
                                    ? "selected"
                                    : "" ?>
                            >
                                Processing
                            </option>


                            <option
                                value="Shipping"
                                <?= $status_filter === "Shipping"
                                    ? "selected"
                                    : "" ?>
                            >
                                Shipping
                            </option>


                            <option
                                value="Delivered"
                                <?= $status_filter === "Delivered"
                                    ? "selected"
                                    : "" ?>
                            >
                                Delivered
                            </option>


                            <option
                                value="Cancelled"
                                <?= $status_filter === "Cancelled"
                                    ? "selected"
                                    : "" ?>
                            >
                                Cancelled
                            </option>


                        </select>


                    </div>


                    <div class="form-group">

                        <label for="user_filter">
                            Customer
                        </label>


                        <select
                            id="user_filter"
                            name="user_filter"
                        >


                            <option value="0">
                                All Customers
                            </option>


                            <?php foreach ($users as $user): ?>


                                <option
                                    value="<?= (int) $user["id"] ?>"
                                    <?= $user_filter === (int) $user["id"]
                                        ? "selected"
                                        : "" ?>
                                >

                                    <?= htmlspecialchars(
                                        $user["first_name"] .
                                        " " .
                                        $user["last_name"]
                                    ) ?>

                                </option>


                            <?php endforeach; ?>


                        </select>


                    </div>


                </div>


                <div
                    class="filter-actions"
                    style="margin-top: 15px;"
                >


                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Apply Filters
                    </button>


                    <a
                        href="orders.php"
                        class="btn btn-light"
                    >
                        Clear
                    </a>


                </div>


            </form>


        </div>


        <!-- =====================================
             FILTER SUMMARY
        ====================================== -->

        <div class="filter-summary">


            <div class="filter-summary-left">

                Showing

                <strong>
                    <?= count($orders) ?>
                </strong>

                matching order(s).

            </div>


            <?php if (!empty($active_filters)): ?>


                <div class="filter-tags">


                    <?php foreach ($active_filters as $filter): ?>


                        <span class="filter-tag">

                            <?= $filter ?>

                        </span>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </div>


        <!-- =====================================
             ORDERS TABLE
        ====================================== -->

        <div class="table-card">


            <?php if (empty($orders)): ?>


                <div class="empty-state">

                    <h3>
                        No Orders Found
                    </h3>

                    <p>
                        There are no orders matching the selected filters.
                    </p>

                </div>


            <?php else: ?>


                <div class="table-wrapper">


                    <table>


                        <thead>

                            <tr>

                                <th>
                                    Order
                                </th>

                                <th>
                                    Customer
                                </th>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Items
                                </th>

                                <th>
                                    Total
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach ($orders as $order): ?>


                                <tr>


                                    <td>

                                        <span class="order-id">

                                            #<?= (int) $order["id"] ?>

                                        </span>

                                    </td>


                                    <td>

                                        <div class="customer-name">

                                            <?= htmlspecialchars(
                                                trim(
                                                    ($order["first_name"] ?? "") .
                                                    " " .
                                                    ($order["last_name"] ?? "")
                                                )
                                            ) ?>

                                        </div>


                                        <div class="customer-email">

                                            <?= htmlspecialchars(
                                                $order["email"] ?? ""
                                            ) ?>

                                        </div>

                                    </td>


                                    <td>

                                        <div class="order-date">

                                            <?= date(
                                                "M d, Y",
                                                strtotime(
                                                    $order["created_at"]
                                                )
                                            ) ?>

                                            <br>

                                            <?= date(
                                                "h:i A",
                                                strtotime(
                                                    $order["created_at"]
                                                )
                                            ) ?>

                                        </div>

                                    </td>


                                    <td>

                                        <span class="item-count">

                                            <?= (int) $order["item_count"] ?>

                                            <?= (int) $order["item_count"] === 1
                                                ? "item"
                                                : "items"
                                            ?>

                                        </span>

                                    </td>


                                    <td>

                                        <span class="amount">

                                            ₱<?= number_format(
                                                (float) $order["total_amount"],
                                                2
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>


                                        <?php if (
                                            $order["status"] === "Delivered"
                                        ): ?>


                                            <!-- =================================
                                                 DELIVERED = LOCKED
                                            ================================== -->

                                            <span class="delivered-status">

                                                ✓ Delivered

                                            </span>


                                        <?php else: ?>


                                            <!-- =================================
                                                 ACTIVE STATUS FORM
                                            ================================== -->

                                            <form
                                                method="POST"
                                                class="status-form"
                                            >


                                                <input
                                                    type="hidden"
                                                    name="order_id"
                                                    value="<?= (int) $order["id"] ?>"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="from_date"
                                                    value="<?= htmlspecialchars(
                                                        $from_date
                                                    ) ?>"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="from_time"
                                                    value="<?= htmlspecialchars(
                                                        $from_time
                                                    ) ?>"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="to_date"
                                                    value="<?= htmlspecialchars(
                                                        $to_date
                                                    ) ?>"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="to_time"
                                                    value="<?= htmlspecialchars(
                                                        $to_time
                                                    ) ?>"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="status_filter"
                                                    value="<?= htmlspecialchars(
                                                        $status_filter
                                                    ) ?>"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="user_filter"
                                                    value="<?= (int) $user_filter ?>"
                                                >


                                                <select
                                                    name="status"
                                                >


                                                    <option
                                                        value="Pending"
                                                        <?= $order["status"] === "Pending"
                                                            ? "selected"
                                                            : "" ?>
                                                    >
                                                        Pending
                                                    </option>


                                                    <option
                                                        value="Processing"
                                                        <?= $order["status"] === "Processing"
                                                            ? "selected"
                                                            : "" ?>
                                                    >
                                                        Processing
                                                    </option>


                                                    <option
                                                        value="Shipping"
                                                        <?= $order["status"] === "Shipping"
                                                            ? "selected"
                                                            : "" ?>
                                                    >
                                                        Shipping
                                                    </option>


                                                    <option
                                                        value="Delivered"
                                                        <?= $order["status"] === "Delivered"
                                                            ? "selected"
                                                            : "" ?>
                                                    >
                                                        Delivered
                                                    </option>


                                                    <option
                                                        value="Cancelled"
                                                        <?= $order["status"] === "Cancelled"
                                                            ? "selected"
                                                            : "" ?>
                                                    >
                                                        Cancelled
                                                    </option>


                                                </select>


                                                <button
                                                    type="submit"
                                                    name="update_status"
                                                    value="1"
                                                >
                                                    Update
                                                </button>


                                            </form>


                                        <?php endif; ?>


                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php endif; ?>


        </div>


    </main>


</div>


</body>

</html>