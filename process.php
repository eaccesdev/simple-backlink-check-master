<?php
error_reporting(E_ALL);
ini_set('display_errors', 'on');

$mode = isset($_POST['mode']) ? trim((string)$_POST['mode']) : 'check';
$action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';

if ($mode === 'create') {
    require_once('lib/SimpleBacklinkCreate.class.php');

    $sbc = new SimpleBacklinkCreate();
    $sbc->setTarget($_POST['target'] ?? '');
    $sbc->setUrls(explode("\n", (string)($_POST['urls'] ?? '')));

    $strategy = $_POST['strategy'] ?? 'html_snippet';
    $sbc->setStrategy($strategy);

    $anchorText = $_POST['anchorText'] ?? null;
    $sbc->setAnchorText($anchorText);

    $rel = $_POST['rel'] ?? 'nofollow';
    $sbc->setRel($rel);

    $targetBlank = isset($_POST['targetBlank']) ? (bool)$_POST['targetBlank'] : false;
    $sbc->setTargetBlank($targetBlank);

    $sbc->process();
} else {
    require_once('lib/SimpleBacklinkCheck.class.php');

    $sbc = new SimpleBacklinkCheck();
    $sbc->setTarget($_POST['target'] ?? '');
    $sbc->setUrls(explode("\n", (string)($_POST['urls'] ?? '')));

    $sbc->process();
}
?>
<html>
<head>
    <title><?= $mode === 'create' ? 'Backlink Creator - Results' : 'Results SimpleBacklinkCheck' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" integrity="sha384-gfdkjb5BdAXd+lj+gudLWI+BXq4IuLW5IT+brZEZsLFm++aCMlF1V92rMkPaX4PP" crossorigin="anonymous">
</head>
<body class="bg-grey-light">
<div class="mt-4 p-4 container mx-auto bg-white rounded border shadow">
    <h1 class="pb-8 text-blue-dark"><?= $mode === 'create' ? 'Generated Backlinks' : 'Results SimpleBacklinkCheck' ?></h1>

    <div class="pb-4">
        Cible : <?= htmlspecialchars($sbc->getTarget(), ENT_QUOTES, 'UTF-8') ?>
    </div>

    <?php if ($mode === 'create') : ?>
        <?php
            $results = $sbc->getResults();
            $count = is_array($results) ? count($results) : 0;

            $renderLimit = 100;
            $shouldDownload = ($action === 'download_zip' && $count > 0);
            $hasTargetError = method_exists($sbc, 'getTargetError') && $sbc->getTargetError() !== '';
        ?>

        <?php if ($hasTargetError) : ?>
            <div class="pb-4 text-red-700 font-bold">
                <?= htmlspecialchars($sbc->getTargetError(), ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php else : ?>
            <div class="pb-4 note text-gray-700">
                Below are generated backlink HTML pages/snippets for each input URL.
            </div>

            <?php
                // Render ZIP download early (no big HTML responses)
                if ($shouldDownload) {
                    $zip = new ZipArchive();
                    $tmpFile = tempnam(sys_get_temp_dir(), 'backlink_');
                    $zipPath = $tmpFile . '.zip';
                    @unlink($tmpFile);

                    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                        echo '<div class="text-red-600 font-bold">ZIP generation failed.</div>';
                    } else {
                        $i = 0;
                        foreach ($results as $sourceUrl => $generated) {
                            $i++;
                            $content = isset($generated['content']) ? (string)$generated['content'] : '';
                            $name = 'backlink_' . $i . '.html';
                            $zip->addFromString($name, $content);
                        }
                        $zip->close();

                        header('Content-Type: application/zip');
                        header('Content-Disposition: attachment; filename="backlinks_' . date('Ymd_His') . '.zip"');
                        header('Content-Length: ' . filesize($zipPath));
                        readfile($zipPath);
                        @unlink($zipPath);
                        exit;
                    }
                }
            ?>

            <?php if ($count > 0) : ?>
                <?php if ($count > $renderLimit) : ?>
                    <div class="pb-4">
                        Showing first <?= $renderLimit ?> of <?= $count ?> generated pages/snippets.
                    </div>
                <?php endif; ?>

                <?php if ($count > 0) : ?>
                    <?php if ($count > 200) : ?>
                        <div class="pb-4">
                            <form method="post">
                                <input type="hidden" name="mode" value="create">
                                <input type="hidden" name="action" value="download_zip">
                                <input type="hidden" name="target" value="<?= htmlspecialchars($sbc->getTarget(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="urls" value="<?= htmlspecialchars($_POST['urls'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (!empty($_POST['strategy'])) : ?>
                                    <input type="hidden" name="strategy" value="<?= htmlspecialchars($_POST['strategy'], ENT_QUOTES, 'UTF-8') ?>">
                                <?php endif; ?>
                                <?php if (array_key_exists('anchorText', $_POST)) : ?>
                                    <input type="hidden" name="anchorText" value="<?= htmlspecialchars($_POST['anchorText'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <?php endif; ?>
                                <input type="hidden" name="rel" value="<?= htmlspecialchars($_POST['rel'] ?? 'nofollow', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="targetBlank" value="<?= !empty($_POST['targetBlank']) ? '1' : '0' ?>">
                                <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white font-bold">
                                    Download ZIP (<?= $count ?> files)
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php
            $lines = [];
                $i = 0;
                $firstLine = '';
                foreach ($results as $sourceUrl => $generated) {
                    $i++;
                    if ($i > $renderLimit) { break; }
                    $content = isset($generated['content']) ? (string)$generated['content'] : '';
                    if ($content !== '') {
                        // When using html_snippet strategy, this should already be a single-line <a ...>...</a>
                        $lines[] = $content;
                        if ($firstLine === '') {
                            $firstLine = $content;
                        }
                    }
                }
                $outputText = implode("\n", $lines);
            ?>

            <div class="pt-4 pb-2 note text-gray-700">
                Created <?= (int)$count ?> backlinks.
            </div>

            <?php if ($count > 0) : ?>
                <div class="pb-2 text-gray-700 text-sm">
                    First generated line (raw):
                    <div class="p-3 bg-gray-50 border rounded mt-2" style="white-space:pre-wrap; word-break:break-word;">
                        <?= $firstLine ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="pt-4 pb-2 note text-gray-700">
                Output (one backlink HTML line per pasted URL):
            </div>

            <div class="pb-4">
                <textarea
                    class="w-full"
                    name="output"
                    readonly
                    style="min-height: 240px; padding: 16px; border-radius: 18px; border: 1px solid rgba(91, 65, 135, 0.20); background: rgba(255,255,255,0.76); font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 12px;"
                ><?= htmlspecialchars($outputText, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <?php if ($count > $renderLimit) : ?>
                <div class="pb-4 text-gray-600">
                    Showing first <?= $renderLimit ?> backlinks of <?= $count ?> total. Download ZIP for the full set.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php else : ?>
        <table class="w-full">
            <tr>
                <th>URL</th>
                <th>Résultat</th>
            </tr>

            <?php foreach($sbc->getResults() as $url => $result) : ?>
                <tr>
                    <td><?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?= 
                            $result ? '<i class="text-green fas fa-check-circle"></i> oui'
                                    : '<i class="text-red fas fa-times-circle"></i> non';
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
