<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Get products and categories from database
$products = getProducts();
$categories = getCategories();

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle add to cart requests
if (isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    $product = getProduct($product_id);
    
    if ($product) {
        // Check if product already in cart
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] == $product_id) {
                $item['quantity'] += 1;
                $found = true;
                break;
            }
        }
        
        // If not found, add to cart
        if (!$found) {
            $_SESSION['cart'][] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => 1
            ];
        }
        
        // Update cart count
        $cart_count = 0;
        foreach ($_SESSION['cart'] as $item) {
            $cart_count += $item['quantity'];
        }
        
        echo json_encode(['success' => true, 'cart_count' => $cart_count]);
        exit;
    }
}

// Handle search requests
if (isset($_GET['search'])) {
    $search_term = '%' . $_GET['search'] . '%';
    $sql = "SELECT * FROM products WHERE name LIKE :search OR description LIKE :search";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':search', $search_term);
    $stmt->execute();
    $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For AJAX requests, return JSON
    if (isset($_GET['ajax'])) {
        echo json_encode($search_results);
        exit;
    }
}

// Handle login requests
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $result = loginUser($email, $password);
    
    if ($result === true) {
        echo json_encode(['success' => true, 'message' => 'Login successful']);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => $result]);
        exit;
    }
}

// Handle registration requests
if (isset($_POST['register'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    
    $result = registerUser($email, $password, $full_name, $phone, $address);
    
    if ($result === true) {
        echo json_encode(['success' => true, 'message' => 'Registration successful']);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => $result]);
        exit;
    }
}

