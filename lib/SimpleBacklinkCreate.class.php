<?php

class SimpleBacklinkCreate
{
    private $target = '';
    private $targetValid = false;
    private $targetError = '';
    private $urls = [];
    private $results = [];
    private $anchorText = null;
    private $strategy = 'page_template';

    private $rel = 'nofollow';
    private $targetBlank = false;

    public function setTarget($target)
    {
        $target = trim((string)$target);

        // Validate target as URL
        if ($target !== '' && filter_var($target, FILTER_VALIDATE_URL)) {
            $this->target = $target;
            $this->targetValid = true;
            $this->targetError = '';
        } else {
            $this->target = $target;
            $this->targetValid = false;
            $this->targetError = 'Target URL is invalid.';
        }
    }

    public function getTarget()
    {
        return $this->target;
    }

    public function getTargetError()
    {
        return $this->targetError;
    }

    public function setUrls($urls)
    {
        // Accept raw string-lines split by caller; also tolerate URL-encoded strings
        if (!is_array($urls)) {
            $urls = [$urls];
        }

        $urls = array_map(function ($url) {
            $url = trim((string)$url);
            $url = trim($url, "\r\t\n ");
            return $url;
        }, $urls);

        // Remove empty lines
        $urls = array_filter($urls, function ($url) {
            return $url !== '';
        });

        // If a caller accidentally passes a URL-encoded multi-line blob, decode and split
        $decoded = [];
        foreach ($urls as $u) {
            $maybeDecoded = urldecode($u);
            if (strpos($maybeDecoded, "\n") !== false || strpos($maybeDecoded, "\r") !== false) {
                $parts = preg_split("/\r\n|\n|\r/", $maybeDecoded);
                foreach ($parts as $p) {
                    $p = trim((string)$p);
                    if ($p !== '') {
                        $decoded[] = $p;
                    }
                }
            } else {
                $decoded[] = $maybeDecoded;
            }
        }

        $urls = array_filter($decoded, function ($url) {
            return filter_var($url, FILTER_VALIDATE_URL);
        });

        $this->urls = array_values($urls);
    }

    public function setAnchorText($anchorText)
    {
        if ($anchorText === null) {
            $this->anchorText = null;
            return;
        }
        $anchorText = trim((string)$anchorText);
        $this->anchorText = $anchorText !== '' ? $anchorText : null;
    }

    public function setStrategy($strategy)
    {
        $strategy = trim((string)$strategy);
        $allowed = ['html_snippet', 'page_template'];

        if (!in_array($strategy, $allowed, true)) {
            $strategy = 'page_template';
        }

        $this->strategy = $strategy;
    }

    public function setRel($rel)
    {
        $rel = strtolower(trim((string)$rel));
        $allowed = ['nofollow', 'dofollow', 'sponsored', 'ugc', ''];
        if (!in_array($rel, $allowed, true)) {
            $rel = 'nofollow';
        }

        $this->rel = $rel;
    }

    public function setTargetBlank($targetBlank)
    {
        $this->targetBlank = (bool)$targetBlank;
    }

    public function process()
    {
        $this->results = [];

        if (!$this->targetValid) {
            // Keep results empty; process.php will decide how to render the error
            return;
        }

        $anchor = $this->getAnchorText();
        $targetEsc = $this->target; // validated; escape later

        foreach ($this->urls as $sourceUrl) {
            $content = $this->generateForSourceUrl($sourceUrl, $anchor, $targetEsc);

            $this->results[$sourceUrl] = [
                'content' => $content
            ];
        }
    }

    public function getResults()
    {
        return $this->results;
    }

    private function getAnchorText()
    {
        if (is_string($this->anchorText) && $this->anchorText !== '') {
            return $this->anchorText;
        }

        $host = parse_url($this->target, PHP_URL_HOST);
        $host = $host ? strtolower($host) : 'your site';

        // Strip only leading www.
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }

        return $host;
    }

    private function generateForSourceUrl($sourceUrl, $anchor, $target)
    {
        $href = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');
        $aText = htmlspecialchars($anchor, ENT_QUOTES, 'UTF-8');

        $relAttr = '';
        if ($this->rel === 'dofollow') {
            $relAttr = ''; // omit rel for default dofollow
        } elseif ($this->rel === '' || $this->rel === 'nofollow') {
            $relAttr = ' rel="nofollow"';
        } else {
            // sponsored / ugc etc.
            $relAttr = ' rel="' . htmlspecialchars($this->rel, ENT_QUOTES, 'UTF-8') . '"';
        }

        $targetAttr = '';
        $noopener = ' rel="' . htmlspecialchars(trim($this->rel) ?: 'nofollow', ENT_QUOTES, 'UTF-8') . '"';
        if ($this->targetBlank) {
            // If target blank, add noopener/noreferrer; keep rel already handled above where possible
            $targetAttr = ' target="_blank"';
            if ($relAttr === '') {
                // If rel omitted (dofollow), still add noopener/noreferrer without overriding rel semantics
                $noopener = ' rel="nofollow noopener noreferrer"';
            } else {
                $relVal = $this->rel;
                if ($relVal === 'dofollow') {
                    $noopener = ' rel="noopener noreferrer"';
                } else {
                    // Append noopener/noreferrer to existing rel
                    $noopener = ' rel="' . $relVal . ' noopener noreferrer"';
                }
            }
        }

        if ($this->targetBlank) {
            $snippet = '<a href="' . $href . '"' . $targetAttr . $noopener . '>' . $aText . '</a>';
        } else {
            $snippet = '<a href="' . $href . '"' . $relAttr . '>' . $aText . '</a>';
        }

        if ($this->strategy === 'html_snippet') {
            return $snippet;
        }

        return '<!doctype html>' .
            '<html>' .
            '<head>' .
            '<meta charset="utf-8">' .
            '<title>Backlink</title>' .
            '</head>' .
            '<body>' .
            '<p>Example backlink:</p>' .
            '<p>' . $snippet . '</p>' .
            '<!-- Source URL (for reference): ' . htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8') . ' -->' .
            '</body>' .
            '</html>';
    }
}

?>
