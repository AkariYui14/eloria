<?php

session_start();

require_once "../db.php";


/* =========================================
   PREVENT BROWSER CACHING
========================================= */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");


/* =========================================
   ADMIN ACCESS PROTECTION
========================================= */

if (!isset($_SESSION["user_id"])) {

    header("Location: ../login.php");
    exit;
}


/* =========================================
   GET CURRENT USER
========================================= */

$user_id = (int) $_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT
        first_name,
        last_name,
        email,
        is_admin
     FROM users
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

$stmt->close();


/* =========================================
   VERIFY ADMIN
========================================= */

if (!$user || (int) $user["is_admin"] !== 1) {

    header("Location: ../index.php");
    exit;
}


/* =========================================
   VARIABLES
========================================= */

$message = "";
$error = "";
$edit_product = null;


/* =========================================
   SUCCESS MESSAGES
========================================= */

if (isset($_GET["updated"])) {

    $message = "Product updated successfully.";
}


if (isset($_GET["added"])) {

    $message = "Product added successfully.";
}


if (isset($_GET["removed"])) {

    $message = "Product removed successfully.";
}


/* =========================================
   IMAGE UPLOAD SETTINGS
========================================= */

$upload_directory = "../image/products/";

$database_image_directory = "image/products/";


/* =========================================
   CREATE IMAGE DIRECTORY
========================================= */

if (!is_dir($upload_directory)) {

    mkdir($upload_directory, 0755, true);
}


/* =========================================
   IMAGE UPLOAD FUNCTION
========================================= */

function upload_product_image(
    $file,
    $upload_directory,
    $database_image_directory
) {

    if (
        !isset($file) ||
        !isset($file["error"]) ||
        $file["error"] === UPLOAD_ERR_NO_FILE
    ) {

        return [
            "success" => true,
            "path" => ""
        ];
    }


    if ($file["error"] !== UPLOAD_ERR_OK) {

        return [
            "success" => false,
            "error" => "There was a problem uploading the image."
        ];
    }


    /* =========================================
       MAXIMUM FILE SIZE - 5 MB
    ========================================= */

    if ($file["size"] > 5 * 1024 * 1024) {

        return [
            "success" => false,
            "error" => "Image must be 5 MB or smaller."
        ];
    }


    /* =========================================
       VERIFY IMAGE
    ========================================= */

    $image_info = @getimagesize($file["tmp_name"]);


    if ($image_info === false) {

        return [
            "success" => false,
            "error" => "Please upload a valid image file."
        ];
    }


    /* =========================================
       ALLOWED IMAGE TYPES
    ========================================= */

    $allowed_types = [

        "image/jpeg" => "jpg",

        "image/png" => "png",

        "image/webp" => "webp",

        "image/gif" => "gif"

    ];


    $mime_type = $image_info["mime"] ?? "";


    if (!isset($allowed_types[$mime_type])) {

        return [
            "success" => false,
            "error" =>
                "Only JPG, PNG, WEBP, and GIF images are allowed."
        ];
    }


    /* =========================================
       CREATE UNIQUE FILE NAME
    ========================================= */

    try {

        $random_name = bin2hex(random_bytes(12));

    } catch (Exception $e) {

        $random_name = uniqid();
    }


    $extension = $allowed_types[$mime_type];


    $file_name =
        "product_" .
        $random_name .
        "." .
        $extension;


    $destination =
        $upload_directory .
        $file_name;


    /* =========================================
       MOVE FILE
    ========================================= */

    if (
        !move_uploaded_file(
            $file["tmp_name"],
            $destination
        )
    ) {

        return [
            "success" => false,
            "error" => "Unable to save the uploaded image."
        ];
    }


    return [

        "success" => true,

        "path" =>
            $database_image_directory .
            $file_name
    ];
}


/* =========================================
   DELETE UPLOADED PRODUCT IMAGE
========================================= */

function delete_uploaded_product_image($image_path)
{

    if (
        empty($image_path) ||
        strpos(
            $image_path,
            "image/products/"
        ) !== 0
    ) {

        return;
    }


    $full_path = "../" . $image_path;


    if (is_file($full_path)) {

        @unlink($full_path);
    }
}


