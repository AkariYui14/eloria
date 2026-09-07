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
   GET CURRENT USER
========================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        first_name,
        last_name,
        email
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

$stmt->close();


if (!$user) {

    session_destroy();

    header("Location: login.php");
    exit;
}


/* =========================================
   VARIABLES
========================================= */

$success_message = "";
$error_message = "";


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
   UPDATE PROFILE
========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["update_profile"])
) {

    $first_name = trim($_POST["first_name"] ?? "");
    $last_name = trim($_POST["last_name"] ?? "");
    $email = trim($_POST["email"] ?? "");


    /* -----------------------------------------
       VALIDATION
    ----------------------------------------- */

    if (
        $first_name === ""
        || $last_name === ""
        || $email === ""
    ) {

        $error_message =
            "Please fill in all profile fields.";

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error_message =
            "Please enter a valid email address.";

    } else {


        /* -----------------------------------------
           CHECK EMAIL
        ----------------------------------------- */

        $email_stmt = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            AND id != ?
            LIMIT 1
        ");

        $email_stmt->bind_param(
            "si",
            $email,
            $user_id
        );

        $email_stmt->execute();

        $email_result =
            $email_stmt->get_result();

        $email_exists =
            $email_result->num_rows > 0;

        $email_stmt->close();


        if ($email_exists) {

            $error_message =
                "That email address is already being used.";

        } else {


            /* -----------------------------------------
               UPDATE USER
            ----------------------------------------- */

            $update_stmt = $conn->prepare("
                UPDATE users
                SET
                    first_name = ?,
                    last_name = ?,
                    email = ?
                WHERE id = ?
            ");

            $update_stmt->bind_param(
                "sssi",
                $first_name,
                $last_name,
                $email,
                $user_id
            );


            if ($update_stmt->execute()) {

                $update_stmt->close();


                /* -----------------------------------------
                   UPDATE SESSION
                ----------------------------------------- */

                $_SESSION["first_name"] = $first_name;
                $_SESSION["last_name"] = $last_name;


                /* -----------------------------------------
                   ACTIVITY LOG
                ----------------------------------------- */

                $action = "Profile Updated";

                $description =
                    "Customer updated their profile information.";

                $target_type = "user";

                $target_id = $user_id;


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


                /* -----------------------------------------
                   UPDATE DISPLAYED USER
                ----------------------------------------- */

                $user["first_name"] =
                    $first_name;

                $user["last_name"] =
                    $last_name;

                $user["email"] =
                    $email;


                $success_message =
                    "Your profile has been updated successfully.";

            } else {

                $update_stmt->close();

                $error_message =
                    "Something went wrong while updating your profile.";
            }
        }
    }
}


/* =========================================
   CHANGE PASSWORD
========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["change_password"])
) {

    $current_password =
        $_POST["current_password"] ?? "";

    $new_password =
        $_POST["new_password"] ?? "";

    $confirm_password =
        $_POST["confirm_password"] ?? "";


    /* -----------------------------------------
       VALIDATION
    ----------------------------------------- */

    if (
        $current_password === ""
        || $new_password === ""
        || $confirm_password === ""
    ) {

        $error_message =
            "Please fill in all password fields.";

    } elseif (
        $new_password !== $confirm_password
    ) {

        $error_message =
            "The new passwords do not match.";

    } elseif (
        strlen($new_password) < 8
    ) {

        $error_message =
            "Your new password must be at least 8 characters long.";

    } elseif (
        $current_password === $new_password
    ) {

        $error_message =
            "Your new password must be different from your current password.";

    } else {


        /* -----------------------------------------
           GET CURRENT PASSWORD
        ----------------------------------------- */

        $password_stmt = $conn->prepare("
            SELECT password
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $password_stmt->bind_param(
            "i",
            $user_id
        );

        $password_stmt->execute();

        $password_result =
            $password_stmt->get_result();

        $password_user =
            $password_result->fetch_assoc();

        $password_stmt->close();


        /* -----------------------------------------
           VERIFY CURRENT PASSWORD
        ----------------------------------------- */

        if (
            !$password_user
            || !password_verify(
                $current_password,
                $password_user["password"]
            )
        ) {

            $error_message =
                "Your current password is incorrect.";

        } else {


            /* -----------------------------------------
               HASH NEW PASSWORD
            ----------------------------------------- */

            $hashed_password =
                password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );


            /* -----------------------------------------
               UPDATE PASSWORD
            ----------------------------------------- */

            $update_password_stmt = $conn->prepare("
                UPDATE users
                SET password = ?
                WHERE id = ?
            ");

            $update_password_stmt->bind_param(
                "si",
                $hashed_password,
                $user_id
            );


            if (
                $update_password_stmt->execute()
            ) {

                $update_password_stmt->close();


                /* -----------------------------------------
                   ACTIVITY LOG
                ----------------------------------------- */

                $action =
                    "Password Changed";

                $description =
                    "Customer changed their account password.";

                $target_type =
                    "user";

                $target_id =
                    $user_id;


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


                $success_message =
                    "Your password has been changed successfully.";

            } else {

                $update_password_stmt->close();

                $error_message =
                    "Something went wrong while changing your password.";
            }
        }
    }
}


/* =========================================
   GET ORDER COUNT
========================================= */

$order_count = 0;

$order_stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM orders
    WHERE user_id = ?
");

if ($order_stmt) {

    $order_stmt->bind_param(
        "i",
        $user_id
    );

    $order_stmt->execute();

    $order_stmt->bind_result(
        $order_count
    );

    $order_stmt->fetch();

    $order_stmt->close();
}

$order_count = (int) $order_count;


/* =========================================
   GET CUSTOMER TOTAL SPENT
========================================= */

$total_spent = 0;

$spent_stmt = $conn->prepare("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM orders
    WHERE user_id = ?
    AND status != 'Cancelled'
");

if ($spent_stmt) {

    $spent_stmt->bind_param(
        "i",
        $user_id
    );

    $spent_stmt->execute();

    $spent_stmt->bind_result(
        $total_spent
    );

    $spent_stmt->fetch();

    $spent_stmt->close();
}

$total_spent = (float) $total_spent;

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Profile | Elora Plants</title>

    <link
        rel="stylesheet"
        href="style.css?v=9"
    >


    <style>

        /* =========================================
           PROFILE PAGE
        ========================================= */

        .profile-page {
            min-height: 100vh;
            background: #f8f6ef;
            padding: 50px 20px 80px;
        }


        .profile-container {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
        }


        .profile-header {
            margin-bottom: 30px;
        }


        .profile-header h1 {
            margin: 0 0 8px;
            color: #173f2a;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 42px;
        }


        .profile-header p {
            margin: 0;
            color: #6d756f;
            font-size: 16px;
        }


        /* =========================================
           MESSAGES
        ========================================= */

        .profile-message {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
        }


        .profile-success {
            background: #e4f3e8;
            border: 1px solid #c5e2cd;
            color: #27633d;
        }


        .profile-error {
            background: #fbe5e5;
            border: 1px solid #efcaca;
            color: #9b3c3c;
        }


        /* =========================================
           PROFILE GRID
        ========================================= */

        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }


        .profile-card {
            background: #ffffff;
            border: 1px solid #e4e8e1;
            border-radius: 20px;
            padding: 28px;
            box-shadow:
                0 8px 25px rgba(25, 55, 38, 0.06);
        }


        .profile-card-full {
            grid-column: 1 / -1;
        }


        .profile-card h2 {
            margin: 0 0 7px;
            color: #173f2a;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 25px;
        }


        .profile-card-description {
            margin: 0 0 24px;
            color: #777f79;
            font-size: 14px;
            line-height: 1.6;
        }


        /* =========================================
           PROFILE AVATAR
        ========================================= */

        .profile-summary {
            display: flex;
            align-items: center;
            gap: 20px;
        }


        .profile-avatar {
            width: 78px;
            height: 78px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #dfeee1;
            color: #214f36;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 29px;
            font-weight: 700;
        }


        .profile-summary-name {
            margin: 0 0 5px;
            color: #173f2a;
            font-size: 23px;
            font-weight: 700;
        }


        .profile-summary-email {
            color: #777f79;
            font-size: 14px;
        }


        /* =========================================
           STATISTICS
        ========================================= */

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }


        .profile-stat {
            padding: 20px;
            border-radius: 14px;
            background: #f3f7f1;
        }


        .profile-stat-label {
            display: block;
            margin-bottom: 8px;
            color: #788179;
            font-size: 13px;
        }


        .profile-stat-value {
            color: #173f2a;
            font-size: 25px;
            font-weight: 700;
        }


        /* =========================================
           FORM
        ========================================= */

        .profile-form {
            display: flex;
            flex-direction: column;
            gap: 17px;
        }


        .profile-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }


        .profile-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }


        .profile-field label {
            color: #36533f;
            font-size: 13px;
            font-weight: 700;
        }


        /* =========================================
           PASSWORD FIELD
        ========================================= */

        .password-wrapper {
            position: relative;
            width: 100%;
        }


        .password-wrapper input {
            padding-right: 48px;
        }


        .show-password-button {
            position: absolute;

            top: 50%;
            right: 10px;

            transform: translateY(-50%);

            width: 34px;
            height: 34px;

            border: 0;
            border-radius: 50%;

            background: transparent;

            color: #617267;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 17px;

            cursor: pointer;

            transition:
                background 0.2s ease,
                color 0.2s ease;
        }


        .show-password-button:hover {
            background: #eef4ed;
            color: #214f36;
        }


        .profile-field input {
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
            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }


        .profile-field input:focus {
            border-color: #789a78;
            box-shadow:
                0 0 0 3px rgba(120, 154, 120, 0.12);
        }


        .profile-submit {
            align-self: flex-start;

            border: 0;

            padding: 12px 22px;

            border-radius: 10px;

            background: #214f36;

            color: #ffffff;

            font-family: inherit;

            font-size: 14px;

            font-weight: 700;

            cursor: pointer;

            transition: 0.25s ease;
        }


        .profile-submit:hover {
            background: #173f2a;
            transform: translateY(-1px);
        }


        /* =========================================
           QUICK LINKS
        ========================================= */

        .profile-links {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }


        .profile-link-card {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 7px;
            padding: 18px;
            border: 1px solid #e3e8e1;
            border-radius: 14px;
            background: #f8faf6;
            color: #173f2a;
            text-decoration: none;
            transition: 0.25s ease;
        }


        .profile-link-card:hover {
            background: #eef5eb;
            transform: translateY(-2px);
        }


        .profile-link-icon {
            font-size: 23px;
        }


        .profile-link-title {
            font-size: 14px;
            font-weight: 700;
        }


        .profile-link-description {
            color: #788179;
            font-size: 12px;
            line-height: 1.5;
        }


        /* =========================================
           PROFILE NAVIGATION
        ========================================= */

        .profile-navbar-logo {
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }


        .profile-navbar-logo img {
            width: 105px;
            height: auto;
            display: block;
            transition:
                transform 0.3s ease,
                filter 0.3s ease;
        }


        .profile-navbar-logo:hover img {
            transform: scale(1.05);
        }


        .profile-nav-menu {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 38px;
        }


        .profile-nav-menu a {
            position: relative;
            color: var(--dark-green);
            font-size: 14px;
            font-weight: 600;
            transition:
                color 0.25s ease,
                transform 0.25s ease;
        }


        .profile-nav-menu a::after {
            content: "";

            position: absolute;

            left: 0;

            bottom: -7px;

            width: 0;

            height: 2px;

            background: var(--dark-green);

            transition: width 0.25s ease;
        }


        .profile-nav-menu a:hover {
            color: #5f8657;
            transform: translateY(-2px);
        }


        .profile-nav-menu a:hover::after {
            width: 100%;
        }


        .profile-nav-menu a.active {
            color: var(--dark-green);
        }


        .profile-nav-menu a.active::after {
            width: 100%;
        }


        /* =========================================
           CART
        ========================================= */

        .profile-cart-link {
            position: relative;

            display: inline-flex !important;

            align-items: center;

            gap: 7px;
        }


        .profile-cart-badge {
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
           MOBILE MENU
        ========================================= */

        .profile-mobile-button {
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


        .profile-mobile-button:hover {
            background: var(--light-green);

            transform: scale(1.05);
        }


        /* =========================================
           TABLET
        ========================================= */

        @media (max-width: 900px) {

            .profile-nav-menu {
                gap: 20px;
            }


            .profile-grid {
                grid-template-columns: 1fr;
            }


            .profile-card-full {
                grid-column: auto;
            }

        }


        /* =========================================
           MOBILE
        ========================================= */

        @media (max-width: 650px) {

            .profile-navbar-container {
                min-height: 70px;
                gap: 8px;
            }


            .profile-navbar-logo img {
                width: 90px;
            }


            .profile-nav-menu {
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


            .profile-nav-menu.active {
                display: flex;

                animation: searchOpen 0.25s ease both;
            }


            .profile-nav-menu a {
                padding: 14px 0;
            }


            .profile-mobile-button {
                display: flex;

                align-items: center;

                justify-content: center;
            }


            .profile-page {
                padding: 35px 15px 60px;
            }


            .profile-header h1 {
                font-size: 34px;
            }


            .profile-card {
                padding: 21px;
            }


            .profile-form-row {
                grid-template-columns: 1fr;
            }


            .profile-links {
                grid-template-columns: 1fr;
            }


            .profile-submit {
                width: 100%;
            }

        }


        /* =========================================
           SMALL PHONE
        ========================================= */

        @media (max-width: 400px) {

            .profile-mobile-button {
                width: 36px;
                height: 36px;
            }

        }

    </style>

</head>


<body>


    <!-- =========================================
         NAVIGATION
    ========================================= -->

    <nav class="navbar">

        <div class="nav-container profile-navbar-container">


            <!-- LOGO -->

            <a
                href="index.php"
                class="profile-navbar-logo"
            >

                <img
                    src="image/logo.png"
                    alt="Elora Plants"
                >

            </a>


            <!-- NAVIGATION LINKS -->

            <div
                class="profile-nav-menu"
                id="profileNavMenu"
            >

                <a href="index.php">
                    Home
                </a>


                <a href="shop.php">
                    Shop
                </a>


                <a
                    href="cart.php"
                    class="profile-cart-link"
                >

                    🛒 Cart

                    <span class="profile-cart-badge">
                        <?php echo $cart_count; ?>
                    </span>

                </a>


                <a href="orders.php">
                    My Orders
                </a>


                <a
                    href="profile.php"
                    class="active"
                >
                    Profile
                </a>


                <a href="logout.php">
                    Log Out
                </a>

            </div>


            <!-- MOBILE MENU BUTTON -->

            <button
                type="button"
                class="profile-mobile-button"
                id="profileMobileButton"
                aria-label="Open navigation menu"
                aria-expanded="false"
            >
                ☰
            </button>

        </div>

    </nav>


    <!-- =========================================
         PROFILE CONTENT
    ========================================= -->

    <main class="profile-page">

        <div class="profile-container">


            <!-- PAGE HEADER -->

            <div class="profile-header">

                <h1>
                    My Profile
                </h1>

                <p>
                    Manage your account information and security settings.
                </p>

            </div>


            <!-- =========================================
                 MESSAGES
            ========================================= -->

            <?php if ($success_message !== ""): ?>

                <div class="profile-message profile-success">

                    <?php
                    echo htmlspecialchars(
                        $success_message
                    );
                    ?>

                </div>

            <?php endif; ?>


            <?php if ($error_message !== ""): ?>

                <div class="profile-message profile-error">

                    <?php
                    echo htmlspecialchars(
                        $error_message
                    );
                    ?>

                </div>

            <?php endif; ?>


            <!-- =========================================
                 PROFILE GRID
            ========================================= -->

            <div class="profile-grid">


                <!-- =====================================
                     PROFILE SUMMARY
                ====================================== -->

                <section class="profile-card">

                    <div class="profile-summary">


                        <div class="profile-avatar">

                            <?php

                            echo strtoupper(
                                substr(
                                    $user["first_name"],
                                    0,
                                    1
                                )
                            );

                            echo strtoupper(
                                substr(
                                    $user["last_name"],
                                    0,
                                    1
                                )
                            );

                            ?>

                        </div>


                        <div>

                            <h2 class="profile-summary-name">

                                <?php

                                echo htmlspecialchars(
                                    $user["first_name"]
                                    . " "
                                    . $user["last_name"]
                                );

                                ?>

                            </h2>


                            <div class="profile-summary-email">

                                <?php

                                echo htmlspecialchars(
                                    $user["email"]
                                );

                                ?>

                            </div>

                        </div>

                    </div>

                </section>


                <!-- =====================================
                     ACCOUNT OVERVIEW
                ====================================== -->

                <section class="profile-card">

                    <h2>
                        Account Overview
                    </h2>

                    <p class="profile-card-description">
                        A quick look at your Elora account activity.
                    </p>


                    <div class="profile-stats">


                        <div class="profile-stat">

                            <span class="profile-stat-label">
                                Orders
                            </span>

                            <span class="profile-stat-value">

                                <?php
                                echo $order_count;
                                ?>

                            </span>

                        </div>


                        <div class="profile-stat">

                            <span class="profile-stat-label">
                                Total Spent
                            </span>

                            <span class="profile-stat-value">

                                ₱<?php

                                echo number_format(
                                    $total_spent,
                                    2
                                );

                                ?>

                            </span>

                        </div>


                    </div>

                </section>


                <!-- =====================================
                     EDIT PROFILE
                ====================================== -->

                <section class="profile-card">

                    <h2>
                        Personal Information
                    </h2>

                    <p class="profile-card-description">
                        Update your name and email address.
                    </p>


                    <form
                        method="POST"
                        class="profile-form"
                    >


                        <div class="profile-form-row">


                            <div class="profile-field">

                                <label for="first_name">
                                    First Name
                                </label>

                                <input
                                    type="text"
                                    id="first_name"
                                    name="first_name"
                                    value="<?php

                                    echo htmlspecialchars(
                                        $user["first_name"]
                                    );

                                    ?>"
                                    maxlength="100"
                                    required
                                >

                            </div>


                            <div class="profile-field">

                                <label for="last_name">
                                    Last Name
                                </label>

                                <input
                                    type="text"
                                    id="last_name"
                                    name="last_name"
                                    value="<?php

                                    echo htmlspecialchars(
                                        $user["last_name"]
                                    );

                                    ?>"
                                    maxlength="100"
                                    required
                                >

                            </div>


                        </div>


                        <div class="profile-field">

                            <label for="email">
                                Email Address
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?php

                                echo htmlspecialchars(
                                    $user["email"]
                                );

                                ?>"
                                maxlength="255"
                                required
                            >

                        </div>


                        <button
                            type="submit"
                            name="update_profile"
                            class="profile-submit"
                        >
                            Save Changes
                        </button>


                    </form>

                </section>


                <!-- =====================================
                     CHANGE PASSWORD
                ====================================== -->

                <section class="profile-card">

                    <h2>
                        Change Password
                    </h2>

                    <p class="profile-card-description">
                        Keep your account secure by using a strong password.
                    </p>


                    <form
                        method="POST"
                        class="profile-form"
                    >


                        <!-- CURRENT PASSWORD -->

                        <div class="profile-field">

                            <label for="current_password">
                                Current Password
                            </label>


                            <div class="password-wrapper">

                                <input
                                    type="password"
                                    id="current_password"
                                    name="current_password"
                                    autocomplete="current-password"
                                    required
                                >


                                <button
                                    type="button"
                                    class="show-password-button"
                                    data-target="current_password"
                                    aria-label="Show current password"
                                    aria-pressed="false"
                                >
                                    👁
                                </button>

                            </div>

                        </div>


                        <!-- NEW PASSWORD -->

                        <div class="profile-field">

                            <label for="new_password">
                                New Password
                            </label>


                            <div class="password-wrapper">

                                <input
                                    type="password"
                                    id="new_password"
                                    name="new_password"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required
                                >


                                <button
                                    type="button"
                                    class="show-password-button"
                                    data-target="new_password"
                                    aria-label="Show new password"
                                    aria-pressed="false"
                                >
                                    👁
                                </button>

                            </div>

                        </div>


                        <!-- CONFIRM PASSWORD -->

                        <div class="profile-field">

                            <label for="confirm_password">
                                Confirm New Password
                            </label>


                            <div class="password-wrapper">

                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required
                                >


                                <button
                                    type="button"
                                    class="show-password-button"
                                    data-target="confirm_password"
                                    aria-label="Show confirm password"
                                    aria-pressed="false"
                                >
                                    👁
                                </button>

                            </div>

                        </div>


                        <button
                            type="submit"
                            name="change_password"
                            class="profile-submit"
                        >
                            Change Password
                        </button>


                    </form>

                </section>


                <!-- =====================================
                     QUICK LINKS
                ====================================== -->

                <section class="profile-card profile-card-full">

                    <h2>
                        Quick Links
                    </h2>

                    <p class="profile-card-description">
                        Quickly access the parts of Elora you use most.
                    </p>


                    <div class="profile-links">


                        <a
                            href="shop.php"
                            class="profile-link-card"
                        >

                            <span class="profile-link-icon">
                                🌿
                            </span>

                            <span class="profile-link-title">
                                Browse Plants
                            </span>

                            <span class="profile-link-description">
                                Explore our available plants and collection.
                            </span>

                        </a>


                        <a
                            href="cart.php"
                            class="profile-link-card"
                        >

                            <span class="profile-link-icon">
                                🛒
                            </span>

                            <span class="profile-link-title">
                                My Cart
                            </span>

                            <span class="profile-link-description">
                                View the plants currently in your cart.
                            </span>

                        </a>


                        <a
                            href="orders.php"
                            class="profile-link-card"
                        >

                            <span class="profile-link-icon">
                                📦
                            </span>

                            <span class="profile-link-title">
                                My Orders
                            </span>

                            <span class="profile-link-description">
                                View your previous orders and their status.
                            </span>

                        </a>


                    </div>

                </section>


            </div>

        </div>

    </main>


    <!-- =========================================
         JAVASCRIPT
    ========================================= -->

    <script>

        /* =========================================
           MOBILE NAVIGATION
        ========================================= */

        const profileMobileButton =
            document.getElementById(
                "profileMobileButton"
            );

        const profileNavMenu =
            document.getElementById(
                "profileNavMenu"
            );


        if (
            profileMobileButton
            && profileNavMenu
        ) {

            profileMobileButton.addEventListener(
                "click",
                function () {

                    const isOpen =
                        profileNavMenu.classList.toggle(
                            "active"
                        );


                    profileMobileButton.setAttribute(
                        "aria-expanded",
                        isOpen ? "true" : "false"
                    );


                    profileMobileButton.innerHTML =
                        isOpen ? "✕" : "☰";

                }
            );


            profileNavMenu
                .querySelectorAll("a")
                .forEach(function (link) {

                    link.addEventListener(
                        "click",
                        function () {

                            profileNavMenu
                                .classList
                                .remove("active");


                            profileMobileButton
                                .setAttribute(
                                    "aria-expanded",
                                    "false"
                                );


                            profileMobileButton.innerHTML =
                                "☰";

                        }
                    );

                });

        }


        /* =========================================
           SHOW / HIDE PASSWORD
        ========================================= */

        const showPasswordButtons =
            document.querySelectorAll(
                ".show-password-button"
            );


        showPasswordButtons.forEach(
            function (button) {

                button.addEventListener(
                    "click",
                    function () {

                        const targetId =
                            button.getAttribute(
                                "data-target"
                            );

                        const passwordInput =
                            document.getElementById(
                                targetId
                            );


                        if (!passwordInput) {
                            return;
                        }


                        if (
                            passwordInput.type ===
                            "password"
                        ) {

                            passwordInput.type =
                                "text";

                            button.innerHTML =
                                "🙈";

                            button.setAttribute(
                                "aria-label",
                                "Hide password"
                            );

                            button.setAttribute(
                                "aria-pressed",
                                "true"
                            );

                        } else {

                            passwordInput.type =
                                "password";

                            button.innerHTML =
                                "👁";

                            button.setAttribute(
                                "aria-label",
                                "Show password"
                            );

                            button.setAttribute(
                                "aria-pressed",
                                "false"
                            );

                        }

                    }
                );

            }
        );

    </script>


</body>

</html>