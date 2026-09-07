<?php

session_start();

require_once "db.php";


/* =========================================
   CUSTOMER ACCESS PROTECTION
========================================= */

if (!isset($_SESSION["user_id"])) {

    header("Location: login.php");
    exit;
}


$user_id = (int) $_SESSION["user_id"];


/* =========================================
   GET CUSTOMER ORDERS
========================================= */

$sql = "
    SELECT
        o.id,
        o.total_amount,
        o.status,
        o.created_at,
        COALESCE(SUM(oi.quantity), 0) AS item_count
    FROM orders o
    LEFT JOIN order_items oi
        ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY
        o.id,
        o.total_amount,
        o.status,
        o.created_at
    ORDER BY o.created_at DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database query failed.");
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();


/* =========================================
   CART COUNT
========================================= */

$cart_count = 0;

$cart_stmt = $conn->prepare("
    SELECT COALESCE(SUM(quantity), 0)
    FROM cart_items
    WHERE user_id = ?
");

if ($cart_stmt) {

    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_stmt->bind_result($cart_count);
    $cart_stmt->fetch();
    $cart_stmt->close();

}

$cart_count = (int) $cart_count;


/* =========================================
   STATUS CLASS
========================================= */

function order_status_class($status)
{
    $status = strtolower(trim($status));

    if (
        $status === "completed" ||
        $status === "delivered"
    ) {
        return "status-completed";
    }

    if ($status === "cancelled") {
        return "status-cancelled";
    }

    if (
        $status === "processing" ||
        $status === "shipped"
    ) {
        return "status-processing";
    }

    return "status-pending";
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

    <title>My Orders | Elora Plants</title>

    <link rel="stylesheet" href="style.css?v=9">

    <style>

        /* =========================================
           ORDERS PAGE
        ========================================= */

        .orders-page {
            min-height: 100vh;
            background: #f8f6ef;
            padding: 50px 20px 80px;
        }


        .orders-container {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
        }


        .orders-header {
            margin-bottom: 30px;
        }


        .orders-header h1 {
            margin: 0 0 8px;
            color: #173f2a;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 42px;
        }


        .orders-header p {
            margin: 0;
            color: #6d756f;
            font-size: 16px;
        }


        /* =========================================
           ORDER CARDS
        ========================================= */

        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }


        .order-card {
            background: #ffffff;
            border: 1px solid #e4e8e1;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 8px 25px rgba(25, 55, 38, 0.06);
            transition: 0.25s ease;
        }


        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(25, 55, 38, 0.09);
        }


        .order-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 22px;
        }


        .order-number {
            color: #173f2a;
            font-size: 20px;
            font-weight: 700;
        }


        .order-date {
            margin-top: 6px;
            color: #777f79;
            font-size: 14px;
        }


        .order-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }


        .status-pending {
            background: #fff4d6;
            color: #946b00;
        }


        .status-processing {
            background: #e6f0ff;
            color: #315f9b;
        }


        .status-completed {
            background: #e3f3e8;
            color: #27633d;
        }


        .status-cancelled {
            background: #fbe5e5;
            color: #9b3c3c;
        }


        /* =========================================
           ORDER INFO
        ========================================= */

        .order-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            border-top: 1px solid #edf0eb;
            border-bottom: 1px solid #edf0eb;
            padding: 18px 0;
        }


        .order-info-box {
            padding: 4px 10px;
        }


        .order-info-label {
            display: block;
            margin-bottom: 6px;
            color: #858d87;
            font-size: 13px;
        }


        .order-info-value {
            color: #173f2a;
            font-size: 17px;
            font-weight: 700;
        }


        /* =========================================
           ORDER BOTTOM
        ========================================= */

        .order-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-top: 20px;
        }


        .order-total {
            color: #173f2a;
            font-size: 22px;
            font-weight: 700;
        }


        .view-order-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 20px;
            border: 0;
            border-radius: 10px;
            background: #214f36;
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.25s ease;
        }


        .view-order-button:hover {
            background: #173f2a;
            transform: translateY(-1px);
        }


        /* =========================================
           EMPTY ORDERS
        ========================================= */

        .empty-orders {
            background: #ffffff;
            border: 1px solid #e4e8e1;
            border-radius: 20px;
            padding: 65px 25px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(25, 55, 38, 0.05);
        }


        .empty-orders-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }


        .empty-orders h2 {
            margin: 0 0 10px;
            color: #173f2a;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 30px;
        }


        .empty-orders p {
            margin: 0 auto 25px;
            max-width: 500px;
            color: #777f79;
            line-height: 1.6;
        }


        .shop-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 22px;
            border-radius: 10px;
            background: #214f36;
            color: #ffffff;
            text-decoration: none;
            font-weight: 700;
            transition: 0.25s ease;
        }


        .shop-button:hover {
            background: #173f2a;
        }


        /* =========================================
           ORDERS NAVBAR
        ========================================= */

        .orders-navbar-logo {
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }


        .orders-navbar-logo img {
            width: 105px;
            height: auto;
            display: block;
            transition:
                transform 0.3s ease,
                filter 0.3s ease;
        }


        .orders-navbar-logo:hover img {
            transform: scale(1.05);
        }


        .orders-nav-menu {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 38px;
        }


        .orders-nav-menu a {
            position: relative;
            color: var(--dark-green);
            font-size: 14px;
            font-weight: 600;
            transition:
                color 0.25s ease,
                transform 0.25s ease;
        }


        .orders-nav-menu a::after {
            content: "";

            position: absolute;

            left: 0;

            bottom: -7px;

            width: 0;

            height: 2px;

            background: var(--dark-green);

            transition: width 0.25s ease;
        }


        .orders-nav-menu a:hover {
            color: #5f8657;
            transform: translateY(-2px);
        }


        .orders-nav-menu a:hover::after {
            width: 100%;
        }


        .orders-nav-menu a.active {
            color: var(--dark-green);
        }


        .orders-nav-menu a.active::after {
            width: 100%;
        }


        /* =========================================
           CART NAVIGATION
        ========================================= */

        .orders-cart-link {
            position: relative;

            display: inline-flex !important;

            align-items: center;

            gap: 7px;
        }


        .orders-cart-badge {
            min-width: 21px;

            height: 21px;

            padding: 0 6px;

            border-radius: 50px;

            background: #294f37;

            color: #ffffff;

            font-size: 11px;

            font-weight: 700;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            line-height: 1;

            box-sizing: border-box;
        }


        /* =========================================
           MOBILE NAVIGATION
        ========================================= */

        .orders-mobile-button {
            display: none;

            width: 42px;
            height: 42px;

            border: 0;

            border-radius: 50%;

            background: transparent;

            color: var(--dark-green);

            font-size: 24px;

            cursor: pointer;

            transition:
                background 0.25s ease,
                transform 0.25s ease;
        }


        .orders-mobile-button:hover {
            background: var(--light-green);

            transform: scale(1.05);
        }


        /* =========================================
           ORDER DETAILS MODAL
        ========================================= */

        .order-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 5000;

            display: none;

            align-items: center;
            justify-content: center;

            padding: 25px;

            background: rgba(15, 35, 24, 0.55);

            backdrop-filter: blur(5px);

            overflow-y: auto;
        }


        .order-modal-overlay.active {
            display: flex;

            animation: modalFadeIn 0.2s ease;
        }


        @keyframes modalFadeIn {

            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }

        }


        .order-modal {
            position: relative;

            width: 100%;
            max-width: 850px;

            max-height: 90vh;

            overflow-y: auto;

            background: #ffffff;

            border: 1px solid #e2e7df;

            border-radius: 24px;

            box-shadow:
                0 25px 70px rgba(15, 35, 24, 0.25);

            animation: modalSlideUp 0.25s ease;
        }


        @keyframes modalSlideUp {

            from {
                opacity: 0;
                transform: translateY(20px) scale(0.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }

        }


        .order-modal-header {
            position: sticky;

            top: 0;

            z-index: 5;

            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 20px;

            padding: 25px 28px;

            background: #ffffff;

            border-bottom: 1px solid #edf0eb;
        }


        .modal-eyebrow {
            margin-bottom: 6px;

            color: #718074;

            font-size: 12px;

            font-weight: 700;

            letter-spacing: 1.5px;

            text-transform: uppercase;
        }


        .order-modal-header h2 {
            margin: 0;

            color: #173f2a;

            font-family: Georgia, "Times New Roman", serif;

            font-size: 30px;
        }


        .modal-close-button {
            flex-shrink: 0;

            width: 38px;
            height: 38px;

            border: 0;

            border-radius: 50%;

            background: #f1f4ef;

            color: #294f37;

            font-size: 21px;

            line-height: 1;

            cursor: pointer;

            transition:
                background 0.2s ease,
                transform 0.2s ease;
        }


        .modal-close-button:hover {
            background: #dfe9df;

            transform: rotate(5deg);
        }


        .order-modal-body {
            padding: 28px;
        }


        .modal-status-row {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            margin-bottom: 25px;
        }


        .modal-date {
            color: #737c75;

            font-size: 14px;
        }


        /* =========================================
           MODAL PRODUCTS
        ========================================= */

        .modal-section {
            margin-bottom: 25px;
        }


        .modal-section-title {
            margin: 0 0 13px;

            color: #173f2a;

            font-size: 15px;

            font-weight: 800;

            letter-spacing: 0.5px;
        }


        .modal-products {
            border: 1px solid #e5e9e3;

            border-radius: 15px;

            overflow: hidden;
        }


        .modal-product {
            display: flex;

            align-items: center;

            gap: 15px;

            padding: 15px;

            border-bottom: 1px solid #edf0eb;
        }


        .modal-product:last-child {
            border-bottom: 0;
        }


        .modal-product-image {
            width: 72px;
            height: 72px;

            flex-shrink: 0;

            border-radius: 12px;

            background: #edf3eb;

            overflow: hidden;
        }


        .modal-product-image img {
            width: 100%;
            height: 100%;

            display: block;

            object-fit: cover;
        }


        .modal-product-info {
            flex: 1;

            min-width: 0;
        }


        .modal-product-name {
            margin-bottom: 5px;

            color: #173f2a;

            font-size: 16px;

            font-weight: 700;
        }


        .modal-product-quantity {
            color: #7b837d;

            font-size: 13px;
        }


        .modal-product-price {
            flex-shrink: 0;

            color: #173f2a;

            font-size: 16px;

            font-weight: 700;
        }


        /* =========================================
           MODAL INFORMATION GRID
        ========================================= */

        .modal-info-grid {
            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 18px;

            margin-bottom: 25px;
        }


        .modal-info-card {
            padding: 20px;

            background: #f8faf6;

            border: 1px solid #e3e9e0;

            border-radius: 15px;
        }


        .modal-info-card h3 {
            margin: 0 0 14px;

            color: #173f2a;

            font-size: 15px;
        }


        .modal-info-row {
            margin-bottom: 10px;
        }


        .modal-info-row:last-child {
            margin-bottom: 0;
        }


        .modal-info-label {
            display: block;

            margin-bottom: 3px;

            color: #858d87;

            font-size: 12px;
        }


        .modal-info-value {
            color: #37483d;

            font-size: 14px;

            line-height: 1.5;

            overflow-wrap: anywhere;
        }


        /* =========================================
           DELIVERY NOTES
        ========================================= */

        .modal-notes {
            padding: 17px 19px;

            background: #fffaf0;

            border: 1px solid #eee3c9;

            border-radius: 14px;

            color: #685f4c;

            font-size: 14px;

            line-height: 1.6;
        }


        .modal-notes strong {
            display: block;

            margin-bottom: 4px;

            color: #574e3d;
        }


        /* =========================================
           MODAL TOTAL
        ========================================= */

        .modal-summary {
            padding-top: 20px;

            border-top: 1px solid #e5e9e3;
        }


        .modal-summary-row {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 10px;

            color: #68736c;

            font-size: 14px;
        }


        .modal-summary-row.total {
            margin-top: 15px;

            padding-top: 15px;

            border-top: 1px solid #e5e9e3;

            color: #173f2a;

            font-size: 21px;

            font-weight: 800;
        }


        .modal-footer {
            display: flex;

            justify-content: flex-end;

            padding: 0 28px 28px;
        }


        .modal-close-bottom {
            padding: 11px 22px;

            border: 0;

            border-radius: 10px;

            background: #214f36;

            color: #ffffff;

            font-size: 14px;

            font-weight: 700;

            cursor: pointer;

            transition: 0.25s ease;
        }


        .modal-close-bottom:hover {
            background: #173f2a;
        }


        /* =========================================
           TABLET
        ========================================= */

        @media (max-width: 900px) {

            .orders-nav-menu {
                gap: 20px;
            }

        }


        /* =========================================
           MOBILE
        ========================================= */

        @media (max-width: 650px) {

            .orders-navbar-container {
                min-height: 70px;
                gap: 8px;
            }


            .orders-navbar-logo img {
                width: 90px;
            }


            .orders-nav-menu {
                position: absolute;

                top: 70px;

                left: 0;
                right: 0;

                display: none;

                flex-direction: column;

                align-items: stretch;

                gap: 0;

                padding: 10px 5%;

                background: var(--cream);

                border-bottom: 1px solid var(--border);

                box-shadow:
                    0 10px 25px rgba(23, 60, 39, 0.07);
            }


            .orders-nav-menu.active {
                display: flex;

                animation: searchOpen 0.25s ease both;
            }


            .orders-nav-menu a {
                padding: 14px 0;
            }


            .orders-mobile-button {
                display: flex;

                align-items: center;

                justify-content: center;
            }


            .orders-page {
                padding: 35px 15px 60px;
            }


            .orders-header h1 {
                font-size: 34px;
            }


            .order-card {
                padding: 18px;
            }


            .order-top {
                align-items: flex-start;

                flex-direction: column;

                gap: 12px;
            }


            .order-info {
                grid-template-columns: 1fr;

                gap: 12px;
            }


            .order-info-box {
                padding: 0;
            }


            .order-bottom {
                align-items: stretch;

                flex-direction: column;
            }


            .view-order-button {
                width: 100%;
            }


            /* MODAL MOBILE */

            .order-modal-overlay {
                align-items: flex-end;

                padding: 0;
            }


            .order-modal {
                max-height: 94vh;

                border-radius: 22px 22px 0 0;
            }


            .order-modal-header {
                padding: 20px;
            }


            .order-modal-header h2 {
                font-size: 25px;
            }


            .order-modal-body {
                padding: 20px;
            }


            .modal-info-grid {
                grid-template-columns: 1fr;
            }


            .modal-product {
                padding: 13px;
            }


            .modal-product-image {
                width: 60px;
                height: 60px;
            }


            .modal-product-price {
                font-size: 14px;
            }


            .modal-footer {
                padding: 0 20px 20px;
            }


            .modal-close-bottom {
                width: 100%;
            }

        }


        /* =========================================
           SMALL PHONE
        ========================================= */

        @media (max-width: 400px) {

            .orders-navbar-container {
                gap: 3px;
            }


            .orders-nav-menu {
                gap: 0;
            }


            .orders-mobile-button {
                width: 36px;
                height: 36px;
            }


            .order-modal-header h2 {
                font-size: 22px;
            }


            .modal-product-image {
                width: 52px;
                height: 52px;
            }

        }

    </style>

</head>


<body>


    <!-- =========================================
         NAVIGATION
    ========================================= -->

    <nav class="navbar">

        <div class="nav-container orders-navbar-container">

            <!-- LOGO -->

            <a
                href="index.php"
                class="orders-navbar-logo"
            >

                <img
                    src="image/logo.png"
                    alt="Elora Plants"
                >

            </a>


            <!-- NAVIGATION -->

            <div
                class="orders-nav-menu"
                id="ordersNavMenu"
            >

                <a href="index.php">
                    Home
                </a>

                <a href="shop.php">
                    Shop
                </a>

                <a
                    href="cart.php"
                    class="orders-cart-link"
                >
                    🛒 Cart

                    <span class="orders-cart-badge">
                        <?php echo $cart_count; ?>
                    </span>
                </a>

                <a
                    href="orders.php"
                    class="active"
                >
                    My Orders
                </a>

                <a href="profile.php">
                    Profile
                </a>

                <a href="logout.php">
                    Log Out
                </a>

            </div>


            <!-- MOBILE MENU BUTTON -->

            <button
                type="button"
                class="orders-mobile-button"
                id="ordersMobileButton"
                aria-label="Open navigation menu"
                aria-expanded="false"
            >
                ☰
            </button>

        </div>

    </nav>


    <!-- =========================================
         ORDERS CONTENT
    ========================================= -->

    <main class="orders-page">

        <div class="orders-container">


            <div class="orders-header">

                <h1>
                    My Orders
                </h1>

                <p>
                    View your order history and track your previous purchases.
                </p>

            </div>


            <?php if (empty($orders)): ?>

                <div class="empty-orders">

                    <div class="empty-orders-icon">
                        🌿
                    </div>

                    <h2>
                        No orders yet
                    </h2>

                    <p>
                        You haven't placed any orders yet.
                        Browse our collection and find something beautiful
                        for your space.
                    </p>

                    <a
                        href="shop.php"
                        class="shop-button"
                    >
                        Start Shopping
                    </a>

                </div>

            <?php else: ?>

                <div class="orders-list">

                    <?php foreach ($orders as $order): ?>

                        <article class="order-card">


                            <div class="order-top">

                                <div>

                                    <div class="order-number">
                                        Order #<?php echo (int) $order["id"]; ?>
                                    </div>

                                    <div class="order-date">

                                        <?php
                                        echo date(
                                            "F j, Y • g:i A",
                                            strtotime($order["created_at"])
                                        );
                                        ?>

                                    </div>

                                </div>


                                <span
                                    class="order-status <?php echo htmlspecialchars(
                                        order_status_class($order["status"])
                                    ); ?>"
                                >
                                    <?php
                                    echo htmlspecialchars(
                                        $order["status"]
                                    );
                                    ?>
                                </span>

                            </div>


                            <div class="order-info">

                                <div class="order-info-box">

                                    <span class="order-info-label">
                                        Items
                                    </span>

                                    <span class="order-info-value">

                                        <?php
                                        echo (int) $order["item_count"];
                                        ?>

                                        <?php
                                        echo ((int) $order["item_count"] === 1)
                                            ? " item"
                                            : " items";
                                        ?>

                                    </span>

                                </div>


                                <div class="order-info-box">

                                    <span class="order-info-label">
                                        Status
                                    </span>

                                    <span class="order-info-value">
                                        <?php
                                        echo htmlspecialchars(
                                            $order["status"]
                                        );
                                        ?>
                                    </span>

                                </div>


                                <div class="order-info-box">

                                    <span class="order-info-label">
                                        Order Date
                                    </span>

                                    <span class="order-info-value">

                                        <?php
                                        echo date(
                                            "M j, Y",
                                            strtotime($order["created_at"])
                                        );
                                        ?>

                                    </span>

                                </div>

                            </div>


                            <div class="order-bottom">

                                <div class="order-total">

                                    ₱<?php echo number_format(
                                        (float) $order["total_amount"],
                                        2
                                    ); ?>

                                </div>


                                <!--
                                    IMPORTANT:
                                    This is now a BUTTON.
                                    It DOES NOT go to order-details.php.
                                -->

                                <button
                                    type="button"
                                    class="view-order-button"
                                    onclick="openOrderModal(<?php echo (int) $order["id"]; ?>)"
                                >
                                    View Order
                                </button>

                            </div>


                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>


        </div>

    </main>


    <!-- =========================================
         ORDER DETAILS MODAL
    ========================================= -->

    <div
        class="order-modal-overlay"
        id="orderModalOverlay"
        aria-hidden="true"
    >

        <div
            class="order-modal"
            id="orderModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modalOrderTitle"
        >

            <!-- MODAL HEADER -->

            <div class="order-modal-header">

                <div>

                    <div class="modal-eyebrow">
                        Elora Plants // Order Details
                    </div>

                    <h2 id="modalOrderTitle">
                        Order
                    </h2>

                </div>


                <button
                    type="button"
                    class="modal-close-button"
                    id="modalCloseButton"
                    aria-label="Close order details"
                >
                    ×
                </button>

            </div>


            <!-- MODAL BODY -->

            <div class="order-modal-body">

                <div class="modal-status-row">

                    <span
                        class="order-status"
                        id="modalOrderStatus"
                    >
                        Pending
                    </span>

                    <span
                        class="modal-date"
                        id="modalOrderDate"
                    >
                        —
                    </span>

                </div>


                <!-- PRODUCTS -->

                <section class="modal-section">

                    <h3 class="modal-section-title">
                        ORDER ITEMS
                    </h3>

                    <div
                        class="modal-products"
                        id="modalProducts"
                    >
                        <!-- Loaded by JavaScript -->
                    </div>

                </section>


                <!-- DELIVERY + PAYMENT -->

                <div class="modal-info-grid">


                    <!-- DELIVERY -->

                    <section class="modal-info-card">

                        <h3>
                            Delivery Information
                        </h3>


                        <div class="modal-info-row">

                            <span class="modal-info-label">
                                Name
                            </span>

                            <div
                                class="modal-info-value"
                                id="modalCustomerName"
                            >
                                —
                            </div>

                        </div>


                        <div class="modal-info-row">

                            <span class="modal-info-label">
                                Email
                            </span>

                            <div
                                class="modal-info-value"
                                id="modalCustomerEmail"
                            >
                                —
                            </div>

                        </div>


                        <div class="modal-info-row">

                            <span class="modal-info-label">
                                Phone
                            </span>

                            <div
                                class="modal-info-value"
                                id="modalCustomerPhone"
                            >
                                —
                            </div>

                        </div>


                        <div class="modal-info-row">

                            <span class="modal-info-label">
                                Address
                            </span>

                            <div
                                class="modal-info-value"
                                id="modalCustomerAddress"
                            >
                                —
                            </div>

                        </div>


                    </section>


                    <!-- PAYMENT -->

                    <section class="modal-info-card">

                        <h3>
                            Payment Information
                        </h3>


                        <div class="modal-info-row">

                            <span class="modal-info-label">
                                Payment Method
                            </span>

                            <div
                                class="modal-info-value"
                                id="modalPaymentMethod"
                            >
                                —
                            </div>

                        </div>


                        <div class="modal-info-row">

                            <span class="modal-info-label">
                                Payment Status
                            </span>

                            <div
                                class="modal-info-value"
                                id="modalPaymentStatus"
                            >
                                —
                            </div>

                        </div>


                    </section>

                </div>


                <!-- DELIVERY NOTES -->

                <div
                    class="modal-section"
                    id="modalNotesSection"
                    style="display: none;"
                >

                    <div class="modal-notes">

                        <strong>
                            Delivery Notes
                        </strong>

                        <span id="modalDeliveryNotes"></span>

                    </div>

                </div>


                <!-- SUMMARY -->

                <section class="modal-summary">

                    <div class="modal-summary-row">

                        <span>
                            Subtotal
                        </span>

                        <span id="modalSubtotal">
                            ₱0.00
                        </span>

                    </div>


                    <div class="modal-summary-row">

                        <span>
                            Delivery Fee
                        </span>

                        <span id="modalDeliveryFee">
                            ₱0.00
                        </span>

                    </div>


                    <div class="modal-summary-row total">

                        <span>
                            Total
                        </span>

                        <span id="modalTotal">
                            ₱0.00
                        </span>

                    </div>

                </section>

            </div>


            <!-- MODAL FOOTER -->

            <div class="modal-footer">

                <button
                    type="button"
                    class="modal-close-bottom"
                    id="modalCloseBottom"
                >
                    Close
                </button>

            </div>

        </div>

    </div>


    <!-- =========================================
         JAVASCRIPT
    ========================================= -->

    <script>

        /* =========================================
           ORDER DATA
        ========================================= */

        const orderData = <?php

            $modal_orders = [];

            foreach ($orders as $order) {

                $order_id = (int) $order["id"];

                $order_details = [
                    "id" => $order_id,
                    "total_amount" => (float) $order["total_amount"],
                    "status" => $order["status"],
                    "created_at" => $order["created_at"],
                    "first_name" => "",
                    "last_name" => "",
                    "email" => "",
                    "phone" => "",
                    "address" => "",
                    "barangay" => "",
                    "city" => "",
                    "province" => "",
                    "postal_code" => "",
                    "delivery_notes" => "",
                    "payment_method" => "",
                    "payment_status" => "Pending",
                    "delivery_fee" => 0,
                    "items" => []
                ];


                /*
                 * Get order information.
                 */

                $detail_stmt = $conn->prepare("
                    SELECT
                        first_name,
                        last_name,
                        email,
                        phone,
                        address,
                        barangay,
                        city,
                        province,
                        postal_code,
                        delivery_notes,
                        payment_method,
                        payment_status,
                        delivery_fee
                    FROM orders
                    WHERE id = ?
                    AND user_id = ?
                    LIMIT 1
                ");


                if ($detail_stmt) {

                    $detail_stmt->bind_param(
                        "ii",
                        $order_id,
                        $user_id
                    );

                    $detail_stmt->execute();

                    $detail_result =
                        $detail_stmt->get_result();

                    if ($detail_row =
                        $detail_result->fetch_assoc()
                    ) {

                        $order_details["first_name"] =
                            $detail_row["first_name"];

                        $order_details["last_name"] =
                            $detail_row["last_name"];

                        $order_details["email"] =
                            $detail_row["email"];

                        $order_details["phone"] =
                            $detail_row["phone"];

                        $order_details["address"] =
                            $detail_row["address"];

                        $order_details["barangay"] =
                            $detail_row["barangay"];

                        $order_details["city"] =
                            $detail_row["city"];

                        $order_details["province"] =
                            $detail_row["province"];

                        $order_details["postal_code"] =
                            $detail_row["postal_code"];

                        $order_details["delivery_notes"] =
                            $detail_row["delivery_notes"];

                        $order_details["payment_method"] =
                            $detail_row["payment_method"];

                        $order_details["payment_status"] =
                            $detail_row["payment_status"];

                        $order_details["delivery_fee"] =
                            (float) $detail_row["delivery_fee"];

                    }

                    $detail_stmt->close();

                }


                /*
                 * Get order products.
                 */

                $item_stmt = $conn->prepare("
                    SELECT
                        oi.quantity,
                        oi.price,
                        p.name,
                        p.image
                    FROM order_items oi
                    LEFT JOIN products p
                        ON oi.product_id = p.id
                    WHERE oi.order_id = ?
                    ORDER BY oi.id ASC
                ");


                if ($item_stmt) {

                    $item_stmt->bind_param(
                        "i",
                        $order_id
                    );

                    $item_stmt->execute();

                    $item_result =
                        $item_stmt->get_result();


                    while (
                        $item_row =
                        $item_result->fetch_assoc()
                    ) {

                        $order_details["items"][] = [

                            "name" =>
                                $item_row["name"]
                                ?: "Product",

                            "image" =>
                                $item_row["image"]
                                ?: "image/collection1.png",

                            "quantity" =>
                                (int) $item_row["quantity"],

                            "price" =>
                                (float) $item_row["price"]

                        ];

                    }


                    $item_stmt->close();

                }


                $modal_orders[$order_id] =
                    $order_details;

            }


            echo json_encode(
                $modal_orders,
                JSON_HEX_TAG |
                JSON_HEX_APOS |
                JSON_HEX_QUOT |
                JSON_HEX_AMP
            );

        ?>;


        /* =========================================
           MODAL ELEMENTS
        ========================================= */

        const orderModalOverlay =
            document.getElementById(
                "orderModalOverlay"
            );

        const modalCloseButton =
            document.getElementById(
                "modalCloseButton"
            );

        const modalCloseBottom =
            document.getElementById(
                "modalCloseBottom"
            );


        /* =========================================
           ESCAPE HTML
        ========================================= */

        function escapeHtml(value) {

            if (value === null ||
                value === undefined) {

                return "";

            }

            return String(value)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");

        }


        /* =========================================
           MONEY FORMAT
        ========================================= */

        function formatMoney(value) {

            return "₱" +
                Number(value || 0)
                    .toLocaleString(
                        "en-PH",
                        {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }
                    );

        }


        /* =========================================
           STATUS CLASS
        ========================================= */

        function getStatusClass(status) {

            const normalized =
                String(status || "")
                    .toLowerCase()
                    .trim();


            if (
                normalized === "completed" ||
                normalized === "delivered"
            ) {

                return "status-completed";

            }


            if (
                normalized === "cancelled"
            ) {

                return "status-cancelled";

            }


            if (
                normalized === "processing" ||
                normalized === "shipped"
            ) {

                return "status-processing";

            }


            return "status-pending";

        }


        /* =========================================
           FORMAT DATE
        ========================================= */

        function formatOrderDate(dateString) {

            if (!dateString) {
                return "—";
            }


            const date =
                new Date(
                    dateString.replace(" ", "T")
                );


            if (isNaN(date.getTime())) {
                return dateString;
            }


            return date.toLocaleString(
                "en-US",
                {
                    month: "long",
                    day: "numeric",
                    year: "numeric",
                    hour: "numeric",
                    minute: "2-digit"
                }
            );

        }


        /* =========================================
           OPEN ORDER MODAL
        ========================================= */

        function openOrderModal(orderId) {

            const order =
                orderData[orderId];


            if (!order) {

                return;

            }


            /* ORDER TITLE */

            document.getElementById(
                "modalOrderTitle"
            ).textContent =
                "Order #" + order.id;


            /* STATUS */

            const modalStatus =
                document.getElementById(
                    "modalOrderStatus"
                );


            modalStatus.textContent =
                order.status || "Pending";


            modalStatus.className =
                "order-status " +
                getStatusClass(order.status);


            /* DATE */

            document.getElementById(
                "modalOrderDate"
            ).textContent =
                formatOrderDate(
                    order.created_at
                );


            /* CUSTOMER */

            document.getElementById(
                "modalCustomerName"
            ).textContent =
                (
                    order.first_name +
                    " " +
                    order.last_name
                ).trim() || "—";


            document.getElementById(
                "modalCustomerEmail"
            ).textContent =
                order.email || "—";


            document.getElementById(
                "modalCustomerPhone"
            ).textContent =
                order.phone || "—";


            /* ADDRESS */

            let addressParts = [];


            if (order.address) {
                addressParts.push(order.address);
            }


            if (order.barangay) {
                addressParts.push(
                    order.barangay
                );
            }


            if (order.city) {
                addressParts.push(
                    order.city
                );
            }


            if (order.province) {
                addressParts.push(
                    order.province
                );
            }


            if (order.postal_code) {
                addressParts.push(
                    order.postal_code
                );
            }


            document.getElementById(
                "modalCustomerAddress"
            ).textContent =
                addressParts.length
                    ? addressParts.join(", ")
                    : "—";


            /* PAYMENT */

            document.getElementById(
                "modalPaymentMethod"
            ).textContent =
                order.payment_method || "—";


            document.getElementById(
                "modalPaymentStatus"
            ).textContent =
                order.payment_status || "Pending";


            /* DELIVERY NOTES */

            const notesSection =
                document.getElementById(
                    "modalNotesSection"
                );


            const notes =
                document.getElementById(
                    "modalDeliveryNotes"
                );


            if (order.delivery_notes) {

                notes.textContent =
                    order.delivery_notes;

                notesSection.style.display =
                    "block";

            } else {

                notes.textContent = "";

                notesSection.style.display =
                    "none";

            }


            /* PRODUCTS */

            const productsContainer =
                document.getElementById(
                    "modalProducts"
                );


            productsContainer.innerHTML = "";


            let subtotal = 0;


            if (
                order.items &&
                order.items.length > 0
            ) {


                order.items.forEach(function (item) {


                    const itemTotal =
                        Number(item.price) *
                        Number(item.quantity);


                    subtotal += itemTotal;


                    const product =
                        document.createElement(
                            "div"
                        );


                    product.className =
                        "modal-product";


                    product.innerHTML = `

                        <div class="modal-product-image">

                            <img
                                src="${escapeHtml(item.image)}"
                                alt="${escapeHtml(item.name)}"
                            >

                        </div>


                        <div class="modal-product-info">

                            <div class="modal-product-name">
                                ${escapeHtml(item.name)}
                            </div>

                            <div class="modal-product-quantity">
                                Quantity: ${Number(item.quantity)}
                            </div>

                        </div>


                        <div class="modal-product-price">
                            ${formatMoney(itemTotal)}
                        </div>

                    `;


                    productsContainer.appendChild(
                        product
                    );

                });


            } else {

                productsContainer.innerHTML = `

                    <div
                        style="
                            padding:25px;
                            text-align:center;
                            color:#7b837d;
                        "
                    >
                        No product information available.
                    </div>

                `;

            }


            /* TOTALS */

            const deliveryFee =
                Number(order.delivery_fee || 0);


            document.getElementById(
                "modalSubtotal"
            ).textContent =
                formatMoney(subtotal);


            document.getElementById(
                "modalDeliveryFee"
            ).textContent =
                formatMoney(deliveryFee);


            document.getElementById(
                "modalTotal"
            ).textContent =
                formatMoney(
                    order.total_amount
                );


            /* OPEN */

            orderModalOverlay.classList.add(
                "active"
            );


            orderModalOverlay.setAttribute(
                "aria-hidden",
                "false"
            );


            document.body.style.overflow =
                "hidden";

        }


        /* =========================================
           CLOSE ORDER MODAL
        ========================================= */

        function closeOrderModal() {

            orderModalOverlay.classList.remove(
                "active"
            );


            orderModalOverlay.setAttribute(
                "aria-hidden",
                "true"
            );


            document.body.style.overflow =
                "";

        }


        /* =========================================
           CLOSE BUTTONS
        ========================================= */

        modalCloseButton.addEventListener(
            "click",
            closeOrderModal
        );


        modalCloseBottom.addEventListener(
            "click",
            closeOrderModal
        );


        /* =========================================
           CLICK OUTSIDE MODAL
        ========================================= */

        orderModalOverlay.addEventListener(
            "click",
            function (event) {

                if (
                    event.target ===
                    orderModalOverlay
                ) {

                    closeOrderModal();

                }

            }
        );


        /* =========================================
           ESC KEY
        ========================================= */

        document.addEventListener(
            "keydown",
            function (event) {

                if (
                    event.key === "Escape" &&
                    orderModalOverlay.classList.contains(
                        "active"
                    )
                ) {

                    closeOrderModal();

                }

            }
        );


        /* =========================================
           MOBILE NAVIGATION
        ========================================= */

        const ordersMobileButton =
            document.getElementById(
                "ordersMobileButton"
            );

        const ordersNavMenu =
            document.getElementById(
                "ordersNavMenu"
            );


        if (
            ordersMobileButton &&
            ordersNavMenu
        ) {

            ordersMobileButton.addEventListener(
                "click",
                function () {

                    const isOpen =
                        ordersNavMenu.classList.toggle(
                            "active"
                        );


                    ordersMobileButton.setAttribute(
                        "aria-expanded",
                        isOpen
                            ? "true"
                            : "false"
                    );


                    ordersMobileButton.innerHTML =
                        isOpen
                            ? "✕"
                            : "☰";

                }
            );


            ordersNavMenu
                .querySelectorAll("a")
                .forEach(function (link) {

                    link.addEventListener(
                        "click",
                        function () {

                            ordersNavMenu
                                .classList
                                .remove(
                                    "active"
                                );


                            ordersMobileButton
                                .setAttribute(
                                    "aria-expanded",
                                    "false"
                                );


                            ordersMobileButton
                                .innerHTML =
                                "☰";

                        }
                    );

                });

        }

    </script>


</body>

</html>