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
   GET CUSTOMER INFORMATION
========================================= */

$user = null;

$user_stmt = $conn->prepare("
    SELECT
        first_name,
        last_name,
        email
    FROM users
    WHERE id = ?
    LIMIT 1
");

$user_stmt->bind_param(
    "i",
    $user_id
);

$user_stmt->execute();

$user_result = $user_stmt->get_result();

$user = $user_result->fetch_assoc();

$user_stmt->close();


if (!$user) {

    session_destroy();

    header("Location: login.php");
    exit;
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
    ORDER BY ci.created_at ASC
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
   REDIRECT IF CART IS EMPTY
========================================= */

if (empty($cart_items)) {

    $_SESSION["cart_error"] =
        "Your cart is empty.";

    header("Location: cart.php");
    exit;
}


/* =========================================
   DELIVERY FEE
========================================= */

$delivery_fee = 50.00;


/* =========================================
   CHECK STOCK
========================================= */

$stock_error = "";

foreach ($cart_items as $item) {

    $quantity = (int) $item["quantity"];

    $stock = (int) $item["stock"];


    if ((int) $item["is_active"] !== 1) {

        $stock_error =
            $item["name"] .
            " is no longer available.";

        break;
    }


    if ($stock <= 0) {

        $stock_error =
            $item["name"] .
            " is out of stock.";

        break;
    }


    if ($quantity > $stock) {

        $stock_error =
            $item["name"] .
            " only has " .
            $stock .
            " available, but your cart has " .
            $quantity .
            ".";

        break;
    }
}


/* =========================================
   CALCULATE TOTAL
========================================= */

$subtotal = 0;

$total_items = 0;


foreach ($cart_items as $item) {

    $quantity =
        (int) $item["quantity"];

    $price =
        (float) $item["price"];


    $subtotal +=
        $price * $quantity;


    $total_items +=
        $quantity;
}


$total =
    $subtotal + $delivery_fee;


/* =========================================
   FORM VALUES
========================================= */

$first_name =
    $user["first_name"] ?? "";

$last_name =
    $user["last_name"] ?? "";

$email =
    $user["email"] ?? "";

$phone = "";

$address = "";

$barangay = "";

$city = "";

$province = "";

$postal_code = "";

$delivery_notes = "";

$payment_method = "";


/* =========================================
   PLACE ORDER
========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["place_order"])
) {


    /* =====================================
       GET CHECKOUT INFORMATION
    ====================================== */

    $first_name =
        trim($_POST["first_name"] ?? "");

    $last_name =
        trim($_POST["last_name"] ?? "");

    $email =
        trim($_POST["email"] ?? "");

    $phone =
        trim($_POST["phone"] ?? "");

    $address =
        trim($_POST["address"] ?? "");

    $barangay =
        trim($_POST["barangay"] ?? "");

    $city =
        trim($_POST["city"] ?? "");

    $province =
        trim($_POST["province"] ?? "");

    $postal_code =
        trim($_POST["postal_code"] ?? "");

    $delivery_notes =
        trim($_POST["delivery_notes"] ?? "");

    $payment_method =
        trim($_POST["payment_method"] ?? "");


    /* =====================================
       VALIDATE CUSTOMER INFORMATION
    ====================================== */

    if (
        $first_name === ""
        || $last_name === ""
        || $email === ""
        || $phone === ""
    ) {

        $stock_error =
            "Please complete your customer information.";

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $stock_error =
            "Please enter a valid email address.";

    } elseif (
        $address === ""
        || $barangay === ""
        || $city === ""
        || $province === ""
        || $postal_code === ""
    ) {

        $stock_error =
            "Please complete your delivery address.";

    } elseif (
        $payment_method === ""
    ) {

        $stock_error =
            "Please select a payment method.";

    } elseif (
        !in_array(
            $payment_method,
            [
                "Cash on Delivery",
                "GCash",
                "Bank Transfer"
            ],
            true
        )
    ) {

        $stock_error =
            "Please select a valid payment method.";
    }


    /* =====================================
       STOP IF FORM HAS ERRORS
    ====================================== */

    if ($stock_error === "") {


        /* =================================
           START TRANSACTION
        ================================== */

        $conn->begin_transaction();


        try {


            /* =============================
               RE-CHECK CART AND STOCK
            ============================== */

            $verify_stmt = $conn->prepare("
                SELECT
                    ci.id AS cart_item_id,
                    ci.product_id,
                    ci.quantity,
                    p.name,
                    p.price,
                    p.stock,
                    p.is_active
                FROM cart_items ci
                INNER JOIN products p
                    ON ci.product_id = p.id
                WHERE ci.user_id = ?
                FOR UPDATE
            ");


            $verify_stmt->bind_param(
                "i",
                $user_id
            );


            $verify_stmt->execute();


            $verify_result =
                $verify_stmt->get_result();


            $verified_items = [];


            while (
                $row =
                $verify_result->fetch_assoc()
            ) {

                $verified_items[] =
                    $row;
            }


            $verify_stmt->close();


            if (empty($verified_items)) {

                throw new Exception(
                    "Your cart is empty."
                );
            }


            /* =============================
               VERIFY PRODUCTS
            ============================== */

            $verified_subtotal = 0;


            foreach (
                $verified_items
                as $item
            ) {

                $quantity =
                    (int) $item["quantity"];

                $stock =
                    (int) $item["stock"];

                $price =
                    (float) $item["price"];


                if (
                    (int) $item["is_active"]
                    !== 1
                ) {

                    throw new Exception(
                        $item["name"] .
                        " is no longer available."
                    );
                }


                if ($stock <= 0) {

                    throw new Exception(
                        $item["name"] .
                        " is out of stock."
                    );
                }


                if ($quantity > $stock) {

                    throw new Exception(
                        $item["name"] .
                        " only has " .
                        $stock .
                        " available."
                    );
                }


                $verified_subtotal +=
                    $price * $quantity;
            }


            /* =============================
               CALCULATE FINAL TOTAL
            ============================== */

            $verified_total =
                $verified_subtotal +
                $delivery_fee;


            /* =============================
               CREATE ORDER
            ============================== */

            $order_stmt = $conn->prepare("
                INSERT INTO orders
                (
                    user_id,
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
                    delivery_fee,
                    total_amount,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'Pending',
                    ?,
                    ?,
                    'Pending'
                )
            ");


            $order_stmt->bind_param(
                "isssssssssssdd",
                $user_id,
                $first_name,
                $last_name,
                $email,
                $phone,
                $address,
                $barangay,
                $city,
                $province,
                $postal_code,
                $delivery_notes,
                $payment_method,
                $delivery_fee,
                $verified_total
            );


            if (!$order_stmt->execute()) {

                throw new Exception(
                    "Unable to create your order."
                );
            }


            $order_id =
                $conn->insert_id;


            $order_stmt->close();


            /* =============================
               CREATE ORDER ITEMS
            ============================== */

            $item_stmt = $conn->prepare("
                INSERT INTO order_items
                (
                    order_id,
                    product_id,
                    quantity,
                    price
                )
                VALUES
                (?, ?, ?, ?)
            ");


            foreach (
                $verified_items
                as $item
            ) {

                $product_id =
                    (int) $item["product_id"];

                $quantity =
                    (int) $item["quantity"];

                $price =
                    (float) $item["price"];


                $item_stmt->bind_param(
                    "iiid",
                    $order_id,
                    $product_id,
                    $quantity,
                    $price
                );


                if (
                    !$item_stmt->execute()
                ) {

                    throw new Exception(
                        "Unable to save order items."
                    );
                }
            }


            $item_stmt->close();


            /* =============================
               REDUCE PRODUCT STOCK
            ============================== */

            $stock_update_stmt =
                $conn->prepare("
                    UPDATE products
                    SET stock = stock - ?
                    WHERE id = ?
                    AND stock >= ?
                ");


            foreach (
                $verified_items
                as $item
            ) {

                $product_id =
                    (int) $item["product_id"];

                $quantity =
                    (int) $item["quantity"];


                $stock_update_stmt->bind_param(
                    "iii",
                    $quantity,
                    $product_id,
                    $quantity
                );


                if (
                    !$stock_update_stmt->execute()
                ) {

                    throw new Exception(
                        "Unable to update product stock."
                    );
                }


                if (
                    $stock_update_stmt->affected_rows
                    !== 1
                ) {

                    throw new Exception(
                        "Product stock changed. Please try again."
                    );
                }
            }


            $stock_update_stmt->close();


            /* =============================
               CLEAR CUSTOMER CART
            ============================== */

            $clear_cart_stmt =
                $conn->prepare("
                    DELETE FROM cart_items
                    WHERE user_id = ?
                ");


            $clear_cart_stmt->bind_param(
                "i",
                $user_id
            );


            if (
                !$clear_cart_stmt->execute()
            ) {

                throw new Exception(
                    "Unable to clear your cart."
                );
            }


            $clear_cart_stmt->close();


            /* =============================
               ACTIVITY LOG
            ============================== */

            $log_stmt = $conn->prepare("
                INSERT INTO activity_logs
                (
                    user_id,
                    action,
                    description,
                    target_type,
                    target_id
                )
                VALUES
                (
                    ?,
                    'Order Created',
                    ?,
                    'Order',
                    ?
                )
            ");


            $log_description =
                "Customer created order #" .
                $order_id .
                " with a total of ₱" .
                number_format(
                    $verified_total,
                    2
                ) .
                " using " .
                $payment_method .
                ".";


            $log_stmt->bind_param(
                "isi",
                $user_id,
                $log_description,
                $order_id
            );


            $log_stmt->execute();

            $log_stmt->close();


            /* =============================
               COMMIT
            ============================== */

            $conn->commit();


            /* =============================
               STORE ORDER INFORMATION
            ============================== */

            $_SESSION["last_order_id"] =
                $order_id;

            $_SESSION["last_order_total"] =
                $verified_total;


            header(
                "Location: order-success.php"
            );

            exit;


        } catch (Exception $e) {


            /* =============================
               ROLLBACK
            ============================== */

            $conn->rollback();


            $stock_error =
                $e->getMessage();
        }
    }
}


/* =========================================
   HELPER
========================================= */

function checkout_image_path($image)
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
        Checkout | Elora Plants
    </title>


    <link
        rel="stylesheet"
        href="style.css?v=9"
    >


    <style>

        /* =========================================
           CHECKOUT PAGE
        ========================================= */

        .checkout-page {
            min-height: 100vh;
            background: #faf9f4;
        }


        /* =========================================
           NAVBAR
        ========================================= */

        .checkout-navbar {
            width: 100%;
            padding: 18px 6%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            background: #ffffff;
            border-bottom: 1px solid #e7e7df;
            box-sizing: border-box;
        }


        .checkout-logo img {
            width: 150px;
            height: auto;
            display: block;
            object-fit: contain;
        }


        .checkout-nav-links {
            display: flex;
            align-items: center;
            gap: 25px;
        }


        .checkout-nav-links a {
            color: #294a38;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: color 0.2s ease;
        }


        .checkout-nav-links a:hover {
            color: #6f9f79;
        }


        /* =========================================
           HERO
        ========================================= */

        .checkout-hero {
            padding: 55px 6% 45px;
            text-align: center;
            background: #edf3e9;
        }


        .checkout-hero h1 {
            margin: 0;
            color: #1d402c;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(40px, 6vw, 60px);
        }


        .checkout-hero p {
            max-width: 600px;
            margin: 15px auto 0;
            color: #607065;
            font-size: 15px;
            line-height: 1.7;
        }


        /* =========================================
           CONTAINER
        ========================================= */

        .checkout-container {
            width: min(1150px, 90%);
            margin: 0 auto;
            padding: 45px 0 80px;
        }


        /* =========================================
           ERROR
        ========================================= */

        .checkout-error {
            margin-bottom: 25px;
            padding: 16px 20px;
            border: 1px solid #edcbc7;
            border-radius: 12px;
            background: #f9e9e7;
            color: #8a3d35;
            font-size: 14px;
        }


        /* =========================================
           SUCCESS STYLE
        ========================================= */

        .checkout-info {
            margin-bottom: 25px;
            padding: 16px 20px;
            border: 1px solid #d7e5d5;
            border-radius: 12px;
            background: #f1f7ef;
            color: #385b40;
            font-size: 14px;
        }


        /* =========================================
           LAYOUT
        ========================================= */

        .checkout-layout {
            display: grid;
            grid-template-columns:
                minmax(0, 1fr)
                360px;
            gap: 30px;
            align-items: start;
        }


        /* =========================================
           LEFT COLUMN
        ========================================= */

        .checkout-left {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }


        /* =========================================
           CARDS
        ========================================= */

        .checkout-card {
            padding: 27px;
            background: #ffffff;
            border: 1px solid #e6ebe5;
            border-radius: 20px;
            box-shadow:
                0 8px 30px
                rgba(31, 58, 40, 0.06);
        }


        .checkout-card h2 {
            margin: 0 0 7px;
            color: #284b36;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 27px;
        }


        .checkout-card-description {
            margin: 0 0 23px;
            color: #748078;
            font-size: 13px;
            line-height: 1.6;
        }


        /* =========================================
           FORM
        ========================================= */

        .checkout-form {
            display: flex;
            flex-direction: column;
            gap: 17px;
        }


        .checkout-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }


        .checkout-form-row-three {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }


        .checkout-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }


        .checkout-field label {
            color: #36533f;
            font-size: 13px;
            font-weight: 700;
        }


        .checkout-field input,
        .checkout-field textarea,
        .checkout-field select {
            width: 100%;
            min-height: 46px;
            padding: 0 13px;
            border: 1px solid #d9dfd8;
            border-radius: 10px;
            background: #ffffff;
            color: #24352b;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            box-sizing: border-box;
            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }


        .checkout-field textarea {
            min-height: 95px;
            padding: 12px 13px;
            resize: vertical;
        }


        .checkout-field input:focus,
        .checkout-field textarea:focus,
        .checkout-field select:focus {
            border-color: #789a78;
            box-shadow:
                0 0 0 3px
                rgba(120, 154, 120, 0.12);
        }


        /* =========================================
           PAYMENT METHODS
        ========================================= */

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }


        .payment-option {
            position: relative;
        }


        .payment-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }


        .payment-option label {
            min-height: 110px;
            padding: 17px;
            border: 1px solid #dce3db;
            border-radius: 14px;
            background: #fafbf8;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 7px;
            text-align: center;
            cursor: pointer;
            box-sizing: border-box;
            transition:
                border-color 0.2s ease,
                background 0.2s ease,
                transform 0.2s ease;
        }


        .payment-option label:hover {
            border-color: #9ab49b;
            background: #f4f8f2;
            transform: translateY(-1px);
        }


        .payment-option input:checked + label {
            border: 2px solid #294f37;
            background: #edf5eb;
        }


        .payment-icon {
            font-size: 25px;
        }


        .payment-title {
            color: #294a38;
            font-size: 14px;
            font-weight: 700;
        }


        .payment-description {
            color: #7a837d;
            font-size: 11px;
            line-height: 1.4;
        }


        /* =========================================
           PAYMENT INFO
        ========================================= */

        .payment-info {
            margin-top: 15px;
            padding: 14px 16px;
            border-radius: 11px;
            background: #f3f6f1;
            color: #718078;
            font-size: 12px;
            line-height: 1.6;
        }


        /* =========================================
           PRODUCTS
        ========================================= */

        .checkout-products {
            padding: 27px;
            background: #ffffff;
            border: 1px solid #e6ebe5;
            border-radius: 20px;
            box-shadow:
                0 8px 30px
                rgba(31, 58, 40, 0.06);
        }


        .checkout-products h2 {
            margin: 0 0 25px;
            color: #284b36;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 27px;
        }


        .checkout-item {
            display: grid;
            grid-template-columns: 75px 1fr auto;
            gap: 15px;
            align-items: center;
            padding: 17px 0;
            border-bottom: 1px solid #edf0ec;
        }


        .checkout-item:last-child {
            border-bottom: none;
        }


        .checkout-item-image {
            width: 75px;
            height: 75px;
            overflow: hidden;
            border-radius: 13px;
            background: #edf2e9;
        }


        .checkout-item-image img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }


        .checkout-item-name {
            margin: 0 0 6px;
            color: #284b36;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 18px;
        }


        .checkout-item-details {
            color: #748078;
            font-size: 12px;
            line-height: 1.6;
        }


        .checkout-item-price {
            color: #1d402c;
            font-size: 15px;
            font-weight: bold;
            text-align: right;
        }


        /* =========================================
           SUMMARY
        ========================================= */

        .checkout-summary {
            position: sticky;
            top: 30px;
            padding: 27px;
            background: #ffffff;
            border: 1px solid #e6ebe5;
            border-radius: 20px;
            box-shadow:
                0 8px 30px
                rgba(31, 58, 40, 0.06);
        }


        .checkout-summary h2 {
            margin: 0 0 25px;
            color: #284b36;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 27px;
        }


        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 10px 0;
            color: #69766e;
            font-size: 14px;
        }


        .summary-total {
            margin-top: 12px;
            padding-top: 18px;
            border-top: 1px solid #e5eae4;
            color: #1d402c;
            font-size: 21px;
            font-weight: bold;
        }


        /* =========================================
           PLACE ORDER
        ========================================= */

        .place-order-button {
            width: 100%;
            margin-top: 23px;
            padding: 15px;
            border: none;
            border-radius: 12px;
            background: #294f37;
            color: #ffffff;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s ease;
        }


        .place-order-button:hover {
            background: #1d402c;
            transform: translateY(-1px);
        }


        .back-cart-button {
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
        }


        .back-cart-button:hover {
            background: #f2f5f0;
        }


        .checkout-note {
            margin-top: 18px;
            padding: 13px;
            border-radius: 10px;
            background: #f3f6f1;
            color: #718078;
            font-size: 11px;
            line-height: 1.6;
            text-align: center;
        }


        /* =========================================
           FOOTER
        ========================================= */

        .checkout-footer {
            padding: 30px 6%;
            background: #1d3828;
            color: #dce7dd;
            text-align: center;
            font-size: 13px;
        }


        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 950px) {

            .checkout-layout {
                grid-template-columns: 1fr;
            }


            .checkout-summary {
                position: static;
            }

        }


        @media (max-width: 750px) {

            .checkout-form-row-three {
                grid-template-columns: 1fr;
            }


            .payment-methods {
                grid-template-columns: 1fr;
            }

        }


        @media (max-width: 600px) {

            .checkout-navbar {
                padding: 15px 5%;
            }


            .checkout-logo img {
                width: 120px;
            }


            .checkout-nav-links {
                gap: 10px;
            }


            .checkout-nav-links a {
                font-size: 12px;
            }


            .checkout-container {
                width: 92%;
                padding-top: 30px;
            }


            .checkout-card,
            .checkout-products,
            .checkout-summary {
                padding: 20px;
            }


            .checkout-form-row {
                grid-template-columns: 1fr;
            }


            .checkout-item {
                grid-template-columns: 65px 1fr;
            }


            .checkout-item-image {
                width: 65px;
                height: 65px;
            }


            .checkout-item-price {
                grid-column: 2;
                text-align: left;
            }

        }


        @media (max-width: 450px) {

            .checkout-navbar {
                flex-direction: column;
                align-items: flex-start;
            }


            .checkout-nav-links {
                width: 100%;
                flex-wrap: wrap;
            }


            .checkout-hero {
                padding: 40px 5% 35px;
            }

        }

    </style>

