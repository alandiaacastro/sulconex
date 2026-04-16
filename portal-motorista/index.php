<?php
$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/portal-motorista/index.php'));
$portalPath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
$appBase = preg_replace('#/portal-motorista$#', '', $portalPath);

$buildUrl = static function (string $path) use ($appBase): string {
    $base = $appBase !== '' ? rtrim($appBase, '/') : '';
    return $base . '/' . ltrim($path, '/');
};

$config = [
    'apiBase' => $buildUrl('api/portal-motorista'),
    'legacyLoginUrl' => $buildUrl('index.php?class=LoginForm'),
    'portalUrl' => $buildUrl('portal-motorista/'),
    'cadastroRequestUrl' => $buildUrl('index.php?class=PortalMotoristaSolicitarCadastro'),
];

$manifestPath = __DIR__ . '/dist/manifest.json';
$entry = null;
if (file_exists($manifestPath)) {
    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    if (is_array($manifest)) {
        $entry = $manifest['src/main.jsx'] ?? $manifest['index.html'] ?? null;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Portal do Motorista</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet" />
<?php if ($entry && !empty($entry['css']) && is_array($entry['css'])): ?>
<?php foreach ($entry['css'] as $cssFile): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($buildUrl('portal-motorista/dist/' . ltrim((string) $cssFile, '/')), ENT_QUOTES, 'UTF-8'); ?>" />
<?php endforeach; ?>
<?php else: ?>
    <link rel="stylesheet" href="assets/styles.css" />
<?php endif; ?>
</head>
<body>
    <div id="root"></div>

    <script>
        window.PORTAL_MOTORISTA_CONFIG = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
<?php if ($entry && !empty($entry['file'])): ?>
    <script type="module" src="<?php echo htmlspecialchars($buildUrl('portal-motorista/dist/' . ltrim((string) $entry['file'], '/')), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php else: ?>
    <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <script type="text/babel" src="assets/shared.js"></script>
    <script type="text/babel" src="assets/auth.js"></script>
    <script type="text/babel" src="assets/pages-overview.js"></script>
    <script type="text/babel" src="assets/pages-ops.js"></script>
    <script type="text/babel" src="assets/app.js"></script>
<?php endif; ?>
</body>
</html>
