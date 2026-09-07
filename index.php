<?php

session_start();

require_once "db.php";


/* =========================================
   USER LOGIN STATUS
========================================= */

$isLoggedIn = isset($_SESSION["user_id"]);


/* =========================================
   GET HOMEPAGE PRODUCTS
========================================= */

$homepage_products = [];

$product_sql = "
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
    ORDER BY created_at DESC
    LIMIT 3
";

$product_result = $conn->query($product_sql);

if ($product_result) {

    while ($product = $product_result->fetch_assoc()) {

        $homepage_products[] = $product;

    }

}


/* =========================================
   CART COUNT
========================================= */

$cart_count = 0;

if ($isLoggedIn) {

    $user_id = (int) $_SESSION["user_id"];

    $cart_sql = "
        SELECT COALESCE(SUM(quantity), 0) AS total_items
        FROM cart_items
        WHERE user_id = ?
    ";

    $cart_stmt = $conn->prepare($cart_sql);

    if ($cart_stmt) {

        $cart_stmt->bind_param(
            "i",
            $user_id
        );

        $cart_stmt->execute();

        $cart_result = $cart_stmt->get_result();

        $cart_row = $cart_result->fetch_assoc();

        if ($cart_row) {

            $cart_count = (int) $cart_row["total_items"];

        }

        $cart_stmt->close();

    }

}


/* =========================================
   IMAGE HELPER
========================================= */