/* =========================================
   REMOVE PRODUCT
========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_product"])
) {

    $product_id =
        (int) ($_POST["product_id"] ?? 0);


    if ($product_id <= 0) {

        $error = "Invalid product.";

    } else {

        /* =========================================
           GET PRODUCT
        ========================================= */

        $stmt = $conn->prepare(
            "SELECT
                name,
                image
             FROM products
             WHERE id = ?
             LIMIT 1"
        );


        $stmt->bind_param(
            "i",
            $product_id
        );


        $stmt->execute();


        $result = $stmt->get_result();


        $product = $result->fetch_assoc();


        $stmt->close();


        if (!$product) {

            $error = "Product could not be found.";

        } else {

            $product_name =
                $product["name"];

            $product_image =
                $product["image"] ?? "";


            /* =========================================
               START TRANSACTION
            ========================================= */

            $conn->begin_transaction();


            try {

                /* =========================================
                   REMOVE FROM CUSTOMER CARTS
                ========================================= */

                $stmt = $conn->prepare(
                    "DELETE FROM cart_items
                     WHERE product_id = ?"
                );


                if (!$stmt) {

                    throw new Exception(
                        "Unable to prepare cart removal."
                    );
                }


                $stmt->bind_param(
                    "i",
                    $product_id
                );


                if (!$stmt->execute()) {

                    $stmt->close();

                    throw new Exception(
                        "Unable to remove product from carts."
                    );
                }


                $stmt->close();


                /* =========================================
                   DELETE PRODUCT
                ========================================= */

                $stmt = $conn->prepare(
                    "DELETE FROM products
                     WHERE id = ?
                     LIMIT 1"
                );


                if (!$stmt) {

                    throw new Exception(
                        "Unable to prepare product deletion."
                    );
                }


                $stmt->bind_param(
                    "i",
                    $product_id
                );


                if (!$stmt->execute()) {

                    $stmt->close();

                    throw new Exception(
                        "Unable to delete product."
                    );
                }


                if ($stmt->affected_rows !== 1) {

                    $stmt->close();

                    throw new Exception(
                        "Product could not be deleted."
                    );
                }


                $stmt->close();


                /* =========================================
                   ACTIVITY LOG
                ========================================= */

                $action =
                    "PRODUCT_REMOVED";

                $description_log =
                    "Admin permanently removed product: " .
                    $product_name;

                $target_type =
                    "Product";

                $target_id =
                    $product_id;


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


                if (!$activity) {

                    throw new Exception(
                        "Unable to create activity log."
                    );
                }


                $activity->bind_param(
                    "isssi",
                    $user_id,
                    $action,
                    $description_log,
                    $target_type,
                    $target_id
                );


                if (!$activity->execute()) {

                    $activity->close();

                    throw new Exception(
                        "Unable to save activity log."
                    );
                }


                $activity->close();


                /* =========================================
                   COMMIT
                ========================================= */

                $conn->commit();


                /* =========================================
                   DELETE IMAGE
                ========================================= */

                if (!empty($product_image)) {

                    delete_uploaded_product_image(
                        $product_image
                    );
                }


                /* =========================================
                   REDIRECT
                ========================================= */

                header(
                    "Location: products.php?removed=1"
                );

                exit;

            } catch (Exception $e) {

                $conn->rollback();

                $error =
                    "Unable to remove the product. " .
                    $e->getMessage();
            }
        }
    }
}


/* =========================================
   ADD PRODUCT
========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["add_product"])
) {

    $name =
        trim($_POST["name"] ?? "");

    $description =
        trim($_POST["description"] ?? "");

    $price =
        trim($_POST["price"] ?? "");

    $stock =
        trim($_POST["stock"] ?? "");

    $category =
        trim($_POST["category"] ?? "");


    /* =========================================
       VALIDATION
    ========================================= */

    if ($name === "") {

        $error =
            "Please enter a product name.";

    } elseif (
        $price === "" ||
        !is_numeric($price)
    ) {

        $error =
            "Please enter a valid price.";

    } elseif (
        (float) $price < 0
    ) {

        $error =
            "Price cannot be negative.";

    } elseif (
        $stock === "" ||
        filter_var(
            $stock,
            FILTER_VALIDATE_INT
        ) === false
    ) {

        $error =
            "Please enter a valid stock quantity.";

    } elseif (
        (int) $stock < 0
    ) {

        $error =
            "Stock cannot be negative.";

    } else {

        /* =========================================
           CHECK DUPLICATE
        ========================================= */

        $stmt = $conn->prepare(
            "SELECT id
             FROM products
             WHERE name = ?
             AND is_active = 1
             LIMIT 1"
        );


        $stmt->bind_param(
            "s",
            $name
        );


        $stmt->execute();


        $result =
            $stmt->get_result();


        if ($result->num_rows > 0) {

            $error =
                "A product with this name already exists.";

            $stmt->close();

        } else {

            $stmt->close();


            /* =========================================
               UPLOAD IMAGE
            ========================================= */

            $upload_result =
                upload_product_image(
                    $_FILES["image"] ?? null,
                    $upload_directory,
                    $database_image_directory
                );


            if (!$upload_result["success"]) {

                $error =
                    $upload_result["error"];

            } else {

                $price_value =
                    (float) $price;

                $stock_value =
                    (int) $stock;

                $image =
                    $upload_result["path"] ?? "";


                /* =========================================
                   INSERT PRODUCT
                ========================================= */

                $stmt = $conn->prepare(
                    "INSERT INTO products
                    (
                        name,
                        description,
                        price,
                        stock,
                        image,
                        category,
                        is_active
                    )
                    VALUES (?, ?, ?, ?, ?, ?, 1)"
                );


                $stmt->bind_param(
                    "ssdiss",
                    $name,
                    $description,
                    $price_value,
                    $stock_value,
                    $image,
                    $category
                );


                if ($stmt->execute()) {

                    $new_product_id =
                        $stmt->insert_id;


                    /* =========================================
                       ACTIVITY LOG
                    ========================================= */

                    $action =
                        "PRODUCT_ADDED";

                    $description_log =
                        "Admin added product: " .
                        $name;

                    $target_type =
                        "Product";

                    $target_id =
                        $new_product_id;


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
                            $description_log,
                            $target_type,
                            $target_id
                        );


                        $activity->execute();

                        $activity->close();
                    }


                    $stmt->close();


                    header(
                        "Location: products.php?added=1"
                    );

                    exit;

                } else {

                    if (!empty($image)) {

                        delete_uploaded_product_image(
                            $image
                        );
                    }


                    $error =
                        "Unable to add the product.";

                    $stmt->close();
                }
            }
        }
    }
}


