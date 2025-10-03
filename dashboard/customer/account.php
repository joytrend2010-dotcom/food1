<?php
session_start();
require_once '../../includes/dbh.inc.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Get current user data
$user = [];
try {
    $stmt = $pdo->prepare("SELECT name, email, phone, address, image FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception("User not found");
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading user data: " . $e->getMessage();
    header("Location: index.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Basic validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email is already taken by another user
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            $errors[] = "Email is already in use by another account";
        }
    } catch (PDOException $e) {
        $errors[] = "Error checking email availability";
    }
    
    // Handle image upload
    $imagePath = $user['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/users/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = $_FILES['image']['type'];
        
        if (in_array($fileType, $allowedTypes)) {
            $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                // Delete old image if it exists and isn't the default
                if ($imagePath && !str_contains($imagePath, 'assets/default-user.jpg')) {
                    @unlink('../' . $imagePath);
                }
                $imagePath = 'uploads/users/' . $fileName;
            } else {
                $errors[] = "Failed to upload image";
            }
        } else {
            $errors[] = "Only JPG, PNG, and GIF images are allowed";
        }
    }
    
    // Update user if no errors
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ?, image = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $address, $imagePath, $userId]);
            
            $_SESSION['success'] = "Profile updated successfully!";
            header("Location: account.php");
            exit();
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account | Sweet Bite</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #fff7ed 0%, #ffe3c0 100%);
        }
        .glass {
            background: rgba(255,255,255,0.7);
            box-shadow: 0 8px 32px 0 rgba(31,38,135,0.10);
            backdrop-filter: blur(8px);
            border-radius: 1.5rem;
        }
        .profile-card {
            transition: all 0.3s cubic-bezier(.4,0,.2,1);
            border: 2px solid #fff7ed;
        }
        .profile-card:hover {
            transform: scale(1.02) translateY(-2px);
            box-shadow: 0 8px 32px #ff7b2522;
            border: 2px solid #ff7b25;
        }
        .file-input-label {
            transition: background 0.2s;
        }
        .file-input-label:hover {
            background: #fff7ed;
        }
        .glow-btn {
            transition: box-shadow 0.2s, background 0.2s;
        }
        .glow-btn:hover {
            box-shadow: 0 0 0 4px #ffedd5, 0 4px 20px #ff7b25a0;
            background: #ff7b25;
            color: #fff;
        }
        .fade-in {
            animation: fadeIn 0.8s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .footer {
            background: #fff7ed;
            color: #ff7b25;
            padding: 2rem 0 1rem 0;
            text-align: center;
            border-radius: 2rem 2rem 0 0;
            margin-top: 3rem;
            font-weight: 500;
        }
        .footer a {
            color: #ff7b25;
            margin: 0 0.5rem;
            transition: color 0.2s;
        }
        .footer a:hover {
            color: #ff9d5c;
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg glass mb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center">
                    <a href="index.php" class="text-2xl font-extrabold text-orange-500 flex items-center tracking-tight">
                        <i class="fas fa-utensils mr-2 animate-spin-slow"></i>
                        Sweet Bite
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-gray-700 hover:text-orange-500 font-medium transition">
                        <i class="fas fa-home mr-1"></i> Home
                    </a>
                    <a href="orders.php" class="text-gray-700 hover:text-orange-500 font-medium transition">
                        <i class="fas fa-clipboard-list mr-1"></i> My Orders
                    </a>
                    <a href="/food-delivery-website/auth/logout.php" class="text-gray-700 hover:text-orange-500 font-medium transition">
                        <i class="fas fa-sign-out-alt mr-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 fade-in">
        <!-- Page Header -->
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-extrabold text-orange-600 tracking-tight">
                <i class="fas fa-user-circle text-orange-500 mr-2 animate-bounce"></i>
                My Account
            </h1>
        </div>
        <!-- Messages -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 p-4 mb-6 rounded shadow">
                <p class="text-red-700 text-lg font-semibold"><?= $_SESSION['error'] ?></p>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 p-4 mb-6 rounded shadow">
                <p class="text-green-700 text-lg font-semibold"><?= $_SESSION['success'] ?></p>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Profile Card -->
            <div class="lg:col-span-1">
                <div class="profile-card bg-white rounded-xl shadow overflow-hidden glass fade-in">
                    <div class="p-8 flex flex-col items-center">
                        <div class="relative mb-4">
                            <img src="../<?= htmlspecialchars($user['image'] ?: 'assets/default-user.jpg') ?>" 
                                 alt="Profile Image" 
                                 class="w-32 h-32 rounded-full object-cover border-4 border-orange-100">
                            <label for="image-upload" class="file-input-label absolute bottom-0 right-0 bg-white p-2 rounded-full shadow-md cursor-pointer hover:bg-orange-100 border border-orange-200">
                                <i class="fas fa-camera text-orange-500"></i>
                            </label>
                        </div>
                        <h2 class="text-xl font-bold text-center text-gray-800 mb-1"><?= htmlspecialchars($user['name']) ?></h2>
                        <p class="text-gray-500 text-center mb-2 capitalize"><?= htmlspecialchars($userRole) ?></p>
                    </div>
                </div>
            </div>

            <!-- Account Form -->
            <div class="lg:col-span-2">
                <div class="profile-card bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-6">
                        <h2 class="text-xl font-bold mb-6">Account Information</h2>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <input type="file" id="image-upload" name="image" class="hidden" accept="image/*">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Name -->
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                    <input type="text" id="name" name="name" required
                                           value="<?= htmlspecialchars($user['name']) ?>" 
                                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                                </div>
                                
                                <!-- Email -->
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                                    <input type="email" id="email" name="email" required
                                           value="<?= htmlspecialchars($user['email']) ?>" 
                                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                                </div>
                                
                                <!-- Phone -->
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                    <input type="tel" id="phone" name="phone"
                                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                                </div>
                                
                                <!-- Address -->
                                <div class="md:col-span-2">
                                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Delivery Address</label>
                                    <textarea id="address" name="address" rows="3"
                                              class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"><?= htmlspecialchars($user['address']) ?></textarea>
                                </div>
                            </div>
                            
                            <div class="mt-8 flex justify-end">
                                <button type="submit" class="bg-orange-500 text-white px-6 py-2 rounded-md hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Change Password Section -->
                <div class="profile-card bg-white rounded-lg shadow overflow-hidden mt-6">
                    <div class="p-6">
                        <h2 class="text-xl font-bold mb-6">Change Password</h2>
                        
                        <form method="POST" action="change_password.php">
                            <div class="space-y-4">
                                <div>
                                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" required
                                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                                </div>
                                
                                <div>
                                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                                    <input type="password" id="new_password" name="new_password" required
                                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                                </div>
                                
                                <div>
                                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" required
                                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                                </div>
                            </div>
                            
                            <div class="mt-6 flex justify-end">
                                <button type="submit" class="bg-orange-500 text-white px-6 py-2 rounded-md hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                                    Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Preview image when selected
        document.getElementById('image-upload').addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.querySelector('img[alt="Profile Image"]').src = event.target.result;
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    </script>
</body>
</html>