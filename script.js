/* =========================================
   ELORA PLANTS SHOP
   INTERACTIVE JAVASCRIPT
========================================= */


/* =========================================
   MOBILE MENU
========================================= */

const mobileMenuButton =
    document.getElementById("mobileMenuButton");

const navMenu =
    document.querySelector(".nav-menu");


if (mobileMenuButton && navMenu) {

    mobileMenuButton.addEventListener("click", () => {

        navMenu.classList.toggle("active");

    });

}


/* =========================================
   CLOSE MOBILE MENU AFTER CLICKING LINK
========================================= */

const navLinks =
    document.querySelectorAll(".nav-menu a");


navLinks.forEach(link => {

    link.addEventListener("click", () => {

        if (navMenu) {

            navMenu.classList.remove("active");

        }

    });

});


/* =========================================
   SEARCH
========================================= */

const searchButton =
    document.getElementById("searchButton");

const searchBox =
    document.getElementById("searchBox");

const closeSearch =
    document.getElementById("closeSearch");

const searchInput =
    document.getElementById("searchInput");


/* =========================================
   OPEN SEARCH
========================================= */

if (
    searchButton &&
    searchBox
) {

    searchButton.addEventListener("click", () => {

        searchBox.classList.toggle("active");


        if (
            searchBox.classList.contains("active") &&
            searchInput
        ) {

            setTimeout(() => {

                searchInput.focus();

            }, 100);

        }

    });

}


/* =========================================
   CLOSE SEARCH
========================================= */

if (
    closeSearch &&
    searchBox
) {

    closeSearch.addEventListener("click", () => {

        searchBox.classList.remove("active");


        if (searchInput) {

            searchInput.value = "";

            resetProductSearch();

        }

    });

}


/* =========================================
   SEARCH PRODUCTS
========================================= */

if (searchInput) {

    searchInput.addEventListener("input", () => {

        const searchValue =
            searchInput.value
                .toLowerCase()
                .trim();


        const products =
            document.querySelectorAll(".product-card");


        products.forEach(product => {

            const title =
                product.querySelector("h3");


            if (!title) {

                return;

            }


            const productName =
                title.textContent
                    .toLowerCase();


            if (
                productName.includes(searchValue)
            ) {

                product.style.display = "";

            } else {

                product.style.display = "none";

            }

        });

    });

}


/* =========================================
   RESET PRODUCT SEARCH
========================================= */

function resetProductSearch() {

    const products =
        document.querySelectorAll(".product-card");


    products.forEach(product => {

        product.style.display = "";

    });

}


/* =========================================
   CART
========================================= */

/*
    The cart is only created when the user
    is logged in and index.php displays
    the cart panel.
*/

let cart = [];


const cartButton =
    document.getElementById("cartButton");

const cartOverlay =
    document.getElementById("cartOverlay");

const closeCart =
    document.getElementById("closeCart");

const cartItems =
    document.getElementById("cartItems");

const cartCount =
    document.getElementById("cartCount");

const cartTotal =
    document.getElementById("cartTotal");

const checkoutButton =
    document.getElementById("checkoutButton");


/* =========================================
   LOAD CART FROM BROWSER STORAGE
========================================= */

function loadCart() {

    /*
        If there is no cart panel,
        the user is not logged in.
    */

    if (!cartItems) {

        return;

    }


    try {

        const savedCart =
            localStorage.getItem(
                "eloraCart"
            );


        if (savedCart) {

            cart =
                JSON.parse(savedCart);

        }

    } catch (error) {

        console.error(
            "Unable to load cart:",
            error
        );

        cart = [];

    }


    updateCart();

}


/* =========================================
   SAVE CART
========================================= */

function saveCart() {

    if (!cartItems) {

        return;

    }


    try {

        localStorage.setItem(
            "eloraCart",
            JSON.stringify(cart)
        );

    } catch (error) {

        console.error(
            "Unable to save cart:",
            error
        );

    }

}


/* =========================================
   OPEN CART
========================================= */

if (
    cartButton &&
    cartOverlay
) {

    cartButton.addEventListener("click", () => {

        cartOverlay.classList.add("active");

        document.body.classList.add(
            "cart-open"
        );

        updateCart();

    });

}


