<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Student Submission</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/ui.css">
</head>
<body>
<div class="backdrop-grid"></div>
<main class="shell">
    <section class="hero">
        <div>
            <p class="kicker">Collector Node</p>
            <h1>Student Submission Console</h1>
        </div>
        <div class="chip">Detected IP: <?= e($clientIp) ?></div>
    </section>

    <section class="panel fade-up">
        <?php foreach ($errors as $err): ?>
            <div class="alert error"><?= e($err) ?></div>
        <?php endforeach; ?>

        <?php if ($success): ?>
            <div class="alert ok">
                Submission recorded. ID <strong>#<?= e((string) $success['id']) ?></strong>
                at <strong><?= e($success['created_at']) ?> UTC</strong>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" action="/submit">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

            <div class="form-grid">
                <label>Name
                    <input type="text" name="name" maxlength="120" required value="<?= e($post['name']) ?>">
                </label>

                <label>Register Number
                    <input type="text" name="register_no" maxlength="32" required value="<?= e($post['register_no']) ?>">
                </label>

                <label>Set Number (optional)
                    <input type="text" name="set_no" maxlength="32" value="<?= e($post['set_no']) ?>">
                </label>

                <label>Upload File
                    <input type="file" name="submission_file" required>
                </label>
            </div>

            <p class="meta">Only approved file types and LAN IP ranges are accepted.</p>
            <p><button type="submit">Transmit Submission</button></p>
        </form>
    </section>
</main>
</body>
</html>
