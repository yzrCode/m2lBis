<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= echapperHtml($title ?? 'Espace membre') ?> · M2L Football Club</title>
<meta name="robots" content="noindex">
<script src="<?= echapperHtml(urlRessource('js/theme.js')) ?>"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,500..900&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= echapperHtml(urlRessource('css/base.css')) ?>">
<link rel="stylesheet" href="<?= echapperHtml(urlRessource('css/site.css')) ?>">
</head>
<body class="plain">
<?php require BASE_PATH . '/app/Views/partials/sprite.php'; ?>
<?= $content ?>
</body>
</html>
