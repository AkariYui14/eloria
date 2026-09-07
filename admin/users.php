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
   SEARCH / FILTER VALUES
========================================= */

$search = isset($_GET["search"])
    ? trim($_GET["search"])
    : "";

$role = isset($_GET["role"])
    ? trim($_GET["role"])
    : "";


/* =========================================
   GET USER COUNTS
========================================= */

$totalUsers = 0;
$totalCustomers = 0;
$totalAdmins = 0;


/* TOTAL USERS */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
");

if ($result) {

    $row = $result->fetch_assoc();

    $totalUsers = (int) $row["total"];

    $result->free();
}


/* TOTAL CUSTOMERS */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE is_admin = 0
");

if ($result) {

    $row = $result->fetch_assoc();

    $totalCustomers = (int) $row["total"];

    $result->free();
}


/* TOTAL ADMINS */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE is_admin = 1
");

if ($result) {

    $row = $result->fetch_assoc();

    $totalAdmins = (int) $row["total"];

    $result->free();
}


/* =========================================
   BUILD USER SEARCH QUERY
========================================= */

$users = [];

$sql = "
    SELECT
        id,
        first_name,
        last_name,
        email,
        is_admin,
        created_at
    FROM users
    WHERE 1 = 1
";


$params = [];
$types = "";


/* =========================================
   SEARCH BY NAME / EMAIL
========================================= */

if ($search !== "") {

    $sql .= "
        AND (
            first_name LIKE ?
            OR last_name LIKE ?
            OR CONCAT(first_name, ' ', last_name) LIKE ?
            OR email LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "ssss";
}


/* =========================================
   ROLE FILTER
========================================= */

if ($role === "customer") {

    $sql .= "
        AND is_admin = 0
    ";

} elseif ($role === "admin") {

    $sql .= "
        AND is_admin = 1
    ";
}


/* =========================================
   SORT
========================================= */

$sql .= "
    ORDER BY created_at DESC
";


/* =========================================
   PREPARE QUERY
========================================= */

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

        $users[] = $row;
    }


    $stmt->close();
}


/* =========================================
   MATCHING USER COUNT
========================================= */

