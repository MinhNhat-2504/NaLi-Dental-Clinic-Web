<?php
/** Dynamic XML sitemap for the PHP site. Public group pages come from the current products table. */
header('Content-Type: application/xml; charset=UTF-8');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = preg_replace('/[^A-Za-z0-9.:-]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
$dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/sitemap.php')), '/');
$base = $scheme . '://' . $host . ($dir ? $dir : '');
$urls = ['services.php', 'about.php', 'contact.php', 'team.php', 'news.php'];

// Never fail the sitemap if MySQL is temporarily unavailable.
$db = @new mysqli(getenv('DB_HOST') ?: 'localhost', getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '', getenv('DB_NAME') ?: 'nali_dental', (int)(getenv('DB_PORT') ?: 3306));
if (!$db->connect_error) {
    $result = $db->query("SELECT DISTINCT target_group FROM products WHERE COALESCE(is_active,1)=1 AND target_group <> ''");
    while ($result && ($row = $result->fetch_assoc())) {
        $urls[] = 'services.php?group=' . rawurlencode($row['target_group']);
    }
    $db->close();
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach (array_unique($urls) as $url) {
    $loc = htmlspecialchars($base . '/' . $url, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    echo "  <url><loc>{$loc}</loc><lastmod>" . date('c') . "</lastmod></url>\n";
}
echo "</urlset>\n";