function homepage_image($image)
{

    if (
        !empty($image) &&
        file_exists($image)
    ) {

        return $image;

    }

    return "image/collection1.png";
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
        Elora Plants Shop
    </title>

    <!-- CSS -->

    <link
        rel="stylesheet"
        href="./style.css?v=11"
    >

</head>

<body>


<!-- =========================================
     NAVIGATION
========================================= -->

<header class="navbar">

    <div class="nav-container">


        <!-- LOGO -->

        <a
            href="#home"
            class="logo-link"
        >

            <img
                src="./image/logo.png"
                alt="Elora Plants"
                class="logo"
            >

        </a>


        <!-- NAVIGATION LINKS -->

        <nav
            class="nav-menu"
            id="navMenu"
        >

            <!-- FIXED: SHOP NOW OPENS shop.php -->

            <a href="shop.php">
                Shop
            </a>

            <a href="#story">
                About
            </a>

            <a href="#care">
                Care Guide
            </a>

            <a href="#contact">
                Contact
            </a>

        </nav>


        <!-- NAVIGATION ACTIONS -->

        <div class="nav-actions">


            <?php if ($isLoggedIn): ?>


                <!-- =================================
                     SEARCH
                ================================= -->

                <a
                    href="shop.php"
                    class="icon-button"
                    aria-label="Search"
                    title="Search"
                >
                    ⌕
                </a>


                <!-- =================================
                     CART
                ================================= -->

                <a
                    href="cart.php"
                    class="icon-button cart-button"
                    aria-label="Shopping Cart"
                    title="Shopping Cart"
                >

                    🛒

                    <?php if ($cart_count > 0): ?>

                        <span
                            class="cart-count"
                            id="cartCount"
                        >
                            <?php echo $cart_count; ?>
                        </span>

                    <?php endif; ?>

                </a>


                <!-- =================================
                     MY ORDERS
                ================================= -->

                <a
                    href="orders.php"
                    class="account-button"
                    title="My Orders"
                >

                    <span class="account-icon">
                        ◷
                    </span>

                    <span class="account-text">
                        Orders
                    </span>

                </a>


                <!-- =================================
                     PROFILE
                ================================= -->

                <a
                    href="profile.php"
                    class="account-button"
                    title="My Profile"
                >

                    <span class="account-icon">
                        ♙
                    </span>

                    <span class="account-text">
                        Profile
                    </span>

                </a>


                <!-- =================================
                     LOG OUT
                ================================= -->

                <a
                    href="logout.php"
                    class="account-button"
                    title="Log Out"
                >

                    <span class="account-icon">
                        ↪
                    </span>

                    <span class="account-text">
                        Log Out
                    </span>

                </a>


            <?php else: ?>


                <!-- =================================
                     LOG IN
                ================================= -->

                <a
                    href="login.php"
                    class="account-button"
                    title="Log In"
                >

                    <span class="account-icon">
                        ♙
                    </span>

                    <span class="account-text">
                        Log In
                    </span>

                </a>


                <!-- =================================
                     SIGN UP
                ================================= -->

                <a
                    href="signup.php"
                    class="account-button"
                    title="Sign Up"
                >

                    <span class="account-icon">
                        ✦
                    </span>

                    <span class="account-text">
                        Sign Up
                    </span>

                </a>


            <?php endif; ?>


            <!-- =================================
                 MOBILE MENU
            ================================= -->

            <button
                type="button"
                class="mobile-menu-button"
                id="mobileMenuButton"
                aria-label="Menu"
            >
                ☰
            </button>

        </div>

    </div>

</header>



<main>


<!-- =========================================
     HERO
========================================= -->

<section
    class="hero"
    id="home"
>

    <div class="hero-container">


        <!-- HERO TEXT -->

        <div class="hero-content">

            <span class="eyebrow">
                WHERE LITTLE THINGS GROW
            </span>


            <h1>
                Bring Nature to the
                <br>
                Smallest Corners
            </h1>


            <p>
                Thoughtfully curated mini plants, perfect for desks,
                bedrooms, and tiny windowsills. Affordable, cute,
                and delightfully easy to keep alive.
            </p>


            <div class="hero-buttons">


                <?php if ($isLoggedIn): ?>

                    <a
                        href="shop.php"
                        class="primary-button"
                    >

                        Shop Now

                        <span>
                            →
                        </span>

                    </a>

                <?php else: ?>

                    <a
                        href="login.php"
                        class="primary-button"
                    >

                        Sign In to Shop

                        <span>
                            →
                        </span>

                    </a>

                <?php endif; ?>


                <a
                    href="#care"
                    class="secondary-button"
                >

                    Explore Care Guides

                </a>

            </div>

        </div>


        <!-- HERO IMAGE -->

        <div class="hero-image-container">

            <img
                src="./image/home.png"
                alt="Plants on a desk"
                class="hero-image"
            >


            <!-- FLOATING PRODUCT -->

            <div class="floating-product">

                <div class="floating-icon">
                    🌱
                </div>


                <div class="floating-product-text">

                    <strong>
                        Mini Plants
                    </strong>

                    <small>
                        Starting at ₱8.00
                    </small>

                </div>

            </div>

        </div>

    </div>

</section>



<!-- =========================================
     STORY
========================================= -->

<section
    class="story-section"
    id="story"
>

    <div class="story-container">


        <!-- IMAGE -->

        <div class="story-image">

            <img
                src="./image/story.png"
                alt="Caring for small plants"
            >

        </div>


        <!-- CONTENT -->

        <div class="story-content">

            <span class="section-label">
                OUR STORY
            </span>


            <h2>
                Little Companions for Busy
                Lifestyles
            </h2>


            <p>
                At Elora, we believe that plant parenthood should be
                simple, joyful, and completely accessible. We specialize
                in mini plant varieties that fit comfortably on small
                desks, cozy dorm rooms, and compact apartments.
            </p>


            <p>
                Whether you are a busy student, a young adult setting
                up your first home, or a complete beginner who has
                "never successfully kept a plant alive," we support
                you with hardy mini green buddies and detailed Care
                Guides to make every step painless.
            </p>


            <!-- STATISTICS -->

            <div class="statistics">


                <div class="stat">

                    <strong>
                        100%
                    </strong>

                    <span>
                        BEGINNER FRIENDLY
                    </span>

                </div>


                <div class="stat">

                    <strong>
                        15k+
                    </strong>

                    <span>
                        HAPPY PLANT PARENTS
                    </span>

                </div>


                <div class="stat">

                    <strong>
                        Under ₱15
                    </strong>

                    <span>
                        AVERAGE PLANT PRICE
                    </span>

                </div>


            </div>

        </div>

    </div>

</section>



<!-- =========================================
     COLLECTION
========================================= -->

<section
    class="collection-section"
    id="shop"
>

    <div class="collection-container">


        <div class="section-heading">


            <div>

                <span class="section-label">
                    THE COLLECTION
                </span>

                <h2>
                    Meet Our Little Favorites
                </h2>

            </div>


            <?php if ($isLoggedIn): ?>

                <a
                    href="shop.php"
                    class="view-shop"
                >

                    View Entire Shop

                    <span>
                        →
                    </span>

                </a>

            <?php else: ?>

                <a
                    href="login.php"
                    class="view-shop"
                >

                    Sign In to View Shop

                    <span>
                        →
                    </span>

                </a>

            <?php endif; ?>

        </div>



        <!-- =====================================
             DATABASE PRODUCTS
        ====================================== -->

        <?php if (!empty($homepage_products)): ?>

            <div class="products-grid">


                <?php foreach ($homepage_products as $product): ?>

                    <?php

                    $product_id = (int) $product["id"];

                    $product_name = $product["name"];

                    $product_price = (float) $product["price"];

                    $product_stock = (int) $product["stock"];

                    $product_image = homepage_image(
                        $product["image"]
                    );

                    $product_category = !empty(
                        $product["category"]
                    )
                        ? $product["category"]
                        : "PLANT";

                    ?>


                    <article
                        class="product-card"
                        data-name="<?php echo htmlspecialchars(
                            $product_name
                        ); ?>"
                    >


                        <!-- PRODUCT IMAGE -->

                        <div class="product-image-container">

                            <img
                                src="<?php echo htmlspecialchars(
                                    $product_image
                                ); ?>"
                                alt="<?php echo htmlspecialchars(
                                    $product_name
                                ); ?>"
                                class="product-image"
                            >

                        </div>


                        <!-- PRODUCT INFO -->

                        <div class="product-info">


                            <div class="product-meta">

                                <span class="product-tag">

                                    <?php
                                    echo htmlspecialchars(
                                        strtoupper(
                                            $product_category
                                        )
                                    );
                                    ?>

                                </span>


                                <strong>

                                    ₱<?php echo number_format(
                                        $product_price,
                                        2
                                    ); ?>

                                </strong>

                            </div>


                            <div class="product-bottom">


                                <div>

                                    <h3>
                                        <?php
                                        echo htmlspecialchars(
                                            $product_name
                                        );
                                        ?>
                                    </h3>


                                    <?php if ($product_stock <= 0): ?>

                                        <small
                                            style="
                                                display:block;
                                                margin-top:5px;
                                                color:#a94442;
                                                font-size:12px;
                                            "
                                        >
                                            Out of Stock
                                        </small>

                                    <?php elseif ($product_stock <= 5): ?>

                                        <small
                                            style="
                                                display:block;
                                                margin-top:5px;
                                                color:#946b00;
                                                font-size:12px;
                                            "
                                        >
                                            Only
                                            <?php echo $product_stock; ?>
                                            left
                                        </small>

                                    <?php endif; ?>

                                </div>


                                <?php if ($isLoggedIn): ?>


                                    <?php if ($product_stock > 0): ?>

                                        <form
                                            action="shop.php"
                                            method="POST"
                                            style="margin:0;"
                                        >

                                            <input
                                                type="hidden"
                                                name="add_to_cart"
                                                value="1"
                                            >

                                            <input
                                                type="hidden"
                                                name="product_id"
                                                value="<?php echo $product_id; ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="quantity"
                                                value="1"
                                            >


                                            <button
                                                type="submit"
                                                class="add-button"
                                                aria-label="Add product to cart"
                                                title="Add to cart"
                                            >
                                                +
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <button
                                            type="button"
                                            class="add-button"
                                            disabled
                                            title="Out of stock"
                                            style="opacity:0.45; cursor:not-allowed;"
                                        >
                                            ×
                                        </button>

                                    <?php endif; ?>


                                <?php else: ?>

                                    <a
                                        href="login.php"
                                        class="add-button sign-in-add-button"
                                        aria-label="Log in to add product"
                                        title="Log in to add to cart"
                                    >
                                        ♙
                                    </a>

                                <?php endif; ?>


                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <!-- =================================
                 NO PRODUCTS
            ================================= -->

            <div
                style="
                    padding:50px 20px;
                    text-align:center;
                    background:#ffffff;
                    border-radius:18px;
                    border:1px solid #e4e8e1;
                "
            >

                <h3
                    style="
                        margin-bottom:10px;
                        color:#173f2a;
                        font-family:Georgia, 'Times New Roman', serif;
                    "
                >
                    Our plants are getting ready 🌱
                </h3>


                <p
                    style="
                        margin-bottom:20px;
                        color:#777f79;
                    "
                >
                    Check back soon for our latest collection.
                </p>


                <?php if ($isLoggedIn): ?>

                    <a
                        href="shop.php"
                        class="primary-button"
                    >
                        Visit Shop
                    </a>

                <?php else: ?>

                    <a
                        href="login.php"
                        class="primary-button"
                    >
                        Sign In
                    </a>

                <?php endif; ?>

            </div>


        <?php endif; ?>


    </div>

