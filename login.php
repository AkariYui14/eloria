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

$error = ""; 

$email = ""; 

$registered = 
    isset($_GET["registered"]) && 
    $_GET["registered"] === "1"; 


/* ========================================= 
   ALREADY LOGGED IN
========================================= */ 

if (isset($_SESSION["user_id"])) { 

    if ( 
        isset($_SESSION["is_admin"]) && 
        (int) $_SESSION["is_admin"] === 1 
    ) { 

        header("Location: admin/index.php"); 
        exit; 

    } 

    header("Location: index.php"); 
    exit; 
} 


/* ========================================= 
   HANDLE LOGIN
========================================= */ 

if ($_SERVER["REQUEST_METHOD"] === "POST") { 

    $email = trim($_POST["email"] ?? ""); 

    $password = $_POST["password"] ?? ""; 


    /* ========================================= 
       VALIDATION
    ========================================= */ 

    if ($email === "" || $password === "") { 

        $error = 
            "Please enter your email and password."; 

    } elseif (!filter_var( 
        $email, 
        FILTER_VALIDATE_EMAIL 
    )) { 

        $error = 
            "Please enter a valid email address."; 

    } else { 


        /* ========================================= 
           FIND ACCOUNT
        ========================================= */ 

        $stmt = $conn->prepare( 
            "SELECT 
                id, 
                first_name, 
                last_name, 
                email, 
                password, 
                is_admin 
             FROM users 
             WHERE email = ? 
             LIMIT 1" 
        ); 


        $stmt->bind_param( 
            "s", 
            $email 
        ); 


        $stmt->execute(); 


        $result = $stmt->get_result(); 


        /* ========================================= 
           CHECK ACCOUNT
        ========================================= */ 

        if ($result->num_rows === 1) { 

            $user = $result->fetch_assoc(); 


            /* ========================================= 
               VERIFY PASSWORD
            ========================================= */ 

            if ( 
                password_verify( 
                    $password, 
                    $user["password"] 
                ) 
            ) { 


                /* ========================================= 
                   REFRESH SESSION ID
                ========================================= */ 

                session_regenerate_id(true); 


                /* ========================================= 
                   CREATE SESSION
                ========================================= */ 

                $_SESSION["user_id"] = 
                    $user["id"]; 

                $_SESSION["first_name"] = 
                    $user["first_name"]; 

                $_SESSION["last_name"] = 
                    $user["last_name"]; 

                $_SESSION["email"] = 
                    $user["email"]; 

                $_SESSION["is_admin"] = 
                    (int) $user["is_admin"]; 


                /* ========================================= 
                   RECORD LOGIN ACTIVITY
                ========================================= */ 

                $action = "LOGIN"; 

                $description = 
                    "User logged into the account."; 

                $targetType = "User"; 

                $targetId = 
                    (int) $user["id"]; 


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
                        $targetId, 
                        $action, 
                        $description, 
                        $targetType, 
                        $targetId 
                    ); 


                    $activity->execute(); 

                    $activity->close(); 
                } 


                $stmt->close(); 


                /* ========================================= 
                   ADMIN / CUSTOMER REDIRECT
                ========================================= */ 

                if ( 
                    (int) $user["is_admin"] === 1 
                ) { 

                    header( 
                        "Location: admin/index.php" 
                    ); 

                    exit; 

                } else { 

                    header( 
                        "Location: index.php" 
                    ); 

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
        Sign In | Elora Plants 
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

        .login-wrapper { 

            width: 100%; 

            max-width: 440px; 
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
           LOGIN CARD
        ========================================= */ 

        .login-card { 

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

        .login-card h1 { 

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
           SUCCESS MESSAGE
        ========================================= */ 

        .success-message { 

            padding: 13px 15px; 

            margin-bottom: 20px; 

            border: 
                1px solid #cbdcc5; 

            border-radius: 12px; 

            background: #edf3e8; 

            color: #245637; 

            font-size: 13px; 

            line-height: 1.5; 
        } 


        /* ========================================= 
           ERROR MESSAGE
        ========================================= */ 

        .error-message { 

            padding: 13px 15px; 

            margin-bottom: 20px; 

            border: 
                1px solid #efd2cc; 

            border-radius: 12px; 

            background: #fff3f1; 

            color: #9b3d31; 

            font-size: 13px; 

            line-height: 1.5; 
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
           PASSWORD WRAPPER
        ========================================= */ 

        .password-wrapper { 

            position: relative; 
        } 


        .password-wrapper input { 

            padding-right: 72px; 
        } 


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
           LOGIN BUTTON
        ========================================= */ 

        .login-button { 

            width: 100%; 

            padding: 14px 18px; 

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


        .login-button:hover { 

            background: #245637; 

            transform: 
                translateY(-2px); 

            box-shadow: 
                0 10px 24px 
                rgba(23, 60, 39, 0.18); 
        } 


        /* ========================================= 
           SIGN UP
        ========================================= */ 

        .signup-text { 

            margin-top: 22px; 

            text-align: center; 

            color: #607064; 

            font-size: 13px; 
        } 


        .signup-text a { 

            color: #245637; 

            font-weight: 700; 

            text-decoration: none; 
        } 


        .signup-text a:hover { 

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

        @media (max-width: 520px) { 

            body { 

                padding: 18px; 
            } 


            .brand img { 

                width: 165px; 
            } 


            .login-card { 

                padding: 26px 20px; 
            } 


            .login-card h1 { 

                font-size: 27px; 
            } 

        } 

    </style> 

</head> 


<body> 


    <main class="login-wrapper"> 


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
             LOGIN CARD
        ====================================== --> 

        <section class="login-card"> 


            <h1> 
                Welcome Back 
            </h1> 


            <p class="subtitle"> 

                Sign in to continue to your 
                Elora Plants account. 

            </p> 


            <!-- =================================
                 REGISTRATION SUCCESS
            ================================== --> 

            <?php if ($registered): ?> 

                <div class="success-message"> 

                    Your account was created successfully. 
                    You can now sign in. 

                </div> 

            <?php endif; ?> 


            <!-- =================================
                 ERROR
            ================================== --> 

            <?php if ($error !== ""): ?> 

                <div class="error-message"> 

                    <?= htmlspecialchars($error) ?> 

                </div> 

            <?php endif; ?> 


            <!-- =================================
                 LOGIN FORM
            ================================== --> 

            <form 
                method="POST" 
                action="login.php" 
            > 


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
                            placeholder="Enter your password" 
                            autocomplete="current-password" 
                            required 
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


                <!-- =============================
                     LOGIN
                ============================== --> 

                <button 
                    type="submit" 
                    class="login-button" 
                > 
                    Sign In 
                </button> 


            </form> 


            <!-- =================================
                 SIGN UP
            ================================== --> 

            <p class="signup-text"> 

                Don't have an account? 

                <a href="signup.php"> 
                    Create Account 
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

        const showPassword = 
            document.getElementById("showPassword"); 

        const passwordInput = 
            document.getElementById("password"); 


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