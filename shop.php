<?php

session_start();

require_once "db.php";


/* =========================================
   LOGIN STATUS
========================================= */

$isLoggedIn = isset($_SESSION["user_id"]);

$user_id = $isLoggedIn
    ? (int) $_SESSION["user_id"]
    : 0;


/* =========================================
   ADMIN STATUS
========================================= */

$isAdmin = (
    $isLoggedIn
    && isset($_SESSION["is_admin"])
    && (int) $_SESSION["is_admin"] === 1
);


/* =========================================
   ADD PRODUCT TO CART
========================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_to_cart"])) {

    /*
     * Admins do not use the customer cart.
     */

    if ($isAdmin) {

        $_SESSION["shop_error"] =
            "Administrators cannot add products to a customer cart.";

        header("Location: shop.php");

        exit;
    }


    /*
     * Guests must log in before adding products.
     */

    if (!$isLoggedIn) {

        header("Location: login.php");

        exit;
    }


    $product_id = isset($_POST["product_id"])
        ? (int) $_POST["product_id"]
        : 0;

    $quantity = isset($_POST["quantity"])
        ? (int) $_POST["quantity"]
        : 1;


    if ($quantity < 1) {

        $quantity = 1;
    }


    /* =========================================
       GET PRODUCT AND STOCK
    ========================================== */

    $product_stmt = $conn->prepare("
        SELECT id, name, stock, is_active
        FROM products
        WHERE id = ?
        LIMIT 1
    ");

    $product_stmt->bind_param(
        "i",
        $product_id
    );

    $product_stmt->execute();

    $product_result =
        $product_stmt->get_result();

    $product =
        $product_result->fetch_assoc();

    $product_stmt->close();


    if (!$product) {

        $_SESSION["shop_error"] =
            "Product not found.";

    } elseif ((int) $product["is_active"] !== 1) {

        $_SESSION["shop_error"] =
            "This product is no longer available.";

    } elseif ((int) $product["stock"] <= 0) {

        $_SESSION["shop_error"] =
            "This product is currently out of stock.";

    } else {

        $available_stock =
            (int) $product["stock"];


        /* =========================================
           CHECK IF PRODUCT IS ALREADY IN CART
        ========================================== */

        $cart_stmt = $conn->prepare("
            SELECT id, quantity
            FROM cart_items
            WHERE user_id = ?
            AND product_id = ?
            LIMIT 1
        ");

        $cart_stmt->bind_param(
            "ii",
            $user_id,
            $product_id
        );

        $cart_stmt->execute();

        $cart_result =
            $cart_stmt->get_result();

        $existing_cart =
            $cart_result->fetch_assoc();

        $cart_stmt->close();


        if ($existing_cart) {

            $new_quantity =
                (int) $existing_cart["quantity"]
                + $quantity;


            if ($new_quantity > $available_stock) {

                $new_quantity =
                    $available_stock;
            }


            $update_cart = $conn->prepare("
                UPDATE cart_items
                SET quantity = ?
                WHERE id = ?
                AND user_id = ?
            ");

            $update_cart->bind_param(
                "iii",
                $new_quantity,
                $existing_cart["id"],
                $user_id
            );

            $update_cart->execute();

            $update_cart->close();


            $_SESSION["shop_success"] =
                $product["name"]
                . " quantity updated in your cart.";

        } else {

            if ($quantity > $available_stock) {

                $quantity =
                    $available_stock;
            }


            $insert_cart = $conn->prepare("
                INSERT INTO cart_items
                (user_id, product_id, quantity)
                VALUES (?, ?, ?)
            ");

            $insert_cart->bind_param(
                "iii",
                $user_id,
                $product_id,
                $quantity
            );

            $insert_cart->execute();

            $insert_cart->close();


            $_SESSION["shop_success"] =
                $product["name"]
                . " was added to your cart.";
        }
    }


    header("Location: shop.php");

    exit;
}


/* =========================================
   SEARCH
========================================= */

$search = isset($_GET["search"])
    ? trim($_GET["search"])
    : "";


/* =========================================
   CATEGORY
========================================= */

$category = isset($_GET["category"])
    ? trim($_GET["category"])
    : "";


/* =========================================
   GET CATEGORIES
========================================= */

$categories = [];

$category_result = $conn->query("
    SELECT DISTINCT category
    FROM products
    WHERE is_active = 1
    AND category IS NOT NULL
    AND category != ''
    ORDER BY category ASC
");

if ($category_result) {

    while ($row =
        $category_result->fetch_assoc()) {

        $categories[] =
            $row["category"];
    }
}


/* =========================================
   GET PRODUCTS
========================================= */

$products = [];

$sql = "
    SELECT
        id,
        name,
        description,
        price,
        stock,
        image,
        category
    FROM products
    WHERE is_active = 1
";

$params = [];

$types = "";


/* =========================================
   SEARCH FILTER
========================================= */

if ($search !== "") {

    $sql .= "
        AND (
            name LIKE ?
            OR description LIKE ?
            OR category LIKE ?
        )
    ";

    $search_value =
        "%" . $search . "%";

    $params[] =
        $search_value;

    $params[] =
        $search_value;

    $params[] =
        $search_value;

    $types .= "sss";
}


/* =========================================
   CATEGORY FILTER
========================================= */

if ($category !== "") {

    $sql .= "
        AND category = ?
    ";

    $params[] =
        $category;

    $types .= "s";
}


$sql .= "
    ORDER BY created_at DESC, id DESC
";


$product_query =
    $conn->prepare($sql);


if (!empty($params)) {

    $product_query->bind_param(
        $types,
        ...$params
    );
}


$product_query->execute();

$product_result =
    $product_query->get_result();


while ($row =
    $product_result->fetch_assoc()) {

    $products[] =
        $row;
}


$product_query->close();


/* =========================================
   GET CART COUNT
========================================= */

$cart_count = 0;


if ($isLoggedIn && !$isAdmin) {

    $cart_count_stmt = $conn->prepare("
        SELECT COALESCE(SUM(quantity), 0) AS total
        FROM cart_items
        WHERE user_id = ?
    ");

    $cart_count_stmt->bind_param(
        "i",
        $user_id
    );

    $cart_count_stmt->execute();

    $cart_count_result =
        $cart_count_stmt->get_result();

    $cart_count_row =
        $cart_count_result->fetch_assoc();

    $cart_count =
        (int) $cart_count_row["total"];

    $cart_count_stmt->close();
}


/* =========================================
   FLASH MESSAGES
========================================= */

$success_message = "";

$error_message = "";


if (isset($_SESSION["shop_success"])) {

    $success_message =
        $_SESSION["shop_success"];

    unset($_SESSION["shop_success"]);
}


if (isset($_SESSION["shop_error"])) {

    $error_message =
        $_SESSION["shop_error"];

    unset($_SESSION["shop_error"]);
}


/* =========================================
   HELPER
========================================= */

function shop_image_path($image)
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

    <title>
        <?php
        echo $isAdmin
            ? "View Shop | Elora Plants"
            : "Shop | Elora Plants";
        ?>
    </title>


    <link
        rel="stylesheet"
        href="style.css?v=7"
    >


    <style>

        /* =========================================
           SHOP PAGE
        ========================================= */

        .shop-page {
            min-height: 100vh;
            background: #faf9f4;
        }


        /* =========================================
           CUSTOMER NAVBAR
        ========================================= */

        .shop-navbar {
            width: 100%;
            padding: 16px 6%;
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
           REAL ELORA LOGO
        ========================================= */

        .shop-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }


        .shop-logo img {
            width: 150px;
            height: auto;
            display: block;
            object-fit: contain;
        }


        .shop-nav-links {
            display: flex;
            align-items: center;
            gap: 24px;
        }


        .shop-nav-links a {
            text-decoration: none;
            color: #294a38;
            font-size: 15px;
            font-weight: 600;
            transition: 0.2s ease;
        }


        .shop-nav-links a:hover {
            color: #6f9f79;
        }


        /* =========================================
           ADMIN NAVBAR
        ========================================== */

        .admin-shop-navbar {
            width: 100%;
            padding: 13px 5%;
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


        .admin-shop-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            flex-shrink: 0;
        }


        .admin-shop-logo {
            width: 145px;
            height: auto;
            display: block;
            object-fit: contain;
        }


        .admin-shop-plant {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: #edf3e9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            border: 1px solid #dbe7d8;
        }


        .admin-shop-brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }


        .admin-shop-brand-text span {
            color: #78937e;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.2px;
        }


        .admin-shop-nav {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }


        .admin-shop-nav a {
            text-decoration: none;
            color: #294a38;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s ease;
        }


        .admin-shop-nav a:hover {
            color: #6f9f79;
        }


        .admin-shop-nav .admin-shop-active {
            color: #1d402c;
            font-weight: 800;
        }


        .admin-shop-nav .admin-shop-logout {
            padding: 8px 13px;
            border-radius: 9px;
            background: #294f37;
            color: #ffffff;
        }


        .admin-shop-nav .admin-shop-logout:hover {
            background: #1d402c;
            color: #ffffff;
        }


        /* =========================================
           CART
        ========================================== */

        .shop-cart-link {
            position: relative;
            display: inline-flex;
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }


        /* =========================================
           GUEST LOGIN LINK
        ========================================== */

        .shop-login-link {
            padding: 9px 15px;
            border-radius: 9px;
            background: #294f37;
            color: #ffffff !important;
        }


        .shop-login-link:hover {
            background: #1d402c;
            color: #ffffff !important;
        }


        .shop-signup-link {
            padding: 9px 15px;
            border: 1px solid #294f37;
            border-radius: 9px;
        }


        /* =========================================
           ADMIN NOTICE
        ========================================== */

        .admin-shop-notice {
            margin-bottom: 28px;
            padding: 16px 20px;
            border-radius: 13px;
            background: #edf3e9;
            border: 1px solid #d8e5d4;
            color: #365440;
            font-size: 14px;
            line-height: 1.6;
        }


        .admin-shop-notice strong {
            color: #294f37;
        }


        /* =========================================
           GUEST NOTICE
        ========================================== */

        .guest-shop-notice {
            margin-bottom: 28px;
            padding: 16px 20px;
            border-radius: 13px;
            background: #edf3e9;
            border: 1px solid #d8e5d4;
            color: #365440;
            font-size: 14px;
            line-height: 1.6;
        }


        .guest-shop-notice a {
            color: #294f37;
            font-weight: 700;
            text-decoration: none;
        }


        .guest-shop-notice a:hover {
            text-decoration: underline;
        }


        /* =========================================
           SHOP HERO
        ========================================== */

        .shop-hero {
            padding: 70px 6% 55px;
            text-align: center;
            background: #edf3e9;
        }


        .shop-hero h1 {
            margin: 0;
            color: #1d402c;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(40px, 6vw, 68px);
            line-height: 1.1;
        }


        .shop-hero p {
            max-width: 650px;
            margin: 18px auto 0;
            color: #607065;
            font-size: 16px;
            line-height: 1.7;
        }


        /* =========================================
           SHOP CONTENT
        ========================================== */

        .shop-container {
            width: min(1250px, 88%);
            margin: 0 auto;
            padding: 45px 0 80px;
        }


        /* =========================================
           FILTER AREA
        ========================================== */

        .shop-filters {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 40px;
        }


        .shop-search-form {
            display: flex;
            flex: 1;
            min-width: 280px;
            max-width: 600px;
            gap: 10px;
        }


        .shop-search-input {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid #d9dfd7;
            border-radius: 12px;
            background: #ffffff;
            color: #263c2e;
            font-size: 14px;
            outline: none;
        }


        .shop-search-input:focus {
            border-color: #789a7f;
        }


        .shop-search-button {
            padding: 14px 22px;
            border: none;
            border-radius: 12px;
            background: #294f37;
            color: #ffffff;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s ease;
        }


        .shop-search-button:hover {
            background: #1d402c;
            transform: translateY(-1px);
        }


        .category-links {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
        }


        .category-link {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 50px;
            background: #ffffff;
            border: 1px solid #dfe5dc;
            color: #365440;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s ease;
        }


        .category-link:hover,
        .category-link.active {
            background: #294f37;
            border-color: #294f37;
            color: #ffffff;
        }


        /* =========================================
           FLASH MESSAGES
        ========================================== */

        .shop-message {
            padding: 15px 18px;
            margin-bottom: 25px;
            border-radius: 12px;
            font-size: 14px;
        }


        .shop-success {
            background: #e7f3e8;
            border: 1px solid #c9e3cc;
            color: #285b35;
        }


        .shop-error {
            background: #f9e9e7;
            border: 1px solid #edcbc7;
            color: #8a3d35;
        }


        /* =========================================
           PRODUCTS HEADER
        ========================================== */

        .products-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
            gap: 15px;
        }


        .products-heading h2 {
            margin: 0;
            color: #234430;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 30px;
        }


        .products-heading span {
            color: #718078;
            font-size: 14px;
        }


        /* =========================================
           PRODUCT GRID
        ========================================== */

        .shop-product-grid {
            display: grid;
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
            gap: 28px;
        }


        .shop-product-card {
            overflow: hidden;
            background: #ffffff;
            border: 1px solid #e8ebe5;
            border-radius: 20px;
            box-shadow:
                0 8px 30px
                rgba(31, 58, 40, 0.06);
            transition: 0.25s ease;
        }


        .shop-product-card:hover {
            transform: translateY(-5px);
            box-shadow:
                0 15px 35px
                rgba(31, 58, 40, 0.11);
        }


        .shop-product-image {
            width: 100%;
            height: 270px;
            background: #edf2e9;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }


        .shop-product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: 0.3s ease;
        }


        .shop-product-card:hover
        .shop-product-image img {
            transform: scale(1.04);
        }


        .shop-product-content {
            padding: 23px;
        }


        .shop-product-category {
            display: inline-block;
            margin-bottom: 10px;
            color: #78937e;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }


        .shop-product-name {
            margin: 0 0 9px;
            color: #244530;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 23px;
        }


        .shop-product-description {
            min-height: 48px;
            margin: 0 0 17px;
            color: #69766e;
            font-size: 14px;
            line-height: 1.6;
        }


        .shop-product-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-top: 10px;
        }


        .shop-product-price {
            color: #1d402c;
            font-size: 21px;
            font-weight: bold;
        }


        .shop-stock {
            margin-top: 8px;
            color: #7a857e;
            font-size: 12px;
        }


        .shop-stock.out {
            color: #b14d45;
            font-weight: 600;
        }


        /* =========================================
           ADD TO CART
        ========================================== */

        .add-cart-form {
            display: flex;
            align-items: center;
            gap: 8px;
        }


        .quantity-input {
            width: 58px;
            padding: 11px 7px;
            border: 1px solid #d7dfd6;
            border-radius: 10px;
            text-align: center;
            font-size: 14px;
            outline: none;
        }


        .quantity-input:focus {
            border-color: #71947a;
        }


        .add-cart-button {
            padding: 12px 15px;
            border: none;
            border-radius: 10px;
            background: #294f37;
            color: #ffffff;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s ease;
        }


        .add-cart-button:hover {
            background: #1d402c;
            transform: translateY(-1px);
        }


        .add-cart-button:disabled {
            background: #b8beb9;
            cursor: not-allowed;
            transform: none;
        }


        /* =========================================
           ADMIN PRODUCT LABEL
        ========================================== */

        .admin-product-label {
            display: inline-flex;
            align-items: center;
            padding: 11px 14px;
            border-radius: 10px;
            background: #edf3e9;
            border: 1px solid #d8e5d4;
            color: #365440;
            font-size: 12px;
            font-weight: 700;
        }


        /* =========================================
           LOGIN TO CART BUTTON
        ========================================== */

        .login-to-cart-button {
            display: inline-block;
            padding: 12px 15px;
            border-radius: 10px;
            background: #294f37;
            color: #ffffff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s ease;
        }


        .login-to-cart-button:hover {
            background: #1d402c;
            transform: translateY(-1px);
        }


        /* =========================================
           EMPTY STATE
        ========================================== */

        .shop-empty {
            padding: 70px 25px;
            text-align: center;
            background: #ffffff;
            border: 1px solid #e6ebe5;
            border-radius: 20px;
        }


        .shop-empty h3 {
            margin: 0 0 10px;
            color: #284b36;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 28px;
        }


        .shop-empty p {
            margin: 0 0 22px;
            color: #718078;
        }


        .clear-search {
            display: inline-block;
            padding: 12px 20px;
            border-radius: 10px;
            background: #294f37;
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }


        /* =========================================
           FOOTER
        ========================================== */

        .shop-footer {
            padding: 30px 6%;
            text-align: center;
            background: #1d3828;
            color: #dce7dd;
            font-size: 13px;
        }


        /* =========================================
           RESPONSIVE
        ========================================== */

        @media (max-width: 1100px) {

            .admin-shop-nav {
                gap: 12px;
            }


            .admin-shop-nav a {
                font-size: 12px;
            }

        }


        @media (max-width: 950px) {

            .shop-product-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }


            .admin-shop-navbar {
                align-items: flex-start;
                flex-direction: column;
            }


            .admin-shop-nav {
                width: 100%;
                justify-content: flex-start;
            }

        }


        @media (max-width: 700px) {

            .shop-navbar {
                padding: 14px 5%;
            }


            .shop-logo img {
                width: 120px;
            }


            .shop-nav-links {
                gap: 12px;
            }


            .shop-nav-links a {
                font-size: 13px;
            }


            .admin-shop-navbar {
                padding: 14px 5%;
            }


            .admin-shop-logo {
                width: 120px;
            }


            .admin-shop-nav {
                gap: 10px;
            }


            .admin-shop-nav a {
                font-size: 12px;
            }


            .shop-hero {
                padding: 55px 5% 45px;
            }


            .shop-container {
                width: 90%;
                padding-top: 30px;
            }


            .shop-product-grid {
                grid-template-columns: 1fr;
            }


            .shop-search-form {
                min-width: 100%;
            }


            .shop-filters {
                align-items: stretch;
            }


            .category-links {
                width: 100%;
            }

        }


        @media (max-width: 450px) {

            .shop-logo img {
                width: 100px;
            }


            .shop-nav-links a {
                font-size: 12px;
            }


            .shop-nav-links {
                gap: 7px;
            }


            .shop-nav-links
            a:not(.shop-cart-link) {
                display: none;
            }


            .admin-shop-logo {
                width: 100px;
            }


            .admin-shop-brand {
                gap: 8px;
            }


            .admin-shop-plant {
                width: 30px;
                height: 30px;
                font-size: 17px;
            }


            .admin-shop-nav {
                gap: 8px;
            }


            .admin-shop-nav a {
                font-size: 11px;
            }


            .shop-search-form {
                flex-direction: column;
            }


            .shop-search-button {
                width: 100%;
            }


            .shop-product-bottom {
                align-items: flex-start;
                flex-direction: column;
            }


            .add-cart-form {
                width: 100%;
            }


            .quantity-input {
                flex: 0 0 65px;
            }


            .add-cart-button,
            .login-to-cart-button {
                flex: 1;
            }

        }

    </style>

</head>


<body>

<div class="shop-page">


    <!-- =========================================
         ADMIN NAVBAR
    ========================================== -->

    <?php if ($isAdmin): ?>


        <nav class="admin-shop-navbar">


            <a
                href="admin/index.php"
                class="admin-shop-brand"
            >

                <img
                    src="image/logo.png"
                    alt="Elora Plants"
                    class="admin-shop-logo"
                >


                <div class="admin-shop-plant">
                    🌱
                </div>


                <div class="admin-shop-brand-text">

                    <span>
                        ADMIN PANEL
                    </span>

                </div>

            </a>


            <div class="admin-shop-nav">


                <a href="admin/index.php">
                    Dashboard
                </a>


                <a href="admin/products.php">
                    Products
                </a>


                <a href="admin/orders.php">
                    Orders
                </a>


                <a href="admin/users.php">
                    Users
                </a>


                <a href="admin/activity.php">
                    Activity Logs
                </a>


                <a
                    href="shop.php"
                    class="admin-shop-active"
                >
                    View Shop
                </a>


                <a
                    href="logout.php"
                    class="admin-shop-logout"
                >
                    Log Out
                </a>


            </div>


        </nav>


    <?php else: ?>


        <!-- =========================================
             CUSTOMER / GUEST NAVBAR
        ========================================== -->

        <nav class="shop-navbar">


            <a
                href="index.php"
                class="shop-logo"
            >

                <img
                    src="image/logo.png"
                    alt="Elora Plants"
                >

            </a>


            <div class="shop-nav-links">


                <a href="index.php">
                    Home
                </a>


                <a href="shop.php">
                    Shop
                </a>


                <?php if ($isLoggedIn): ?>


                    <a
                        href="cart.php"
                        class="shop-cart-link"
                    >

                        🛒 Cart

                        <span class="cart-badge">

                            <?php
                            echo $cart_count;
                            ?>

                        </span>

                    </a>


                    <a href="profile.php">
                        Profile
                    </a>


                    <a href="orders.php">
                        Orders
                    </a>


                    <a href="logout.php">
                        Log Out
                    </a>


                <?php else: ?>


                    <a
                        href="login.php"
                        class="shop-login-link"
                    >
                        Log In
                    </a>


                    <a
                        href="signup.php"
                        class="shop-signup-link"
                    >
                        Sign Up
                    </a>


                <?php endif; ?>


            </div>

        </nav>


    <?php endif; ?>


    <!-- =========================================
         HERO
    ========================================== -->

    <section class="shop-hero">

        <h1>

            <?php if ($isAdmin): ?>

                Shop Preview

            <?php else: ?>

                Shop Our Plants

            <?php endif; ?>

        </h1>


        <p>

            <?php if ($isAdmin): ?>

                Preview the customer-facing plant
                collection from the administrator
                panel.

            <?php else: ?>

                Bring a little more green
                into your space.

                Explore our collection of
                carefully selected plants
                for your home, desk, and
                everyday spaces.

            <?php endif; ?>

        </p>

    </section>


    <!-- =========================================
         SHOP CONTENT
    ========================================== -->

    <main class="shop-container">


        <!-- =====================================
             ADMIN NOTICE
        ====================================== -->

        <?php if ($isAdmin): ?>

            <div class="admin-shop-notice">

                <strong>
                    Administrator View:
                </strong>

                You are viewing the shop as an
                administrator. Customer cart
                functions are disabled on this
                page.

            </div>

        <?php endif; ?>


        <!-- =====================================
             GUEST NOTICE
        ====================================== -->

        <?php if (!$isLoggedIn): ?>

            <div class="guest-shop-notice">

                You're welcome to browse our
                collection, search for plants,
                and explore our categories.

                <a href="login.php">
                    Log in
                </a>

                to add plants to your cart.

            </div>

        <?php endif; ?>


        <!-- =====================================
             SUCCESS MESSAGE
        ====================================== -->

        <?php if ($success_message !== ""): ?>

            <div class="shop-message shop-success">

                <?php

                echo htmlspecialchars(
                    $success_message,
                    ENT_QUOTES,
                    "UTF-8"
                );

                ?>

            </div>

        <?php endif; ?>


        <!-- =====================================
             ERROR MESSAGE
        ====================================== -->

        <?php if ($error_message !== ""): ?>

            <div class="shop-message shop-error">

                <?php

                echo htmlspecialchars(
                    $error_message,
                    ENT_QUOTES,
                    "UTF-8"
                );

                ?>

            </div>

        <?php endif; ?>


        <!-- =====================================
             FILTERS
        ====================================== -->

        <div class="shop-filters">


            <form
                action="shop.php"
                method="GET"
                class="shop-search-form"
            >


                <input
                    type="text"
                    name="search"
                    class="shop-search-input"
                    placeholder="Search plants..."
                    value="<?php

                    echo htmlspecialchars(
                        $search,
                        ENT_QUOTES,
                        "UTF-8"
                    );

                    ?>"
                >


                <?php if ($category !== ""): ?>

                    <input
                        type="hidden"
                        name="category"
                        value="<?php

                        echo htmlspecialchars(
                            $category,
                            ENT_QUOTES,
                            "UTF-8"
                        );

                        ?>"
                    >

                <?php endif; ?>


                <button
                    type="submit"
                    class="shop-search-button"
                >
                    Search
                </button>


            </form>


            <!-- CATEGORY LINKS -->

            <div class="category-links">


                <a
                    href="shop.php"
                    class="category-link <?php

                    echo $category === ""
                        ? "active"
                        : "";

                    ?>"
                >
                    All Plants
                </a>


                <?php foreach ($categories as $cat): ?>


                    <a
                        href="shop.php?category=<?php

                        echo urlencode($cat);

                        ?>"
                        class="category-link <?php

                        echo $category === $cat
                            ? "active"
                            : "";

                        ?>"
                    >

                        <?php

                        echo htmlspecialchars(
                            $cat,
                            ENT_QUOTES,
                            "UTF-8"
                        );

                        ?>

                    </a>


                <?php endforeach; ?>


            </div>


        </div>


        <!-- =====================================
             PRODUCTS HEADER
        ====================================== -->

        <div class="products-heading">


            <h2>
                Our Collection
            </h2>


            <span>

                <?php
                echo count($products);
                ?>

                product<?php

                echo count($products) === 1
                    ? ""
                    : "s";

                ?>

            </span>


        </div>


        <!-- =====================================
             PRODUCT GRID
        ====================================== -->

        <?php if (!empty($products)): ?>


            <div class="shop-product-grid">


                <?php foreach ($products as $product): ?>


                    <?php

                    $product_id =
                        (int) $product["id"];

                    $stock =
                        (int) $product["stock"];

                    ?>


                    <article
                        class="shop-product-card"
                    >


                        <!-- PRODUCT IMAGE -->

                        <div
                            class="shop-product-image"
                        >

                            <img
                                src="<?php

                                echo shop_image_path(
                                    $product["image"]
                                );

                                ?>"
                                alt="<?php

                                echo htmlspecialchars(
                                    $product["name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                );

                                ?>"
                                onerror="
                                    this.src=
                                    'image/collection1.png';
                                "
                            >

                        </div>


                        <!-- PRODUCT CONTENT -->

                        <div
                            class="shop-product-content"
                        >


                            <?php if (
                                !empty(
                                    $product["category"]
                                )
                            ): ?>


                                <span
                                    class="shop-product-category"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $product["category"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );

                                    ?>

                                </span>


                            <?php endif; ?>


                            <h3
                                class="shop-product-name"
                            >

                                <?php

                                echo htmlspecialchars(
                                    $product["name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                );

                                ?>

                            </h3>


                            <p
                                class="
                                    shop-product-description
                                "
                            >

                                <?php

                                echo htmlspecialchars(
                                    $product["description"] ?? "",
                                    ENT_QUOTES,
                                    "UTF-8"
                                );

                                ?>

                            </p>


                            <div
                                class="shop-product-bottom"
                            >


                                <div>


                                    <div
                                        class="
                                            shop-product-price
                                        "
                                    >

                                        ₱<?php

                                        echo number_format(
                                            (float)
                                            $product["price"],
                                            2
                                        );

                                        ?>

                                    </div>


                                    <?php if ($stock > 0): ?>


                                        <div
                                            class="shop-stock"
                                        >

                                            <?php
                                            echo $stock;
                                            ?>

                                            available

                                        </div>


                                    <?php else: ?>


                                        <div
                                            class="
                                                shop-stock out
                                            "
                                        >

                                            Out of stock

                                        </div>


                                    <?php endif; ?>


                                </div>


                                <!-- =================================
                                     ADMIN VIEW
                                ================================== -->

                                <?php if ($isAdmin): ?>


                                    <div
                                        class="admin-product-label"
                                    >

                                        🌱 Admin Preview

                                    </div>


                                <?php elseif ($isLoggedIn): ?>


                                    <!-- =================================
                                         CUSTOMER LOGGED-IN
                                    ================================== -->

                                    <form
                                        action="shop.php"
                                        method="POST"
                                        class="add-cart-form"
                                    >


                                        <input
                                            type="hidden"
                                            name="product_id"
                                            value="<?php

                                            echo $product_id;

                                            ?>"
                                        >


                                        <input
                                            type="number"
                                            name="quantity"
                                            class="
                                                quantity-input
                                            "
                                            value="1"
                                            min="1"
                                            max="<?php

                                            echo max(
                                                1,
                                                $stock
                                            );

                                            ?>"
                                            <?php

                                            echo $stock <= 0
                                                ? "disabled"
                                                : "";

                                            ?>
                                        >


                                        <button
                                            type="submit"
                                            name="add_to_cart"
                                            class="
                                                add-cart-button
                                            "
                                            <?php

                                            echo $stock <= 0
                                                ? "disabled"
                                                : "";

                                            ?>
                                        >

                                            <?php

                                            echo $stock > 0
                                                ? "Add to Cart"
                                                : "Sold Out";

                                            ?>

                                        </button>


                                    </form>


                                <?php else: ?>


                                    <!-- =================================
                                         GUEST USER
                                    ================================== -->

                                    <?php if ($stock > 0): ?>


                                        <a
                                            href="login.php"
                                            class="
                                                login-to-cart-button
                                            "
                                        >
                                            Log in to add
                                        </a>


                                    <?php else: ?>


                                        <a
                                            href="#"
                                            class="
                                                login-to-cart-button
                                            "
                                            style="
                                                background:#b8beb9;
                                                pointer-events:none;
                                            "
                                        >
                                            Sold Out
                                        </a>


                                    <?php endif; ?>


                                <?php endif; ?>


                            </div>


                        </div>


                    </article>


                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <!-- =================================
                 NO PRODUCTS
            ================================== -->

            <div class="shop-empty">


                <h3>
                    No plants found
                </h3>


                <p>

                    We couldn't find any products
                    matching your search.

                </p>


                <a
                    href="shop.php"
                    class="clear-search"
                >
                    View All Plants
                </a>


            </div>


        <?php endif; ?>


    </main>


    <!-- =========================================
         FOOTER
    ========================================== -->

    <footer class="shop-footer">

        © <?php

        echo date("Y");

        ?>

        Elora Plants.

        Grow something beautiful.

    </footer>


</div>


<script>

    /* =========================================
       AUTO HIDE FLASH MESSAGES
    ========================================== */

    setTimeout(function () {

        const messages =
            document.querySelectorAll(
                ".shop-message"
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
       QUANTITY VALIDATION
    ========================================== */

    const quantityInputs =
        document.querySelectorAll(
            ".quantity-input"
        );


    quantityInputs.forEach(function (input) {

        input.addEventListener(
            "change",
            function () {

                let value =
                    parseInt(this.value);


                const max =
                    parseInt(
                        this.getAttribute("max")
                    );


                if (
                    isNaN(value)
                    || value < 1
                ) {

                    value = 1;
                }


                if (
                    !isNaN(max)
                    && value > max
                ) {

                    value = max;
                }


                this.value = value;

            }
        );

    });


    /* =========================================
       KEEP SHOP SCROLL POSITION
    ========================================== */

    const shopScrollKey =
        "shopScrollPosition";


    const addCartForms =
        document.querySelectorAll(
            ".add-cart-form"
        );


    addCartForms.forEach(function (form) {

        form.addEventListener(
            "submit",
            function () {

                sessionStorage.setItem(
                    shopScrollKey,
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
                    shopScrollKey
                );


            if (savedPosition !== null) {

                const position =
                    parseInt(
                        savedPosition,
                        10
                    );


                sessionStorage.removeItem(
                    shopScrollKey
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

</script>


</body>

</html>