/* =========================================
   CLOSE CART
========================================= */

if (
    closeCart &&
    cartOverlay
) {

    closeCart.addEventListener("click", () => {

        cartOverlay.classList.remove("active");

        document.body.classList.remove(
            "cart-open"
        );

    });

}


/* =========================================
   CLOSE CART WHEN CLICKING OUTSIDE
========================================= */

if (cartOverlay) {

    cartOverlay.addEventListener(
        "click",
        event => {

            if (
                event.target === cartOverlay
            ) {

                cartOverlay.classList.remove(
                    "active"
                );

                document.body.classList.remove(
                    "cart-open"
                );

            }

        }
    );

}


/* =========================================
   ESCAPE KEY
========================================= */

document.addEventListener(
    "keydown",
    event => {

        if (
            event.key === "Escape"
        ) {


            /* Close search */

            if (
                searchBox &&
                searchBox.classList.contains(
                    "active"
                )
            ) {

                searchBox.classList.remove(
                    "active"
                );


                if (searchInput) {

                    searchInput.value = "";

                    resetProductSearch();

                }

            }


            /* Close cart */

            if (
                cartOverlay &&
                cartOverlay.classList.contains(
                    "active"
                )
            ) {

                cartOverlay.classList.remove(
                    "active"
                );

                document.body.classList.remove(
                    "cart-open"
                );

            }


            /* Close mobile menu */

            if (navMenu) {

                navMenu.classList.remove(
                    "active"
                );

            }

        }

    }
);


/* =========================================
   ADD TO CART BUTTONS
========================================= */

const addButtons =
    document.querySelectorAll(
        ".add-button"
    );


addButtons.forEach(button => {

    /*
        Only real add-to-cart buttons have
        a data-product attribute.

        Logged-out buttons are links to
        login.php and therefore won't be
        treated as cart buttons.
    */

    if (
        !button.dataset.product ||
        !button.dataset.price
    ) {

        return;

    }


    button.addEventListener(
        "click",
        event => {

            event.stopPropagation();


            const product =
                button.dataset.product;


            const price =
                Number(
                    button.dataset.price
                );


            if (
                !product ||
                Number.isNaN(price)
            ) {

                return;

            }


            const existingProduct =
                cart.find(
                    item =>
                        item.product === product
                );


            if (existingProduct) {

                existingProduct.quantity++;

            } else {

                cart.push({

                    product: product,

                    price: price,

                    quantity: 1

                });

            }


            saveCart();

            updateCart();


            /* =================================
               BUTTON FEEDBACK
            ================================= */

            const originalText =
                button.textContent;


            button.textContent = "✓";

            button.style.transform =
                "scale(1.2)";


            setTimeout(() => {

                button.textContent =
                    originalText;

                button.style.transform = "";

            }, 700);


            /* =================================
               OPEN CART AFTER ADDING
            ================================= */

            if (
                cartOverlay &&
                cartButton
            ) {

                cartOverlay.classList.add(
                    "active"
                );

                document.body.classList.add(
                    "cart-open"
                );

            }

        }
    );

});


/* =========================================
   UPDATE CART
========================================= */

