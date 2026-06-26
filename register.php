<?php
// register.php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: /bookmarks/index.php');
    exit;
}

$errors = [];
$success = false;
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        if (!$username || !$email || !$password) {
            $errors[] = 'Please fill in all fields.';
        } elseif ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        } else {
            $result = register($username, $email, $password);
            if ($result['success']) {
                $success = true;
            } else {
                $errors = $result['errors'];
            }
        }
    }
}

$pageTitle = 'Register';
$csrf = generate_csrf_token();
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow">
            <div class="card-body p-4">
                <h3 class="text-center mb-4"><i class="bi bi-bookmark-fill"></i> Register</h3>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> Registration successful! Your account is pending admin approval. You'll be able to login once approved.
                    </div>
                    <p class="text-center mb-0">
                        <a href="/bookmarks/login.php" class="btn btn-primary">Go to Login</a>
                    </p>
                <?php else: ?>
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $err): ?>
                                <div><?= h($err) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required minlength="3" maxlength="50" value="<?= h($username) ?>" autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required value="<?= h($email) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="password_confirm" class="form-control" required minlength="8">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Register</button>
                    </form>

                    <p class="text-center mt-3 mb-0">
                        Already have an account? <a href="/bookmarks/login.php">Login</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