$matchingUsers = count($users);

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
        Users | Elora Admin
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
           STAT CARDS
        ========================================= */

        .stats {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;

            margin-bottom: 30px;
        }


        .stat-card {

            background: #ffffff;

            border-radius: 16px;

            padding: 22px;

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
           USERS CARD
        ========================================= */

        .users-card {

            background: #ffffff;

            border-radius: 18px;

            padding: 25px;

            box-shadow:
                0 8px 30px
                rgba(35, 60, 45, 0.07);

            overflow-x: auto;
        }


        /* =========================================
           SEARCH AREA
        ========================================= */

        .search-area {

            margin-bottom: 25px;
        }


        .search-form {

            display: grid;

            grid-template-columns:
                minmax(200px, 1fr)
                180px
                auto
                auto;

            gap: 12px;

            align-items: center;
        }


        .search-input,
        .role-select {

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


        .search-input:focus,
        .role-select:focus {

            border-color: #8bab91;

            box-shadow:
                0 0 0 3px
                rgba(139, 171, 145, 0.12);
        }


        .search-button {

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


        .search-button:hover {

            background: #23452d;

            transform:
                translateY(-1px);
        }


        .clear-button {

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

            display: flex;

            align-items: center;

            justify-content: center;

            transition: 0.2s ease;
        }


        .clear-button:hover {

            background: #e9eee8;

            color: #315c3d;
        }


        /* =========================================
           SEARCH SUMMARY
        ========================================= */

        .search-summary {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            margin-top: 18px;

            padding-bottom: 18px;

            border-bottom:
                1px solid #edf1ed;
        }


        .matching-count {

            color: #65736a;

            font-size: 13px;
        }


        .matching-count strong {

            color: #183a2a;
        }


        .filter-status {

            color: #78857d;

            font-size: 12px;

            text-align: right;
        }


        /* =========================================
           TABLE
        ========================================= */

        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 750px;
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
           USER INFORMATION
        ========================================= */

        .user-id {

            font-weight: bold;

            color: #183a2a;
        }


        .user-name {

            font-weight: bold;

            color: #263a2d;
        }


        .email {

            color: #65736a;

            font-size: 14px;
        }


        .date {

            color: #65736a;

            font-size: 14px;
        }


        /* =========================================
           ROLE BADGES
        ========================================= */

        .role-badge {

            display: inline-block;

            padding:
                7px 12px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;
        }


        .role-admin {

            background: #e5efe7;

            color: #28563f;
        }


        .role-customer {

            background: #f0f2ed;

            color: #65736a;
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

            .search-form {

                grid-template-columns:
                    1fr 180px;
            }


            .search-button,
            .clear-button {

                width: 100%;
            }


            .stats {

                grid-template-columns: 1fr;
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


            .users-card {

                padding: 15px;
            }


            .search-form {

                grid-template-columns: 1fr;
            }


            .search-summary {

                align-items: flex-start;

                flex-direction: column;
            }


            .filter-status {

                text-align: left;
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

            <a
                href="users.php"
                class="active"
            >
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


        <!-- =========================================
             HEADER
        ========================================== -->

        <div class="page-header">

            <h2>
                Users
            </h2>

            <p>
                View and search registered customers
                and administrator accounts.
            </p>

        </div>


        <!-- =========================================
             STATISTICS
        ========================================== -->

        <section class="stats">


            <div class="stat-card">

                <div class="stat-label">
                    Total Users
                </div>

                <div class="stat-number">
                    <?= $totalUsers ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Customers
                </div>

                <div class="stat-number">
                    <?= $totalCustomers ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Administrators
                </div>

                <div class="stat-number">
                    <?= $totalAdmins ?>
                </div>

            </div>


        </section>


        <!-- =========================================
             USERS CARD
        ========================================== -->

        <div class="users-card">


            <!-- =====================================
                 SEARCH
            ====================================== -->

            <div class="search-area">


                <form
                    method="GET"
                    action="users.php"
                    class="search-form"
                >


                    <!-- SEARCH -->

                    <input
                        type="text"
                        name="search"
                        class="search-input"
                        placeholder="Search name or email..."
                        value="<?= htmlspecialchars($search) ?>"
                    >


                    <!-- ROLE -->

                    <select
                        name="role"
                        class="role-select"
                    >

                        <option value="">
                            All Roles
                        </option>

                        <option
                            value="customer"
                            <?= $role === "customer"
                                ? "selected"
                                : "" ?>
                        >
                            Customers
                        </option>

                        <option
                            value="admin"
                            <?= $role === "admin"
                                ? "selected"
                                : "" ?>
                        >
                            Administrators
                        </option>

                    </select>


                    <!-- SEARCH BUTTON -->

                    <button
                        type="submit"
                        class="search-button"
                    >
                        🔍 Search
                    </button>


                    <!-- CLEAR -->

                    <a
                        href="users.php"
                        class="clear-button"
                    >
                        Clear
                    </a>


                </form>


                <!-- =================================
                     SEARCH SUMMARY
                ================================== -->

                <div class="search-summary">


                    <div class="matching-count">

                        Showing

                        <strong>
                            <?= $matchingUsers ?>
                        </strong>

                        user<?= $matchingUsers === 1
                            ? ""
                            : "s" ?>

                    </div>


                    <div class="filter-status">

                        <?php if (
                            $search !== "" ||
                            $role !== ""
                        ): ?>

                            Filters:

                            <?php if ($search !== ""): ?>

                                Search:
                                "<?= htmlspecialchars($search) ?>"

                            <?php endif; ?>


                            <?php if (
                                $search !== "" &&
                                $role !== ""
                            ): ?>

                                ·

                            <?php endif; ?>


                            <?php if ($role === "customer"): ?>

                                Role:
                                Customer

                            <?php elseif ($role === "admin"): ?>

                                Role:
                                Administrator

                            <?php endif; ?>

                        <?php else: ?>

                            Showing all registered users.

                        <?php endif; ?>

                    </div>


                </div>

            </div>


            <!-- =====================================
                 RESULTS
            ====================================== -->

            <?php if (empty($users)): ?>


                <div class="empty-state">

                    <div class="empty-icon">
                        🔍
                    </div>

                    <h3>
                        No users found
                    </h3>

                    <p>
                        Try changing your search or
                        filter.
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
                                Name
                            </th>

                            <th>
                                Email
                            </th>

                            <th>
                                Role
                            </th>

                            <th>
                                Registered
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $users as $user
                    ): ?>


                        <tr>


                            <!-- USER ID -->

                            <td>

                                <span class="user-id">

                                    #<?= (int)
                                        $user["id"] ?>

                                </span>

                            </td>


                            <!-- NAME -->

                            <td>

                                <div class="user-name">

                                    <?= htmlspecialchars(
                                        $user["first_name"] .
                                        " " .
                                        $user["last_name"]
                                    ) ?>

                                </div>

                            </td>


                            <!-- EMAIL -->

                            <td>

                                <span class="email">

                                    <?= htmlspecialchars(
                                        $user["email"]
                                    ) ?>

                                </span>

                            </td>


                            <!-- ROLE -->

                            <td>

                                <?php if (
                                    (int)
                                    $user["is_admin"] === 1
                                ): ?>

                                    <span
                                        class="role-badge role-admin"
                                    >
                                        Administrator
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="role-badge role-customer"
                                    >
                                        Customer
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- DATE -->

                            <td>

                                <span class="date">

                                    <?= date(
                                        "M d, Y",
                                        strtotime(
                                            $user["created_at"]
                                        )
                                    ) ?>

                                    <br>

                                    <?= date(
                                        "h:i A",
                                        strtotime(
                                            $user["created_at"]
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