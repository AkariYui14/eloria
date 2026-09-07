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
   CART ACTIONS
========================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = isset($_POST["action"])
        ? $_POST["action"]
        : "";

    $cart_item_id = isset($_POST["cart_item_id"])
        ? (int) $_POST["cart_item_id"]
        : 0;


    /* =====================================
       REMOVE ITEM
    ====================================== */

    if ($action === "remove") {

        $delete_stmt = $conn->prepare("
            DELETE FROM cart_items
            WHERE id = ?
            AND user_id = ?
        ");

        $delete_stmt->bind_param(
            "ii",
            $cart_item_id,
            $user_id
        );

        $delete_stmt->execute();

        $delete_stmt->close();

        $_SESSION["cart_success"] =
            "Product removed from your cart.";

        header("Location: cart.php");
        exit;
    }


    /* =====================================
       UPDATE QUANTITY
    ====================================== */

    if ($action === "update") {

        $quantity = isset($_POST["quantity"])
            ? (int) $_POST["quantity"]
            : 1;


        if ($quantity < 1) {
            $quantity = 1;
        }


        $stock_stmt = $conn->prepare("
            SELECT
                ci.product_id,
                p.stock,
                p.is_active
            FROM cart_items ci
            INNER JOIN products p
                ON ci.product_id = p.id
            WHERE ci.id = ?
            AND ci.user_id = ?
            LIMIT 1
        ");

        $stock_stmt->bind_param(
            "ii",
            $cart_item_id,
            $user_id
        );

        $stock_stmt->execute();

        $stock_result = $stock_stmt->get_result();
        $stock_data = $stock_result->fetch_assoc();

        $stock_stmt->close();


        if (!$stock_data) {

            $_SESSION["cart_error"] =
                "Cart item not found.";

        } elseif ((int) $stock_data["is_active"] !== 1) {

            $_SESSION["cart_error"] =
                "This product is no longer available.";

        } elseif ((int) $stock_data["stock"] <= 0) {

            $_SESSION["cart_error"] =
                "This product is out of stock.";

        } else {

            $stock = (int) $stock_data["stock"];


            if ($quantity > $stock) {

                $quantity = $stock;

                $_SESSION["cart_success"] =
                    "Quantity adjusted to the available stock.";

            } else {

                $_SESSION["cart_success"] =
                    "Cart updated successfully.";
            }


            $update_stmt = $conn->prepare("
                UPDATE cart_items
                SET quantity = ?
                WHERE id = ?
                AND user_id = ?
            ");

            $update_stmt->bind_param(
                "iii",
                $quantity,
                $cart_item_id,
                $user_id
            );

            $update_stmt->execute();

            $update_stmt->close();
        }


        header("Location: cart.php");
        exit;
    }


    /* =====================================
       INCREASE QUANTITY
    ====================================== */

    if ($action === "increase") {

        $item_stmt = $conn->prepare("
            SELECT
                ci.quantity,
                p.stock,
                p.is_active
            FROM cart_items ci
            INNER JOIN products p
                ON ci.product_id = p.id
            WHERE ci.id = ?
            AND ci.user_id = ?
            LIMIT 1
        ");

        $item_stmt->bind_param(
            "ii",
            $cart_item_id,
            $user_id
        );

        $item_stmt->execute();

        $item_result = $item_stmt->get_result();
        $item = $item_result->fetch_assoc();

        $item_stmt->close();


        if ($item) {

            $current_quantity =
                (int) $item["quantity"];

            $stock =
                (int) $item["stock"];


            if ((int) $item["is_active"] !== 1) {

                $_SESSION["cart_error"] =
                    "This product is no longer available.";

            } elseif ($stock <= 0) {

                $_SESSION["cart_error"] =
                    "This product is out of stock.";

            } elseif ($current_quantity >= $stock) {

                $_SESSION["cart_error"] =
                    "You have reached the available stock.";

            } else {

                $new_quantity =
                    $current_quantity + 1;


                $update_stmt = $conn->prepare("
                    UPDATE cart_items
                    SET quantity = ?
                    WHERE id = ?
                    AND user_id = ?
                ");

                $update_stmt->bind_param(
                    "iii",
                    $new_quantity,
                    $cart_item_id,
                    $user_id
                );

                $update_stmt->execute();

                $update_stmt->close();
            }

        } else {

            $_SESSION["cart_error"] =
                "Cart item not found.";
        }


        header("Location: cart.php");
        exit;
    }


    /* =====================================
       DECREASE QUANTITY
    ====================================== */

    if ($action === "decrease") {

        $item_stmt = $conn->prepare("
            SELECT quantity
            FROM cart_items
            WHERE id = ?
            AND user_id = ?
            LIMIT 1
        ");

        $item_stmt->bind_param(
            "ii",
            $cart_item_id,
            $user_id
        );

        $item_stmt->execute();

        $item_result = $item_stmt->get_result();
        $item = $item_result->fetch_assoc();

        $item_stmt->close();


        if ($item) {

            $current_quantity =
                (int) $item["quantity"];


            if ($current_quantity > 1) {

                $new_quantity =
                    $current_quantity - 1;


                $update_stmt = $conn->prepare("
                    UPDATE cart_items
                    SET quantity = ?
                    WHERE id = ?
                    AND user_id = ?
                ");

                $update_stmt->bind_param(
                    "iii",
                    $new_quantity,
                    $cart_item_id,
                    $user_id
                );

                $update_stmt->execute();

                $update_stmt->close();

            } else {

                $delete_stmt = $conn->prepare("
                    DELETE FROM cart_items
                    WHERE id = ?
                    AND user_id = ?
                ");

                $delete_stmt->bind_param(
                    "ii",
                    $cart_item_id,
                    $user_id
                );

                $delete_stmt->execute();

                $delete_stmt->close();

                $_SESSION["cart_success"] =
                    "Product removed from your cart.";
            }

        } else {

            $_SESSION["cart_error"] =
                "Cart item not found.";
        }


        header("Location: cart.php");
        exit;
    }


    /* =====================================
       CLEAR CART
    ====================================== */

    if ($action === "clear") {

        $clear_stmt = $conn->prepare("
            DELETE FROM cart_items
            WHERE user_id = ?
        ");

        $clear_stmt->bind_param(
            "i",
            $user_id
        );

        $clear_stmt->execute();

        $clear_stmt->close();

        $_SESSION["cart_success"] =
            "Your cart has been cleared.";

        header("Location: cart.php");
        exit;
    }
}


/* =========================================
   GET CART ITEMS
========================================= */

$cart_items = [];

$cart_stmt = $conn->prepare("
    SELECT
        ci.id AS cart_item_id,
        ci.product_id,
        ci.quantity,
        p.name,
        p.description,
        p.price,
        p.stock,
        p.image,
        p.category,
        p.is_active
    FROM cart_items ci
    INNER JOIN products p
        ON ci.product_id = p.id
    WHERE ci.user_id = ?
    ORDER BY ci.created_at DESC
");

$cart_stmt->bind_param(
    "i",
    $user_id
);

$cart_stmt->execute();

$cart_result = $cart_stmt->get_result();


while ($row = $cart_result->fetch_assoc()) {
    $cart_items[] = $row;
}

$cart_stmt->close();


/* =========================================
   CALCULATE TOTALS
========================================= */

$subtotal = 0;
$cart_count = 0;


foreach ($cart_items as $item) {

    $quantity = (int) $item["quantity"];

    $price = (float) $item["price"];

    $subtotal += $price * $quantity;

    $cart_count += $quantity;
}


$total = $subtotal;


/* =========================================
   FLASH MESSAGES
========================================= */

$success_message = "";

$error_message = "";


if (isset($_SESSION["cart_success"])) {

    $success_message =
        $_SESSION["cart_success"];

    unset($_SESSION["cart_success"]);
}


if (isset($_SESSION["cart_error"])) {

    $error_message =
        $_SESSION["cart_error"];

    unset($_SESSION["cart_error"]);
}


/* =========================================
   HELPER
========================================= */

function cart_image_path($image)
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

    <title>My Cart | Elora Plants</title>

    <link
        rel="stylesheet"
        href="style.css?v=7"
    >

    <style>

        /* =========================================
           CART PAGE
        ========================================= */

        .cart-page {
            min-height: 100vh;
            background: #faf9f4;
        }


        /* =========================================
           NAVBAR
        ========================================= */

        .cart-navbar {
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


        /* =========================================
           ELORA LOGO
        ========================================= */

        .cart-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            line-height: 0;
        }


        .cart-logo img {
            display: block;
            width: 145px;
            height: auto;
            max-height: 55px;
            object-fit: contain;
        }


        .cart-nav-links {
            display: flex;
            align-items: center;
            gap: 24px;
        }


        .cart-nav-links a {
            text-decoration: none;
            color: #294a38;
            font-size: 15px;
            font-weight: 600;
            transition: 0.2s ease;
        }


        .cart-nav-links a:hover {
            color: #6f9f79;
        }


        .cart-nav-current {
            color: #6f9f79 !important;
        }


        /* =========================================
           CART NAVIGATION
        ========================================== */

        .cart-nav-cart {
            position: relative;
            display: inline-flex !important;
            align-items: center;
            gap: 7px;
        }


        .cart-badge {
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
           HERO
        ========================================= */

        .cart-hero {
            padding: 60px 6% 45px;
            background: #edf3e9;
            text-align: center;
        }


        .cart-hero h1 {
            margin: 0;
            color: #1d402c;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(40px, 6vw, 60px);
        }


        .cart-hero p {
            max-width: 600px;
            margin: 15px auto 0;
            color: #607065;
            font-size: 15px;
            line-height: 1.7;
        }


        /* =========================================
           CONTAINER
        ========================================= */

        .cart-container {
            width: min(1200px, 88%);
            margin: 0 auto;
            padding: 45px 0 80px;
        }


        /* =========================================
           MESSAGES
        ========================================= */

        .cart-message {
            padding: 15px 18px;
            margin-bottom: 25px;
            border-radius: 12px;
            font-size: 14px;
        }


        .cart-success {
            background: #e7f3e8;
            border: 1px solid #c9e3cc;
            color: #285b35;
        }


        .cart-error {
            background: #f9e9e7;
            border: 1px solid #edcbc7;
            color: #8a3d35;
        }


        /* =========================================
           CART LAYOUT
        ========================================= */

        .cart-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 350px;
            gap: 30px;
            align-items: start;
        }


        /* =========================================
           CART ITEMS
        ========================================= */

        .cart-items-box {
            background: #ffffff;
            border: 1px solid #e6ebe5;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(31, 58, 40, 0.06);
        }


        .cart-items-header {
            padding: 23px 25px;
            border-bottom: 1px solid #e8ece7;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }


        .cart-items-header h2 {
            margin: 0;
            color: #284b36;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 25px;
        }


        .cart-items-header span {
            color: #758178;
            font-size: 13px;
        }


        .cart-item {
            display: grid;
            grid-template-columns: 105px minmax(0, 1fr) auto;
            gap: 20px;
            align-items: center;
            padding: 23px 25px;
            border-bottom: 1px solid #edf0ec;
        }


        .cart-item:last-child {
            border-bottom: none;
        }


        /* =========================================
           PRODUCT IMAGE
        ========================================= */

        .cart-item-image {
            width: 105px;
            height: 105px;
            border-radius: 14px;
            overflow: hidden;
            background: #edf2e9;
        }


        .cart-item-image img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }


        /* =========================================
           PRODUCT INFORMATION
        ========================================= */

        .cart-item-category {
            display: block;
            margin-bottom: 5px;
            color: #78937e;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }


        .cart-item-name {
            margin: 0 0 7px;
            color: #284b36;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 21px;
        }


        .cart-item-description {
            margin: 0 0 9px;
            color: #748078;
            font-size: 13px;
            line-height: 1.5;
        }


        .cart-item-price {
            color: #294f37;
            font-size: 14px;
            font-weight: 600;
        }


        .cart-item-stock {
            margin-top: 4px;
            color: #7a857e;
            font-size: 11px;
        }


        .cart-item-stock.warning {
            color: #a66a30;
        }


        /* =========================================
           ITEM ACTIONS
        ========================================= */

        .cart-item-actions {
            min-width: 150px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 12px;
        }


        .quantity-controls {
            display: flex;
            align-items: center;
            border: 1px solid #dbe2d9;
            border-radius: 10px;
            overflow: hidden;
            background: #ffffff;
        }


        .quantity-button {
            width: 36px;
            height: 36px;
            border: none;
            background: #f1f4ef;
            color: #294f37;
            cursor: pointer;
            font-size: 18px;
            transition: 0.2s ease;
        }


        .quantity-button:hover {
            background: #e3eadf;
        }


        .quantity-button:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }


        .quantity-button:disabled:hover {
            background: #f1f4ef;
        }


        .quantity-value {
            min-width: 40px;
            text-align: center;
            color: #294f37;
            font-size: 14px;
            font-weight: 600;
        }


        .remove-button {
            border: none;
            background: transparent;
            color: #a45249;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            padding: 3px;
        }


        .remove-button:hover {
            color: #813a33;
            text-decoration: underline;
        }


        .item-total {
            color: #1d402c;
            font-size: 17px;
            font-weight: bold;
        }


        /* =========================================
           SUMMARY
        ========================================= */

        .cart-summary {
            padding: 26px;
            background: #ffffff;
            border: 1px solid #e6ebe5;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(31, 58, 40, 0.06);
            position: sticky;
            top: 100px;
        }


        .cart-summary h2 {
            margin: 0 0 25px;
            color: #284b36;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 27px;
        }


        .summary-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 10px 0;
            color: #69766e;
            font-size: 14px;
        }


        .summary-total {
            margin-top: 10px;
            padding-top: 18px;
            border-top: 1px solid #e5eae4;
            color: #1d402c;
            font-size: 20px;
            font-weight: bold;
        }


        /* =========================================
           CHECKOUT BUTTON
        ========================================= */

        .checkout-button {
            display: block;
            width: 100%;
            margin-top: 22px;
            padding: 15px;
            border: none;
            border-radius: 12px;
            background: #294f37;
            color: #ffffff;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s ease;
            box-sizing: border-box;
        }


        .checkout-button:hover {
            background: #1d402c;
            transform: translateY(-1px);
        }


        /* =========================================
           CONTINUE SHOPPING
        ========================================= */

        .continue-shopping {
            display: block;
            margin-top: 13px;
            padding: 14px;
            border: 1px solid #d6dfd5;
            border-radius: 12px;
            color: #31513d;
            text-align: center;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s ease;
        }


        .continue-shopping:hover {
            background: #f2f5f0;
        }


        /* =========================================
           CLEAR CART
        ========================================= */

        .clear-cart-form {
            margin-top: 13px;
        }


        .clear-cart-button {
            width: 100%;
            padding: 12px;
            border: none;
            background: transparent;
            color: #a45249;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }


        .clear-cart-button:hover {
            text-decoration: underline;
        }


        /* =========================================
           EMPTY CART
        ========================================= */

        .empty-cart {
            padding: 80px 25px;
            text-align: center;
            background: #ffffff;
            border: 1px solid #e6ebe5;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(31, 58, 40, 0.05);
        }


        .empty-cart-icon {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }


        .empty-cart-icon svg {
            width: 55px;
            height: 55px;
            display: block;
        }


        .empty-cart h2 {
            margin: 0 0 10px;
            color: #284b36;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 31px;
        }


        .empty-cart p {
            max-width: 500px;
            margin: 0 auto 25px;
            color: #718078;
            font-size: 14px;
            line-height: 1.7;
        }


        .empty-cart-button {
            display: inline-block;
            padding: 13px 22px;
            border-radius: 11px;
            background: #294f37;
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }


        .empty-cart-button:hover {
            background: #1d402c;
        }


        /* =========================================
           CUSTOM CONFIRMATION MODAL
        ========================================= */

        .cart-confirm-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(22, 45, 31, 0.48);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }


        .cart-confirm-overlay.active {
            display: flex;
            animation: cartModalFadeIn 0.2s ease;
        }


        .cart-confirm-modal {
            width: min(430px, 100%);
            padding: 32px;
            background: #ffffff;
            border: 1px solid #e2e9e1;
            border-radius: 22px;
            box-shadow: 0 20px 60px rgba(20, 48, 31, 0.22);
            text-align: center;
            animation: cartModalScaleIn 0.22s ease;
        }


        .cart-confirm-icon {
            width: 58px;
            height: 58px;
            margin: 0 auto 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5e9e7;
            color: #a45249;
        }


        .cart-confirm-icon svg {
            width: 27px;
            height: 27px;
        }


        .cart-confirm-title {
            margin: 0 0 10px;
            color: #284b36;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 28px;
        }


        .cart-confirm-text {
            margin: 0 auto;
            max-width: 340px;
            color: #6f7c73;
            font-size: 14px;
            line-height: 1.7;
        }


        .cart-confirm-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 25px;
        }


        .cart-confirm-cancel,
        .cart-confirm-delete {
            min-width: 120px;
            padding: 12px 20px;
            border-radius: 11px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s ease;
        }


        .cart-confirm-cancel {
            border: 1px solid #d7e0d6;
            background: #ffffff;
            color: #31513d;
        }


        .cart-confirm-cancel:hover {
            background: #f2f5f0;
        }


        .cart-confirm-delete {
            border: 1px solid #a45249;
            background: #a45249;
            color: #ffffff;
        }


        .cart-confirm-delete:hover {
            background: #873f38;
            border-color: #873f38;
            transform: translateY(-1px);
        }


        @keyframes cartModalFadeIn {

            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }

        }


        @keyframes cartModalScaleIn {

            from {
                opacity: 0;
                transform: scale(0.94) translateY(8px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }

        }


        /* =========================================
           FOOTER
        ========================================= */

        .cart-footer {
            padding: 30px 6%;
            text-align: center;
            background: #1d3828;
            color: #dce7dd;
            font-size: 13px;
        }


        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 950px) {

            .cart-layout {
                grid-template-columns: 1fr;
            }


            .cart-summary {
                position: static;
            }

        }


        @media (max-width: 700px) {

            .cart-navbar {
                padding: 16px 5%;
            }


            .cart-logo img {
                width: 120px;
                max-height: 45px;
            }


            .cart-nav-links {
                gap: 12px;
            }


            .cart-nav-links a {
                font-size: 13px;
            }


            .cart-hero {
                padding: 50px 5% 40px;
            }


            .cart-container {
                width: 90%;
                padding-top: 30px;
            }


            .cart-item {
                grid-template-columns: 80px minmax(0, 1fr);
                gap: 15px;
                padding: 20px;
            }


            .cart-item-image {
                width: 80px;
                height: 80px;
            }


            .cart-item-actions {
                grid-column: 1 / -1;
                width: 100%;
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                min-width: 0;
            }


            .cart-confirm-modal {
                padding: 28px 22px;
            }

        }


        @media (max-width: 450px) {

            .cart-logo img {
                width: 105px;
            }


            .cart-nav-links a:not(.cart-nav-current) {
                display: none;
            }


            .cart-item {
                grid-template-columns: 70px minmax(0, 1fr);
            }


            .cart-item-image {
                width: 70px;
                height: 70px;
            }


            .cart-item-name {
                font-size: 18px;
            }


            .cart-item-description {
                display: none;
            }


            .cart-item-actions {
                flex-wrap: wrap;
            }


            .cart-confirm-actions {
                flex-direction: column-reverse;
            }


            .cart-confirm-cancel,
            .cart-confirm-delete {
                width: 100%;
            }

        }

    </style>

</head>


<body>

<div class="cart-page">


    <!-- =========================================
         NAVBAR
    ========================================== -->

    <nav class="cart-navbar">


        <!-- ELORA LOGO -->

        <a
            href="index.php"
            class="cart-logo"
        >

            <img
                src="image/logo.png"
                alt="Elora Plants"
                onerror="this.style.display='none';"
            >

        </a>


        <div class="cart-nav-links">

            <a href="index.php">
                Home
            </a>

            <a href="shop.php">
                Shop
            </a>


            <!-- CART -->

            <a
                href="cart.php"
                class="cart-nav-current cart-nav-cart"
            >

                🛒 Cart

                <span class="cart-badge">
                    <?php echo $cart_count; ?>
                </span>

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
         HERO
    ========================================== -->

    <section class="cart-hero">

        <h1>
            Your Cart
        </h1>

        <p>
            Review the plants you've selected
            before continuing to checkout.
        </p>

    </section>


    <!-- =========================================
         MAIN
    ========================================== -->

    <main class="cart-container">


        <!-- =====================================
             MESSAGES
        ====================================== -->

        <?php if ($success_message !== ""): ?>

            <div class="cart-message cart-success">

                <?php
                echo htmlspecialchars(
                    $success_message,
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>

            </div>

        <?php endif; ?>


        <?php if ($error_message !== ""): ?>

            <div class="cart-message cart-error">

                <?php
                echo htmlspecialchars(
                    $error_message,
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>

            </div>

        <?php endif; ?>


        <?php if (!empty($cart_items)): ?>


            <div class="cart-layout">


                <!-- =================================
                     CART ITEMS
                ================================== -->

                <section class="cart-items-box">


                    <div class="cart-items-header">

                        <h2>
                            Selected Plants
                        </h2>

                        <span>
                            <?php echo $cart_count; ?>
                            item<?php
                            echo $cart_count === 1
                                ? ""
                                : "s";
                            ?>
                        </span>

                    </div>


                    <?php foreach ($cart_items as $item): ?>

                        <?php

                        $cart_item_id =
                            (int) $item["cart_item_id"];

                        $quantity =
                            (int) $item["quantity"];

                        $stock =
                            (int) $item["stock"];

                        $price =
                            (float) $item["price"];

                        $item_total =
                            $price * $quantity;

                        ?>


                        <article class="cart-item">


                            <!-- IMAGE -->

                            <div class="cart-item-image">

                                <img
                                    src="<?php
                                    echo cart_image_path(
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


                            <!-- INFORMATION -->

                            <div class="cart-item-info">


                                <?php if (!empty($item["category"])): ?>

                                    <span class="cart-item-category">

                                        <?php
                                        echo htmlspecialchars(
                                            $item["category"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );
                                        ?>

                                    </span>

                                <?php endif; ?>


                                <h3 class="cart-item-name">

                                    <?php
                                    echo htmlspecialchars(
                                        $item["name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </h3>


                                <p class="cart-item-description">

                                    <?php
                                    echo htmlspecialchars(
                                        $item["description"] ?? "",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </p>


                                <div class="cart-item-price">

                                    $<?php
                                    echo number_format(
                                        $price,
                                        2
                                    );
                                    ?>

                                    each

                                </div>


                                <div class="cart-item-stock
                                    <?php
                                    echo $quantity >= $stock
                                        ? "warning"
                                        : "";
                                    ?>"
                                >

                                    <?php echo $stock; ?>
                                    available

                                </div>

                            </div>


                            <!-- ACTIONS -->

                            <div class="cart-item-actions">


                                <!-- QUANTITY -->

                                <div class="quantity-controls">


                                    <!-- DECREASE -->

                                    <form
                                        action="cart.php"
                                        method="POST"
                                        style="margin: 0;"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="decrease"
                                        >

                                        <input
                                            type="hidden"
                                            name="cart_item_id"
                                            value="<?php
                                            echo $cart_item_id;
                                            ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="quantity-button"
                                            aria-label="Decrease quantity"
                                        >
                                            −
                                        </button>

                                    </form>


                                    <span class="quantity-value">

                                        <?php echo $quantity; ?>

                                    </span>


                                    <!-- INCREASE -->

                                    <form
                                        action="cart.php"
                                        method="POST"
                                        style="margin: 0;"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="increase"
                                        >

                                        <input
                                            type="hidden"
                                            name="cart_item_id"
                                            value="<?php
                                            echo $cart_item_id;
                                            ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="quantity-button"
                                            aria-label="Increase quantity"
                                            <?php
                                            echo $quantity >= $stock
                                                ? "disabled"
                                                : "";
                                            ?>
                                        >
                                            +
                                        </button>

                                    </form>


                                </div>


                                <!-- ITEM TOTAL -->

                                <div class="item-total">

                                    $<?php
                                    echo number_format(
                                        $item_total,
                                        2
                                    );
                                    ?>

                                </div>


                                <!-- REMOVE -->

                                <form
                                    action="cart.php"
                                    method="POST"
                                    class="remove-cart-form"
                                    style="margin: 0;"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="remove"
                                    >

                                    <input
                                        type="hidden"
                                        name="cart_item_id"
                                        value="<?php
                                        echo $cart_item_id;
                                        ?>"
                                    >

                                    <button
                                        type="button"
                                        class="remove-button"
                                        onclick="openRemoveModal(this)"
                                    >
                                        Remove
                                    </button>

                                </form>


                            </div>


                        </article>

                    <?php endforeach; ?>


                </section>


                <!-- =================================
                     SUMMARY
                ================================== -->

                <aside class="cart-summary">


                    <h2>
                        Order Summary
                    </h2>


                    <div class="summary-row">

                        <span>
                            Items
                        </span>

                        <span>
                            <?php echo $cart_count; ?>
                        </span>

                    </div>


                    <div class="summary-row">

                        <span>
                            Subtotal
                        </span>

                        <span>
                            $<?php
                            echo number_format(
                                $subtotal,
                                2
                            );
                            ?>
                        </span>

                    </div>


                    <div class="summary-row">

                        <span>
                            Shipping
                        </span>

                        <span>
                            To be calculated
                        </span>

                    </div>


                    <div class="summary-row summary-total">

                        <span>
                            Total
                        </span>

                        <span>
                            $<?php
                            echo number_format(
                                $total,
                                2
                            );
                            ?>
                        </span>

                    </div>


                    <!-- CHECKOUT -->

                    <a
                        href="checkout.php"
                        class="checkout-button"
                    >
                        Proceed to Checkout
                    </a>


                    <!-- CONTINUE SHOPPING -->

                    <a
                        href="shop.php"
                        class="continue-shopping"
                    >
                        Continue Shopping
                    </a>


                    <!-- CLEAR CART -->

                    <form
                        action="cart.php"
                        method="POST"
                        class="clear-cart-form"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="clear"
                        >

                        <button
                            type="button"
                            class="clear-cart-button"
                            onclick="openClearModal(this)"
                        >
                            Clear Entire Cart
                        </button>

                    </form>


                </aside>


            </div>


        <?php else: ?>


            <!-- =================================
                 EMPTY CART
            ================================== -->

            <section class="empty-cart">


                <div class="empty-cart-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                        aria-hidden="true"
                    >

                        <path
                            d="M3 4H5L7.2 14.2C7.4 15.1 8.2 15.7 9.1 15.7H17.8C18.7 15.7 19.5 15.1 19.7 14.2L21 8H6"
                            stroke="#294f37"
                            stroke-width="1.6"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />

                        <circle
                            cx="9.5"
                            cy="19"
                            r="1.5"
                            fill="#294f37"
                        />

                        <circle
                            cx="17"
                            cy="19"
                            r="1.5"
                            fill="#294f37"
                        />

                    </svg>

                </div>


                <h2>
                    Your cart is empty
                </h2>


                <p>
                    You haven't added any plants yet.
                    Explore our collection and find
                    something beautiful for your space.
                </p>


                <a
                    href="shop.php"
                    class="empty-cart-button"
                >
                    Browse Plants
                </a>

            </section>


        <?php endif; ?>


    </main>


    <!-- =========================================
         CUSTOM CONFIRMATION MODAL
    ========================================== -->

    <div
        class="cart-confirm-overlay"
        id="cartConfirmOverlay"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cartConfirmTitle"
    >

        <div
            class="cart-confirm-modal"
            onclick="event.stopPropagation();"
        >


            <!-- ICON -->

            <div class="cart-confirm-icon">

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >

                    <path
                        d="M12 9V13"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />

                    <circle
                        cx="12"
                        cy="16.5"
                        r="1"
                        fill="currentColor"
                    />

                    <path
                        d="M10.3 4.8L2.8 18C2.1 19.2 3 20.7 4.4 20.7H19.6C21 20.7 21.9 19.2 21.2 18L13.7 4.8C13 3.6 11 3.6 10.3 4.8Z"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linejoin="round"
                    />

                </svg>

            </div>


            <!-- TITLE -->

            <h2
                class="cart-confirm-title"
                id="cartConfirmTitle"
            >
                Remove Product?
            </h2>


            <!-- MESSAGE -->

            <p
                class="cart-confirm-text"
                id="cartConfirmText"
            >
                Are you sure you want to remove this product from your cart?
            </p>


            <!-- BUTTONS -->

            <div class="cart-confirm-actions">


                <button
                    type="button"
                    class="cart-confirm-cancel"
                    onclick="closeCartConfirm()"
                >
                    Cancel
                </button>


                <button
                    type="button"
                    class="cart-confirm-delete"
                    id="cartConfirmActionButton"
                    onclick="confirmCartAction()"
                >
                    Remove Product
                </button>


            </div>


        </div>

    </div>


    <!-- =========================================
         FOOTER
    ========================================== -->

    <footer class="cart-footer">

        © <?php echo date("Y"); ?> Elora Plants.
        Grow something beautiful.

    </footer>


</div>


<script>

    /* =========================================
       AUTOMATICALLY HIDE MESSAGES
    ========================================== */

    setTimeout(function () {

        const messages =
            document.querySelectorAll(
                ".cart-message"
            );


        messages.forEach(function (message) {

            message.style.transition =
                "opacity 0.4s ease";

            message.style.opacity = "0";


            setTimeout(function () {

                message.remove();

            }, 400);

        });

    }, 4000);


    /* =========================================
       KEEP CART SCROLL POSITION
    ========================================== */

    const cartScrollKey =
        "cartScrollPosition";


    const cartForms =
        document.querySelectorAll(
            'form[action="cart.php"][method="POST"]'
        );


    cartForms.forEach(function (form) {

        form.addEventListener(
            "submit",
            function () {

                sessionStorage.setItem(
                    cartScrollKey,
                    String(window.scrollY)
                );

            }
        );

    });


    window.addEventListener(
        "load",
        function () {

            const savedPosition =
                sessionStorage.getItem(
                    cartScrollKey
                );


            if (savedPosition !== null) {

                const position =
                    parseInt(
                        savedPosition,
                        10
                    );


                sessionStorage.removeItem(
                    cartScrollKey
                );


                requestAnimationFrame(function () {

                    window.scrollTo(
                        0,
                        position
                    );


                    setTimeout(function () {

                        window.scrollTo(
                            0,
                            position
                        );

                    }, 100);

                });

            }

        }
    );


    /* =========================================
       CUSTOM CART CONFIRMATION
    ========================================== */

    const cartConfirmOverlay =
        document.getElementById(
            "cartConfirmOverlay"
        );

    const cartConfirmTitle =
        document.getElementById(
            "cartConfirmTitle"
        );

    const cartConfirmText =
        document.getElementById(
            "cartConfirmText"
        );

    const cartConfirmActionButton =
        document.getElementById(
            "cartConfirmActionButton"
        );


    let selectedCartForm = null;


    /* =========================================
       OPEN REMOVE MODAL
    ========================================== */

    function openRemoveModal(button) {

        selectedCartForm =
            button.closest(
                ".remove-cart-form"
            );


        cartConfirmTitle.textContent =
            "Remove Product?";


        cartConfirmText.textContent =
            "Are you sure you want to remove this product from your cart?";


        cartConfirmActionButton.textContent =
            "Remove Product";


        cartConfirmOverlay.classList.add(
            "active"
        );


        document.body.style.overflow =
            "hidden";


        setTimeout(function () {

            cartConfirmActionButton.focus();

        }, 50);

    }


    /* =========================================
       OPEN CLEAR CART MODAL
    ========================================== */

    function openClearModal(button) {

        selectedCartForm =
            button.closest(
                ".clear-cart-form"
            );


        cartConfirmTitle.textContent =
            "Clear Entire Cart?";


        cartConfirmText.textContent =
            "Are you sure you want to remove all products from your cart?";


        cartConfirmActionButton.textContent =
            "Clear Cart";


        cartConfirmOverlay.classList.add(
            "active"
        );


        document.body.style.overflow =
            "hidden";


        setTimeout(function () {

            cartConfirmActionButton.focus();

        }, 50);

    }


    /* =========================================
       CLOSE MODAL
    ========================================== */

    function closeCartConfirm() {

        cartConfirmOverlay.classList.remove(
            "active"
        );


        document.body.style.overflow =
            "";


        selectedCartForm = null;

    }


    /* =========================================
       CONFIRM ACTION
    ========================================== */

    function confirmCartAction() {

        if (selectedCartForm) {

            sessionStorage.setItem(
                cartScrollKey,
                String(window.scrollY)
            );


            selectedCartForm.submit();

        }

    }


    /* =========================================
       CLOSE WHEN CLICKING OUTSIDE
    ========================================== */

    cartConfirmOverlay.addEventListener(
        "click",
        function (event) {

            if (
                event.target ===
                cartConfirmOverlay
            ) {

                closeCartConfirm();

            }

        }
    );


    /* =========================================
       ESCAPE KEY
    ========================================== */

    document.addEventListener(
        "keydown",
        function (event) {

            if (
                event.key === "Escape" &&
                cartConfirmOverlay.classList.contains(
                    "active"
                )
            ) {

                closeCartConfirm();

            }

        }
    );

</script>


</body>

</html>