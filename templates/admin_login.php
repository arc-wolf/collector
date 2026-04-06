<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/ui.css">
</head>
<body>
<div class="backdrop-grid"></div>
<main class="shell" style="max-width: 640px;">
    <section class="hero">
        <div>
            <p class="kicker">Control Access</p>
            <h1>Admin Authentication</h1>
        </div>
        <div class="chip">Secure Zone</div>
    </section>

    <section class="panel fade-up">
        <?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

        <form method="post" action="/admin">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

            <div class="form-grid">
                <label>Username
                    <input type="text" name="username" autocomplete="username" required>
                </label>

                <label>Password
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
            </div>

            <p><button type="submit">Access Dashboard</button></p>
        </form>
    </section>
</main>
</body>
</html>