</section>



<!-- =========================================
     DIFFERENCE
========================================= -->

<section class="difference-section">

    <div class="difference-container">

        <span class="section-label">
            THE ELORA DIFFERENCE
        </span>


        <h2>
            Designed for Modern Plant
            <br>
            Parents
        </h2>


        <div class="features-grid">


            <div class="feature-card">

                <div class="feature-icon">
                    ♢
                </div>

                <h3>
                    Affordable Prices
                </h3>

                <p>
                    Lively mini plants starting from just ₱8.
                    Healthy greens shouldn't break your student budget.
                </p>

            </div>


            <div class="feature-card">

                <div class="feature-icon">
                    ✣
                </div>

                <h3>
                    Hardy, Easy-Care Plants
                </h3>

                <p>
                    Carefully selected varieties that are incredibly
                    resilient. Perfect for plant care beginners.
                </p>

            </div>


            <div class="feature-card">

                <div class="feature-icon">
                    ⌂
                </div>

                <h3>
                    Perfect for Small Spaces
                </h3>

                <p>
                    Tailored to thrive on minimal footprints:
                    study desks, bedside tables, or compact shelves.
                </p>

            </div>


        </div>

    </div>

</section>



<!-- =========================================
     CARE GUIDE
========================================= -->

<section
    class="care-section"
    id="care"
