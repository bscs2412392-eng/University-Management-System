<?php
// users.php
include 'db.php';
include 'auth.php';
checkAdmin();

$message = "";

// 1. ADD NEW USER
if (isset($_POST['add_user'])) {
    $name     = $conn->real_escape_string($_POST['name']);
    $email    = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role     = $conn->real_escape_string($_POST['role']);
    $status   = $conn->real_escape_string($_POST['status']);

    // Check if email already exists
    $check = $conn->query("SELECT id FROM users WHERE email='$email'");
    if ($check->num_rows > 0) {
        $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>Email address already exists!</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $email, $password, $role, $status);
        if ($stmt->execute()) {
            $message = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>User created successfully!</div>";
        }
        $stmt->close();
    }
}

// 2. TOGGLE USER STATUS (Active / Inactive)
if (isset($_GET['toggle_status'])) {
    $id = (int)$_GET['toggle_status'];
    $current_status = $_GET['current'];
    $new_status = ($current_status === 'Active') ? 'Inactive' : 'Active';
    
    $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute();
    header("Location: users.php");
    exit();
}

// 3. DELETE USER
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Prevent self deletion
    if ($id != $_SESSION['user_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: users.php");
    exit();
}

// FETCH ALL USERS
$users_result = $conn->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - University Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-indigo-900 min-h-screen text-white flex flex-col justify-between p-4">
        <div>
            <h1 class="text-2xl font-bold mb-8 text-center border-b border-indigo-700 pb-4">UniAdmin</h1>
            <nav class="space-y-3">
                <a href="admin_dashboard.php" class="flex items-center gap-3 hover:bg-indigo-800 px-4 py-2.5 rounded-lg text-sm font-medium">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
                <a href="users.php" class="flex items-center gap-3 bg-indigo-700 px-4 py-2.5 rounded-lg text-sm font-medium">
                    <i class="fas fa-users-cog"></i> User Management
                </a>
                <a href="#" class="flex items-center gap-3 hover:bg-indigo-800 px-4 py-2.5 rounded-lg text-sm font-medium">
                    <i class="fas fa-user-graduate"></i> Students
                </a>
                <a href="#" class="flex items-center gap-3 hover:bg-indigo-800 px-4 py-2.5 rounded-lg text-sm font-medium">
                    <i class="fas fa-chalkboard-teacher"></i> Faculty
                </a>
            </nav>
        </div>
        <a href="logout.php" class="flex items-center gap-3 bg-red-600 hover:bg-red-700 px-4 py-2.5 rounded-lg text-sm font-medium">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-8">
        <header class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">User Management</h2>
                <p class="text-sm text-gray-500">System roles, permissions, and accounts control.</p>
            </div>
            <button onclick="document.getElementById('addUserModal').classList.remove('hidden')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2">
                <i class="fas fa-plus"></i> Add New User
            </button>
        </header>

        <?php echo $message; ?>

        <!-- Users Table -->
        <section class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 border-b text-xs font-semibold text-gray-500 uppercase">
                    <tr>
                        <th class="py-3 px-4">ID</th>
                        <th class="py-3 px-4">User Details</th>
                        <th class="py-3 px-4">Role</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Registered Date</th>
                        <th class="py-3 px-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y text-gray-700">
                    <?php if ($users_result && $users_result->num_rows > 0): ?>
                        <?php while($row = $users_result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-4 font-semibold">#<?php echo $row['id']; ?></td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-gray-800"><?php echo htmlspecialchars($row['name']); ?></div>
                                    <div class="text-xs text-gray-400"><?php echo htmlspecialchars($row['email']); ?></div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold 
                                        <?php 
                                            if($row['role'] == 'Admin') echo 'bg-purple-100 text-purple-700';
                                            elseif($row['role'] == 'Faculty') echo 'bg-blue-100 text-blue-700';
                                            else echo 'bg-gray-100 text-gray-700';
                                        ?>">
                                        <?php echo $row['role']; ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <a href="users.php?toggle_status=<?php echo $row['id']; ?>&current=<?php echo $row['status']; ?>" 
                                       class="px-2.5 py-1 rounded-full text-xs font-bold cursor-pointer transition 
                                        <?php echo ($row['status'] == 'Active') ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-red-100 text-red-700 hover:bg-red-200'; ?>">
                                        <?php echo $row['status']; ?>
                                    </a>
                                </td>
                                <td class="py-3 px-4 text-xs text-gray-500">
                                    <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <?php if($row['id'] != $_SESSION['user_id']): ?>
                                        <a href="users.php?delete=<?php echo $row['id']; ?>" 
                                           onclick="return confirm('Are you sure you want to delete this user?')" 
                                           class="text-red-600 hover:text-red-800 p-2 text-sm">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400 italic">Current User</span>
                                    <?php endif; ?>
                                </td>
                                </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="py-6 text-center text-gray-400">No users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>

    <!-- Add User Modal -->
    <div id="addUserModal" class="fixed inset-0 bg-black/50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-md w-full p-6 shadow-xl relative">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-lg font-bold text-gray-800">Add New System User</h3>
                <button onclick="document.getElementById('addUserModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="users.php" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Full Name</label>
                    <input type="text" name="name" required class="w-full border rounded-lg p-2.5 text-sm focus:outline-none focus:border-indigo-600">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Email Address</label>
                    <input type="email" name="email" required class="w-full border rounded-lg p-2.5 text-sm focus:outline-none focus:border-indigo-600">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Password</label>
                    <input type="password" name="password" required class="w-full border rounded-lg p-2.5 text-sm focus:outline-none focus:border-indigo-600">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">System Role</label>
                        <select name="role" class="w-full border rounded-lg p-2.5 text-sm focus:outline-none focus:border-indigo-600">
                            <option value="Student">Student</option>
                            <option value="Faculty">Faculty</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Account Status</label>
                        <select name="status" class="w-full border rounded-lg p-2.5 text-sm focus:outline-none focus:border-indigo-600">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="pt-4 border-t flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('addUserModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">Cancel</button>
                    <button type="submit" name="add_user" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium">Create Account</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>