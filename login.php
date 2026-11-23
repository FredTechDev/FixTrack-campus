<?php
/**
 * FixTrack Campus - Authentication endpoint.
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect('dashboard.php');

$pdo = get_db();
$errors = [];
$email = '';

// Lightweight brute-force throttling stored in the session.
$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? ['count' => 0, 'first' => time()];
if (time() - $_SESSION['login_attempts']['first'] > 300) {
    $_SESSION['login_attempts'] = ['count' => 0, 'first' => time()];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($_SESSION['login_attempts']['count'] >= 8) {
        $errors[] = 'Too many failed sign-in attempts. Please wait a few minutes and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $errors[] = 'Enter a valid email address and password.';
        $_SESSION['login_attempts']['count']++;
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'Invalid email or password.';
            $_SESSION['login_attempts']['count']++;
        } elseif (!(int)$user['is_active']) {
            $errors[] = 'This account has been deactivated. Contact the facilities office.';
        } else {
            login_user($user);
            $_SESSION['login_attempts'] = ['count' => 0, 'first' => time()];
            set_flash('success', 'Welcome back, ' . explode(' ', $user['full_name'])[0] . '!');

            $target = $_GET['redirect'] ?? '';
            if ($target === '' || strpos($target, '/') !== 0 || strpos($target, '//') === 0) {
                $target = 'dashboard.php';
            }
            redirect(ltrim($target, '/'));
        }
    }
}

$page_title = 'Sign in';
$active_nav = 'login';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
    <aside class="auth-side">
        <h2>Welcome back</h2>
        <p>Sign in to manage campus maintenance requests.</p>
        <ul>
            <li><i class="fas fa-user-graduate"></i> <strong>Students:</strong> track and verify your reports</li>
            <li><i class="fas fa-user-tie"></i> <strong>Administrators:</strong> review, assign and analyse</li>
            <li><i class="fas fa-screwdriver-wrench"></i> <strong>Technicians:</strong> manage assigned jobs</li>
        </ul>
    </aside>
    <div class="auth-main">
        <h1>Sign in</h1>
        <p class="subtitle">Use your campus account credentials.</p>

        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <div><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('login.php' . (isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''))) ?>" data-validate novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label" for="email">Email address <span class="required">*</span></label>
                <input class="form-control" type="email" id="email" name="email" required
                       data-type="email" value="<?= e($email) ?>" autocomplete="username" placeholder="you@campus.edu">
                <div class="invalid-feedback"></div>
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Password <span class="required">*</span></label>
                <input class="form-control" type="password" id="password" name="password" required
                       autocomplete="current-password" placeholder="Your password">
                <div class="invalid-feedback"></div>
            </div>
            <button class="btn btn-primary btn-block btn-lg" type="submit"><i class="fas fa-right-to-bracket mr-2"></i>Sign in</button>
        </form>

        <div class="demo-credentials">
            <strong>Demo accounts</strong>
            <div><i class="fas fa-user-tie mr-1"></i> Admin: <code>admin@fixtrack.campus</code> / <code>Admin@123</code></div>
            <div><i class="fas fa-screwdriver-wrench mr-1"></i> Technician: <code>tech.electrical@fixtrack.campus</code> / <code>Tech@123</code></div>
            <div><i class="fas fa-user-graduate mr-1"></i> Student: <code>alice.student@fixtrack.campus</code> / <code>Student@123</code></div>
        </div>

        <div class="auth-alt">
            <a href="<?= e(url('register.php')) ?>"><strong>Create an account</strong></a>
            &middot; <a href="<?= e(url('guest-report.php')) ?>"><strong>Report as guest</strong></a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
