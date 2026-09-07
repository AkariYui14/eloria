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
   GET LAST ORDER INFORMATION
========================================= */

$order_id = isset($_SESSION["last_order_id"])
    ? (int) $_SESSION["last_order_id"]
    : 0;

$order_total = isset($_SESSION["last_order_total"])
    ? (float) $_SESSION["last_order_total"]
    : 0;


/* =========================================
   IF NO ORDER INFORMATION EXISTS
========================================= */

if ($order_id <= 0) {

    header("Location: shop.php");
    exit;
}


/* =========================================
   VERIFY ORDER BELONGS TO CURRENT USER
========================================= */

$order_stmt = $conn->prepare("
    SELECT
        id,
        total_amount,
        status,
        created_at
    FROM orders
    WHERE id = ?
    AND user_id = ?
    LIMIT 1
");

$order_stmt->bind_param(
    "ii",
    $order_id,
    $user_id
);

$order_stmt->execute();

$order_result = $order_stmt->get_result();

$order = $order_result->fetch_assoc();

$order_stmt->close();


/* =========================================
   ORDER NOT FOUND
========================================= */

if (!$order) {

    unset($_SESSION["last_order_id"]);
    unset($_SESSION["last_order_total"]);

    header("Location: shop.php");
    exit;
}


/* =========================================
   USE DATABASE TOTAL
========================================= */

$order_total = (float) $order["total_amount"];

$status = $order["status"];

$created_at = $order["created_at"];


/* =========================================
   GET ORDER ITEMS
========================================= */

$order_items = [];

$items_stmt = $conn->prepare("
    SELECT
        oi.quantity,
        oi.price,
        p.name,
        p.image
    FROM order_items oi
    INNER JOIN products p
        ON oi.product_id = p.id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");

$items_stmt->bind_param(
    "i",
    $order_id
);

$items_stmt->execute();

$items_result = $items_stmt->get_result();


while ($item = $items_result->fetch_assoc()) {
    $order_items[] = $item;
}

$items_stmt->close();


/* =========================================
   CALCULATE ITEM COUNT
========================================= */

$item_count = 0;

foreach ($order_items as $item) {

    $item_count += (int) $item["quantity"];
}


/* =========================================
   HELPER
========================================= */

function success_image_path($image)
{
    if (empty($image)) {
        return "image/collection1.png";
    }

    return htmlspecialchars(
        $image,
        ENT_QUOTES,
        "UTF-8"
    );
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

    <title>Order Confirmed | Elora Plants</title>

    <link
        rel="stylesheet"
        href="style.css?v=8"
    >


    <style>

        /* =========================================
           ORDER SUCCESS PAGE
        ========================================= */

        .success-page {
            min-height: 100vh;
            background: #faf9f4;
        }


        /* =========================================
           NAVBAR
        ========================================= */

        .success-navbar {
            width: 100%;
            padding: 20px 6%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            background: #ffffff;
            border-bottom: 1px solid #e7e7df;
            position: sticky;
            top: 0;
            z-index: 1000;
        }


        .success-logo {
            text-decoration: none;
            color: #173d2a;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 30px;
            font-weight: bold;
            letter-spacing: 1px;
        }


        .success-nav-links {
            display: flex;
            align-items: center;
            gap: 24px;
        }


        .success-nav-links a {
            text-decoration: none;
            color: #294a38;
            font-size: 15px;
            font-weight: 600;
            transition: 0.2s ease;
        }


        .success-nav-links a:hover {
            color: #6f9f79;
        }


        /* =========================================
           HERO
        ========================================= */

        .success-hero {
            padding: 70px 6% 55px;
            background: #edf3e9;
            text-align: center;
        }


        .success-icon {
            width: 76px;
            height: 76px;
            margin: 0 auto 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #dcebdc;
            color: #315e3d;
            font-size: 36px;
            font-weight: bold;
        }


        .success-hero h1 {
            margin: 0;
            color: #1d402c;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(38px, 6vw, 58px);
        }


        .success-hero p {
            max-width: 650px;
            margin: 15px auto 0;
            color: #607065;
            font-size: 15px;
            line-height: 1.7;
        }


        /* =========================================
           MAIN CONTAINER
        ========================================= */

        .success-container {
            width: min(900px, 88%);
            margin: 0 auto;
            padding: 45px 0 80px;
        }


        /* =========================================
           ORDER CARD
        ========================================= */

        .order-card {
            background: #ffffff;
            border: 1px solid #e6ebe5;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(31, 58, 40, 0.06);
        }


        .order-header {
            padding: 25px 28px;
            border-bottom: 1px solid #e7ebe6;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }


        .order-header h2 {
            margin: 0;
            color: #284b36;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 26px;
        }


        .order-number {
            color: #758178;
            font-size: 13px;
        }


        .order-number strong {
            color: #294f37;
        }


        /* =========================================
           ORDER DETAILS
        ========================================= */

        .order-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            border-bottom: 1px solid #e7ebe6;
        }


        .order-detail {
            padding: 22px 25px;
            border-right: 1px solid #e7ebe6;
        }


        .order-detail:last-child {
            border-right: none;
        }


        .order-detail-label {
            display: block;
            margin-bottom: 7px;
            color: #829087;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }


        .order-detail-value {
            color: #294f37;
            font-size: 15px;
            font-weight: 600;
        }


        .order-status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #edf4ea;
            color: #3d6749;
            font-size: 12px;
        }


        /* =========================================
           ITEMS
        ========================================= */

        .order-items {
            padding: 10px 28px;
        }


        .order-item {
            display: grid;
            grid-template-columns: 70px minmax(0, 1fr) auto;
            align-items: center;
            gap: 16px;
            padding: 18px 0;
            border-bottom: 1px solid #edf0ec;
        }


        .order-item:last-child {
            border-bottom: none;
        }


        .order-item-image {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            overflow: hidden;
            background: #edf2e9;
        }


        .order-item-image img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }


        .order-item-name {
            margin: 0 0 6px;
            color: #294b37;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 18px;
        }


        .order-item-quantity {
            color: #7a857e;
            font-size: 12px;
        }


        .order-item-price {
            color: #294f37;
            font-size: 15px;
            font-weight: 600;
        }


        /* =========================================
           TOTAL
        ========================================= */

        .order-total {
            padding: 22px 28px;
            border-top: 1px solid #e5eae4;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            background: #fbfcf9;
        }


        .order-total-label {
            color: #607065;
            font-size: 14px;
        }


        .order-total-value {
            color: #1d402c;
            font-size: 24px;
            font-weight: bold;
        }


        /* =========================================
           MESSAGE
        ========================================= */

        .success-message {
            margin-bottom: 25px;
            padding: 18px 20px;
            border: 1px solid #c9e3cc;
            border-radius: 14px;
            background: #e7f3e8;
            color: #285b35;
            text-align: center;
            font-size: 14px;
            line-height: 1.6;
        }


        /* =========================================
           BUTTONS
        ========================================= */

        .success-actions {
            margin-top: 25px;
            display: flex;
            justify-content: center;
            gap: 13px;
        }


        .success-button {
            display: inline-block;
            padding: 14px 24px;
            border-radius: 12px;
            background: #294f37;
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s ease;
        }


        .success-button:hover {
            background: #1d402c;
            transform: translateY(-1px);
        }


        .success-button.secondary {
            background: #ffffff;
            border: 1px solid #d6dfd5;
            color: #31513d;
        }


        .success-button.secondary:hover {
            background: #f2f5f0;
        }


        /* =========================================
           FOOTER
        ========================================= */

        .success-footer {
            padding: 30px 6%;
            text-align: center;
            background: #1d3828;
            color: #dce7dd;
            font-size: 13px;
        }


        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 700px) {

            .success-navbar {
                padding: 16px 5%;
            }


            .success-logo {
                font-size: 25px;
            }


            .success-nav-links {
                gap: 12px;
            }


            .success-nav-links a {
                font-size: 13px;
            }


            .success-hero {
                padding: 55px 5% 45px;
            }


            .success-container {
                width: 90%;
                padding-top: 30px;
            }


            .order-header {
                align-items: flex-start;
                flex-direction: column;
            }


            .order-details {
                grid-template-columns: 1fr;
            }


            .order-detail {
                border-right: none;
                border-bottom: 1px solid #e7ebe6;
            }


            .order-detail:last-child {
                border-bottom: none;
            }


            .order-item {
                grid-template-columns: 60px minmax(0, 1fr);
            }


            .order-item-image {
                width: 60px;
                height: 60px;
            }


            .order-item-price {
                grid-column: 2;
            }


            .success-actions {
                flex-direction: column;
            }


            .success-button {
                width: 100%;
                box-sizing: border-box;
                text-align: center;
            }

        }


        @media (max-width: 450px) {

            .success-nav-links a:not(:last-child) {
                display: none;
            }


            .success-hero h1 {
                font-size: 38px;
            }


            .order-items {
                padding: 10px 20px;
            }


            .order-header {
                padding: 22px 20px;
            }


            .order-total {
                padding: 20px;
            }

        }

    </style>

</head>


<body>

<div class="success-page">


    <!-- =========================================
         NAVBAR
    ========================================== -->

    <nav class="success-navbar">

        <a
            href="index.php"
            class="success-logo"
        >
            Elora
        </a>


        <div class="success-nav-links">

            <a href="index.php">
                Home
            </a>

            <a href="shop.php">
                Shop
            </a>

            <a href="cart.php">
                Cart
            </a>

            <a href="profile.php">
                Profile
            </a>

            <a href="logout.php">
                Log Out
            </a>

        </div>

    </nav>


    <!-- =========================================
         SUCCESS HERO
    ========================================== -->

    <section class="success-hero">

        <div class="success-icon">
            ✓
        </div>


        <h1>
            Order Confirmed!
        </h1>


        <p>
            Thank you for your order.
            Your plants have been successfully
            placed and are now being prepared.
        </p>

    </section>


    <!-- =========================================
         MAIN
    ========================================== -->

    <main class="success-container">


        <div class="success-message">

            Your order has been successfully created.
            Please keep your order number for reference.

        </div>


        <!-- =====================================
             ORDER CARD
        ====================================== -->

        <section class="order-card">


            <!-- ORDER HEADER -->

            <div class="order-header">

                <h2>
                    Order Summary
                </h2>


                <div class="order-number">

                    Order #

                    <strong>
                        <?php echo $order_id; ?>
                    </strong>

                </div>

            </div>


            <!-- ORDER DETAILS -->

            <div class="order-details">


                <div class="order-detail">

                    <span class="order-detail-label">
                        Order Date
                    </span>

                    <span class="order-detail-value">

                        <?php
                        echo date(
                            "M d, Y",
                            strtotime($created_at)
                        );
                        ?>

                    </span>

                </div>


                <div class="order-detail">

                    <span class="order-detail-label">
                        Status
                    </span>

                    <span class="order-detail-value">

                        <span class="order-status">

                            <?php
                            echo htmlspecialchars(
                                $status,
                                ENT_QUOTES,
                                "UTF-8"
                            );
                            ?>

                        </span>

                    </span>

                </div>


                <div class="order-detail">

                    <span class="order-detail-label">
                        Items
                    </span>

                    <span class="order-detail-value">

                        <?php echo $item_count; ?>

                        item<?php
                        echo $item_count === 1
                            ? ""
                            : "s";
                        ?>

                    </span>

                </div>


            </div>


            <!-- ORDER ITEMS -->

            <div class="order-items">


                <?php foreach ($order_items as $item): ?>

                    <?php

                    $quantity =
                        (int) $item["quantity"];

                    $price =
                        (float) $item["price"];

                    $item_total =
                        $quantity * $price;

                    ?>


                    <div class="order-item">


                        <div class="order-item-image">

                            <img
                                src="<?php
                                echo success_image_path(
                                    $item["image"]
                                );
                                ?>"
                                alt="<?php
                                echo htmlspecialchars(
                                    $item["name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>"
                                onerror="this.src='image/collection1.png';"
                            >

                        </div>


                        <div>

                            <h3 class="order-item-name">

                                <?php
                                echo htmlspecialchars(
                                    $item["name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>

                            </h3>


                            <div class="order-item-quantity">

                                Quantity:
                                <?php echo $quantity; ?>

                            </div>

                        </div>


                        <div class="order-item-price">

                            $<?php
                            echo number_format(
                                $item_total,
                                2
                            );
                            ?>

                        </div>


                    </div>

                <?php endforeach; ?>


            </div>


            <!-- TOTAL -->

            <div class="order-total">

                <span class="order-total-label">

                    Total

                </span>


                <span class="order-total-value">

                    $<?php
                    echo number_format(
                        $order_total,
                        2
                    );
                    ?>

                </span>

            </div>


        </section>


        <!-- =====================================
             ACTION BUTTONS
        ====================================== -->

        <div class="success-actions">


            <a
                href="shop.php"
                class="success-button"
            >
                Continue Shopping
            </a>


            <a
                href="index.php"
                class="success-button secondary"
            >
                Back to Home
            </a>


        </div>


    </main>


    <!-- =========================================
         FOOTER
    ========================================== -->

    <footer class="success-footer">

        © <?php echo date("Y"); ?> Elora Plants.
        Grow something beautiful.

    </footer>


</div>

</body>

</html>