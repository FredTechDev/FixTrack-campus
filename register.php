<?php
/**
 * FixTrack Campus - Student registration with server-side validation.
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect('dashboard.php');

$pdo = get_db();
$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => '', 'identifier' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $old = [
        'full_name'   => trim($_POST['full_name'] ?? ''),
        'email'       => trim($_POST['email'] ?? ''),
        'phone'       => trim($_POST['phone'] ?? ''),
        'identifier'  => trim($_POST['identifier'] ?? ''),
    ];
    $password        = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (mb_strlen($old['full_name']) < 2 || mb_strlen($old['full_name']) > 100) {
        $errors[] = 'Full name must be between 2 and 100 characters.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 150) {
        $errors[] = 'Enter a valid email address (max 150 characters).';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) $errors[] = 'That email address is already registered.';
    }
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
        $errors[] = 'Password must be at least 8 characters and include upper case, lower case and a number.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Password confirmation does not match.';
    }
    if (mb_strlen($old['phone']) > 30 || mb_strlen($old['identifier']) > 50) {
        $errors[] = 'Phone or student ID is too long.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, email, password_hash, role, phone, identifier, is_active)
                VALUES (?, ?, ?, 'student', ?, ?, 1)
            ");
            $stmt->execute([
                $old['full_name'],
                $old['email'],
                password_hash($password, PASSWORD_DEFAULT),
                $old['phone'] !== '' ? $old['phone'] : null,
                $old['identifier'] !== '' ? $old['identifier'] : null,
            ]);
            set_flash('success', 'Account created successfully. You can now sign in and submit reports.');
            redirect('login.php');
        } catch (PDOException $ex) {
            $errors[] = 'We could not create your account. Please try again.';
        }
    }
}

$page_title = 'Create account';
$active_nav = 'register';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
    <aside class="auth-side">
        <h2>Join FixTrack Campus</h2>
        <p>One account gives you a complete view of every issue you report.</p>
        <ul>
            <li><i class="fas fa-check-circle"></i> Submit reports with photo evidence</li>
            <li><i class="fas fa-check-circle"></i> Follow every status change in real time</li>
            <li><i class="fas fa-check-circle"></i> Verify repairs and reopen unresolved issues</li>
            <li><i class="fas fa-check-circle"></i> Get notified when your report moves</li>
        </ul>
    </aside>
    <div class="auth-main">
        <h1>Student registration</h1>
        <p class="subtitle">Fields marked <span class="required">*</span> are required.</p>

        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <div><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('register.php')) ?>" data-validate novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label" for="full_name">Full name <span class="required">*</span></label>
                <input class="form-control" type="text" id="full_name" name="full_name" required
                       data-min="2" data-max="100" data-label="Full name"
                       value="<?= e($old['full_name']) ?>" placeholder="e.g. Alice Walker" autocomplete="name">
                <div class="invalid-feedback"></div>
            </div>
            <div class="form-group">
                <label class="form-label" for="email">Email address <span class="required">*</span></label>
                <input class="form-control" type="email" id="email" name="email" required
                       data-type="email" data-label="Email address"
                       value="<?= e($old['email']) ?>" placeholder="you@university.edu" autocomplete="email">
                <div class="invalid-feedback"></div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="phone">Phone <span class="text-muted small">(optional)</span></label>
                    <input class="form-control" type="tel" id="phone" name="phone" maxlength="30"
                           value="<?= e($old['phone']) ?>" placeholder="+254 7xx xxx xxx" autocomplete="tel">
                </div>
                <div class="form-group">
                    <label class="form-label" for="identifier">Student ID <span class="text-muted small">(optional)</span></label>
                    <input class="form-control" type="text" id="identifier" name="identifier" maxlength="50"
                           value="<?= e($old['identifier']) ?>" placeholder="STU-2024-1049">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="password">Password <span class="required">*</span></label>
                    <input class="form-control" type="password" id="password" name="password" required
                           data-rule="password" data-label="Password" autocomplete="new-password">
                    <div class="invalid-feedback"></div>
                    <div class="form-hint">Minimum 8 characters with upper &amp; lower case and a number.</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="password_confirm">Confirm password <span class="required">*</span></label>
                    <input class="form-control" type="password" id="password_confirm" name="password_confirm" required
                           data-match="#password" data-label="Confirmation" autocomplete="new-password">
                    <div class="invalid-feedback"></div>
                </div>
            </div>
            <button class="btn btn-primary btn-block btn-lg" type="submit"><i class="fas fa-user-plus mr-2"></i>Create account</button>
        </form>

        <div class="auth-alt">
            Already have an account? <a href="<?= e(url('login.php')) ?>"><strong>Sign in</strong></a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