/* =========================================
   EDIT PRODUCT
========================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["edit_product"])
) {

    $product_id =
        (int) ($_POST["product_id"] ?? 0);

    $name =
        trim($_POST["name"] ?? "");

    $description =
        trim($_POST["description"] ?? "");

    $price =
        trim($_POST["price"] ?? "");

    $stock =
        trim($_POST["stock"] ?? "");

    $category =
        trim($_POST["category"] ?? "");


    /* =========================================
       VALIDATION
    ========================================= */

    if ($product_id <= 0) {

        $error =
            "Invalid product.";

    } elseif ($name === "") {

        $error =
            "Please enter a product name.";

    } elseif (
        $price === "" ||
        !is_numeric($price)
    ) {

        $error =
            "Please enter a valid price.";

    } elseif (
        (float) $price < 0
    ) {

        $error =
            "Price cannot be negative.";

    } elseif (
        $stock === "" ||
        filter_var(
            $stock,
            FILTER_VALIDATE_INT
        ) === false
    ) {

        $error =
            "Please enter a valid stock quantity.";

    } elseif (
        (int) $stock < 0
    ) {

        $error =
            "Stock cannot be negative.";

    } else {

        /* =========================================
           GET EXISTING PRODUCT
        ========================================= */

        $stmt = $conn->prepare(
            "SELECT
                name,
                price,
                stock,
                description,
                category,
                image
             FROM products
             WHERE id = ?
             AND is_active = 1
             LIMIT 1"
        );


        $stmt->bind_param(
            "i",
            $product_id
        );


        $stmt->execute();


        $result =
            $stmt->get_result();


        $old_product =
            $result->fetch_assoc();


        $stmt->close();


        if (!$old_product) {

            $error =
                "Product could not be found.";

        } else {

            /* =========================================
               CHECK DUPLICATE NAME
            ========================================= */

            $stmt = $conn->prepare(
                "SELECT id
                 FROM products
                 WHERE name = ?
                 AND id != ?
                 AND is_active = 1
                 LIMIT 1"
            );


            $stmt->bind_param(
                "si",
                $name,
                $product_id
            );


            $stmt->execute();


            $result =
                $stmt->get_result();


            if ($result->num_rows > 0) {

                $error =
                    "Another product already uses this name.";

                $stmt->close();

            } else {

                $stmt->close();


                /* =========================================
                   IMAGE HANDLING
                ========================================= */

                $new_image =
                    $old_product["image"] ?? "";

                $uploaded_new_image =
                    false;


                if (
                    isset($_FILES["image"]) &&
                    $_FILES["image"]["error"] !==
                    UPLOAD_ERR_NO_FILE
                ) {

                    $upload_result =
                        upload_product_image(
                            $_FILES["image"],
                            $upload_directory,
                            $database_image_directory
                        );


                    if (!$upload_result["success"]) {

                        $error =
                            $upload_result["error"];

                    } else {

                        $new_image =
                            $upload_result["path"];

                        $uploaded_new_image =
                            true;
                    }
                }


                if ($error === "") {

                    /* =========================================
                       UPDATE PRODUCT
                    ========================================= */

                    $price_value =
                        (float) $price;

                    $stock_value =
                        (int) $stock;


                    if ($uploaded_new_image) {

                        $stmt = $conn->prepare(
                            "UPDATE products
                             SET
                                name = ?,
                                description = ?,
                                price = ?,
                                stock = ?,
                                image = ?,
                                category = ?,
                                updated_at = CURRENT_TIMESTAMP
                             WHERE id = ?
                             AND is_active = 1"
                        );


                        $stmt->bind_param(
                            "ssdissi",
                            $name,
                            $description,
                            $price_value,
                            $stock_value,
                            $new_image,
                            $category,
                            $product_id
                        );

                    } else {

                        $stmt = $conn->prepare(
                            "UPDATE products
                             SET
                                name = ?,
                                description = ?,
                                price = ?,
                                stock = ?,
                                category = ?,
                                updated_at = CURRENT_TIMESTAMP
                             WHERE id = ?
                             AND is_active = 1"
                        );


                        $stmt->bind_param(
                            "ssdisi",
                            $name,
                            $description,
                            $price_value,
                            $stock_value,
                            $category,
                            $product_id
                        );
                    }


                    /* =========================================
                       EXECUTE UPDATE
                    ========================================= */

                    if ($stmt->execute()) {

                        $stmt->close();


                        /* =========================================
                           DELETE OLD IMAGE
                        ========================================= */

                        if (
                            $uploaded_new_image &&
                            !empty(
                                $old_product["image"]
                            )
                        ) {

                            delete_uploaded_product_image(
                                $old_product["image"]
                            );
                        }


                        /* =========================================
                           ACTIVITY LOG
                        ========================================= */

                        $action =
                            "PRODUCT_EDITED";

                        $description_log =
                            "Admin edited product: " .
                            $name;

                        $target_type =
                            "Product";

                        $target_id =
                            $product_id;


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
                                $description_log,
                                $target_type,
                                $target_id
                            );


                            $activity->execute();

                            $activity->close();
                        }


                        /* =========================================
                           REDIRECT AFTER UPDATE
                        ========================================= */

                        header(
                            "Location: products.php?updated=1"
                        );

                        exit;

                    } else {

                        $stmt->close();


                        if ($uploaded_new_image) {

                            delete_uploaded_product_image(
                                $new_image
                            );
                        }


                        $error =
                            "Unable to update the product.";
                    }
                }
            }
        }
    }
}