function updateCart() {

    /*
        If the user is logged out,
        these elements don't exist.
    */

    if (
        !cartItems ||
        !cartCount ||
        !cartTotal
    ) {

        return;

    }


    cartItems.innerHTML = "";


    /* =================================
       EMPTY CART
    ================================= */

    if (cart.length === 0) {

        cartItems.innerHTML = `

            <p class="empty-cart">
                Your cart is empty.
            </p>

        `;


        cartCount.textContent = "0";

        cartTotal.textContent = "$0.00";

        saveCart();

        return;

    }


    let total = 0;

    let count = 0;


    /* =================================
       CREATE CART ITEMS
    ================================= */

    cart.forEach(
        (item, index) => {

            const itemTotal =
                item.price *
                item.quantity;


            total += itemTotal;

            count += item.quantity;


            const cartItem =
                document.createElement(
                    "div"
                );


            cartItem.className =
                "cart-item";


            cartItem.innerHTML = `

                <div class="cart-item-info">

                    <strong>
                        ${escapeHTML(
                            item.product
                        )}
                    </strong>

                    <span>
                        $${item.price.toFixed(2)}
                        × ${item.quantity}
                    </span>

                </div>


                <button
                    type="button"
                    class="remove-item"
                    data-index="${index}"
                >
                    Remove
                </button>

            `;


            cartItems.appendChild(
                cartItem
            );

        }
    );


    /* =================================
       UPDATE TOTALS
    ================================= */

    cartCount.textContent =
        count;


    cartTotal.textContent =
        "$" + total.toFixed(2);


    /* =================================
       REMOVE ITEMS
    ================================= */

    const removeButtons =
        document.querySelectorAll(
            ".remove-item"
        );


    removeButtons.forEach(
        button => {

            button.addEventListener(
                "click",
                event => {

                    event.stopPropagation();


                    const index =
                        Number(
                            button.dataset.index
                        );


                    if (
                        Number.isNaN(index)
                    ) {

                        return;

                    }


                    cart.splice(
                        index,
                        1
                    );


                    saveCart();

                    updateCart();

                }
            );

        }
    );

}


/* =========================================
   ESCAPE HTML
========================================= */

function escapeHTML(value) {

    const div =
        document.createElement(
            "div"
        );


    div.textContent =
        value;


    return div.innerHTML;

}


/* =========================================
   CHECKOUT
========================================= */

if (checkoutButton) {

    checkoutButton.addEventListener(
        "click",
        () => {

            if (cart.length === 0) {

                alert(
                    "Your cart is empty."
                );

                return;

            }


            /*
                For now checkout is not connected
                to the database.

                We will replace this later with
                checkout.php.
            */

            alert(
                "Checkout will be available soon!"
            );

        }
    );

}


/* =========================================
   PRODUCT CARD INTERACTION
========================================= */

const productCards =
    document.querySelectorAll(
        ".product-card"
    );


productCards.forEach(card => {

    card.addEventListener(
        "click",
        event => {

            /*
                Do not trigger the product
                interaction when clicking
                the add-to-cart button.
            */

            if (
                event.target.closest(
                    ".add-button"
                )
            ) {

                return;

            }


            const productTitle =
                card.querySelector(
                    "h3"
                );


            if (!productTitle) {

                return;

            }


            const productName =
                productTitle.textContent
                    .trim();


            /*
                Temporary behavior.

                Later this can become:
                product.php?id=...
            */

            alert(
                productName +
                "\n\nProduct details coming soon!"
            );

        }
    );

});


/* =========================================
   SCROLL REVEAL
========================================= */

const revealElements =
    document.querySelectorAll(
        `
        .story-content,
        .product-card,
        .feature-card,
        .care-content,
        .testimonial-card
        `
    );


/*
    Check if IntersectionObserver exists.
*/

if (
    "IntersectionObserver" in window
) {

    const revealObserver =
        new IntersectionObserver(

            entries => {

                entries.forEach(
                    entry => {

                        if (
                            entry.isIntersecting
                        ) {

                            entry.target.style.opacity =
                                "1";


                            entry.target.style.transform =
                                "translateY(0)";


                            revealObserver.unobserve(
                                entry.target
                            );

                        }

                    }
                );

            },

            {
                threshold: 0.12
            }

        );


    revealElements.forEach(
        element => {

            element.style.opacity =
                "0";


            element.style.transform =
                "translateY(25px)";


            element.style.transition =
                "opacity 0.7s ease, transform 0.7s ease";


            revealObserver.observe(
                element
            );

        }
    );

} else {

    /*
        Fallback for browsers without
        IntersectionObserver.
    */

    revealElements.forEach(
        element => {

            element.style.opacity = "1";

            element.style.transform =
                "translateY(0)";

        }
    );

}


/* =========================================
   INITIALIZE
========================================= */

loadCart();


/* =========================================
   PAGE READY
========================================= */

document.addEventListener(
    "DOMContentLoaded",
    () => {

        /*
            Make sure the cart starts
            with the correct values.
        */

        updateCart();

    }
);