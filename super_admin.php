<?php
require_once("cann.php");
require_once("check.php");

if (!isset($_SESSION['requestnameid']) || $_SESSION['requestnameid'] != 1) {
    header("Location: index.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        if (empty($username) || empty($password)) {
            $message = '<div style="color: red; text-align: center; margin-bottom: 20px;">Please fill in all fields correctly.</div>';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO [Final_Clearance].[dbo].[ClearanceAdmins] (username, password) VALUES (?, ?)";
            $params = [$username, $hashed_password];
            $stmt = sqlsrv_prepare($conn, $sql, $params);

            if ($stmt === false) {
                $message = '<div style="color: red; text-align: center; margin-bottom: 20px;">Database error. Please try again.</div>';
            } else {
                if (sqlsrv_execute($stmt)) {
                    $message = '<div style="color: green; text-align: center; margin-bottom: 20px;">Admin created successfully.</div>';
                } else {
                    $errors = sqlsrv_errors();
                    $errorMsg = isset($errors[0]['message']) ? $errors[0]['message'] : 'Unknown error';
                    $message = '<div style="color: red; text-align: center; margin-bottom: 20px;">Error creating admin: ' . htmlspecialchars($errorMsg) . '</div>';
                }
            }
        }
    } elseif (isset($_POST['delete'])) {
        $id = intval($_POST['id']);
        $sql = "DELETE FROM [Final_Clearance].[dbo].[ClearanceAdmins] WHERE id = ?";
        $params = [$id];
        $stmt = sqlsrv_prepare($conn, $sql, $params);

        if ($stmt === false) {
            $message = '<div style="color: red; text-align: center; margin-bottom: 20px;">Database error. Please try again.</div>';
        } else {
            if (sqlsrv_execute($stmt)) {
                $message = '<div style="color: green; text-align: center; margin-bottom: 20px;">Admin deleted successfully.</div>';
            } else {
                $message = '<div style="color: red; text-align: center; margin-bottom: 20px;">Error deleting admin.</div>';
            }
        }
    } elseif (isset($_POST['edit'])) {
        $id = intval($_POST['edit_id']);
        $username = trim($_POST['edit_username']);
        $password = trim($_POST['edit_password']);

        if (empty($username)) {
            $message = '<div style="color: red; text-align: center; margin-bottom: 20px;">Username cannot be empty.</div>';
        } else {
            $sql = "UPDATE [Final_Clearance].[dbo].[ClearanceAdmins] SET username = ?";
            $params = [$username];
            if (!empty($password)) {
                $sql .= ", password = ?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id = ?";
            $params[] = $id;

            $stmt = sqlsrv_prepare($conn, $sql, $params);

            if ($stmt === false) {
                $message = '<div style="color: red; text-align: center; margin-bottom: 20px;">Database error. Please try again.</div>';
            } else {
                if (sqlsrv_execute($stmt)) {
                    $message = '<div style="color: green; text-align: center; margin-bottom: 20px;">Admin updated successfully.</div>';
                } else {
                    $message = '<div style="color: red; text-align: center; margin-bottom: 20px;">Error updating admin.</div>';
                }
            }
        }
    }
}

// Fetch existing admins
$admins = [];
$query = "SELECT id, username, created_at FROM [Final_Clearance].[dbo].[ClearanceAdmins] ORDER BY id";
$stmt = sqlsrv_query($conn, $query);
if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $admins[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - Manage Admins</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .logout-btn {
            background-color: red;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Manage Admins</h1>
            <a href="main_clearance.php" class="logout-btn">Back to Clearance</a>
        </div>

        <?php echo $message; ?>

        <h3>Create New Admin</h3>
        <form method="post" action="">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" name="create" class="btn btn-primary">Create Admin</button>
        </form>

        <h3 class="mt-5">Existing Admins</h3>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>S/N</th>          
                    <th>Username</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $sn = 1; foreach ($admins as $admin): ?>
                    <tr>
                        <td><?php echo $sn++; ?></td>
                        <td><?php echo htmlspecialchars($admin['username']); ?></td>
                        <td><?php echo htmlspecialchars($admin['created_at']->format('Y-m-d H:i:s')); ?></td>
                        <td>
                            <?php if ($admin['username'] !== $_SESSION['username']): ?>
                                <button class="btn btn-warning btn-sm" onclick="openEditModal(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['username']); ?>', event)">Edit</button>
                                <form method="post" action="" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo $admin['id']; ?>">
                                    <button type="submit" name="delete" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this admin?')">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Edit Modal -->
        <div id="editModal" style="display:none; position: absolute; z-index: 1000; background: white; border: 1px solid #ccc; border-radius: 5px; padding: 10px; width: 300px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <span class="close-btn" onclick="closeEditModal()" style="position: absolute; top: 5px; right: 10px; cursor: pointer;">&times;</span>
            <h3 style="margin-top: 0;">Edit Admin</h3>
            <form method="post" action="">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="mb-3">
                    <label for="edit_username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="edit_username" name="edit_username" required>
                </div>
                <div class="mb-3">
                    <label for="edit_password" class="form-label">New Password (leave blank to keep current)</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="edit_password" name="edit_password">
                        <button class="btn btn-outline-secondary" type="button" id="toggleEditPassword">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" name="edit" class="btn btn-primary">Update</button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });

        function openEditModal(id, username, event) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_password').value = '';
            const modal = document.getElementById('editModal');
            const button = event.target;
            const rect = button.getBoundingClientRect();
            modal.style.display = 'block';
            modal.style.top = (rect.top + window.scrollY - modal.offsetHeight) + 'px';
            modal.style.left = (rect.left + window.scrollX) + 'px';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        document.getElementById('toggleEditPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('edit_password');
            const icon = this.querySelector('i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });

    </script>
</body>
</html>