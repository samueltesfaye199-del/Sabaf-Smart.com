<?php
require_once 'config.php';

// Get all products
function getProducts($category_id = null) {
    global $pdo;
    
    $sql = "SELECT * FROM products";
    if ($category_id) {
        $sql .= " WHERE category_id = :category_id";
    }
    
    $stmt = $pdo->prepare($sql);
    
    if ($category_id) {
        $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get product by ID
function getProduct($id) {
    global $pdo;
    
    $sql = "SELECT * FROM products WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all categories
function getCategories() {
    global $pdo;
    
    $sql = "SELECT * FROM categories";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// User registration
function registerUser($email, $password, $full_name, $phone, $address) {
    global $pdo;
    
    // Check if email already exists
    $check_sql = "SELECT id FROM users WHERE email = :email";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->bindParam(':email', $email);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        return "Email already exists";
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $sql = "INSERT INTO users (email, password, full_name, phone, address) 
            VALUES (:email, :password, :full_name, :phone, :address)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':full_name', $full_name);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':address', $address);
    
    if ($stmt->execute()) {
        return true;
    } else {
        return "Error creating user";
    }
}

// User login
function loginUser($email, $password) {
    global $pdo;
    
    $sql = "SELECT * FROM users WHERE email = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['full_name'];
            return true;
        } else {
            return "Invalid password";
        }
    } else {
        return "Email not found";
    }
}

// Create order
function createOrder($user_id, $cart_items) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Calculate total amount
        $total_amount = 0;
        foreach ($cart_items as $item) {
            $total_amount += $item['price'] * $item['quantity'];
        }
        
        // Insert order
        $order_sql = "INSERT INTO orders (user_id, total_amount) VALUES (:user_id, :total_amount)";
        $order_stmt = $pdo->prepare($order_sql);
        $order_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $order_stmt->bindParam(':total_amount', $total_amount);
        $order_stmt->execute();
        
        $order_id = $pdo->lastInsertId();
        
        // Insert order items
        $item_sql = "INSERT INTO order_items (order_id, product_id, quantity, price) 
                     VALUES (:order_id, :product_id, :quantity, :price)";
        $item_stmt = $pdo->prepare($item_sql);
        
        foreach ($cart_items as $item) {
            $item_stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
            $item_stmt->bindParam(':product_id', $item['id'], PDO::PARAM_INT);
            $item_stmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
            $item_stmt->bindParam(':price', $item['price']);
            $item_stmt->execute();
        }
        
        $pdo->commit();
        return $order_id;
    } catch (Exception $e) {
        $pdo->rollBack();
        return "Error creating order: " . $e->getMessage();
    }
}
?>