/* =========================================
   EDIT MODE
========================================= */

if (
    isset($_GET["edit"]) &&
    is_numeric($_GET["edit"])
) {

    $edit_id =
        (int) $_GET["edit"];


    $stmt = $conn->prepare(
        "SELECT
            id,
            name,
            description,
            price,
            stock,
            image,
            category
         FROM products
         WHERE id = ?
         AND is_active = 1
         LIMIT 1"
    );


    $stmt->bind_param(
        "i",
        $edit_id
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $edit_product =
        $result->fetch_assoc();


    $stmt->close();


    if (!$edit_product) {

        $error =
            "Product could not be found.";
    }
}


/* =========================================
   GET ACTIVE PRODUCTS
========================================= */

$products = [];


$result = $conn->query(
    "SELECT
        id,
        name,
        description,
        price,
        stock,
        image,
        category,
        created_at,
        updated_at
     FROM products
     WHERE is_active = 1
     ORDER BY id DESC"
);


if ($result) {

    while (
        $row = $result->fetch_assoc()
    ) {

        $products[] = $row;
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

    <meta
        http-equiv="Cache-Control"
        content="no-cache, no-store, must-revalidate"
    >

    <meta
        http-equiv="Pragma"
        content="no-cache"
    >

    <meta
        http-equiv="Expires"
        content="0"
    >

    <title>
        Products | Elora Admin
    </title>


    <style>

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

            min-height: 100vh;
        }


        a {
            text-decoration: none;
            color: inherit;
        }


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

            margin-top: 5px;

            letter-spacing: 1.5px;

            font-weight: 600;
        }


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

            transform:
                translateX(3px);
        }


        .nav a.active {

            background: #294f37;

            color: #ffffff;

            font-weight: bold;
        }


        .logout {

            margin-top: 25px;

            border-top:
                1px solid
                rgba(41, 79, 55, 0.18);

            padding-top: 25px;
        }


        /* =========================================
           MAIN
        ========================================= */

        .main-content {

            margin-left: 250px;

            width:
                calc(100% - 250px);

            padding: 35px;
        }


        .page-header {

            margin-bottom: 30px;

            display: flex;

            align-items: flex-end;

            justify-content: space-between;

            gap: 20px;
        }


        .page-label {

            display: block;

            color: #718077;

            font-size: 11px;

            font-weight: bold;

            letter-spacing: 2px;

            margin-bottom: 8px;
        }


        .page-header h2 {

            font-family:
                Georgia,
                "Times New Roman",
                serif;

            font-size: 34px;

            font-weight: normal;

            color: #183a2a;
        }


        .page-header p {

            color: #718077;

            margin-top: 6px;

            line-height: 1.6;
        }


        .back-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 11px 18px;

            border-radius: 10px;

            background: #ffffff;

            border:
                1px solid #d5ded6;

            color: #315c3d;

            font-size: 13px;

            font-weight: bold;

            white-space: nowrap;

            transition: 0.2s ease;
        }


        .back-button:hover {

            background: #edf3e8;

            transform:
                translateY(-1px);
        }


        /* =========================================
           MESSAGES
        ========================================= */

        .message {

            padding: 14px 17px;

            margin-bottom: 25px;

            border-radius: 12px;

            background: #e5efe7;

            border:
                1px solid #cbdccf;

            color: #28563f;

            font-size: 13px;
        }


        .message.error {

            background: #fff3f1;

            border-color: #efd2cc;

            color: #9b3d31;
        }


        /* =========================================
           FORM CARD
        ========================================= */

        .form-card {

            background: #ffffff;

            border-radius: 18px;

            padding: 25px;

            margin-bottom: 30px;

            box-shadow:
                0 8px 30px
                rgba(35, 60, 45, 0.07);
        }


        .form-card h2 {

            font-family:
                Georgia,
                "Times New Roman",
                serif;

            font-size: 25px;

            font-weight: normal;

            color: #183a2a;

            margin-bottom: 7px;
        }


        .form-description {

            color: #718077;

            font-size: 13px;

            margin-bottom: 25px;
        }


        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 18px;
        }


        .form-group {

            display: flex;

            flex-direction: column;
        }


        .form-group.full {

            grid-column:
                1 / -1;
        }


        .form-group label {

            margin-bottom: 7px;

            color: #355642;

            font-size: 12px;

            font-weight: bold;
        }


        .form-group input,
        .form-group textarea,
        .form-group select {

            width: 100%;

            padding: 12px 13px;

            border:
                1px solid #dce4dc;

            border-radius: 10px;

            outline: none;

            background: #ffffff;

            color: #24352a;

            font-family: inherit;

            font-size: 13px;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }


        .form-group textarea {

            min-height: 100px;

            resize: vertical;
        }


        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {

            border-color: #8bab91;

            box-shadow:
                0 0 0 3px
                rgba(139, 171, 145, 0.12);
        }


        .form-group input[type="file"] {

            padding: 9px;
        }


        .image-help {

            margin-top: 6px;

            color: #78857d;

            font-size: 11px;

            line-height: 1.5;
        }


        .current-image-wrapper {

            margin-top: 12px;

            display: flex;

            align-items: center;

            gap: 12px;
        }


        .current-image {

            width: 70px;

            height: 70px;

            object-fit: cover;

            border-radius: 12px;

            border:
                1px solid #dce4dc;

            background: #f4f7f2;
        }


        .current-image-text {

            color: #718077;

            font-size: 11px;

            line-height: 1.5;
        }


        .form-actions {

            display: flex;

            gap: 10px;

            margin-top: 22px;
        }


        .primary-button {

            border: none;

            border-radius: 10px;

            background: #315c3d;

            color: #ffffff;

            padding: 12px 20px;

            cursor: pointer;

            font-size: 13px;

            font-weight: bold;

            transition: 0.2s ease;
        }


        .primary-button:hover {

            background: #23452d;

            transform:
                translateY(-1px);
        }


        .cancel-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 12px 20px;

            border-radius: 10px;

            background: #f5f7f2;

            border:
                1px solid #d5ded6;

            color: #536359;

            font-size: 13px;

            font-weight: bold;

            transition: 0.2s ease;
        }


        .cancel-button:hover {

            background: #e9eee8;

            color: #315c3d;
        }


        /* =========================================
           PRODUCTS CARD
        ========================================= */

        .products-card {

            background: #ffffff;

            border-radius: 18px;

            box-shadow:
                0 8px 30px
                rgba(35, 60, 45, 0.07);

            overflow: hidden;
        }


        .products-card-header {

            padding: 25px;

            border-bottom:
                1px solid #edf1ed;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;
        }


        .products-card-header h2 {

            font-family:
                Georgia,
                "Times New Roman",
                serif;

            font-size: 25px;

            font-weight: normal;

            color: #183a2a;
        }


        .product-count {

            color: #78857d;

            font-size: 13px;
        }


        .table-wrapper {

            width: 100%;

            overflow-x: auto;
        }


        table {

            width: 100%;

            min-width: 1050px;

            border-collapse: collapse;
        }


        th {

            text-align: left;

            padding: 15px;

            font-size: 12px;

            color: #68766d;

            background: #f4f7f2;

            border-bottom:
                1px solid #e2e8e2;

            white-space: nowrap;
        }


        td {

            padding: 18px 15px;

            border-bottom:
                1px solid #edf1ed;

            color: #65736a;

            font-size: 13px;

            vertical-align: middle;
        }


        tbody tr:hover {

            background: #fafcf9;
        }


        .product-image {

            width: 70px;

            height: 70px;

            object-fit: cover;

            border-radius: 12px;

            border:
                1px solid #dce4dc;

            background: #f4f7f2;

            display: block;
        }


        .no-image {

            width: 70px;

            height: 70px;

            border-radius: 12px;

            background: #f0f4ee;

            border:
                1px solid #dce4dc;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 25px;
        }


        .product-name {

            color: #263a2d;

            font-weight: bold;

            margin-bottom: 4px;
        }


        .product-description {

            max-width: 280px;

            color: #78857d;

            line-height: 1.5;
        }


        .price {

            color: #28563f;

            font-weight: bold;

            white-space: nowrap;
        }


        .stock {

            display: inline-block;

            padding: 7px 12px;

            border-radius: 20px;

            background: #e5efe7;

            color: #28563f;

            font-size: 12px;

            font-weight: bold;
        }


        .stock.empty {

            background: #fff3f1;

            color: #9b3d31;
        }


        .category {

            color: #65736a;

            font-size: 13px;
        }


        .actions {

            display: flex;

            align-items: center;

            gap: 7px;
        }


        .edit-button {

            display: inline-block;

            padding: 8px 12px;

            border-radius: 10px;

            background: #e5efe7;

            color: #28563f;

            text-decoration: none;

            font-size: 11px;

            font-weight: bold;

            transition: 0.2s ease;
        }


        .edit-button:hover {

            background: #d7e7da;

            transform:
                translateY(-1px);
        }


        .delete-button {

            padding: 8px 12px;

            border: none;

            border-radius: 10px;

            background: #fff3f1;

            color: #9b3d31;

            cursor: pointer;

            font-size: 11px;

            font-weight: bold;

            transition: 0.2s ease;
        }


        .delete-button:hover {

            background: #f9e4e0;

            transform:
                translateY(-1px);
        }


        /* =========================================
           EMPTY STATE
        ========================================= */

        .empty-state {

            text-align: center;

            padding: 70px 20px;
        }


        .empty-state-icon {

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

            font-weight: normal;
        }


        .empty-state p {

            color: #7a867e;

            line-height: 1.6;
        }


        /* =========================================
           DELETE CONFIRMATION MODAL
        ========================================= */

        .modal-overlay {

            position: fixed;

            inset: 0;

            background:
                rgba(24, 58, 42, 0.45);

            display: none;

            align-items: center;

            justify-content: center;

            padding: 20px;

            z-index: 9999;

            backdrop-filter: blur(3px);
        }


        .modal-overlay.show {

            display: flex;
        }


        .delete-modal {

            width: 100%;

            max-width: 440px;

            background: #ffffff;

            border-radius: 20px;

            padding: 30px;

            box-shadow:
                0 20px 60px
                rgba(24, 58, 42, 0.2);

            animation:
                modalIn 0.2s ease;
        }


        @keyframes modalIn {

            from {

                opacity: 0;

                transform:
                    translateY(-10px)
                    scale(0.98);
            }

            to {

                opacity: 1;

                transform:
                    translateY(0)
                    scale(1);
            }
        }


        .modal-icon {

            width: 55px;

            height: 55px;

            border-radius: 50%;

            background: #fff3f1;

            color: #9b3d31;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 25px;

            margin-bottom: 18px;
        }


        .delete-modal h3 {

            font-family:
                Georgia,
                "Times New Roman",
                serif;

            font-size: 26px;

            font-weight: normal;

            color: #183a2a;

            margin-bottom: 10px;
        }


        .delete-modal p {

            color: #718077;

            font-size: 13px;

            line-height: 1.6;

            margin-bottom: 5px;
        }


        .delete-product-name {

            color: #315c3d;

            font-weight: bold;

            margin-top: 8px;
        }


        .modal-warning {

            color: #9b3d31 !important;

            font-size: 12px !important;

            margin-top: 10px;
        }


        .modal-actions {

            display: flex;

            justify-content: flex-end;

            gap: 10px;

            margin-top: 25px;
        }


        .modal-cancel {

            border: 1px solid #d5ded6;

            background: #f5f7f2;

            color: #536359;

            padding: 11px 18px;

            border-radius: 10px;

            cursor: pointer;

            font-size: 13px;

            font-weight: bold;

            transition: 0.2s ease;
        }


        .modal-cancel:hover {

            background: #e9eee8;
        }


        .modal-delete {

            border: none;

            background: #9b3d31;

            color: #ffffff;

            padding: 11px 18px;

            border-radius: 10px;

            cursor: pointer;

            font-size: 13px;

            font-weight: bold;

            transition: 0.2s ease;
        }


        .modal-delete:hover {

            background: #7f3027;

            transform:
                translateY(-1px);
        }


        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 1000px) {

            .form-grid {

                grid-template-columns: 1fr;
            }


            .form-group.full {

                grid-column: auto;
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


            .page-header {

                align-items: flex-start;

                flex-direction: column;
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


            .form-card {

                padding: 20px;
            }


            .products-card-header {

                padding: 20px;
            }


            .form-actions {

                flex-direction: column;
            }


            .primary-button,
            .cancel-button {

                width: 100%;
            }


            .modal-actions {

                flex-direction: column;
            }


            .modal-cancel,
            .modal-delete {

                width: 100%;
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


            <a
                href="products.php"
                class="active"
            >
                Products
            </a>


            <a href="orders.php">
                Orders
            </a>


            <a href="users.php">
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


        <div class="page-header">


            <div>

                <span class="page-label">
                    INVENTORY MANAGEMENT
                </span>


                <h2>
                    Products
                </h2>


                <p>
                    Add, edit, manage, and remove products
                    from your Elora inventory.
                </p>

            </div>


            <a
                href="index.php"
                class="back-button"
            >
                ← Dashboard
            </a>


        </div>


        <?php if ($message !== ""): ?>

            <div class="message">

                <?= htmlspecialchars(
                    $message
                ) ?>

            </div>

        <?php endif; ?>


        <?php if ($error !== ""): ?>

            <div class="message error">

                <?= htmlspecialchars(
                    $error
                ) ?>

            </div>

        <?php endif; ?>


        <!-- =========================================
             ADD / EDIT FORM
        ========================================== -->

        <section class="form-card">


            <?php if ($edit_product): ?>


                <h2>
                    Edit Product
                </h2>


                <p class="form-description">
                    Update the product information below.
                </p>


                <form
                    method="POST"
                    action="products.php"
                    enctype="multipart/form-data"
                >


                    <input
                        type="hidden"
                        name="product_id"
                        value="<?= (int)
                            $edit_product["id"] ?>"
                    >


                    <div class="form-grid">


                        <div class="form-group">

                            <label for="edit_name">
                                Product Name
                            </label>


                            <input
                                type="text"
                                id="edit_name"
                                name="name"
                                value="<?= htmlspecialchars(
                                    $edit_product["name"]
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="edit_category">
                                Category
                            </label>


                            <input
                                type="text"
                                id="edit_category"
                                name="category"
                                value="<?= htmlspecialchars(
                                    $edit_product["category"] ?? ""
                                ) ?>"
                                placeholder="Succulent, Indoor Plants, etc."
                            >

                        </div>


                        <div class="form-group">

                            <label for="edit_price">
                                Price
                            </label>


                            <input
                                type="number"
                                id="edit_price"
                                name="price"
                                min="0"
                                step="0.01"
                                value="<?= htmlspecialchars(
                                    $edit_product["price"]
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="edit_stock">
                                Stock Quantity
                            </label>


                            <input
                                type="number"
                                id="edit_stock"
                                name="stock"
                                min="0"
                                step="1"
                                value="<?= htmlspecialchars(
                                    $edit_product["stock"]
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="form-group full">

                            <label for="edit_description">
                                Description
                            </label>


                            <textarea
                                id="edit_description"
                                name="description"
                                placeholder="Describe this plant..."
                            ><?= htmlspecialchars(
                                $edit_product["description"] ?? ""
                            ) ?></textarea>

                        </div>


                        <div class="form-group full">

                            <label for="edit_image">
                                Product Image
                            </label>


                            <input
                                type="file"
                                id="edit_image"
                                name="image"
                                accept="image/jpeg,image/png,image/webp,image/gif"
                            >


                            <div class="image-help">
                                Upload a new image only if you want to
                                replace the current one. Maximum 5 MB.
                            </div>


                            <?php if (
                                !empty(
                                    $edit_product["image"]
                                )
                            ): ?>


                                <div class="current-image-wrapper">


                                    <img
                                        src="../<?= htmlspecialchars(
                                            $edit_product["image"]
                                        ) ?>"
                                        alt="Current product image"
                                        class="current-image"
                                        onerror="this.style.display='none';"
                                    >


                                    <div class="current-image-text">
                                        Current product image
                                    </div>


                                </div>


                            <?php endif; ?>


                        </div>


                    </div>


                    <div class="form-actions">


                        <button
                            type="submit"
                            name="edit_product"
                            class="primary-button"
                        >
                            Save Changes
                        </button>


                        <a
                            href="products.php"
                            class="cancel-button"
                        >
                            Cancel
                        </a>


                    </div>


                </form>


            <?php else: ?>


                <h2>
                    Add New Product
                </h2>


                <p class="form-description">
                    Add a new plant to your Elora inventory.
                </p>


                <form
                    method="POST"
                    action="products.php"
                    enctype="multipart/form-data"
                >


                    <div class="form-grid">


                        <div class="form-group">

                            <label for="add_name">
                                Product Name
                            </label>


                            <input
                                type="text"
                                id="add_name"
                                name="name"
                                placeholder="Mini Jade Succulent"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="add_category">
                                Category
                            </label>


                            <input
                                type="text"
                                id="add_category"
                                name="category"
                                placeholder="Succulent"
                            >

                        </div>


                        <div class="form-group">

                            <label for="add_price">
                                Price
                            </label>


                            <input
                                type="number"
                                id="add_price"
                                name="price"
                                min="0"
                                step="0.01"
                                placeholder="8.00"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="add_stock">
                                Stock Quantity
                            </label>


                            <input
                                type="number"
                                id="add_stock"
                                name="stock"
                                min="0"
                                step="1"
                                placeholder="20"
                                required
                            >

                        </div>


                        <div class="form-group full">

                            <label for="add_description">
                                Description
                            </label>


                            <textarea
                                id="add_description"
                                name="description"
                                placeholder="Describe this plant..."
                            ></textarea>

                        </div>


                        <div class="form-group full">

                            <label for="add_image">
                                Product Image
                            </label>


                            <input
                                type="file"
                                id="add_image"
                                name="image"
                                accept="image/jpeg,image/png,image/webp,image/gif"
                            >


                            <div class="image-help">
                                JPG, PNG, WEBP, or GIF. Maximum 5 MB.
                            </div>


                        </div>


                    </div>


                    <div class="form-actions">


                        <button
                            type="submit"
                            name="add_product"
                            class="primary-button"
                        >
                            + Add Product
                        </button>


                    </div>


                </form>


            <?php endif; ?>


        </section>


        <!-- =========================================
             PRODUCT LIST
        ========================================== -->

        <section class="products-card">


            <div class="products-card-header">


                <h2>
                    Inventory
                </h2>


                <span class="product-count">

                    <?= count($products) ?>

                    active product
                    <?= count($products) === 1
                        ? ""
                        : "s" ?>

                </span>


            </div>


            <?php if (
                count($products) > 0
            ): ?>


                <div class="table-wrapper">


                    <table>


                        <thead>

                            <tr>

                                <th>
                                    IMAGE
                                </th>

                                <th>
                                    PRODUCT
                                </th>

                                <th>
                                    CATEGORY
                                </th>

                                <th>
                                    PRICE
                                </th>

                                <th>
                                    STOCK
                                </th>

                                <th>
                                    ADDED
                                </th>

                                <th>
                                    UPDATED
                                </th>

                                <th>
                                    ACTIONS
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach (
                                $products
                                as $product
                            ): ?>


                                <tr>


                                    <td>


                                        <?php if (
                                            !empty(
                                                $product["image"]
                                            )
                                        ): ?>


                                            <img
                                                src="../<?= htmlspecialchars(
                                                    $product["image"]
                                                ) ?>"
                                                alt="<?= htmlspecialchars(
                                                    $product["name"]
                                                ) ?>"
                                                class="product-image"
                                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                            >


                                            <div
                                                class="no-image"
                                                style="display:none;"
                                            >
                                                🌱
                                            </div>


                                        <?php else: ?>


                                            <div class="no-image">
                                                🌱
                                            </div>


                                        <?php endif; ?>


                                    </td>


                                    <td>


                                        <div class="product-name">

                                            <?= htmlspecialchars(
                                                $product["name"]
                                            ) ?>

                                        </div>


                                        <?php if (
                                            !empty(
                                                $product["description"]
                                            )
                                        ): ?>


                                            <div
                                                class="product-description"
                                            >

                                                <?= htmlspecialchars(
                                                    $product["description"]
                                                ) ?>

                                            </div>


                                        <?php endif; ?>


                                    </td>


                                    <td>

                                        <span class="category">

                                            <?= htmlspecialchars(
                                                $product["category"]
                                                ?: "—"
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <span class="price">

                                            $<?= number_format(
                                                (float)
                                                $product["price"],
                                                2
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>


                                        <?php if (
                                            (int)
                                            $product["stock"] > 0
                                        ): ?>


                                            <span class="stock">

                                                <?= (int)
                                                    $product["stock"] ?>

                                                available

                                            </span>


                                        <?php else: ?>


                                            <span
                                                class="stock empty"
                                            >

                                                Out of stock

                                            </span>


                                        <?php endif; ?>


                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $product["created_at"]
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $product["updated_at"]
                                        ) ?>

                                    </td>


                                    <td>


                                        <div class="actions">


                                            <a
                                                href="products.php?edit=<?= (int)
                                                    $product["id"] ?>"
                                                class="edit-button"
                                            >
                                                Edit
                                            </a>


                                            <!--
                                                IMPORTANT:
                                                No browser confirm().
                                                This opens our own website modal.
                                            -->

                                            <form
                                                method="POST"
                                                action="products.php"
                                                class="delete-form"
                                                data-product-id="<?= (int)
                                                    $product["id"] ?>"
                                                data-product-name="<?= htmlspecialchars(
                                                    $product["name"],
                                                    ENT_QUOTES
                                                ) ?>"
                                            >


                                                <input
                                                    type="hidden"
                                                    name="product_id"
                                                    value="<?= (int)
                                                        $product["id"] ?>"
                                                >


                                                <button
                                                    type="button"
                                                    class="delete-button"
                                                    onclick="openDeleteModal(this)"
                                                >
                                                    Remove
                                                </button>


                                                <input
                                                    type="hidden"
                                                    name="delete_product"
                                                    value="1"
                                                >


                                            </form>


                                        </div>


                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php else: ?>


                <div class="empty-state">


                    <div class="empty-state-icon">
                        🌱
                    </div>


                    <h3>
                        No Products Yet
                    </h3>


                    <p>
                        Add your first product using the
                        form above to start building your inventory.
                    </p>


                </div>


            <?php endif; ?>


        </section>


    </main>


