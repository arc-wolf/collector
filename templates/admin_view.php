<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Submission Metadata</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/ui.css">
</head>
<body>
<div class="backdrop-grid"></div>
<main class="shell" style="max-width: 900px;">
    <section class="hero">
        <div>
            <p class="kicker">Metadata View</p>
            <h1>Submission #<?= e((string) $submission['id']) ?></h1>
        </div>
        <div class="toolbar">
            <a class="btn secondary" href="/admin">Back to Dashboard</a>
        </div>
    </section>

    <section class="panel fade-up">
        <div class="table-wrap">
            <table style="min-width: 100%;">
                <tbody>
                    <tr><th style="width:220px;">Name</th><td><?= e((string) $submission['name']) ?></td></tr>
                    <tr><th>Register No</th><td><?= e((string) $submission['register_no']) ?></td></tr>
                    <tr><th>Set No</th><td><?= e((string) ($submission['set_no'] ?? '')) ?></td></tr>
                    <tr><th>IP</th><td><?= e((string) $submission['ip']) ?></td></tr>
                    <tr><th>Status</th><td><span class="chip"><?= e((string) $submission['status']) ?></span></td></tr>
                    <tr><th>Original Filename</th><td><?= e((string) $submission['original_filename']) ?></td></tr>
                    <tr><th>Stored Filename</th><td><?= e((string) $submission['stored_filename']) ?></td></tr>
                    <tr><th>SHA256</th><td><?= e((string) $submission['file_hash']) ?></td></tr>
                    <tr><th>Created At (UTC)</th><td><?= e((string) $submission['created_at']) ?></td></tr>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
