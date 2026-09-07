<?php

session_start();

require_once "../db.php";


/* =========================================
   CHECK IF ALREADY LOGGED IN
========================================= */

if (isset($_SESSION["user_id"]) && isset($_SESSION["is_admin"])) {

    if ((int) $_SESSION["is_admin"] === 1) {

        header("Location: index.php");
        exit;

    }

}


/* =========================================
   VARIABLES
========================================= */

$error = "";

$email = "";


/* =========================================
   ADMIN LOGIN
========================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";


    /* =====================================
       VALIDATION
    ===================================== */

    if ($email === "") {

        $error = "Please enter your email address.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif ($password === "") {

        $error = "Please enter your password.";

    }


    /* =====================================
       FIND USER
    ===================================== */

    if ($error === "") {

        $sql = "
            SELECT
                id,
                first_name,
                last_name,
                email,
                password,
                is_admin
            FROM users
            WHERE email = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);


        if ($stmt) {

            $stmt->bind_param("s", $email);

            $stmt->execute();

            $result = $stmt->get_result();


            if ($user = $result->fetch_assoc()) {


                /* =========================
                   VERIFY PASSWORD
                ========================= */

                if (password_verify($password, $user["password"])) {


                    /* =====================
                       CHECK ADMIN ACCESS
                    ===================== */

                    if ((int) $user["is_admin"] !== 1) {

                        $error =
                            "This account does not have administrator access.";

                    } else {


                        /* =====================
                           SECURE SESSION
                        ===================== */

                        session_regenerate_id(true);


                        $_SESSION["user_id"] =
                            $user["id"];

                        $_SESSION["first_name"] =
                            $user["first_name"];

                        $_SESSION["last_name"] =
                            $user["last_name"];

                        $_SESSION["email"] =
                            $user["email"];

                        $_SESSION["is_admin"] = 1;


                        /* =====================
                           ACTIVITY LOG
                        ===================== */

                        $action = "ADMIN_LOGIN";

                        $description =
                            "Administrator logged into the admin panel.";

                        $targetType = "admin";

                        $targetId = $user["id"];


                        $logSql = "
                            INSERT INTO activity_logs
                            (
                                user_id,
                                action,
                                description,
                                target_type,
                                target_id
                            )
                            VALUES (?, ?, ?, ?, ?)
                        ";

                        $logStmt =
                            $conn->prepare($logSql);


                        if ($logStmt) {

                            $logStmt->bind_param(
                                "isssi",
                                $user["id"],
                                $action,
                                $description,
                                $targetType,
                                $targetId
                            );

                            $logStmt->execute();

                            $logStmt->close();

                        }


                        /* =====================
                           OPEN ADMIN DASHBOARD
                        ===================== */

                        header("Location: index.php");
                        exit;

                    }

                } else {

                    $error =
                        "Invalid email or password.";

                }

            } else {

                $error =
                    "Invalid email or password.";

            }


            $stmt->close();

        } else {

            $error =
                "Something went wrong. Please try again.";

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

    <title>Admin Login | Elora Plants</title>


    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


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


        .admin-login {

            width: 100%;

            max-width: 430px;

            background: #ffffff;

            border: 1px solid #dce5d9;

            border-radius: 24px;

            padding: 42px;

            box-shadow:
                0 20px 50px
                rgba(23, 60, 39, 0.08);

        }


        .logo {

            text-align: center;

            margin-bottom: 28px;

        }


        .logo img {

            width: 150px;

            max-width: 100%;

            height: auto;

        }


        .admin-label {

            text-align: center;

            font-size: 12px;

            font-weight: 700;

            letter-spacing: 2px;

            color: #607064;

            margin-bottom: 10px;

        }


        h1 {

            text-align: center;

            font-family:
                Georgia,
                "Times New Roman",
                serif;

            font-size: 34px;

            margin-bottom: 10px;

            color: #173c27;

        }


        .subtitle {

            text-align: center;

            color: #607064;

            font-size: 14px;

            line-height: 1.6;

            margin-bottom: 28px;

        }


        .error-box {

            background: #fff1f1;

            border: 1px solid #e3bcbc;

            color: #8a2929;

            border-radius: 12px;

            padding: 12px 14px;

            font-size: 13px;

            line-height: 1.5;

            margin-bottom: 20px;

        }


        .form-group {

            margin-bottom: 18px;

        }


        label {

            display: block;

            margin-bottom: 8px;

            font-size: 13px;

            font-weight: 700;

            color: #173c27;

        }


        input {

            width: 100%;

            height: 48px;

            padding: 0 14px;

            border: 1px solid #dce5d9;

            border-radius: 12px;

            background: #fafbf8;

            color: #173c27;

            font-size: 14px;

            outline: none;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;

        }


        input:focus {

            border-color: #245637;

            box-shadow:
                0 0 0 3px
                rgba(36, 86, 55, 0.08);

        }


        .password-wrapper {

            position: relative;

        }


        .password-wrapper input {

            padding-right: 70px;

        }


        .show-password {

            position: absolute;

            right: 12px;

            top: 50%;

            transform: translateY(-50%);

            border: none;

            background: transparent;

            color: #245637;

            font-size: 12px;

            font-weight: 700;

            cursor: pointer;

        }


        .login-button {

            width: 100%;

            height: 50px;

            border: none;

            border-radius: 14px;

            background: #173c27;

            color: #ffffff;

            font-size: 14px;

            font-weight: 700;

            cursor: pointer;

            transition:
                transform 0.2s ease,
                background 0.2s ease,
                box-shadow 0.2s ease;

        }


        .login-button:hover {

            background: #245637;

            transform: translateY(-2px);

            box-shadow:
                0 10px 25px
                rgba(23, 60, 39, 0.15);

        }


        .back-link {

            display: block;

            text-align: center;

            margin-top: 22px;

            color: #607064;

            font-size: 13px;

            text-decoration: none;

        }


        .back-link:hover {

            color: #173c27;

        }


        @media (max-width: 500px) {

            body {

                padding: 18px;

            }


            .admin-login {

                padding: 30px 22px;

                border-radius: 20px;

            }


            h1 {

                font-size: 29px;

            }

        }

    </style>

</head>


<body>


    <main class="admin-login">


        <div class="logo">

            <img
                src="../image/logo.png"
                alt="Elora Plants"
            >

        </div>


        <p class="admin-label">
            ELORA PLANTS
        </p>


        <h1>
            Admin Login
        </h1>


        <p class="subtitle">
            Sign in to manage products,
            inventory, orders, and activity.
        </p>


        <?php if ($error !== ""): ?>

            <div class="error-box">

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action=""
            novalidate
        >


            <div class="form-group">

                <label for="email">
                    Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?php echo htmlspecialchars($email); ?>"
                    placeholder="Enter admin email"
                    autocomplete="email"
                >

            </div>


            <div class="form-group">

                <label for="password">
                    Password
                </label>


                <div class="password-wrapper">

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter password"
                        autocomplete="current-password"
                    >


                    <button
                        type="button"
                        class="show-password"
                        id="showPassword"
                    >
                        Show
                    </button>

                </div>

            </div>


            <button
                type="submit"
                class="login-button"
            >
                Sign In as Admin
            </button>


        </form>


        <a
            href="../index.php"
            class="back-link"
        >
            ← Back to Elora Plants
        </a>


    </main>


    <script>

        const passwordInput =
            document.getElementById("password");

        const showPassword =
            document.getElementById("showPassword");


        showPassword.addEventListener(
            "click",
            function () {

                if (
                    passwordInput.type === "password"
                ) {

                    passwordInput.type = "text";

                    showPassword.textContent =
                        "Hide";

                } else {

                    passwordInput.type = "password";

                    showPassword.textContent =
                        "Show";

                }

            }
        );

    </script>


</body>

</html>