<?php
/**
 * ProcessWire One-File-Installer / Loader (Multi-Language)
 *
 * This script downloads the latest version of ProcessWire (Master or Dev),
 * extracts it to the current directory, and prepares the official installer.
 *
 * @author Markus Thomas
 * @license MIT
 * @version 1.0.1
 *
 */

declare(strict_types=1);

// Check if ProcessWire is already installed
$isInstalled = file_exists(__DIR__ . '/site/assets/installed.php');

// Check PHP Version
$minPhpVersion = '7.4.0';
$currentPhpVersion = PHP_VERSION;
$isPhpVersionOk = version_compare($currentPhpVersion, $minPhpVersion, '>=');

// Detect Browser Language
$validLangs = ['en', 'de', 'es', 'fr'];
$browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
$initialLang = in_array($browserLang, $validLangs) ? $browserLang : 'en';

// -----------------------------------------------------------------------------
// PHP BACKEND LOGIC
// -----------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    if ($isInstalled && $action !== 'cleanup') {
        echo json_encode([
            'success' => false,
            'message' => 'ProcessWire is already installed.',
            'step' => 'error'
        ]);
        exit;
    }

    if (!$isPhpVersionOk) {
        echo json_encode([
            'success' => false,
            'message' => "PHP version {$minPhpVersion} or greater is required. Current version: {$currentPhpVersion}",
            'step' => 'error'
        ]);
        exit;
    }

    // Language Handling for Backend Responses
    $langCode = $_POST['lang'] ?? 'en';
    $validLangs = ['en', 'de', 'es', 'fr'];
    if (!in_array($langCode, $validLangs))
        $langCode = 'en';

    // Backend Translations
    $phpt = [
        'en' => [
            'invalid_branch' => 'Invalid branch selected.',
            'zip_missing' => 'PHP ZipArchive extension is missing. Please enable it.',
            'dir_not_writable' => 'The current directory is not writable. Please check permissions.',
            'file_open_err' => 'Could not open file for writing: ',
            'curl_err' => 'CURL Error: ',
            'download_fail' => 'Download failed or file is corrupt.',
            'download_ok' => 'Download successful.',
            'zip_not_found' => 'ZIP file not found. Please restart.',
            'unzip_fail' => 'Failed to unzip the archive.',
            'move_fail' => 'Could not move file to root: ',
            'extract_dir_err' => 'Extracted directory not found.',
            'extract_ok' => 'ProcessWire extracted successfully.',
            'delete_fail' => 'Could not delete file: ',
        ],
        'de' => [
            'invalid_branch' => 'Ungültiger Branch ausgewählt.',
            'zip_missing' => 'PHP ZipArchive Erweiterung fehlt. Bitte aktivieren.',
            'dir_not_writable' => 'Das aktuelle Verzeichnis ist nicht beschreibbar. Bitte Berechtigungen prüfen.',
            'file_open_err' => 'Konnte Datei nicht zum Schreiben öffnen: ',
            'curl_err' => 'CURL Fehler: ',
            'download_fail' => 'Download fehlgeschlagen oder Datei beschädigt.',
            'download_ok' => 'Download erfolgreich.',
            'zip_not_found' => 'ZIP-Datei nicht gefunden. Bitte neu starten.',
            'unzip_fail' => 'Entpacken des Archivs fehlgeschlagen.',
            'move_fail' => 'Konnte Datei nicht verschieben: ',
            'extract_dir_err' => 'Entpacktes Verzeichnis nicht gefunden.',
            'extract_ok' => 'ProcessWire erfolgreich entpackt.',
            'delete_fail' => 'Konnte Datei nicht löschen: ',
        ],
        'es' => [
            'invalid_branch' => 'Rama seleccionada inválida.',
            'zip_missing' => 'Falta la extensión PHP ZipArchive. Por favor, actívela.',
            'dir_not_writable' => 'El directorio actual no es escribible. Por favor verifique permisos.',
            'file_open_err' => 'No se pudo abrir el archivo para escribir: ',
            'curl_err' => 'Error CURL: ',
            'download_fail' => 'La descarga falló o el archivo está corrupto.',
            'download_ok' => 'Descarga exitosa.',
            'zip_not_found' => 'Archivo ZIP no encontrado. Por favor reinicie.',
            'unzip_fail' => 'Fallo al descomprimir el archivo.',
            'move_fail' => 'No se pudo mover el archivo a la raíz: ',
            'extract_dir_err' => 'Directorio extraído no encontrado.',
            'extract_ok' => 'ProcessWire extraído con éxito.',
            'delete_fail' => 'No se pudo eliminar el archivo: ',
        ],
        'fr' => [
            'invalid_branch' => 'Branche sélectionnée invalide.',
            'zip_missing' => 'L\'extension PHP ZipArchive est manquante. Veuillez l\'activer.',
            'dir_not_writable' => 'Le répertoire actuel n\'est pas accessible en écriture. Veuillez vérifier les permissions.',
            'file_open_err' => 'Impossible d\'ouvrir le fichier pour l\'écriture : ',
            'curl_err' => 'Erreur CURL : ',
            'download_fail' => 'Le téléchargement a échoué ou le fichier est corrompu.',
            'download_ok' => 'Téléchargement réussi.',
            'zip_not_found' => 'Fichier ZIP introuvable. Veuillez recommencer.',
            'unzip_fail' => 'Échec de la décompression de l\'archive.',
            'move_fail' => 'Impossible de déplacer le fichier vers la racine : ',
            'extract_dir_err' => 'Répertoire extrait introuvable.',
            'extract_ok' => 'ProcessWire extrait avec succès.',
            'delete_fail' => 'Impossible de supprimer le fichier : ',
        ],
    ];

    // Helper to get text
    $msg = function ($key, $suffix = '') use ($phpt, $langCode) {
        return ($phpt[$langCode][$key] ?? $phpt['en'][$key]) . $suffix;
    };

    $response = [
        'success' => false,
        'message' => '',
        'step' => 'init'
    ];

    try {
        $branch = $_POST['branch'] ?? 'master';

        // --- ACTION: CHECK VERSIONS ---
        if ($action === 'check_versions') {
            $request = function ($url) {
                $data = null;
                if (function_exists('curl_init')) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'ProcessWire-Installer');
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $data = curl_exec($ch);
                    curl_close($ch);
                }
                if (!$data && ini_get('allow_url_fopen')) {
                    $opts = [
                        'http' => ['method' => 'GET', 'header' => 'User-Agent: ProcessWire-Installer', 'timeout' => 5],
                        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
                    ];
                    $data = @file_get_contents($url, false, stream_context_create($opts));
                }
                return $data;
            };

            // Helper to parse package.json
            $parsePackage = function ($data) {
                $json = json_decode($data, true);
                return is_array($json) ? ($json['version'] ?? '') : '';
            };

            // Master: Try Tags API first
            $masterVersion = '';
            $availableTags = [];
            $tagsJson = $request('https://api.github.com/repos/processwire/processwire/tags');
            if ($tagsJson) {
                $tags = json_decode($tagsJson, true);
                if (is_array($tags)) {
                    foreach ($tags as $t) {
                        if (isset($t['name']))
                            $availableTags[] = $t['name'];
                    }
                    if (isset($tags[0]['name'])) {
                        $masterVersion = ltrim($tags[0]['name'], 'v');
                    }
                }
            }
            // Fallback Master: package.json
            if (!$masterVersion) {
                $pkg = $request('https://raw.githubusercontent.com/processwire/processwire/master/package.json');
                if ($pkg)
                    $masterVersion = $parsePackage($pkg);
            }

            // Dev: package.json (always best for dev branch)
            $devVersion = '';
            $pkgDev = $request('https://raw.githubusercontent.com/processwire/processwire/dev/package.json');
            if ($pkgDev)
                $devVersion = $parsePackage($pkgDev);

            // Fallback: Try API Contents if raw failed (often blocked on shared hosts)
            if (!$masterVersion) {
                $api = $request('https://api.github.com/repos/processwire/processwire/contents/package.json?ref=master');
                if ($api) {
                    $json = json_decode($api, true);
                    if (isset($json['content']))
                        $masterVersion = $parsePackage(base64_decode($json['content']));
                }
            }
            if (!$devVersion) {
                $api = $request('https://api.github.com/repos/processwire/processwire/contents/package.json?ref=dev');
                if ($api) {
                    $json = json_decode($api, true);
                    if (isset($json['content']))
                        $devVersion = $parsePackage(base64_decode($json['content']));
                }
            }

            echo json_encode(['success' => true, 'versions' => ['master' => $masterVersion, 'dev' => $devVersion], 'tags' => $availableTags]);
            exit;
        }

        // --- ACTION: CHECK FILES ---
        if ($action === 'check_files') {
            $files = scandir(__DIR__);
            $foundFiles = [];
            $ignore = ['.', '..', basename(__FILE__)];
            foreach ($files as $file) {
                if (!in_array($file, $ignore)) {
                    if (strpos($file, 'processwire-') === 0 && substr($file, -4) === '.zip')
                        continue;
                    $foundFiles[] = [
                        'name' => $file,
                        'type' => is_dir(__DIR__ . '/' . $file) ? 'dir' : 'file'
                    ];
                }
            }
            usort($foundFiles, function ($a, $b) {
                if ($a['type'] === $b['type'])
                    return strcasecmp($a['name'], $b['name']);
                return $a['type'] === 'dir' ? -1 : 1;
            });
            echo json_encode(['success' => true, 'hasFiles' => count($foundFiles) > 0, 'files' => $foundFiles]);
            exit;
        }

        // Validation
        if (!in_array($branch, ['master', 'dev']) && !preg_match('/^[vV]?\d+(\.\d+)*(-[a-zA-Z0-9]+)?$/', $branch)) {
            throw new Exception($msg('invalid_branch'));
        }

        // Check requirements
        if (!class_exists('ZipArchive')) {
            throw new Exception($msg('zip_missing'));
        }
        if (!is_writable(__DIR__)) {
            throw new Exception($msg('dir_not_writable'));
        }

        $zipUrl = "https://github.com/processwire/processwire/archive/{$branch}.zip";
        $zipFile = __DIR__ . "/processwire-{$branch}.zip";
        $extractFolder = __DIR__ . "/processwire-{$branch}";

        // --- STEP 1: DOWNLOAD ---
        if ($action === 'download') {
            $fp = fopen($zipFile, 'w+');
            if ($fp === false) {
                throw new Exception($msg('file_open_err', $zipFile));
            }

            $ch = curl_init($zipUrl);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'ProcessWire-Loader-Script');

            if (!curl_exec($ch)) {
                throw new Exception($msg('curl_err') . curl_error($ch));
            }
            curl_close($ch);
            fclose($fp);

            if (!file_exists($zipFile) || filesize($zipFile) < 10000) {
                throw new Exception($msg('download_fail'));
            }

            $response['success'] = true;
            $response['message'] = $msg('download_ok');
            $response['step'] = 'extract';
        }

        // --- STEP 2: EXTRACT & INSTALL ---
        elseif ($action === 'extract') {

            if (!file_exists($zipFile)) {
                throw new Exception($msg('zip_not_found'));
            }

            $zip = new ZipArchive;
            if ($zip->open($zipFile) === TRUE) {
                $zip->extractTo(__DIR__);
                $zip->close();
            } else {
                throw new Exception($msg('unzip_fail'));
            }

            // Move files from extracted folder to root
            $sourceDir = $extractFolder;

            // Safety check if GitHub folder naming changes
            if (!is_dir($sourceDir)) {
                $dirs = glob(__DIR__ . '/processwire-*', GLOB_ONLYDIR);
                if (isset($dirs[0]))
                    $sourceDir = $dirs[0];
            }

            if (is_dir($sourceDir)) {
                $files = scandir($sourceDir);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..')
                        continue;

                    $src = $sourceDir . '/' . $file;
                    $dest = __DIR__ . '/' . $file;

                    if ($file === basename(__FILE__))
                        continue;

                    if (file_exists($dest)) {
                        if (is_dir($dest)) {
                            $iterator = new RecursiveIteratorIterator(
                                new RecursiveDirectoryIterator($dest, RecursiveDirectoryIterator::SKIP_DOTS),
                                RecursiveIteratorIterator::CHILD_FIRST
                            );
                            foreach ($iterator as $path) {
                                $path->isDir() ? @rmdir($path->getPathname()) : @unlink($path->getPathname());
                            }
                            @rmdir($dest);
                        } else {
                            @unlink($dest);
                        }
                    }

                    if (!rename($src, $dest)) {
                        if (is_dir($src)) {
                            throw new Exception($msg('move_fail', $file));
                        }
                    }
                }
                // Cleanup
                @rmdir($sourceDir);
                @unlink($zipFile);
            } else {
                throw new Exception($msg('extract_dir_err'));
            }

            $response['success'] = true;
            $response['message'] = $msg('extract_ok');
            $response['step'] = 'done';
        }

        // --- ACTION: CLEANUP ---
        elseif ($action === 'cleanup') {
            ignore_user_abort(true);
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate(__FILE__, true);
            }

            if (file_exists(__FILE__)) {
                @unlink(__FILE__);
            }
            if (file_exists(__FILE__)) {
                echo json_encode(['success' => false, 'message' => $msg('delete_fail') . basename(__FILE__)]);
            } else {
                echo json_encode(['success' => true]);
            }
            exit;
        }

    } catch (Throwable $e) {
        $response['success'] = false;
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProcessWire Installer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
    <style>
        [x-cloak] {
            display: none !important;
        }

        body {
            background-color: #f3f4f6;
        }

        .pw-gradient {
            background: linear-gradient(135deg, #2480e6 0%, rgb(18, 101, 161) 100%);
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen font-sans text-gray-800">

    <div x-data="installer(<?php echo $isInstalled ? 'true' : 'false'; ?>, <?php echo $isPhpVersionOk ? 'true' : 'false'; ?>, '<?php echo $currentPhpVersion; ?>', '<?php echo $initialLang; ?>')" x-cloak class="relative w-full max-w-lg overflow-hidden bg-white shadow-2xl rounded-xl">

        <!-- Language Switcher (Top Right) -->
        <div class="absolute z-10 flex items-center gap-2 top-4 right-4">
            <svg class="w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
                <path d="M3.6 9h16.8" />
                <path d="M3.6 15h16.8" />
                <path d="M11.5 3a17 17 0 0 0 0 18" />
                <path d="M12.5 3a17 17 0 0 1 0 18" />
            </svg>
            <select x-model="lang" class="px-2 py-1 text-xs text-white border rounded cursor-pointer bg-white/20 hover:bg-white/30 border-white/40 focus:outline-none focus:ring-1 focus:ring-white backdrop-blur-sm">
                <option value="en" class="text-gray-800">English</option>
                <option value="de" class="text-gray-800">Deutsch</option>
                <option value="es" class="text-gray-800">Español</option>
                <option value="fr" class="text-gray-800">Français</option>
            </select>
        </div>

        <!-- Header -->
        <div class="p-8 text-center text-white pw-gradient">
            <svg class="w-16 h-16 mx-auto mb-4 fill-current" role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <title>ProcessWire logo</title>
                <path style="fill:#fff" d="M21.939 5.27C21.211 4.183 20 2.941 18.784 2.137 16.258.407 13.332-.207 10.744.061c-2.699.291-5.01 1.308-6.91 3.004C2.074 4.637.912 6.559.4 8.392c-.518 1.833-.449 3.53-.264 4.808.195 1.297.841 2.929.841 2.929.132.313.315.44.41.493.472.258 1.247.031 1.842-.637.03-.041.046-.098.03-.146-.166-.639-.226-1.12-.285-1.492-.135-.736-.195-1.969-.105-3.109.045-.617.165-1.277.375-1.969.406-1.367 1.262-2.794 2.6-3.98 1.441-1.277 3.289-2.066 5.046-2.27.616-.074 1.788-.145 3.199.203.301.075 1.593.412 2.975 1.348 1.006.684 1.816 1.528 2.374 2.363.568.797 1.185 2.141 1.366 3.125.256 1.12.256 2.307.074 3.463-.225 1.158-.631 2.284-1.262 3.275-.435.768-1.337 1.783-2.403 2.545-.961.676-2.058 1.164-3.184 1.434-.57.135-1.142.221-1.728.24-.521.016-1.212 0-1.697-.082-.721-.115-.871-.299-1.036-.549 0 0-.115-.18-.147-.662.011-4.405.009-3.229.009-5.516 0-.646-.021-1.232-.015-1.764.03-.873.104-1.473.728-2.123.451-.479 1.082-.768 1.777-.768.211 0 .938.01 1.577.541.685.572.8 1.354.827 1.563.156 1.223-.652 2.134-.962 2.365-.384.288-.729.428-.962.51-.496.166-1.041.214-1.531.182-.075-.005-.143.044-.158.119l-.165.856c-.161.65.2.888.41.972.671.207 1.266.293 1.971.24 1.081-.076 2.147-.502 3.052-1.346.77-.732 1.209-1.635 1.359-2.645.15-1.121-.045-2.328-.556-3.35-.562-1.127-1.532-2.068-2.81-2.583-1.291-.508-2.318-.526-3.642-.188l-.015.005c-.86.296-1.596.661-2.362 1.452-.525.546-.955 1.207-1.217 1.953-.26.752-.33 1.313-.342 2.185-.016.646.015 1.246.015 1.808v3.701c0 1.184-.04 1.389 0 1.998.022.404.078.861.255 1.352.182.541.564 1.096.826 1.352.367.391.834.705 1.293.9 1.051.467 2.478.541 3.635.496.766-.029 1.536-.135 2.291-.314 1.51-.359 2.96-1.012 4.235-1.918 1.367-.963 2.555-2.277 3.211-3.393.841-1.326 1.385-2.814 1.668-4.343.255-1.532.243-3.103-.099-4.612-.27-1.4-.991-2.936-1.823-4.176l.038.037z" />
            </svg>
            <h1 class="text-3xl font-bold" x-text="t('title')">ProcessWire</h1>
            <p class="text-white/80" x-text="t('subtitle')">One-File Installer</p>
        </div>

        <!-- Content -->
        <div class="p-8">

            <!-- ERROR ALERT -->
            <div x-show="error" x-transition class="p-4 mb-6 text-red-700 border-l-4 border-red-500 bg-red-50">
                <p class="font-bold" x-text="t('error_title')">Error</p>
                <p x-text="error"></p>
                <button @click="reset()" class="mt-2 text-sm underline hover:text-red-900" x-text="t('try_again')">Try Again</button>
            </div>

            <!-- STEP: PHP ERROR -->
            <div x-show="step === 'php_error'" x-transition class="py-4 text-center">
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 text-red-500 bg-red-100 rounded-full">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h2 class="mb-2 text-2xl font-bold text-gray-800" x-text="t('php_error_title')">PHP Version too old</h2>
                <p class="mb-6 text-gray-600">
                    <span x-text="t('php_error_msg')">ProcessWire requires at least PHP 7.4.</span><br>
                    <span class="text-sm text-gray-500" x-text="t('current_php') + ' ' + phpVersion"></span>
                </p>
            </div>

            <!-- STEP: INSTALLED -->
            <div x-show="step === 'installed'" x-transition class="py-4 text-center">
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 text-blue-500 bg-blue-100 rounded-full">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h2 class="mb-2 text-2xl font-bold text-gray-800" x-text="t('installed_title')">Installation detected</h2>
                <p class="mb-6 text-gray-600" x-text="t('installed_msg')">ProcessWire appears to be already installed here.</p>
                <a href="./index.php" @click.prevent="cleanupAndStart('./index.php')" class="inline-block w-full px-4 py-3 font-bold text-white transition duration-200 bg-gray-900 rounded-lg shadow-md hover:bg-gray-800" x-text="t('btn_site')">Open Site</a>
            </div>

            <!-- STEP: OVERWRITE CONFIRM -->
            <div x-show="step === 'confirm_overwrite'" x-transition class="py-4 text-center">
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 text-yellow-500 bg-yellow-100 rounded-full">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h2 class="mb-2 text-2xl font-bold text-gray-800" x-text="t('overwrite_title')">Directory not empty</h2>
                <p class="mb-4 text-gray-600" x-text="t('overwrite_msg')">This directory contains files that might be overwritten.</p>

                <div class="p-3 mb-6 overflow-y-auto text-left border border-gray-200 rounded bg-gray-50 max-h-32">
                    <ul class="text-sm text-gray-600">
                        <template x-for="file in fileList" :key="file.name">
                            <li class="flex items-center py-0.5">
                                <template x-if="file.type === 'dir'">
                                    <svg class="flex-shrink-0 w-4 h-4 mr-2 text-yellow-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                                    </svg>
                                </template>
                                <template x-if="file.type === 'file'">
                                    <svg class="flex-shrink-0 w-4 h-4 mr-2 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                    </svg>
                                </template>
                                <span x-text="file.name" :class="file.type === 'dir' ? 'font-medium text-gray-700' : ''" class="truncate"></span>
                            </li>
                        </template>
                    </ul>
                </div>

                <div class="flex gap-4">
                    <button @click="reset()" class="w-1/2 px-4 py-3 font-bold text-gray-700 transition duration-200 bg-gray-200 rounded-lg hover:bg-gray-300" x-text="t('btn_cancel')">Cancel</button>
                    <button @click="performDownload()" class="w-1/2 px-4 py-3 font-bold text-white transition duration-200 bg-yellow-600 rounded-lg shadow-md hover:bg-yellow-700" x-text="t('btn_overwrite')">Overwrite</button>
                </div>
            </div>

            <!-- STEP 1: SELECTION -->
            <div x-show="step === 'select'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                <p class="mb-4 text-gray-600" x-text="t('choose_version')">Choose your version:</p>

                <div class="space-y-3">
                    <label class="flex items-center p-4 transition-colors border rounded-lg cursor-pointer hover:bg-gray-50" :class="{'border-blue-500 bg-blue-50': installType === 'master'}">
                        <input type="radio" name="installType" value="master" x-model="installType" class="w-5 h-5 text-blue-600 border-gray-300 focus:ring-blue-500">
                        <div class="ml-3">
                            <span class="block text-lg font-medium text-gray-900"><span x-text="t('master_version')">Master Version</span> <span x-show="masterVersion" x-text="'v' + masterVersion" class="ml-2 text-sm text-gray-500 bg-gray-200 px-2 py-0.5 rounded-full"></span></span>
                            <span class="block text-sm text-gray-500" x-text="t('master_desc')">Stable release (Recommended)</span>
                        </div>
                    </label>

                    <label class="flex items-center p-4 transition-colors border rounded-lg cursor-pointer hover:bg-gray-50" :class="{'border-blue-500 bg-blue-50': installType === 'dev'}">
                        <input type="radio" name="installType" value="dev" x-model="installType" class="w-5 h-5 text-blue-600 border-gray-300 focus:ring-blue-500">
                        <div class="ml-3">
                            <span class="block text-lg font-medium text-gray-900"><span x-text="t('dev_version')">Dev Version</span> <span x-show="devVersion" x-text="'v' + devVersion" class="ml-2 text-sm text-gray-500 bg-gray-200 px-2 py-0.5 rounded-full"></span></span>
                            <span class="block text-sm text-gray-500" x-text="t('dev_desc')">Latest features (Nightly)</span>
                        </div>
                    </label>

                    <label class="flex items-start p-4 transition-colors border rounded-lg cursor-pointer hover:bg-gray-50" :class="{'border-blue-500 bg-blue-50': installType === 'tag'}">
                        <input type="radio" name="installType" value="tag" x-model="installType" class="w-5 h-5 mt-1 text-blue-600 border-gray-300 focus:ring-blue-500">
                        <div class="w-full ml-3">
                            <span class="block text-lg font-medium text-gray-900" x-text="t('tag_version')">Specific Version</span>
                            <span class="block text-sm text-gray-500" x-text="t('tag_desc')">Select an older release</span>

                            <div x-show="installType === 'tag'" class="mt-3" @click.stop>
                                <select x-model="selectedTag" class="w-full p-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <template x-for="tag in tags" :key="tag">
                                        <option :value="tag" x-text="tag"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </label>
                </div>

                <button @click="startInstall()" class="w-full mt-6 bg-gray-900 hover:bg-gray-800 text-white font-bold py-3 px-4 rounded-lg transition duration-200 shadow-md transform hover:-translate-y-0.5" x-text="t('btn_install')">
                    Download & Install
                </button>
            </div>

            <!-- STEP 2: PROCESSING (Download/Extract) -->
            <div x-show="step === 'processing'" class="py-8 text-center">
                <div class="relative w-16 h-16 mx-auto mb-4">
                    <div class="w-16 h-16 border-4 border-gray-200 rounded-full loading-circle"></div>
                    <div class="absolute top-0 left-0 w-16 h-16 border-4 border-blue-500 rounded-full loading-circle-fill border-t-transparent animate-spin"></div>
                </div>
                <h3 class="mb-2 text-xl font-semibold text-gray-800" x-text="statusTitle"></h3>
                <p class="text-gray-500" x-text="statusMessage"></p>

                <!-- Progress Bar -->
                <div class="w-full bg-gray-200 rounded-full h-2.5 mt-6">
                    <div class="progress-bar bg-blue-600 h-2.5 rounded-full" style="width: 0%"></div>
                </div>
            </div>

            <!-- STEP 3: DONE -->
            <div x-show="step === 'done'" class="py-4 text-center" style="display: none;">
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 text-green-500 bg-green-100 rounded-full">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h2 class="mb-2 text-2xl font-bold text-gray-800" x-text="t('success_title')">Ready to setup!</h2>
                <p class="mb-6 text-gray-600" x-text="t('success_msg')">ProcessWire files have been extracted.</p>

                <a href="./install.php" @click.prevent="cleanupAndStart()" class="inline-block w-full px-4 py-3 font-bold text-white transition duration-200 bg-green-600 rounded-lg shadow-md hover:bg-green-700" x-text="t('btn_start')">
                    Start ProcessWire Installer
                </a>
            </div>

        </div>

        <!-- Footer -->
        <div class="px-8 py-4 text-xs text-center text-gray-400 border-t bg-gray-50">
            &copy; <?php echo date("Y"); ?> <span x-text="t('footer')">ProcessWire Installer Helper</span>
        </div>
    </div>

    <script>
        function installer(isInstalled = false, isPhpVersionOk = true, phpVersion = '', initialLang = 'en') {
            return {
                step: !isPhpVersionOk ? 'php_error' : (isInstalled ? 'installed' : 'select'),
                phpVersion: phpVersion,
                masterVersion: '',
                devVersion: '',
                branch: 'master',
                installType: 'master',
                fileList: [],
                tags: [],
                selectedTag: '',
                lang: initialLang,
                error: null,
                statusTitle: '',
                statusMessage: '',

                translations: {
                    en: {
                        title: 'ProcessWire',
                        subtitle: 'One-File Installer',
                        choose_version: 'Choose your version:',
                        master_version: 'Master Version',
                        master_desc: 'Stable release (Recommended)',
                        dev_version: 'Dev Version',
                        dev_desc: 'Latest features (Nightly)',
                        tag_version: 'Specific Version',
                        tag_desc: 'Select an older release',
                        btn_install: 'Download & Install',
                        status_init: 'Please wait...',
                        status_init_msg: 'Initializing...',
                        status_dl: 'Downloading...',
                        status_dl_msg: 'Fetching ZIP from GitHub...',
                        status_ex: 'Extracting...',
                        status_ex_msg: 'Unzipping and moving files...',
                        success_title: 'Ready to setup!',
                        success_msg: 'ProcessWire files have been extracted.',
                        btn_start: 'Start ProcessWire Installer',
                        error_title: 'Error',
                        try_again: 'Try Again',
                        footer: 'ProcessWire Installer Helper',
                        installed_title: 'Installation detected',
                        installed_msg: 'ProcessWire appears to be already installed in this directory.',
                        btn_site: 'Open Site',
                        php_error_title: 'PHP Version too old',
                        php_error_msg: 'ProcessWire requires at least PHP 7.4.',
                        current_php: 'Detected PHP version:',
                        overwrite_title: 'Directory not empty',
                        overwrite_msg: 'This directory contains files that might be overwritten. Do you want to proceed?',
                        btn_overwrite: 'Proceed',
                        btn_cancel: 'Cancel'
                    },
                    de: {
                        title: 'ProcessWire',
                        subtitle: 'One-File Installer',
                        choose_version: 'Wähle deine Version:',
                        master_version: 'Master Version',
                        master_desc: 'Stabile Version (Empfohlen)',
                        dev_version: 'Dev Version',
                        dev_desc: 'Neueste Features (Nightly)',
                        tag_version: 'Spezifische Version',
                        tag_desc: 'Wähle eine ältere Version',
                        btn_install: 'Herunterladen & Installieren',
                        status_init: 'Bitte warten...',
                        status_init_msg: 'Initialisiere...',
                        status_dl: 'Lade herunter...',
                        status_dl_msg: 'Lade ZIP von GitHub...',
                        status_ex: 'Entpacken...',
                        status_ex_msg: 'Entpacke und verschiebe Dateien...',
                        success_title: 'Bereit zur Installation!',
                        success_msg: 'ProcessWire Dateien wurden entpackt.',
                        btn_start: 'ProcessWire Installer starten',
                        error_title: 'Fehler',
                        try_again: 'Erneut versuchen',
                        footer: 'ProcessWire Installations-Helfer',
                        installed_title: 'Installation erkannt',
                        installed_msg: 'ProcessWire scheint hier bereits installiert zu sein.',
                        btn_site: 'Seite öffnen',
                        php_error_title: 'PHP-Version zu alt',
                        php_error_msg: 'ProcessWire benötigt mindestens PHP 7.4.',
                        current_php: 'Erkannte PHP-Version:',
                        overwrite_title: 'Verzeichnis nicht leer',
                        overwrite_msg: 'Dieses Verzeichnis enthält Dateien, die überschrieben werden könnten. Möchten Sie fortfahren?',
                        btn_overwrite: 'Fortfahren',
                        btn_cancel: 'Abbrechen'
                    },
                    es: {
                        title: 'ProcessWire',
                        subtitle: 'Instalador de un archivo',
                        choose_version: 'Elige tu versión:',
                        master_version: 'Versión Master',
                        master_desc: 'Versión estable (Recomendada)',
                        dev_version: 'Versión Dev',
                        dev_desc: 'Últimas características (Nightly)',
                        tag_version: 'Versión específica',
                        tag_desc: 'Seleccionar una versión anterior',
                        btn_install: 'Descargar e Instalar',
                        status_init: 'Por favor espere...',
                        status_init_msg: 'Inicializando...',
                        status_dl: 'Descargando...',
                        status_dl_msg: 'Obteniendo ZIP de GitHub...',
                        status_ex: 'Extrayendo...',
                        status_ex_msg: 'Descomprimiendo y moviendo archivos...',
                        success_title: '¡Listo para configurar!',
                        success_msg: 'Archivos de ProcessWire extraídos.',
                        btn_start: 'Iniciar instalador ProcessWire',
                        error_title: 'Error',
                        try_again: 'Intentar de nuevo',
                        footer: 'Asistente de instalación ProcessWire',
                        installed_title: 'Instalación detectada',
                        installed_msg: 'ProcessWire parece estar ya instalado aquí.',
                        btn_site: 'Abrir sitio',
                        php_error_title: 'Versión de PHP demasiado antigua',
                        php_error_msg: 'ProcessWire requiere al menos PHP 7.4.',
                        current_php: 'Versión de PHP detectada:',
                        overwrite_title: 'Directorio no vacío',
                        overwrite_msg: 'Este directorio contiene archivos que podrían ser sobrescritos. ¿Desea continuar?',
                        btn_overwrite: 'Continuar',
                        btn_cancel: 'Cancelar'
                    },
                    fr: {
                        title: 'ProcessWire',
                        subtitle: 'Installateur Fichier Unique',
                        choose_version: 'Choisissez votre version :',
                        master_version: 'Version Master',
                        master_desc: 'Version stable (Recommandée)',
                        dev_version: 'Version Dev',
                        dev_desc: 'Dernières fonctionnalités (Nightly)',
                        tag_version: 'Version spécifique',
                        tag_desc: 'Sélectionner une ancienne version',
                        btn_install: 'Télécharger et Installer',
                        status_init: 'Veuillez patienter...',
                        status_init_msg: 'Initialisation...',
                        status_dl: 'Téléchargement...',
                        status_dl_msg: 'Récupération du ZIP depuis GitHub...',
                        status_ex: 'Extraction...',
                        status_ex_msg: 'Décompression et déplacement...',
                        success_title: 'Prêt à installer !',
                        success_msg: 'Les fichiers ProcessWire sont extraits.',
                        btn_start: 'Lancer l\'installateur ProcessWire',
                        error_title: 'Erreur',
                        try_again: 'Réessayer',
                        footer: 'Assistant d\'installation ProcessWire',
                        installed_title: 'Installation détectée',
                        installed_msg: 'ProcessWire semble déjà installé ici.',
                        btn_site: 'Ouvrir le site',
                        php_error_title: 'Version PHP trop ancienne',
                        php_error_msg: 'ProcessWire nécessite au moins PHP 7.4.',
                        current_php: 'Version PHP détectée :',
                        overwrite_title: 'Répertoire non vide',
                        overwrite_msg: 'Ce répertoire contient des fichiers qui pourraient être écrasés. Voulez-vous continuer ?',
                        btn_overwrite: 'Continuer',
                        btn_cancel: 'Annuler'
                    }
                },

                t(key) {
                    return this.translations[this.lang][key] || key;
                },

                init() {
                    if (this.step === 'select') {
                        this.fetchVersions();
                    }
                    this.$watch('installType', value => {
                        if (value === 'master') this.branch = 'master';
                        else if (value === 'dev') this.branch = 'dev';
                        else if (value === 'tag') this.branch = this.selectedTag;
                    });
                    this.$watch('selectedTag', value => {
                        if (this.installType === 'tag') this.branch = value;
                    });
                },

                reset() {
                    this.step = 'select';
                    this.error = null;
                },

                startInstall() {
                    const formData = new FormData();
                    formData.append('action', 'check_files');

                    fetch(window.location.href, { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success && data.hasFiles) {
                                this.fileList = data.files || [];
                                this.step = 'confirm_overwrite';
                            } else {
                                this.performDownload();
                            }
                        })
                        .catch(() => this.performDownload());
                },

                performDownload() {
                    this.step = 'processing';
                    this.error = null;
                    this.statusTitle = this.t('status_dl');
                    this.statusMessage = this.t('status_dl_msg') + ' (' + this.branch + ')';

                    anime({
                        targets: '.progress-bar',
                        width: '50%',
                        easing: 'easeInOutQuad',
                        duration: 2000
                    });

                    this.request('download');
                },

                handleExtract() {
                    this.statusTitle = this.t('status_ex');
                    this.statusMessage = this.t('status_ex_msg');

                    anime({
                        targets: '.progress-bar',
                        width: '90%',
                        easing: 'easeInOutQuad',
                        duration: 1500
                    });

                    this.request('extract');
                },

                fetchVersions() {
                    const formData = new FormData();
                    formData.append('action', 'check_versions');

                    fetch(window.location.href, { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success && data.versions) {
                                this.masterVersion = data.versions.master;
                                this.devVersion = data.versions.dev;
                                if (data.tags && data.tags.length > 0) {
                                    this.tags = data.tags;
                                    this.selectedTag = data.tags[0];
                                }
                            }
                        }).catch(() => { });
                },

                finish() {
                    anime({
                        targets: '.progress-bar',
                        width: '100%',
                        easing: 'easeInOutQuad',
                        duration: 500,
                        complete: () => {
                            this.step = 'done';
                        }
                    });
                },

                cleanupAndStart(targetUrl = './install.php') {
                    const formData = new FormData();
                    formData.append('action', 'cleanup');
                    formData.append('branch', this.branch);
                    formData.append('lang', this.lang);

                    fetch(window.location.href, { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                window.location.href = targetUrl;
                            } else {
                                this.error = data.message;
                            }
                        })
                        .catch(e => {
                            this.error = e.message;
                        });
                },

                request(action) {
                    const formData = new FormData();
                    formData.append('action', action);
                    formData.append('branch', this.branch);
                    formData.append('lang', this.lang); // Send selected lang to backend

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => {
                            if (!response.ok) throw new Error('Network response was not ok');
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                if (data.step === 'extract') {
                                    this.handleExtract();
                                } else if (data.step === 'done') {
                                    this.finish();
                                }
                            } else {
                                throw new Error(data.message || 'Unknown error occurred');
                            }
                        })
                        .catch(err => {
                            this.step = 'select';
                            this.error = err.message;
                        });
                }
            }
        }
    </script>
</body>
</html>