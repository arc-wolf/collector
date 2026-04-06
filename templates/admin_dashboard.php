<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/ui.css">
</head>
<body>
<div class="backdrop-grid"></div>
<main class="shell">
    <section class="hero">
        <div>
            <p class="kicker">Mission Control</p>
            <h1>Submission Operations Panel</h1>
        </div>
        <div class="toolbar">
            <span class="chip">UTC</span>
            <a class="btn secondary" href="/admin?action=logout">Logout</a>
        </div>
    </section>

    <?php if ($message !== ''): ?><div class="alert ok"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

    <section class="stat-grid fade-up">
        <article class="stat">
            <div class="meta">Total Records</div>
            <div class="value"><?= e((string) $total) ?></div>
        </article>
        <article class="stat">
            <div class="meta">Current Page</div>
            <div class="value"><?= e((string) $page) ?>/<?= e((string) $pages) ?></div>
        </article>
        <article class="stat">
            <div class="meta">ZIP Archives</div>
            <div class="value"><?= e((string) count($zipFiles)) ?></div>
        </article>
    </section>

    <section class="panel fade-up" style="animation-delay:.06s;">
        <h2>Filters</h2>
        <form method="get" action="/admin">
            <div class="form-grid">
                <label>Date From <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>"></label>
                <label>Date To <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>"></label>
                <label>IP <input type="text" name="ip" value="<?= e($filters['ip']) ?>"></label>
                <label>Register No <input type="text" name="register_no" value="<?= e($filters['register_no']) ?>"></label>
            </div>
            <p><button type="submit">Apply Filters</button></p>
        </form>
    </section>

    <section class="panel fade-up" style="animation-delay:.12s;">
        <h2>Submissions</h2>

        <form id="zipForm" method="post" action="/admin">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="post_action" value="generate_zip">
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Select</th>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Register</th>
                        <th>Set</th>
                        <th>IP</th>
                        <th>Created (UTC)</th>
                        <th>Status</th>
                        <th>File</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($submissions === []): ?>
                    <tr>
                        <td colspan="10" class="meta">No submissions found for current filters.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($submissions as $s): ?>
                    <tr>
                        <td><input form="zipForm" type="checkbox" name="selected_ids[]" value="<?= e((string) $s['id']) ?>"></td>
                        <td><?= e((string) $s['id']) ?></td>
                        <td><?= e((string) $s['name']) ?></td>
                        <td><?= e((string) $s['register_no']) ?></td>
                        <td><?= e((string) ($s['set_no'] ?? '')) ?></td>
                        <td><?= e((string) $s['ip']) ?></td>
                        <td><?= e((string) $s['created_at']) ?></td>
                        <td><span class="chip"><?= e((string) $s['status']) ?></span></td>
                        <td><?= e((string) $s['original_filename']) ?></td>
                        <td>
                            <div class="row-actions">
                                <a class="btn secondary compact" href="/admin?action=view&id=<?= e((string) $s['id']) ?>">View</a>
                                <a class="btn secondary compact" href="/admin?action=download_file&id=<?= e((string) $s['id']) ?>">Download</a>

                                <form method="post" action="/admin">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="post_action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= e((string) $s['id']) ?>">
                                    <button class="compact" type="submit"><?= $s['status'] === 'revoked' ? 'Restore' : 'Revoke' ?></button>
                                </form>

                                <form class="danger-zone" method="post" action="/admin" onsubmit="return confirm('Delete this submission?');">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="post_action" value="soft_delete">
                                    <input type="hidden" name="id" value="<?= e((string) $s['id']) ?>">
                                    <label class="inline-check">
                                        <input type="checkbox" name="delete_file" value="1"> Delete file
                                    </label>
                                    <button class="compact danger" type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="toolbar" style="margin-top:12px;">
            <button form="zipForm" type="submit">Generate ZIP from Selected</button>
            <span class="meta">Page <?= e((string) $page) ?> of <?= e((string) $pages) ?></span>
        </p>
    </section>

    <section class="panel fade-up" style="animation-delay:.18s;">
        <h2>Generated ZIPs</h2>
        <div class="toolbar" style="margin-bottom:10px;">
            <form method="post" action="/admin">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="post_action" value="cleanup_zips">
                <button type="submit">Cleanup Old ZIPs</button>
            </form>
        </div>

        <ul class="list-reset">
            <?php if ($zipFiles === []): ?>
                <li class="meta">No generated ZIP archives yet.</li>
            <?php endif; ?>

            <?php foreach ($zipFiles as $z): ?>
                <li>
                    <a href="/admin?action=download_zip&file=<?= e($z['name']) ?>"><?= e($z['name']) ?></a>
                    <span class="meta">(<?= e((string) $z['size']) ?> bytes, <?= e($z['mtime']) ?> UTC)</span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</main>
</body>
</html>