>

    <div class="care-container">


        <div class="care-content">

            <span class="section-label">
                THRIVE GUARANTEE
            </span>


            <h2>
                Every Single Plant Comes with a
                Digital Care Guide
            </h2>


            <p>
                Never guess how much water, light, or love your plant
                needs again. We generate specialized, quick-reading
                care cards designed for absolute beginners so you can
                feel completely confident.
            </p>


            <ul class="care-list">

                <li>

                    <span>
                        ✓
                    </span>

                    Watering schedules tuned to your room's temperature

                </li>


                <li>

                    <span>
                        ✓
                    </span>

                    Lighting spot maps from sunny desk to shady corner

                </li>


                <li>

                    <span>
                        ✓
                    </span>

                    Direct care help online for diagnosing yellowing leaves

                </li>

            </ul>


            <a
                href="care-guide.php"
                class="primary-button"
            >

                Explore Care Hub

                <span>
                    →
                </span>

            </a>

        </div>



        <div class="care-image">

            <img
                src="./image/thrive-guarantee.png"
                alt="Plant care guide"
            >

        </div>


    </div>

</section>



<!-- =========================================
     TESTIMONIALS
========================================= -->

<section class="testimonials-section">

    <div class="testimonials-container">

        <span class="section-label">
            HAPPY PLANT PARENTS
        </span>


        <h2>
            Little Stories of Big Growth
        </h2>


        <div class="testimonial-grid">


            <div class="testimonial-card">

                <p>
                    “As a college student with zero plant experience,
                    I was terrified of killing my Pilea. But Elora's
                    care guides are so clear and simple! My little
                    plant is thriving on my small study desk.”
                </p>


                <div class="customer">

                    <strong>
                        Maya Lin
                    </strong>

                    <span>
                        Student
                    </span>

                </div>

            </div>



            <div class="testimonial-card">

                <p>
                    “The shipping was quick, packaging was entirely
                    eco-friendly, and the mini jade plant looks
                    absolutely adorable next to my monitor.
                    I'm already planning my next order!”
                </p>


                <div class="customer">

                    <strong>
                        Julian Vance
                    </strong>

                    <span>
                        Plant Parent
                    </span>

                </div>

            </div>


        </div>

    </div>

</section>


</main>



<!-- =========================================
     FOOTER
========================================= -->

<footer
    class="footer"
    id="contact"
>

    <div class="footer-container">


        <div class="footer-brand">

            <img
                src="./image/logo.png"
                alt="Elora"
                class="footer-logo"
            >

            <p>
                Where Little Things Grow. Beautiful,
                easy-to-care-for mini plants for tiny desks,
                small apartments, and fresh workspaces.
            </p>

        </div>


        <div class="footer-column">

            <h4>
                SHOP
            </h4>

            <a href="#shop">
                Plants
            </a>

            <a href="shop.php">
                View All Plants
            </a>

            <a href="care-guide.php">
                Care Guides
            </a>

            <a href="cart.php">
                Shopping Cart
            </a>

        </div>


        <div class="footer-column">

            <h4>
                EXPLORE
            </h4>

            <a href="#story">
                Our Story
            </a>

            <a href="care-guide.php">
                Care Hub
            </a>

            <a href="#care">
                Plant Care
            </a>

            <?php if ($isLoggedIn): ?>

                <a href="orders.php">
                    My Orders
                </a>

            <?php endif; ?>

        </div>


        <div class="footer-column">

            <h4>
                CONTACT
            </h4>

            <a href="mailto:hello@eloraplants.com">
                hello@eloraplants.com
            </a>

            <p>
                Elora Plants Shop
            </p>

        </div>


    </div>


    <div class="footer-bottom">

        <span>
            © 2026 Elora Plants Shop. All rights reserved.
        </span>


        <div class="social-links">

            <a href="#">
                ◎
            </a>

            <a href="#">
                𝕏
            </a>

            <a href="#">
                f
            </a>

        </div>

    </div>

</footer>



<!-- =========================================
     JAVASCRIPT
========================================= -->

<script src="./script.js?v=6"></script>

</body>

</html>