</div>


<!-- =========================================
     DELETE CONFIRMATION MODAL
========================================= -->

<div
    class="modal-overlay"
    id="deleteModal"
>


    <div
        class="delete-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="deleteModalTitle"
    >


        <div class="modal-icon">
            !
        </div>


        <h3 id="deleteModalTitle">
            Remove Product?
        </h3>


        <p>
            Are you sure you want to permanently
            remove this product?
        </p>


        <p
            class="delete-product-name"
            id="deleteProductName"
        >
        </p>


        <p class="modal-warning">
            This action cannot be undone.
        </p>


        <div class="modal-actions">


            <button
                type="button"
                class="modal-cancel"
                onclick="closeDeleteModal()"
            >
                Cancel
            </button>


            <button
                type="button"
                class="modal-delete"
                onclick="confirmDelete()"
            >
                Remove Product
            </button>


        </div>


    </div>


</div>


<script>

    /* =========================================
       DELETE MODAL
    ========================================= */

    let selectedDeleteForm = null;


    function openDeleteModal(button) {

        selectedDeleteForm =
            button.closest(".delete-form");


        if (!selectedDeleteForm) {

            return;
        }


        const productName =
            selectedDeleteForm.getAttribute(
                "data-product-name"
            );


        const productNameElement =
            document.getElementById(
                "deleteProductName"
            );


        productNameElement.textContent =
            productName;


        const modal =
            document.getElementById(
                "deleteModal"
            );


        modal.classList.add("show");


        document.body.style.overflow =
            "hidden";
    }


    function closeDeleteModal() {

        const modal =
            document.getElementById(
                "deleteModal"
            );


        modal.classList.remove("show");


        document.body.style.overflow =
            "";


        selectedDeleteForm =
            null;
    }


    function confirmDelete() {

        if (!selectedDeleteForm) {

            return;
        }


        /*
         * Submit the selected form.
         * This sends the POST request to products.php.
         */

        selectedDeleteForm.submit();
    }


    /* =========================================
       CLOSE MODAL WHEN CLICKING OUTSIDE
    ========================================= */

    document.getElementById(
        "deleteModal"
    ).addEventListener(
        "click",
        function(event) {

            if (
                event.target === this
            ) {

                closeDeleteModal();
            }

        }
    );


    /* =========================================
       ESC KEY CLOSES MODAL
    ========================================= */

    document.addEventListener(
        "keydown",
        function(event) {

            if (
                event.key === "Escape"
            ) {

                closeDeleteModal();
            }

        }
    );

</script>


</body>

</html>