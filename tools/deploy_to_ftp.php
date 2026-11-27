<?php
/**
 * FridgeRescue — Automated Direct FTP Deployer for InfinityFree
 * Run: php tools/deploy_to_ftp.php
 */

$appRoot = dirname(__DIR__);

// Load existing local .env if available for defaults
require_once $appRoot . '/config/config.php';

echo "=========================================================\n";
echo "   🥦 FridgeRescue — InfinityFree Automated Deployer      \n";
echo "=========================================================\n\n";

// Configuration prompt or parameters
$args = $_SERVER['argv'] ?? [];
$ftpHost = getenv('IF_FTP_HOST') ?: 'ftpupload.net';
$ftpUser = getenv('IF_FTP_USER') ?: ($args[1] ?? '');
$ftpPass = getenv('IF_FTP_PASS') ?: ($args[2] ?? '');
$dbHost  = getenv('IF_DB_HOST')  ?: ($args[3] ?? '');
$dbName  = getenv('IF_DB_NAME')  ?: ($args[4] ?? '');
$dbUser  = getenv('IF_DB_USER')  ?: ($args[5] ?? $ftpUser);
$dbPass  = getenv('IF_DB_PASS')  ?: ($args[6] ?? $ftpPass);
$siteUrl = getenv('IF_SITE_URL') ?: ($args[7] ?? '');
$geminiKey = env('GEMINI_API_KEY', '');

if (empty($ftpUser) || empty($ftpPass)) {
    echo "Usage:\n";
    echo "  php tools/deploy_to_ftp.php <FTP_USER> <FTP_PASS> <DB_HOST> <DB_NAME> [DB_USER] [DB_PASS] [SITE_URL]\n\n";
    echo "Example:\n";
    echo "  php tools/deploy_to_ftp.php if0_38291024 MySecretPass sql123.infinityfree.com if0_38291024_fridgerescue if0_38291024 MySecretPass https://fridgerescue.infinityfreeapp.com\n\n";
    exit(1);
}

echo "[1/4] Connecting to InfinityFree FTP at {$ftpHost}:21 ...\n";
$conn = @ftp_connect($ftpHost, 21, 30);
if (!$conn) {
    die("❌ Error: Could not connect to {$ftpHost}. Please verify your internet connection.\n");
}

echo "      Logging in as {$ftpUser} ...\n";
if (!@ftp_login($conn, $ftpUser, $ftpPass)) {
    ftp_close($conn);
    die("❌ Error: FTP Authentication failed for {$ftpUser}. Check your vPanel account password.\n");
}

ftp_pasv($conn, true);
echo "      Connected and authenticated successfully!\n\n";

echo "[2/4] Navigating to remote web root directory ...\n";
$remoteList = @ftp_nlist($conn, '.') ?: [];
echo "      Root contents: " . implode(', ', $remoteList) . "\n";

$targetHtdocs = 'htdocs';
// Check if domain-specific directory exists (e.g. fridgerescue.ifree.page/htdocs)
foreach ($remoteList as $entry) {
    $cleanEntry = trim($entry, './\\ ');
    if (stripos($cleanEntry, 'ifree.page') !== false || stripos($cleanEntry, 'fridgerescue') !== false) {
        if (@ftp_chdir($conn, $cleanEntry . '/htdocs')) {
            $targetHtdocs = $cleanEntry . '/htdocs';
            break;
        }
    }
}

if ($targetHtdocs === 'htdocs') {
    if (!@ftp_chdir($conn, 'htdocs')) {
        @ftp_chdir($conn, '/htdocs');
    }
}
echo "      Current remote directory: " . ftp_pwd($conn) . "\n\n";

echo "[3/4] Uploading application files ...\n";

function ftp_upload_recursive($conn, $localDir, $remoteDir, $exclude = []) {
    $items = scandir($localDir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || in_array($item, $exclude, true)) {
            continue;
        }

        $localPath = $localDir . DIRECTORY_SEPARATOR . $item;
        $remotePath = $remoteDir . '/' . $item;

        if (is_dir($localPath)) {
            @ftp_mkdir($conn, $remotePath);
            ftp_upload_recursive($conn, $localPath, $remotePath, $exclude);
        } else {
            echo "  -> " . substr($remotePath, 1) . "\n";
            $mode = FTP_BINARY; // Always use binary mode to prevent UTF-8 encoding corruption across OS
            $upload = @ftp_put($conn, $remotePath, $localPath, $mode);
            if (!$upload) {
                // Retry once
                @ftp_put($conn, $remotePath, $localPath, $mode);
            }
        }
    }
}

$excludeList = ['.git', 'tests', 'tools', 'fridgerescue-infinityfree.zip', '.env'];
ftp_upload_recursive($conn, $appRoot, '.', $excludeList);

echo "\n[4/4] Creating remote production .env configuration ...\n";
$remoteEnv = "APP_NAME=FridgeRescue\n";
$remoteEnv .= "APP_ENV=production\n";
$remoteEnv .= "APP_URL=" . ($siteUrl ?: 'https://' . $ftpUser . '.infinityfreeapp.com') . "\n\n";
$remoteEnv .= "DB_HOST=" . ($dbHost ?: '127.0.0.1') . "\n";
$remoteEnv .= "DB_PORT=3306\n";
$remoteEnv .= "DB_NAME=" . $dbName . "\n";
$remoteEnv .= "DB_USER=" . ($dbUser ?: $ftpUser) . "\n";
$remoteEnv .= "DB_PASS=" . $dbPass . "\n\n";
$remoteEnv .= "GEMINI_API_KEY=" . $geminiKey . "\n";
$remoteEnv .= "GEMINI_MODEL=gemini-2.5-flash\n";

$tempEnvFile = tempnam(sys_get_temp_dir(), 'env_');
file_put_contents($tempEnvFile, $remoteEnv);

if (@ftp_put($conn, '.env', $tempEnvFile, FTP_ASCII)) {
    echo "      Remote .env configured and uploaded successfully!\n";
} else {
    echo "      Warning: Could not create remote .env via FTP.\n";
}
@unlink($tempEnvFile);

ftp_close($conn);

echo "\n=========================================================\n";
echo "🎉 DEPLOYMENT COMPLETE!\n";
echo "=========================================================\n";
if (!empty($siteUrl)) {
    echo "Next: Visit " . rtrim($siteUrl, '/') . "/setup_db.php?auto=1\n";
    echo "to automatically initialize your database with fresh demo items!\n";
} else {
    echo "Next: Visit your domain and open /setup_db.php to finish database initialization.\n";
}
echo "=========================================================\n\n";