// Handle checkout requests
if (isset($_POST['checkout'])) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please login first']);
        exit;
    }
    
    if (empty($_SESSION['cart'])) {
        echo json_encode(['success' => false, 'message' => 'Your cart is empty']);
        exit;
    }
    
    $result = createOrder($_SESSION['user_id'], $_SESSION['cart']);
    
    if (is_numeric($result)) {
        // Clear cart
        $_SESSION['cart'] = [];
        echo json_encode(['success' => true, 'message' => 'Order placed successfully', 'order_id' => $result]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => $result]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sabaf-Smart - Online Shopping</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), 
                        url('55.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            line-height: 1.6;
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Header Styles */
        header {
            background-color: rgba(0, 0, 0, 0.8);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #ff6b6b;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
        }
        
        .search-bar {
            flex: 1;
            max-width: 500px;
            margin: 0 20px;
            position: relative;
        }
        
        .search-bar input {
            width: 100%;
            padding: 12px 20px;
            border: none;
            border-radius: 30px;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.9);
        }
        
        .search-bar button {
            position: absolute;
            right: 5px;
            top: 5px;
            background: #ff6b6b;
            border: none;
            border-radius: 30px;
            padding: 7px 15px;
            color: white;
            cursor: pointer;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
        }
        
        .nav-links li {
            margin-left: 20px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: #fff;
            font-weight: 500;
            display: flex;
            align-items: center;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: #ff6b6b;
        }
        
        .nav-links i {
            margin-right: 5px;
        }
        
        .cart-count {
            background-color: #ff6b6b;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            margin-left: 5px;
        }
        
        /* Hero Section */
        .hero {
            text-align: center;
            padding: 100px 0;
            margin-bottom: 40px;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.5);
        }
        
        .hero p {
            font-size: 1.3rem;
            max-width: 700px;
            margin: 0 auto 30px;
            color: #e0e0e0;
        }
        
        .btn {
            display: inline-block;
            background-color: #ff6b6b;
            color: white;
            padding: 15px 30px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn:hover {
            background-color: #ff5252;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
        }
        
        /* Products Section */
        .section-title {
            text-align: center;
            margin-bottom: 40px;
            font-size: 2.2rem;
            color: #ff6b6b;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        
        .product-card {
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
            color: #333;
        }
        
        .product-card:hover {
            transform: translateY(-10px);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-bottom: 2px solid #ff6b6b;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-title {
            font-size: 18px;
            margin-bottom: 10px;
            color: #333;
        }
        
        .product-price {
            font-size: 20px;
            font-weight: bold;
            color: #ff6b6b;
            margin-bottom: 15px;
        }
        
        .add-to-cart {
            background-color: #4a69bd;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            transition: background-color 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .add-to-cart i {
            margin-right: 8px;
        }
        
        .add-to-cart:hover {
            background-color: #3c58a8;
        }
        
        /* Categories Section */
        .categories {
            display: flex;
            justify-content: space-between;
            margin-bottom: 50px;
            flex-wrap: wrap;
        }
        
        .category {
            flex: 0 0 23%;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
            text-align: center;
            transition: transform 0.3s;
            cursor: pointer;
        }
        
        .category:hover {
            transform: scale(1.05);
        }
        
        .category img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        
        .category h3 {
            padding: 15px;
            font-size: 18px;
            color: #333;
        }
        
        /* Cart Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            overflow: auto;
        }
        
        .modal-content {
            background-color: rgba(255, 255, 255, 0.95);
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 800px;
            color: #333;
            position: relative;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            right: 20px;
            top: 15px;
        }
        
        .close:hover {
            color: #ff6b6b;
        }
        
        .cart-items {
            margin-top: 20px;
        }
        
        .cart-item {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #ddd;
        }
        
        .cart-total {
            font-size: 24px;
            font-weight: bold;
            text-align: right;
            margin-top: 20px;
            color: #ff6b6b;
        }
        
        .checkout-btn {
            margin-top: 20px;
            padding: 12px 24px;
            background-color: #ff6b6b;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        
        .checkout-btn:hover {
            background-color: #ff5252;
        }
        
        /* Login/Register Modal */
        .auth-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            overflow: auto;
        }

        .auth-content {
            background-color: rgba(255, 255, 255, 0.95);
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            color: #333;
            position: relative;
        }
        
        .auth-tabs {
            display: flex;
            margin-bottom: 20px;
        }
        
        .auth-tab {
            flex: 1;
            text-align: center;
            padding: 10px;
            cursor: pointer;
            border-bottom: 2px solid #ddd;
        }
        
        .auth-tab.active {
            border-bottom: 2px solid #ff6b6b;
            color: #ff6b6b;
            font-weight: bold;
        }
        
        .auth-form {
            display: none;
        }
        
        .auth-form.active {
            display: block;
        }
        
        .auth-form input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .auth-form button {
            width: 100%;
            padding: 12px;
            background-color: #ff6b6b;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        
        .auth-form button:hover {
            background-color: #ff5252;
        }
        
        .auth-message {
            margin-top: 15px;
            padding: 10px;
            border-radius: 5px;
            display: none;
        }
        
        .auth-message.success {
            background-color: #d4edda;
            color: #155724;
            display: block;
        }
        
        .auth-message.error {
            background-color: #f8d7da;
            color: #721c24;
            display: block;
        }
        
        /* Search Results */
        .search-results {
            margin-top: 20px;
            display: none;
        }
        
        .search-results.active {
            display: block;
        }
        
        /* Footer */
        footer {
            background-color: rgba(0, 0, 0, 0.9);
            padding: 50px 0 20px;
            margin-top: 50px;
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        
        .footer-section {
            flex: 0 0 30%;
            margin-bottom: 20px;
        }
        
        .footer-section h3 {
            margin-bottom: 20px;
            font-size: 18px;
            color: #ff6b6b;
        }
        
        .footer-section p, .footer-section li {
            color: #b0b0b0;
        }
        
        .footer-section ul {
            list-style: none;
        }
        
        .footer-section ul li {
            margin-bottom: 10px;
        }
        
        .footer-section a {
            color: #b0b0b0;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-section a:hover {
            color: #ff6b6b;
        }
        
        .social-icons {
            display: flex;
            margin-top: 15px;
        }
        
        .social-icons a {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #333;
            color: white;
            margin-right: 10px;
            transition: background-color 0.3s;
        }
        
        .social-icons a:hover {
            background-color: #ff6b6b;
        }
        
        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #444;
            color: #b0b0b0;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
            }
            
            .search-bar {
                margin: 15px 0;
                max-width: 100%;
            }
            
            .category {
                flex: 0 0 48%;
            }
            
            .footer-section {
                flex: 0 0 100%;
            }
            
            .hero h1 {
                font-size: 2.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .category {
                flex: 0 0 100%;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .nav-links li {
                margin: 5px 10px;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .hero p {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <header>
        <div class="container">
            <div class="header-content">
                <a href="#" class="logo"><i class="fas fa-shopping-bag"></i>Sabaf-Smart</a>
                <div class="search-bar">
                    <input type="text" id="search-input" placeholder="Search for products...">
                    <button id="search-button"><i class="fas fa-search"></i></button>
                </div>
                <ul class="nav-links">
                    <li><a href="#"><i class="fas fa-home"></i>Home</a></li>
                    <li><a href="#products"><i class="fas fa-th-large"></i>Products</a></li>
                    <li><a href="#categories"><i class="fas fa-list"></i>Categories</a></li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li><a href="#" id="logout-button"><i class="fas fa-user"></i>Logout (<?php echo $_SESSION['user_name']; ?>)</a></li>
                    <?php else: ?>
                        <li><a href="#" id="login-button"><i class="fas fa-user"></i>Login</a></li>
                    <?php endif; ?>
                    <li>
                        <a href="#" id="cart-button">
                            <i class="fas fa-shopping-cart"></i>Cart <span class="cart-count"><?php 
                                $cart_count = 0;
                                foreach ($_SESSION['cart'] as $item) {
                                    $cart_count += $item['quantity'];
                                }
                                echo $cart_count;
                            ?></span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Summer Collection 2025</h1>
            <p>Discover the latest trends in fashion and get up to 50% off on all items. Free shipping on orders over 1000 ETB.
             FOR Ethiopina New Year.</p>
            <a href="#products" class="btn">Shop Now</a>
        </div>
    </section>

    <!-- Search Results -->
    <section class="container search-results" id="search-results">
        <h2 class="section-title">Search Results</h2>
        <div class="products-grid" id="search-results-container">
            <!-- Search results will be displayed here -->
        </div>
    </section>

    <!-- Featured Products -->
     <section class="container" id="products">
        <h2 class="section-title">Featured Products</h2>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card">
                <img src="<?php echo $product['image_path']; ?>" alt="<?php echo $product['name']; ?>" class="product-image">
                <div class="product-info">
                    <h3 class="product-title"><?php echo $product['name']; ?></h3>
                    <p class="product-price">ETB 
                        <?php if($product['original_price']): ?>
                            <del><?php echo number_format($product['original_price'], 2); ?></del>
                        <?php endif; ?>
                        &nbsp;<?php echo number_format($product['price'], 2); ?>
                    </p>
                    <button class="add-to-cart" data-id="<?php echo $product['id']; ?>" data-name="<?php echo $product['name']; ?>" data-price="<?php echo $product['price']; ?>">
                        <i class="fas fa-cart-plus"></i>Add to Cart
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="container" id="categories">
        <h2 class="section-title">Shop by Category</h2>
        <div class="categories">
            <?php foreach ($categories as $category): ?>
            <div class="category">
                <img src="<?php echo $category['image_path']; ?>" alt="<?php echo $category['name']; ?>">
                <h3><?php echo $category['name']; ?></h3>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <!-- Special Offer -->
    <section class="hero" style="background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1770&q=80') no-repeat center center; background-size: cover;">
        <div class="container">
            <h1>Special Offer - Limited Time!</h1>
            <p>Get 30% off on all you want. Use code: Ethiopian New YeaR 20018 at checkout.</p>
            <a href="#products" class="btn">Shop All You Need</a>
        </div>
    </section>

    <!-- Cart Modal -->
    <div id="cart-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Your Shopping Cart</h2>
            <div class="cart-items" id="cart-items">
                <!-- Cart items will be displayed here -->
                <p id="empty-cart-message">Your cart is empty.</p>
            </div>
            <div class="cart-total" id="cart-total">
                Total: ETB0.00
            </div>
            <button class="checkout-btn" id="checkout-button">Proceed to Checkout</button>
        </div>
    </div>

    <!-- Auth Modal -->
    <div id="auth-modal" class="auth-modal">
        <div class="auth-content">
            <span class="close">&times;</span>
            <div class="auth-tabs">
                <div class="auth-tab active" data-tab="login">Login</div>
                <div class="auth-tab" data-tab="register">Register</div>
            </div>
            
            <div id="login-form" class="auth-form active">
                <input type="email" id="login-email" placeholder="Email">
                <input type="password" id="login-password" placeholder="Password">
                <button id="login-submit">Login</button>
                <div id="login-message" class="auth-message"></div>
            </div>
            
            <div id="register-form" class="auth-form">
                <input type="text" id="register-name" placeholder="Full Name">
                <input type="email" id="register-email" placeholder="Email">
                <input type="password" id="register-password" placeholder="Password">
                <input type="text" id="register-phone" placeholder="Phone">
                <input type="text" id="register-address" placeholder="Address">
                <button id="register-submit">Register</button>
                <div id="register-message" class="auth-message"></div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>About Sabaf-Smart</h3>
                    <p>Sabaf-Smart is your one-stop destination for all your fashion needs. We offer high-quality products at affordable prices.</p>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-pinterest"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="#">Home</a></li>
                        <li><a href="#products">Products</a></li>
                        <li><a href="#categories">Categories</a></li>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contact Us</h3>
                    <p><i class="fas fa-envelope"></i> Email:sabaf-smart@gmail.com</p>
                    <p><i class="fas fa-phone"></i> Phone: +2519 76558943</p>
                    <p><i class="fas fa-map-marker-alt"></i> Address: Around Harambe University, Bale Robe City</p>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2025 Sabaf-Smart. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Cart functionality
        let cart = <?php echo json_encode($_SESSION['cart']); ?>;
        let cartCount = <?php echo $cart_count; ?>;
        
        // Get modal elements
        const modal = document.getElementById("cart-modal");
        const authModal = document.getElementById("auth-modal");
        const cartButton = document.getElementById("cart-button");
        const loginButton = document.getElementById("login-button");
        const logoutButton = document.getElementById("logout-button");
        const closeButtons = document.querySelectorAll(".close");
        const cartItemsContainer = document.getElementById("cart-items");
        const cartTotalElement = document.getElementById("cart-total");
        const emptyCartMessage = document.getElementById("empty-cart-message");
        const cartCountElement = document.querySelector(".cart-count");
        const checkoutButton = document.getElementById("checkout-button");
        const searchInput = document.getElementById("search-input");
        const searchButton = document.getElementById("search-button");
        const searchResults = document.getElementById("search-results");
        const searchResultsContainer = document.getElementById("search-results-container");
        
        // Update cart count display
        function updateCartCount() {
            cartCount = 0;
            cart.forEach(item => {
                cartCount += item.quantity;
            });
            cartCountElement.textContent = cartCount;
        }
        
        // Open modal when cart button is clicked
        cartButton.addEventListener("click", function(e) {
            e.preventDefault();
            modal.style.display = "block";
            updateCartDisplay();
        });
        
        // Open auth modal when login button is clicked
        if (loginButton) {
            loginButton.addEventListener("click", function(e) {
                e.preventDefault();
                authModal.style.display = "block";
            });
        }
        
        // Logout functionality
        if (logoutButton) {
            logoutButton.addEventListener("click", function(e) {
                e.preventDefault();
                window.location.href = "logout.php";
            });
        }

        // Close modals when X is clicked
        for (let i = 0; i < closeButtons.length; i++) {
            closeButtons[i].addEventListener("click", function() {
                modal.style.display = "none";
                authModal.style.display = "none";
            });
        }

        // Close modals when clicking outside of them
        window.addEventListener("click", function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
            if (event.target == authModal) {
                authModal.style.display = "none";
            }
        });
        
        // Add to cart functionality
        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-id');
                const productName = this.getAttribute('data-name');
                const productPrice = parseFloat(this.getAttribute('data-price'));
                
                addToCart(productId, productName, productPrice);
            });
        });

        // Auth tabs functionality
        document.querySelectorAll('.auth-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabName = this.getAttribute('data-tab');
                
                // Update active tab
                document.querySelectorAll('.auth-tab').forEach(t => {
                    t.classList.remove('active');
                });
                this.classList.add('active');
                
                // Show active form
                document.querySelectorAll('.auth-form').forEach(form => {
                    form.classList.remove('active');
                });
                document.getElementById(tabName + '-form').classList.add('active');
            });
        });

        // Login form submission
        document.getElementById('login-submit').addEventListener('click', function() {
            const email = document.getElementById('login-email').value;
            const password = document.getElementById('login-password').value;
            
            const formData = new FormData();
            formData.append('login', true);
            formData.append('email', email);
            formData.append('password', password);
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const messageElement = document.getElementById('login-message');
                messageElement.textContent = data.message;
                
                if (data.success) {
                    messageElement.classList.remove('error');
                    messageElement.classList.add('success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    messageElement.classList.remove('success');
                    messageElement.classList.add('error');
                }
            });
        });

        // Register form submission
        document.getElementById('register-submit').addEventListener('click', function() {
            const name = document.getElementById('register-name').value;
            const email = document.getElementById('register-email').value;
            const password = document.getElementById('register-password').value;
            const phone = document.getElementById('register-phone').value;
            const address = document.getElementById('register-address').value;
            
            const formData = new FormData();
            formData.append('register', true);
            formData.append('full_name', name);
            formData.append('email', email);
            formData.append('password', password);
            formData.append('phone', phone);
            formData.append('address', address);
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const messageElement = document.getElementById('register-message');
                messageElement.textContent = data.message;
                
                if (data.success) {
                    messageElement.classList.remove('error');
                    messageElement.classList.add('success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    messageElement.classList.remove('success');
                    messageElement.classList.add('error');
                }
            });
        });

        // Checkout functionality
        checkoutButton.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('checkout', true);
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Order placed successfully! Order ID: ' + data.order_id);
                    cart = [];
                    updateCartCount();
                    updateCartDisplay();
                    modal.style.display = "none";
                } else {
                    alert(data.message);
                    if (data.message === 'Please login first') {
                        authModal.style.display = "block";
                        modal.style.display = "none";
                    }
                }
            });
        });

        // Search functionality
        searchButton.addEventListener('click', performSearch);
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
        
        function performSearch() {
            const searchTerm = searchInput.value.trim();
            if (searchTerm.length < 2) {
                alert('Please enter at least 2 characters to search');
                return;
            }
            
            // Show loading state
            searchResultsContainer.innerHTML = '<p>Searching...</p>';
            searchResults.classList.add('active');
            
            // Scroll to search results
            searchResults.scrollIntoView({ behavior: 'smooth' });
            
            // Send search request
            fetch(`index.php?search=${encodeURIComponent(searchTerm)}&ajax=1`)
                .then(response => response.json())
                .then(products => {
                    if (products.length === 0) {
                        searchResultsContainer.innerHTML = '<p>No products found matching your search.</p>';
                        return;
                    }
                    
                    // Display search results
                    let html = '';
                    products.forEach(product => {
                        html += `
                            <div class="product-card">
                                <img src="${product.image_url || 'SH.png'}" alt="${product.name}" class="product-image">
                                <div class="product-info">
                                    <h3 class="product-title">${product.name}</h3>
                                    <p class="product-price">ETB ${parseFloat(product.price).toFixed(2)}</p>
                                    <button class="add-to-cart" data-id="${product.id}" data-name="${product.name}" data-price="${product.price}">
                                        <i class="fas fa-cart-plus"></i>Add to Cart
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    
                    searchResultsContainer.innerHTML = html;
                    
                    // Reattach event listeners to add-to-cart buttons
                    document.querySelectorAll('.add-to-cart').forEach(button => {
                        button.addEventListener('click', function() {
                            const productId = this.getAttribute('data-id');
                            const productName = this.getAttribute('data-name');
                            const productPrice = parseFloat(this.getAttribute('data-price'));
                            
                            addToCart(productId, productName, productPrice);
                        });
                    });
                })
                .catch(error => {
                    console.error('Search error:', error);
                    searchResultsContainer.innerHTML = '<p>Error performing search. Please try again.</p>';
                });
        }
        
        // Category filter functionality
        document.querySelectorAll('.category').forEach(category => {
            category.addEventListener('click', function() {
                const categoryId = this.getAttribute('data-category-id');
                
                // Show loading state
                document.getElementById('products').scrollIntoView({ behavior: 'smooth' });
                const productsGrid = document.querySelector('.products-grid');
                productsGrid.innerHTML = '<p>Loading products...</p>';
                
                // Fetch products by category
                fetch(`index.php?category=${categoryId}`)
                    .then(response => response.json())
                    .then(products => {
                        if (products.length === 0) {
                            productsGrid.innerHTML = '<p>No products found in this category.</p>';
                            return;
                        }
                        
                        // Display products
                        let html = '';
                        products.forEach(product => {
                            html += `
                                <div class="product-card">
                                    <img src="${product.image_url || 'SH.png'}" alt="${product.name}" class="product-image">
                                    <div class="product-info">
                                        <h3 class="product-title">${product.name}</h3>
                                        <p class="product-price">ETB ${parseFloat(product.price).toFixed(2)}</p>
                                        <button class="add-to-cart" data-id="${product.id}" data-name="${product.name}" data-price="${product.price}">
                                            <i class="fas fa-cart-plus"></i>Add to Cart
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                        
                        productsGrid.innerHTML = html;
                        
                        // Reattach event listeners to add-to-cart buttons
                        document.querySelectorAll('.add-to-cart').forEach(button => {
                            button.addEventListener('click', function() {
                                const productId = this.getAttribute('data-id');
                                const productName = this.getAttribute('data-name');
                                const productPrice = parseFloat(this.getAttribute('data-price'));
                                
                                addToCart(productId, productName, productPrice);
                            });
                        });
                    })
                    .catch(error => {
                        console.error('Category filter error:', error);
                        productsGrid.innerHTML = '<p>Error loading products. Please try again.</p>';
                    });
            });
        });

        // Add to cart function
        function addToCart(id, name, price) {
            // Check if product already in cart
            const existingItem = cart.find(item => item.id == id);
            
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart.push({
                    id: id,
                    name: name,
                    price: price,
                    quantity: 1
                });
            }
            
            // Update cart count
            updateCartCount();
            
            // Save to session via AJAX
            const formData = new FormData();
            formData.append('add_to_cart', true);
            formData.append('product_id', id);
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show notification
                    alert(`${name} has been added to your cart!`);
                }
            });
        }
        
        // Update cart display
        function updateCartDisplay() {
            if (cart.length === 0) {
                emptyCartMessage.style.display = "block";
                cartTotalElement.textContent = "Total: ETB0.00";
                checkoutButton.style.display = "none";
                return;
            }
            
            emptyCartMessage.style.display = "none";
            checkoutButton.style.display = "block";
            
            // Clear previous items
            while (cartItemsContainer.firstChild) {
                if (cartItemsContainer.firstChild.id !== "empty-cart-message") {
                    cartItemsContainer.removeChild(cartItemsContainer.firstChild);
                } else {
                    break;
                }
            }
            
            // Add items to display
            let total = 0;
            
            cart.forEach(item => {
                const itemTotal = item.price * item.quantity;
                total += itemTotal;
                
                const cartItemElement = document.createElement("div");
                cartItemElement.className = "cart-item";
                cartItemElement.innerHTML = `
                    <div>
                        <h4>${item.name}</h4>
                        <p>ETB${item.price.toFixed(2)} x ${item.quantity}</p>
                    </div>
                    <div>
                        <p>ETB${itemTotal.toFixed(2)}</p>
                    </div>
                `;
                
                cartItemsContainer.appendChild(cartItemElement);
            });
            
            // Update total
            cartTotalElement.textContent = `Total: ETB${total.toFixed(2)}`;
        }
        
        // Initialize page
        document.addEventListener("DOMContentLoaded", function() {
            updateCartCount();
            
            // Check if we have a search parameter in URL
            const urlParams = new URLSearchParams(window.location.search);
            const searchParam = urlParams.get('search');
            if (searchParam) {
                searchInput.value = searchParam;
                performSearch();
            }
            
            // Check if we have a category parameter in URL
            const categoryParam = urlParams.get('category');
            if (categoryParam) {
                // Find and click the category
                const categoryElement = document.querySelector(`.category[data-category-id="${categoryParam}"]`);
                if (categoryElement) {
                    categoryElement.click();
                }
            }
        });
    </script>
    
</body>
</html>