<?php

session_start();

require_once "db.php";


/* =========================================
   PREVENT BROWSER CACHING
========================================= */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");


/* =========================================
   VARIABLES
========================================= */

$errors = [];

$first_name = "";
$last_name = "";
$email = "";
$confirm_email = "";


/* =========================================
   HANDLE SIGN UP
========================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $first_name = trim($_POST["first_name"] ?? "");
    $last_name = trim($_POST["last_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $confirm_email = trim($_POST["confirm_email"] ?? "");

    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";


    /* =========================================
       FIRST NAME
    ========================================= */

    if ($first_name === "") {

        $errors[] = "First name is required.";

    } elseif (!preg_match("/^[a-zA-ZÀ-ÿ\s'-]+$/", $first_name)) {

        $errors[] =
            "First name can only contain letters, spaces, apostrophes, and hyphens.";

    } elseif (strlen($first_name) < 2) {

        $errors[] =
            "First name must contain at least 2 characters.";

    } elseif (strlen($first_name) > 100) {

        $errors[] =
            "First name is too long.";
    }


    /* =========================================
       LAST NAME
    ========================================= */

    if ($last_name === "") {

        $errors[] = "Last name is required.";

    } elseif (!preg_match("/^[a-zA-ZÀ-ÿ\s'-]+$/", $last_name)) {

        $errors[] =
            "Last name can only contain letters, spaces, apostrophes, and hyphens.";

    } elseif (strlen($last_name) < 2) {

        $errors[] =
            "Last name must contain at least 2 characters.";

    } elseif (strlen($last_name) > 100) {

        $errors[] =
            "Last name is too long.";
    }


    /* =========================================
       EMAIL
    ========================================= */

    if ($email === "") {

        $errors[] =
            "Email address is required.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $errors[] =
            "Please enter a valid email address.";

    } elseif (strlen($email) > 255) {

        $errors[] =
            "Email address is too long.";
    }


    /* =========================================
       CONFIRM EMAIL
    ========================================= */

    if ($confirm_email === "") {

        $errors[] =
            "Please confirm your email address.";

    } elseif (
        strcasecmp($email, $confirm_email) !== 0
    ) {

        $errors[] =
            "Email addresses do not match.";
    }


    /* =========================================
       PASSWORD
    ========================================= */

    if ($password === "") {

        $errors[] =
            "Password is required.";

    } elseif (strlen($password) < 8) {

        $errors[] =
            "Password must contain at least 8 characters.";

    } elseif (strlen($password) > 255) {

        $errors[] =
            "Password is too long.";

    } elseif (
        !preg_match("/[A-Za-z]/", $password)
        ||
        !preg_match("/[0-9]/", $password)
    ) {

        $errors[] =
            "Password must contain at least one letter and one number.";
    }


    /* =========================================
       CONFIRM PASSWORD
    ========================================= */

    if ($confirm_password === "") {

        $errors[] =
            "Please confirm your password.";

    } elseif ($password !== $confirm_password) {

        $errors[] =
            "Passwords do not match.";
    }


    /* =========================================
       CHECK DUPLICATE EMAIL
    ========================================= */

    if (empty($errors)) {

        $check = $conn->prepare(
            "SELECT id
             FROM users
             WHERE email = ?
             LIMIT 1"
        );


        if ($check) {

            $check->bind_param(
                "s",
                $email
            );

            $check->execute();

            $check->store_result();


            if ($check->num_rows > 0) {

                $errors[] =
                    "An account with this email already exists.";
            }


            $check->close();

        } else {

            $errors[] =
                "Unable to check your account information. Please try again.";
        }
    }


    /* =========================================
       CREATE ACCOUNT
    ========================================= */

    if (empty($errors)) {

        $hashed_password = password_hash(
            $password,
            PASSWORD_DEFAULT
        );


        if ($hashed_password === false) {

            $errors[] =
                "Unable to securely create your account.";

        } else {

            $stmt = $conn->prepare(
                "INSERT INTO users
                (
                    first_name,
                    last_name,
                    email,
                    password,
                    is_admin
                )
                VALUES (?, ?, ?, ?, 0)"
            );


            if (!$stmt) {

                $errors[] =
                    "Something went wrong while creating your account. Please try again.";

            } else {

                $stmt->bind_param(
                    "ssss",
                    $first_name,
                    $last_name,
                    $email,
                    $hashed_password
                );


                if ($stmt->execute()) {

                    $user_id =
                        $stmt->insert_id;


                    /* =================================
                       ACTIVITY LOG
                    ================================== */

                    $action =
                        "CREATE_ACCOUNT";

                    $description =
                        "User created a new Elora Plants account.";

                    $target_type =
                        "User";

                    $target_id =
                        $user_id;


                    $activity = $conn->prepare(
                        "INSERT INTO activity_logs
                        (
                            user_id,
                            action,
                            description,
                            target_type,
                            target_id
                        )
                        VALUES (?, ?, ?, ?, ?)"
                    );


                    if ($activity) {

                        $activity->bind_param(
                            "isssi",
                            $user_id,
                            $action,
                            $description,
                            $target_type,
                            $target_id
                        );


                        $activity->execute();

                        $activity->close();
                    }


                    $stmt->close();


                    /* =================================
                       REDIRECT
                    ================================== */

                    header(
                        "Location: login.php?registered=1"
                    );

                    exit;

                } else {

                    if ($conn->errno === 1062) {

                        $errors[] =
                            "An account with this email already exists.";

                    } else {

                        $errors[] =
                            "Something went wrong while creating your account. Please try again.";
                    }


                    $stmt->close();
                }
            }
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
        Create Account | Elora Plants
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

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 30px;

            background: #fafbf8;

            color: #173c27;

            font-family:
                Arial,
                Helvetica,
                sans-serif;
        }


        /* =========================================
           WRAPPER
        ========================================= */

        .signup-wrapper {

            width: 100%;

            max-width: 500px;
        }


        /* =========================================
           BRAND
        ========================================= */

        .brand {

            text-align: center;

            margin-bottom: 24px;
        }


        .brand a {

            display: inline-block;

            text-decoration: none;

            transition:
                transform 0.25s ease;
        }


        .brand a:hover {

            transform:
                translateY(-2px);
        }


        .brand img {

            display: block;

            width: 190px;

            max-width: 100%;

            height: auto;

            margin: 0 auto;

            object-fit: contain;
        }


        .brand p {

            margin-top: 8px;

            color: #607064;

            font-size: 13px;
        }


        /* =========================================
           CARD
        ========================================= */

        .signup-card {

            padding: 36px;

            background: #ffffff;

            border:
                1px solid #dce5d9;

            border-radius: 24px;

            box-shadow:
                0 18px 50px
                rgba(23, 60, 39, 0.08);
        }


        /* =========================================
           TITLE
        ========================================= */

        .signup-card h1 {

            margin-bottom: 8px;

            color: #173c27;

            font-family:
                Georgia,
                "Times New Roman",
                serif;

            font-size: 30px;
        }


        .subtitle {

            margin-bottom: 26px;

            color: #607064;

            font-size: 14px;

            line-height: 1.6;
        }


        /* =========================================
           ERROR BOX
        ========================================= */

        .error-box {

            margin-bottom: 22px;

            padding: 15px 17px;

            border:
                1px solid #efd2cc;

            border-radius: 12px;

            background: #fff3f1;

            color: #9b3d31;

            font-size: 13px;

            line-height: 1.6;
        }


        .error-box strong {

            display: block;

            margin-bottom: 6px;

            font-size: 13px;
        }


        .error-box ul {

            padding-left: 19px;
        }


        .error-box li {

            margin-bottom: 3px;
        }


        .error-box li:last-child {

            margin-bottom: 0;
        }


        /* =========================================
           FORM ROW
        ========================================= */

        .form-row {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 14px;
        }


        /* =========================================
           FORM GROUP
        ========================================= */

        .form-group {

            margin-bottom: 18px;
        }


        /* =========================================
           LABEL
        ========================================= */

        label {

            display: block;

            margin-bottom: 7px;

            color: #173c27;

            font-size: 13px;

            font-weight: 700;
        }


        /* =========================================
           INPUT
        ========================================= */

        input {

            width: 100%;

            padding: 13px 14px;

            border:
                1px solid #dce5d9;

            border-radius: 12px;

            outline: none;

            background: #fafbf8;

            color: #173c27;

            font-size: 14px;

            transition:
                border-color 0.25s ease,
                box-shadow 0.25s ease,
                background 0.25s ease;
        }


        input:focus {

            background: #ffffff;

            border-color: #245637;

            box-shadow:
                0 0 0 4px
                rgba(36, 86, 55, 0.08);
        }


        /* =========================================
           INVALID INPUT
        ========================================= */

        input.input-error {

            border-color: #c96b5d;

            background: #fff9f8;
        }


        input.input-error:focus {

            border-color: #b85446;

            box-shadow:
                0 0 0 4px
                rgba(185, 84, 70, 0.08);
        }


        /* =========================================
           PASSWORD WRAPPER
        ========================================= */

        .password-wrapper {

            position: relative;
        }


        .password-wrapper input {

            padding-right: 65px;
        }


        /* =========================================
           SHOW PASSWORD
        ========================================= */

        .show-password {

            position: absolute;

            top: 50%;

            right: 12px;

            transform:
                translateY(-50%);

            padding: 5px;

            border: none;

            background: transparent;

            color: #607064;

            cursor: pointer;

            font-size: 12px;

            font-weight: 700;
        }


        .show-password:hover {

            color: #173c27;
        }


        /* =========================================
           PASSWORD HELP
        ========================================= */

        .password-help {

            margin-top: 7px;

            color: #607064;

            font-size: 11px;

            line-height: 1.5;
        }


        /* =========================================
           BUTTON
        ========================================= */

        .signup-button {

            width: 100%;

            padding: 14px 18px;

            margin-top: 4px;

            border: none;

            border-radius: 24px;

            background: #173c27;

            color: #ffffff;

            cursor: pointer;

            font-size: 14px;

            font-weight: 700;

            transition:
                background 0.25s ease,
                transform 0.25s ease,
                box-shadow 0.25s ease;
        }


        .signup-button:hover {

            background: #245637;

            transform:
                translateY(-2px);

            box-shadow:
                0 10px 24px
                rgba(23, 60, 39, 0.18);
        }


        /* =========================================
           LOGIN
        ========================================= */

        .login-text {

            margin-top: 22px;

            color: #607064;

            text-align: center;

            font-size: 13px;
        }


        .login-text a {

            color: #245637;

            font-weight: 700;

            text-decoration: none;
        }


        .login-text a:hover {

            color: #173c27;

            text-decoration: underline;
        }


        /* =========================================
           HOME
        ========================================= */

        .back-home {

            display: block;

            margin-top: 18px;

            color: #607064;

            text-align: center;

            font-size: 13px;

            text-decoration: none;

            transition:
                color 0.25s ease;
        }


        .back-home:hover {

            color: #173c27;
        }


        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 560px) {

            body {

                padding: 18px;
            }


            .brand img {

                width: 165px;
            }


            .signup-card {

                padding: 26px 20px;
            }


            .signup-card h1 {

                font-size: 27px;
            }


            .form-row {

                grid-template-columns: 1fr;

                gap: 0;
            }

        }

    </style>

