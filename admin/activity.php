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

if (
    !isset($_SESSION["is_admin"]) ||
    (int) $_SESSION["is_admin"] !== 1
) {

    header("Location: ../index.php");
    exit;
}


/* =========================================
   FILTER VALUES
========================================= */

$fromDate = isset($_GET["from_date"])
    ? trim($_GET["from_date"])
    : "";

$fromTime = isset($_GET["from_time"])
    ? trim($_GET["from_time"])
    : "";

$toDate = isset($_GET["to_date"])
    ? trim($_GET["to_date"])
    : "";

$toTime = isset($_GET["to_time"])
    ? trim($_GET["to_time"])
    : "";

$actionFilter = isset($_GET["action_filter"])
    ? trim($_GET["action_filter"])
    : "";

$userFilter = isset($_GET["user_filter"])
    ? (int) $_GET["user_filter"]
    : 0;


/* =========================================
   GET AVAILABLE ACTIONS
========================================= */

$actions = [];

$actionResult = $conn->query(
    "SELECT DISTINCT action
     FROM activity_logs
     WHERE action IS NOT NULL
     AND action <> ''
     ORDER BY action ASC"
);

if ($actionResult) {

    while ($row = $actionResult->fetch_assoc()) {

        $actions[] = $row["action"];
    }

    $actionResult->free();
}


/* =========================================
   GET USERS FOR FILTER
========================================= */

$users = [];

$userResult = $conn->query(
    "SELECT
        id,
        first_name,
        last_name,
        email
     FROM users
     ORDER BY first_name ASC, last_name ASC"
);

if ($userResult) {

    while ($row = $userResult->fetch_assoc()) {

        $users[] = $row;
    }

    $userResult->free();
}


/* =========================================
   BUILD ACTIVITY QUERY
========================================= */

$sql = "
    SELECT
        a.id,
        a.user_id,
        a.action,
        a.description,
        a.target_type,
        a.target_id,
        a.created_at,
        u.first_name,
        u.last_name,
        u.email
    FROM activity_logs a
    LEFT JOIN users u
        ON a.user_id = u.id
    WHERE 1 = 1
";

$params = [];
$types = "";


/* =========================================
   DATE/TIME FILTER
========================================= */

if ($fromDate !== "") {

    $startDateTime =
        $fromDate .
        " " .
        (
            $fromTime !== ""
                ? $fromTime
                : "00:00:00"
        );

    $sql .= " AND a.created_at >= ?";

    $params[] = $startDateTime;
    $types .= "s";
}


if ($toDate !== "") {

    $endDateTime =
        $toDate .
        " " .
        (
            $toTime !== ""
                ? $toTime
                : "23:59:59"
        );

    $sql .= " AND a.created_at <= ?";

    $params[] = $endDateTime;
    $types .= "s";
}


/* =========================================
   ACTION FILTER
========================================= */

if ($actionFilter !== "") {

    $sql .= " AND a.action = ?";

    $params[] = $actionFilter;
    $types .= "s";
}


/* =========================================
   USER FILTER
========================================= */

if ($userFilter > 0) {

    $sql .= " AND a.user_id = ?";

    $params[] = $userFilter;
    $types .= "i";
}


/* =========================================
   SORTING
========================================= */

$sql .= "
    ORDER BY a.created_at DESC
";


/* =========================================
   EXECUTE QUERY
========================================= */

$logs = [];

$stmt = $conn->prepare($sql);

if ($stmt) {

    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $logs[] = $row;
    }

    $stmt->close();
}


/* =========================================
   LOG COUNT
========================================= */

$totalLogs = count($logs);


/* =========================================
   FILTER ACTIVE CHECK
========================================= */

$filterActive =
    $fromDate !== "" ||
    $fromTime !== "" ||
    $toDate !== "" ||
    $toTime !== "" ||
    $actionFilter !== "" ||
    $userFilter > 0;


/* =========================================
   DISPLAY FILTER DATE/TIME
========================================= */

$displayFrom = "";

if ($fromDate !== "") {

    $displayFrom = date(
        "M d, Y",
        strtotime($fromDate)
    );

    if ($fromTime !== "") {

        $displayFrom .=
            " " .
            date(
                "h:i A",
                strtotime($fromTime)
            );
    }
}


$displayTo = "";

if ($toDate !== "") {

    $displayTo = date(
        "M d, Y",
        strtotime($toDate)
    );

    if ($toTime !== "") {

        $displayTo .=
            " " .
            date(
                "h:i A",
                strtotime($toTime)
            );
    }
}


/* =========================================
   SELECTED USER NAME
========================================= */

$selectedUserName = "";

