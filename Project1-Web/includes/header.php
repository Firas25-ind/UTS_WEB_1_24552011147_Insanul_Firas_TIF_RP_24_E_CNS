<?php require_once __DIR__.'/functions.php'; ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= e($pageTitle ?? APP_NAME) ?></title>

    <?php foreach (['logo.png','logo.svg','logo.webp','logo.jpg'] as $__f) { if (is_file(__DIR__.'/../assets/images/'.$__f)) { echo '<link rel="icon" href="'.BASE_URL.'/assets/images/'.$__f.'">'; break; } } ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/dashboard-pages.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/modern.css?v=1" rel="stylesheet">
</head>
<body id="top">