</head>


<body>


    <main class="signup-wrapper">


        <!-- =====================================
             BRAND / LOGO
        ====================================== -->

        <div class="brand">

            <a href="index.php">

                <img
                    src="image/logo.png"
                    alt="Elora Plants"
                >

            </a>


            <p>
                Little plants. Little joys.
            </p>

        </div>


        <!-- =====================================
             SIGNUP CARD
        ====================================== -->

        <section class="signup-card">


            <h1>
                Create Your Account
            </h1>


            <p class="subtitle">

                Join Elora Plants and start
                building your collection.

            </p>


            <!-- =================================
                 SERVER VALIDATION ERRORS
            ================================== -->

            <?php if (!empty($errors)): ?>

                <div
                    class="error-box"
                    role="alert"
                >

                    <strong>
                        Please fix the following:
                    </strong>


                    <ul>

                        <?php foreach ($errors as $error): ?>

                            <li>
                                <?= htmlspecialchars($error) ?>
                            </li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>


            <!-- =================================
                 FORM
            ================================== -->

            <form
                method="POST"
                action="signup.php"
                id="signupForm"
                novalidate
            >


                <!-- =============================
                     FIRST + LAST NAME
                ============================== -->

                <div class="form-row">


                    <div class="form-group">

                        <label for="first_name">
                            First Name
                        </label>


                        <input
                            type="text"
                            id="first_name"
                            name="first_name"
                            value="<?= htmlspecialchars($first_name) ?>"
                            placeholder="First name"
                            autocomplete="given-name"
                            maxlength="100"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="last_name">
                            Last Name
                        </label>


                        <input
                            type="text"
                            id="last_name"
                            name="last_name"
                            value="<?= htmlspecialchars($last_name) ?>"
                            placeholder="Last name"
                            autocomplete="family-name"
                            maxlength="100"
                            required
                        >

                    </div>


                </div>


                <!-- =============================
                     EMAIL
                ============================== -->

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>


                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars($email) ?>"
                        placeholder="you@example.com"
                        autocomplete="email"
                        maxlength="255"
                        required
                    >

                </div>


                <!-- =============================
                     CONFIRM EMAIL
                ============================== -->

                <div class="form-group">

                    <label for="confirm_email">
                        Confirm Email Address
                    </label>


                    <input
                        type="email"
                        id="confirm_email"
                        name="confirm_email"
                        value="<?= htmlspecialchars($confirm_email) ?>"
                        placeholder="Enter your email again"
                        autocomplete="email"
                        maxlength="255"
                        required
                    >

                </div>


                <!-- =============================
                     PASSWORD
                ============================== -->

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>


                    <div class="password-wrapper">

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Create a password"
                            autocomplete="new-password"
                            maxlength="255"
                            required
                        >


                        <button
                            type="button"
                            class="show-password"
                            data-target="password"
                        >
                            Show
                        </button>

                    </div>


                    <p class="password-help">

                        At least 8 characters,
                        including a letter and a number.

                    </p>

                </div>


                <!-- =============================
                     CONFIRM PASSWORD
                ============================== -->

                <div class="form-group">

                    <label for="confirm_password">
                        Confirm Password
                    </label>


                    <div class="password-wrapper">

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Enter your password again"
                            autocomplete="new-password"
                            maxlength="255"
                            required
                        >


                        <button
                            type="button"
                            class="show-password"
                            data-target="confirm_password"
                        >
                            Show
                        </button>

                    </div>

                </div>


                <!-- =============================
                     CREATE ACCOUNT
                ============================== -->

                <button
                    type="submit"
                    class="signup-button"
                >
                    Create Account
                </button>


            </form>


            <!-- =================================
                 LOGIN
            ================================== -->

            <p class="login-text">

                Already have an account?

                <a href="login.php">
                    Sign In
                </a>

            </p>


            <!-- =================================
                 HOME
            ================================== -->

            <a
                href="index.php"
                class="back-home"
            >
                ← Back to Elora Plants
            </a>


        </section>


    </main>


    <!-- =========================================
         SHOW / HIDE PASSWORD
    ========================================= -->

    <script>

        const passwordButtons =
            document.querySelectorAll(
                ".show-password"
            );


        passwordButtons.forEach(function(button) {

            button.addEventListener(
                "click",
                function() {

                    const targetId =
                        button.getAttribute(
                            "data-target"
                        );

                    const input =
                        document.getElementById(
                            targetId
                        );


                    if (input.type === "password") {

                        input.type = "text";

                        button.textContent =
                            "Hide";

                    } else {

                        input.type = "password";

                        button.textContent =
                            "Show";
                    }

                }
            );

        });

    </script>


</body>

</html>