if ($userFilter > 0) {

    foreach ($users as $user) {

        if ((int) $user["id"] === $userFilter) {

            $selectedUserName =
                $user["first_name"] .
                " " .
                $user["last_name"];

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
        Activity Logs | Elora Admin
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


        button,
        input,
        select {

            font-family: inherit;
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

            transform: translateX(3px);
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
                rgba(41, 79, 55, 0.18);

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

            margin-bottom: 30px;

            box-shadow:
                0 8px 30px
                rgba(35, 60, 45, 0.07);
        }


        .filter-title {

            font-family:
                Georgia,
                "Times New Roman",
                serif;

            font-size: 21px;

            color: #183a2a;

            margin-bottom: 20px;
        }


        .filter-grid {

            display: grid;

            grid-template-columns:
                repeat(4, minmax(150px, 1fr));

            gap: 16px;
        }


        .filter-group {

            display: flex;

            flex-direction: column;

            gap: 7px;
        }


        .filter-group label {

            font-size: 12px;

            font-weight: bold;

            color: #68766d;
        }


        .filter-group input,
        .filter-group select {

            width: 100%;

            height: 46px;

            padding:
                0 14px;

            border:
                1px solid #dce4dc;

            border-radius: 10px;

            background: #ffffff;

            color: #24352a;

            font-size: 14px;

            outline: none;

            transition: 0.2s ease;
        }


        .filter-group input:focus,
        .filter-group select:focus {

            border-color: #8bab91;

            box-shadow:
                0 0 0 3px
                rgba(139, 171, 145, 0.12);
        }


        /* =========================================
           FILTER BUTTONS
        ========================================= */

        .filter-actions {

            display: flex;

            gap: 12px;

            margin-top: 20px;
        }


        .filter-btn {

            height: 46px;

            padding:
                0 20px;

            border: none;

            border-radius: 10px;

            background: #315c3d;

            color: #ffffff;

            font-weight: bold;

            cursor: pointer;

            font-size: 14px;

            transition: 0.2s ease;
        }


        .filter-btn:hover {

            background: #23452d;

            transform:
                translateY(-1px);
        }


        .clear-btn {

            height: 46px;

            padding:
                0 20px;

            border:
                1px solid #d5ded6;

            border-radius: 10px;

            background: #f5f7f2;

            color: #536359;

            font-weight: bold;

            cursor: pointer;

            font-size: 14px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            transition: 0.2s ease;
        }


        .clear-btn:hover {

            background: #e9eee8;

            color: #315c3d;
        }


        /* =========================================
           FILTER SUMMARY
        ========================================= */

        .filter-summary {

            margin-top: 18px;

            padding:
                13px 15px;

            background: #f3f7f2;

            border-radius: 10px;

            color: #53645a;

            font-size: 13px;

            line-height: 1.6;
        }


        .filter-summary strong {

            color: #183a2a;
        }


        /* =========================================
           STAT CARD
        ========================================= */

        .stat-card {

            background: #ffffff;

            border-radius: 16px;

            padding: 22px;

            margin-bottom: 30px;

            box-shadow:
                0 8px 30px
                rgba(35, 60, 45, 0.07);
        }


        .stat-label {

            color: #78857d;

            font-size: 13px;

            margin-bottom: 8px;
        }


        .stat-number {

            color: #183a2a;

            font-family:
                Georgia,
                "Times New Roman",
                serif;

            font-size: 32px;

            font-weight: bold;
        }


        /* =========================================
           LOG CARD
        ========================================= */

        .logs-card {

            background: #ffffff;

            border-radius: 18px;

            padding: 25px;

            box-shadow:
                0 8px 30px
                rgba(35, 60, 45, 0.07);

            overflow-x: auto;
        }


        /* =========================================
           TABLE
        ========================================= */

        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 950px;
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

            padding: 18px 15px;

            border-bottom:
                1px solid #edf1ed;

            vertical-align: middle;
        }


        tbody tr:hover {

            background: #fafcf9;
        }


        /* =========================================
           LOG INFORMATION
        ========================================= */

        .log-id {

            font-weight: bold;

            color: #183a2a;
        }


        .user-name {

            font-weight: bold;

            color: #263a2d;
        }


        .user-email {

            color: #65736a;

            font-size: 12px;

            margin-top: 4px;
        }


        .description {

            color: #45564a;

            line-height: 1.5;
        }


        .target {

            color: #65736a;

            font-size: 13px;
        }


        .date {

            color: #65736a;

            font-size: 14px;

            white-space: nowrap;
        }


        /* =========================================
           ACTION BADGE
        ========================================= */

        .action-badge {

            display: inline-block;

            padding:
                7px 12px;

            border-radius: 20px;

            background: #e5efe7;

            color: #28563f;

            font-size: 12px;

            font-weight: bold;
        }


        /* =========================================
           EMPTY STATE
        ========================================= */

        .empty-state {

            text-align: center;

            padding: 70px 20px;
        }


        .empty-icon {

            font-size: 45px;

            margin-bottom: 15px;

            opacity: 0.5;
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
        }


        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 1000px) {

            .filter-grid {

                grid-template-columns:
                    repeat(2, minmax(150px, 1fr));
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
                    repeat(3, 1fr);

                gap: 7px;
            }


            .nav a {

                text-align: center;

                font-size: 12px;

                padding: 10px 7px;
            }


            .logout {

                margin-top: 15px;

                padding-top: 15px;
            }


            .sidebar-bottom {

                display: grid;

                grid-template-columns:
                    1fr 1fr;

                gap: 7px;

                margin-top: 12px;
            }


            .sidebar-bottom a {

                text-align: center;

                margin-top: 0;
            }


            .main-content {

                margin-left: 0;

                width: 100%;

                padding: 20px;
            }


            .page-header h2 {

                font-size: 28px;
            }


            .filter-card {

                padding: 15px;
            }


            .filter-grid {

                grid-template-columns: 1fr;
            }


            .filter-actions {

                flex-direction: column;
            }


            .filter-btn,
            .clear-btn {

                width: 100%;

                justify-content: center;
            }


            .logs-card {

                padding: 15px;
            }

        }


        @media (max-width: 600px) {

            .nav {

                grid-template-columns:
                    repeat(2, 1fr);
            }


            .sidebar-bottom {

                grid-template-columns: 1fr;
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

            <a href="orders.php">
                Orders
            </a>

            <a href="users.php">
                Users
            </a>

            <a
                href="activity.php"
                class="active"
            >
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


        <!-- =========================================
             HEADER
        ========================================== -->

        <div class="page-header">

            <h2>
                Activity Logs
            </h2>

            <p>
                Monitor important actions performed throughout the system.
            </p>

        </div>


        <!-- =========================================
             FILTER
        ========================================== -->

        <div class="filter-card">

            <div class="filter-title">
                Filter Activity
            </div>


            <form
                method="GET"
                action="activity.php"
            >


                <div class="filter-grid">


                    <!-- FROM DATE -->

                    <div class="filter-group">

                        <label for="from_date">
                            From Date
                        </label>

                        <input
                            type="date"
                            id="from_date"
                            name="from_date"
                            value="<?= htmlspecialchars($fromDate) ?>"
                        >

                    </div>


                    <!-- FROM TIME -->

                    <div class="filter-group">

                        <label for="from_time">
                            From Time
                        </label>

                        <input
                            type="time"
                            id="from_time"
                            name="from_time"
                            value="<?= htmlspecialchars($fromTime) ?>"
                        >

                    </div>


                    <!-- TO DATE -->

                    <div class="filter-group">

                        <label for="to_date">
                            To Date
                        </label>

                        <input
                            type="date"
                            id="to_date"
                            name="to_date"
                            value="<?= htmlspecialchars($toDate) ?>"
                        >

                    </div>


                    <!-- TO TIME -->

                    <div class="filter-group">

                        <label for="to_time">
                            To Time
                        </label>

                        <input
                            type="time"
                            id="to_time"
                            name="to_time"
                            value="<?= htmlspecialchars($toTime) ?>"
                        >

                    </div>


                    <!-- ACTION -->

                    <div class="filter-group">

                        <label for="action_filter">
                            Action
                        </label>

                        <select
                            id="action_filter"
                            name="action_filter"
                        >

                            <option value="">
                                All Actions
                            </option>

                            <?php foreach ($actions as $action): ?>

                                <option
                                    value="<?= htmlspecialchars($action) ?>"
                                    <?= $actionFilter === $action
                                        ? "selected"
                                        : "" ?>
                                >

                                    <?= htmlspecialchars($action) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- USER -->

                    <div class="filter-group">

                        <label for="user_filter">
                            User
                        </label>

                        <select
                            id="user_filter"
                            name="user_filter"
                        >

                            <option value="0">
                                All Users
                            </option>

                            <?php foreach ($users as $user): ?>

                                <option
                                    value="<?= (int) $user["id"] ?>"
                                    <?= $userFilter === (int) $user["id"]
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


                <!-- FILTER BUTTONS -->

                <div class="filter-actions">

                    <button
                        type="submit"
                        class="filter-btn"
                    >
                        Apply Filter
                    </button>


                    <a
                        href="activity.php"
                        class="clear-btn"
                    >
                        Clear Filters
                    </a>

                </div>


            </form>


            <?php if ($filterActive): ?>

                <div class="filter-summary">

                    <strong>
                        Active Filters:
                    </strong>


                    <?php if ($displayFrom !== ""): ?>

                        From

                        <strong>
                            <?= htmlspecialchars($displayFrom) ?>
                        </strong>

                    <?php endif; ?>


                    <?php if ($displayTo !== ""): ?>

                        <?php if ($displayFrom !== ""): ?>
                            —
                        <?php endif; ?>

                        To

                        <strong>
                            <?= htmlspecialchars($displayTo) ?>
                        </strong>

                    <?php endif; ?>


                    <?php if ($actionFilter !== ""): ?>

                        &nbsp; | &nbsp;

                        Action:

                        <strong>
                            <?= htmlspecialchars($actionFilter) ?>
                        </strong>

                    <?php endif; ?>


                    <?php if ($selectedUserName !== ""): ?>

                        &nbsp; | &nbsp;

                        User:

                        <strong>
                            <?= htmlspecialchars($selectedUserName) ?>
                        </strong>

                    <?php endif; ?>

                </div>

            <?php endif; ?>


        </div>


        <!-- =========================================
             RESULT COUNT
        ========================================== -->

        <div class="stat-card">

            <div class="stat-label">

                <?php if ($filterActive): ?>

                    Matching Activity Records

                <?php else: ?>

                    Total Activity Records

                <?php endif; ?>

            </div>


            <div class="stat-number">

                <?= $totalLogs ?>

            </div>

        </div>


        <!-- =========================================
             ACTIVITY TABLE
        ========================================== -->

        <div class="logs-card">


            <?php if (empty($logs)): ?>


                <div class="empty-state">

                    <div class="empty-icon">
                        📋
                    </div>


                    <h3>

                        <?php if ($filterActive): ?>

                            No Matching Activity

                        <?php else: ?>

                            No Activity Yet

                        <?php endif; ?>

                    </h3>


                    <p>

                        <?php if ($filterActive): ?>

                            No activity records match the selected filters.

                        <?php else: ?>

                            System activity will appear here as users and administrators perform actions.

                        <?php endif; ?>

                    </p>

                </div>


            <?php else: ?>


                <table>

                    <thead>

                        <tr>

                            <th>
                                ID
                            </th>

                            <th>
                                User
                            </th>

                            <th>
                                Action
                            </th>

                            <th>
                                Description
                            </th>

                            <th>
                                Target
                            </th>

                            <th>
                                Date & Time
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($logs as $log): ?>


                        <tr>


                            <!-- LOG ID -->

                            <td>

                                <span class="log-id">

                                    #<?= (int) $log["id"] ?>

                                </span>

                            </td>


                            <!-- USER -->

                            <td>

                                <?php if (
                                    !empty($log["first_name"])
                                ): ?>

                                    <div class="user-name">

                                        <?= htmlspecialchars(
                                            $log["first_name"] .
                                            " " .
                                            $log["last_name"]
                                        ) ?>

                                    </div>


                                    <div class="user-email">

                                        <?= htmlspecialchars(
                                            $log["email"]
                                        ) ?>

                                    </div>

                                <?php else: ?>

                                    <div class="user-name">
                                        System
                                    </div>

                                <?php endif; ?>

                            </td>


                            <!-- ACTION -->

                            <td>

                                <span class="action-badge">

                                    <?= htmlspecialchars(
                                        $log["action"]
                                    ) ?>

                                </span>

                            </td>


                            <!-- DESCRIPTION -->

                            <td>

                                <div class="description">

                                    <?= htmlspecialchars(
                                        $log["description"]
                                    ) ?>

                                </div>

                            </td>


                            <!-- TARGET -->

                            <td>

                                <?php if (
                                    !empty($log["target_type"])
                                ): ?>

                                    <div class="target">

                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $log["target_type"]
                                            )
                                        ) ?>


                                        <?php if (
                                            !empty($log["target_id"])
                                        ): ?>

                                            #<?= (int) $log["target_id"] ?>

                                        <?php endif; ?>

                                    </div>

                                <?php else: ?>

                                    <span class="target">
                                        —
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- DATE AND TIME -->

                            <td>

                                <span class="date">

                                    <?= date(
                                        "M d, Y",
                                        strtotime(
                                            $log["created_at"]
                                        )
                                    ) ?>

                                    <br>

                                    <?= date(
                                        "h:i A",
                                        strtotime(
                                            $log["created_at"]
                                        )
                                    ) ?>

                                </span>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>


            <?php endif; ?>


        </div>


    </main>


</div>


</body>

</html>