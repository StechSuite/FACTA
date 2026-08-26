<?php
/**
 * FACTA — Topic Detail Page (redirects to topics with id)
 */

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    header("Location: index.php?page=topics&id={$id}");
    exit;
}
header("Location: index.php?page=topics");
exit;