</head>


<body>

<div class="checkout-page">


    <!-- =========================================
         NAVBAR
    ========================================== -->

    <nav class="checkout-navbar">


        <a
            href="index.php"
            class="checkout-logo"
        >

            <img
                src="image/logo.png"
                alt="Elora Plants"
            >

        </a>


        <div class="checkout-nav-links">

            <a href="index.php">
                Home
            </a>

            <a href="shop.php">
                Shop
            </a>

            <a href="cart.php">
                🛒 Cart
            </a>

            <a href="orders.php">
                My Orders
            </a>

            <a href="profile.php">
                Profile
            </a>

        </div>


    </nav>


    <!-- =========================================
         HERO
    ========================================== -->

    <section class="checkout-hero">

        <h1>
            Checkout
        </h1>

        <p>
            Complete your information, choose your
            payment method, and review your order
            before placing it.
        </p>

    </section>


    <!-- =========================================
         MAIN
    ========================================== -->

    <main class="checkout-container">


        <?php if ($stock_error !== ""): ?>

            <div class="checkout-error">

                <?php

                echo htmlspecialchars(
                    $stock_error,
                    ENT_QUOTES,
                    "UTF-8"
                );

                ?>

            </div>

        <?php endif; ?>


        <form
            action="checkout.php"
            method="POST"
            class="checkout-form"
            id="checkoutForm"
        >


            <div class="checkout-layout">


                <!-- =================================
                     LEFT COLUMN
                ================================== -->

                <div class="checkout-left">


                    <!-- =============================
                         CUSTOMER INFORMATION
                    ============================== -->

                    <section class="checkout-card">

                        <h2>
                            Customer Information
                        </h2>

                        <p class="checkout-card-description">
                            Please provide the information
                            we can use to contact you about
                            your order.
                        </p>


                        <div class="checkout-form">


                            <div class="checkout-form-row">


                                <div class="checkout-field">

                                    <label for="first_name">
                                        First Name
                                    </label>

                                    <input
                                        type="text"
                                        id="first_name"
                                        name="first_name"
                                        value="<?php

                                        echo htmlspecialchars(
                                            $first_name,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );

                                        ?>"
                                        maxlength="100"
                                        required
                                    >

                                </div>


                                <div class="checkout-field">

                                    <label for="last_name">
                                        Last Name
                                    </label>

                                    <input
                                        type="text"
                                        id="last_name"
                                        name="last_name"
                                        value="<?php

                                        echo htmlspecialchars(
                                            $last_name,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );

                                        ?>"
                                        maxlength="100"
                                        required
                                    >

                                </div>


                            </div>


                            <div class="checkout-form-row">


                                <div class="checkout-field">

                                    <label for="email">
                                        Email Address
                                    </label>

                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        value="<?php

                                        echo htmlspecialchars(
                                            $email,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );

                                        ?>"
                                        maxlength="255"
                                        required
                                    >

                                </div>


                                <div class="checkout-field">

                                    <label for="phone">
                                        Phone Number
                                    </label>

                                    <input
                                        type="tel"
                                        id="phone"
                                        name="phone"
                                        value="<?php

                                        echo htmlspecialchars(
                                            $phone,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );

                                        ?>"
                                        maxlength="30"
                                        placeholder="09XXXXXXXXX"
                                        required
                                    >

                                </div>


                            </div>


                        </div>

                    </section>


                    <!-- =============================
                         DELIVERY INFORMATION
                    ============================== -->

                    <section class="checkout-card">

                        <h2>
                            Delivery Information
                        </h2>

                        <p class="checkout-card-description">
                            Enter the complete address where
                            you want your plants delivered.
                        </p>


                        <div class="checkout-form">


                            <div class="checkout-field">

                                <label for="address">
                                    House / Unit / Street Address
                                </label>

                                <input
                                    type="text"
                                    id="address"
                                    name="address"
                                    value="<?php

                                    echo htmlspecialchars(
                                        $address,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );

                                    ?>"
                                    maxlength="500"
                                    placeholder="House number, street, subdivision, etc."
                                    required
                                >

                            </div>


                            <div class="checkout-form-row">


                                <div class="checkout-field">

                                    <label for="barangay">
                                        Barangay
                                    </label>

                                    <input
                                        type="text"
                                        id="barangay"
                                        name="barangay"
                                        value="<?php

                                        echo htmlspecialchars(
                                            $barangay,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );

                                        ?>"
                                        maxlength="100"
                                        required
                                    >

                                </div>


                                <div class="checkout-field">

                                    <label for="city">
                                        City / Municipality
                                    </label>

                                    <input
                                        type="text"
                                        id="city"
                                        name="city"
                                        value="<?php

                                        echo htmlspecialchars(
                                            $city,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );

                                        ?>"
                                        maxlength="100"
                                        required
                                    >

                                </div>


                            </div>


                            <div class="checkout-form-row-three">


                                <div class="checkout-field">

                                    <label for="province">
                                        Province
                                    </label>

                                    <input
                                        type="text"
                                        id="province"
                                        name="province"
                                        value="<?php

                                        echo htmlspecialchars(
                                            $province,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );

                                        ?>"
                                        maxlength="100"
                                        required
                                    >

                                </div>


                                <div class="checkout-field">

                                    <label for="postal_code">
                                        Postal Code
                                    </label>

                                    <input
                                        type="text"
                                        id="postal_code"
                                        name="postal_code"
                                        value="<?php

                                        echo htmlspecialchars(
                                            $postal_code,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );

                                        ?>"
                                        maxlength="20"
                                        required
                                    >

                                </div>


                                <div class="checkout-field">

                                    <label for="delivery_notes">
                                        Delivery Notes
                                    </label>

                                    <input
                                        type="text"
                                        id="delivery_notes"
                                        name="delivery_notes"
                                        value="<?php

                                        echo htmlspecialchars(
                                            $delivery_notes,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );

                                        ?>"
                                        maxlength="500"
                                        placeholder="Optional"
                                    >

                                </div>


                            </div>


                        </div>

                    </section>


                    <!-- =============================
                         PAYMENT
                    ============================== -->

                    <section class="checkout-card">

                        <h2>
                            Payment Method
                        </h2>

                        <p class="checkout-card-description">
                            Select how you would like to pay
                            for your order.
                        </p>


                        <div class="payment-methods">


                            <!-- CASH ON DELIVERY -->

                            <div class="payment-option">

                                <input
                                    type="radio"
                                    id="payment_cod"
                                    name="payment_method"
                                    value="Cash on Delivery"
                                    <?php

                                    echo
                                    $payment_method ===
                                    "Cash on Delivery"
                                    ? "checked"
                                    : "";

                                    ?>
                                    required
                                >


                                <label
                                    for="payment_cod"
                                >

                                    <span class="payment-icon">
                                        💵
                                    </span>

                                    <span class="payment-title">
                                        Cash on Delivery
                                    </span>

                                    <span class="payment-description">
                                        Pay when your order arrives.
                                    </span>

                                </label>

                            </div>


                            <!-- GCASH -->

                            <div class="payment-option">

                                <input
                                    type="radio"
                                    id="payment_gcash"
                                    name="payment_method"
                                    value="GCash"
                                    <?php

                                    echo
                                    $payment_method ===
                                    "GCash"
                                    ? "checked"
                                    : "";

                                    ?>
                                >


                                <label
                                    for="payment_gcash"
                                >

                                    <span class="payment-icon">
                                        📱
                                    </span>

                                    <span class="payment-title">
                                        GCash
                                    </span>

                                    <span class="payment-description">
                                        Pay using your GCash account.
                                    </span>

                                </label>

                            </div>


                            <!-- BANK TRANSFER -->

                            <div class="payment-option">

                                <input
                                    type="radio"
                                    id="payment_bank"
                                    name="payment_method"
                                    value="Bank Transfer"
                                    <?php

                                    echo
                                    $payment_method ===
                                    "Bank Transfer"
                                    ? "checked"
                                    : "";

                                    ?>
                                >


                                <label
                                    for="payment_bank"
                                >

                                    <span class="payment-icon">
                                        🏦
                                    </span>

                                    <span class="payment-title">
                                        Bank Transfer
                                    </span>

                                    <span class="payment-description">
                                        Pay through bank transfer.
                                    </span>

                                </label>

                            </div>


                        </div>


                        <div class="payment-info">

                            <strong>
                                Payment note:
                            </strong>

                            For this version of Elora,
                            GCash and Bank Transfer orders
                            will be recorded as
                            <strong>Pending</strong>.
                            Payment verification can be
                            handled by the administrator.

                        </div>


                    </section>


                    <!-- =============================
                         YOUR ORDER
                    ============================== -->

                    <section class="checkout-products">

                        <h2>
                            Your Order
                        </h2>


                        <?php foreach (
                            $cart_items
                            as $item
                        ): ?>


                            <?php

                            $quantity =
                                (int) $item["quantity"];

                            $price =
                                (float) $item["price"];

                            $item_total =
                                $price * $quantity;

                            ?>


                            <article
                                class="checkout-item"
                            >


                                <div
                                    class="checkout-item-image"
                                >

                                    <img
                                        src="<?php

                                        echo checkout_image_path(
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
                                        onerror="
                                            this.src='image/collection1.png';
                                        "
                                    >

                                </div>


                                <div>

                                    <h3
                                        class="checkout-item-name"
                                    >

                                        <?php

                                        echo htmlspecialchars(
                                            $item["name"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        );

                                        ?>

                                    </h3>


                                    <div
                                        class="checkout-item-details"
                                    >

                                        Quantity:
                                        <?php
                                        echo $quantity;
                                        ?>

                                        <br>

                                        ₱<?php

                                        echo number_format(
                                            $price,
                                            2
                                        );

                                        ?>
                                        each

                                    </div>

                                </div>


                                <div
                                    class="checkout-item-price"
                                >

                                    ₱<?php

                                    echo number_format(
                                        $item_total,
                                        2
                                    );

                                    ?>

                                </div>


                            </article>


                        <?php endforeach; ?>


                    </section>


                </div>


                <!-- =================================
                     ORDER SUMMARY
                ================================== -->

                <aside class="checkout-summary">


                    <h2>
                        Order Summary
                    </h2>


                    <div class="summary-row">

                        <span>
                            Items
                        </span>

                        <span>
                            <?php
                            echo $total_items;
                            ?>
                        </span>

                    </div>


                    <div class="summary-row">

                        <span>
                            Subtotal
                        </span>

                        <span>
                            ₱<?php

                            echo number_format(
                                $subtotal,
                                2
                            );

                            ?>
                        </span>

                    </div>


                    <div class="summary-row">

                        <span>
                            Delivery Fee
                        </span>

                        <span>
                            ₱<?php

                            echo number_format(
                                $delivery_fee,
                                2
                            );

                            ?>
                        </span>

                    </div>


                    <div
                        class="summary-row summary-total"
                    >

                        <span>
                            Total
                        </span>

                        <span>
                            ₱<?php

                            echo number_format(
                                $total,
                                2
                            );

                            ?>
                        </span>

                    </div>


                    <!-- PLACE ORDER -->

                    <?php if ($stock_error === ""): ?>

                        <button
                            type="submit"
                            name="place_order"
                            class="place-order-button"
                        >
                            Place Order
                        </button>

                    <?php else: ?>

                        <button
                            type="button"
                            class="place-order-button"
                            disabled
                            style="
                                background:#b8beb9;
                                cursor:not-allowed;
                            "
                        >
                            Cannot Place Order
                        </button>

                    <?php endif; ?>


                    <a
                        href="cart.php"
                        class="back-cart-button"
                    >
                        Back to Cart
                    </a>


                    <div class="checkout-note">

                        Your order will be created with
                        <strong>Pending</strong> status.
                        Your selected payment method and
                        delivery information will be saved
                        with the order.

                    </div>


                </aside>


            </div>


        </form>


    </main>


    <!-- =========================================
         FOOTER
    ========================================== -->

    <footer class="checkout-footer">

        © <?php echo date("Y"); ?>
        Elora Plants.
        Grow something beautiful.

    </footer>


</div>

</body>

</html>