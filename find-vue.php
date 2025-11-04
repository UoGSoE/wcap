<?php
/**
 * check-vue.php
 *
 * Scan Blade templates for Vue usage with minimal false-positives.
 * - Looks only inside HTML tags.
 * - Matches v-*, @event=, :prop= (excluding xmlns/xlink/xml).
 * - Detects registered Vue components from resources/js/app.js.
 * - Skips Blade-like component tags: <x-...>, <flux:...>, <livewire:...>.
 * - Suppresses Vue shorthand (@..., :...) on tags that clearly use Livewire/Alpine.
 * - Skips vendor-ish paths (resources/views/vendor, vendor, node_modules) by default.
 *
 * PHP 7.4+ compatible.
 */

declare(strict_types=1);

$root      = __DIR__;
$bladeRoot = $root . '/resources/views';
$appJsPath = $root . '/resources/js/app.js';

/** CONFIG *******************************************************************/
$excludePaths = [
    $root . '/vendor',
    $root . '/node_modules',
    $bladeRoot . '/vendor', // vendor-published views
];
$flagCustomTags = true; // Set to false to only report definite Vue hits
/*****************************************************************************/

/** Polyfill for PHP < 8 str_starts_with */
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle !== '' && strpos($haystack, $needle) === 0;
    }
}

/** Path exclusion */
function pathIsExcluded(string $path, array $excludePaths): bool {
    $p = str_replace('\\', '/', $path);
    foreach ($excludePaths as $ex) {
        $ex = str_replace('\\', '/', $ex);
        if (strpos($p, rtrim($ex, '/')) === 0) return true;
    }
    return false;
}

/** Extract Vue component names from app.js (Vue 2 and Vue 3 styles) */
function loadVueComponentsFromAppJs(string $appJsPath): array {
    if (!is_file($appJsPath)) return [];
    $js = file_get_contents($appJsPath);

    // Vue.component("name", ...)
    preg_match_all('/Vue\.component\(\s*[\'"]([^\'"]+)[\'"]\s*,/i', $js, $m1);
    // app.component("name", ...) — Vue 3 style (just in case)
    preg_match_all('/\bapp\.component\(\s*[\'"]([^\'"]+)[\'"]\s*,/i', $js, $m2);

    $names = array_unique(array_filter(array_merge($m1[1] ?? [], $m2[1] ?? [])));

    // Convert PascalCase to kebab-case as an additional alias
    $extra = [];
    foreach ($names as $n) {
        if (strpos($n, '-') === false && preg_match('/[A-Z]/', $n)) {
            $kebab = strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $n));
            $extra[] = $kebab;
        }
    }
    return array_values(array_unique(array_merge($names, $extra)));
}

$vueComponents = loadVueComponentsFromAppJs($appJsPath);

/** Minimal set of standard HTML/SVG tag names to avoid false "custom-tag" flags */
$standardTags = array_flip([
    // HTML
    'html','head','title','meta','link','style','script','body','header','footer','nav','main','section','article','aside',
    'h1','h2','h3','h4','h5','h6','p','div','span','a','ul','ol','li','dl','dt','dd','table','thead','tbody','tfoot','tr','td','th',
    'form','label','input','textarea','select','option','button','fieldset','legend','datalist','output','progress','meter',
    'img','picture','source','figure','figcaption','canvas','iframe','video','audio','track','map','area','blockquote','pre','code',
    'small','strong','em','i','b','u','s','sub','sup','br','hr','time','mark','kbd','samp','var','template','slot',
    // SVG (common)
    'svg','g','path','rect','circle','ellipse','line','polyline','polygon','text','defs','use','symbol','clipPath','mask','linearGradient','radialGradient','stop','pattern','filter'
]);

/** Return all opening tags in content: [tagName, attrsString, offsetStart] */
function findTags(string $content): array {
    $tags = [];
    if (preg_match_all('/<([a-zA-Z][\w:-]*)\b([^>]*?)>/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
        $count = count($matches[0]);
        for ($i = 0; $i < $count; $i++) {
            $tagName = $matches[1][$i][0];
            $attrs   = $matches[2][$i][0];
            $offset  = $matches[0][$i][1];
            $tags[] = [$tagName, $attrs, $offset];
        }
    }
    return $tags;
}

/** Convert char offset to 1-based line number */
function lineFromOffset(string $content, int $offset): int {
    $prefix = substr($content, 0, $offset);
    return substr_count($prefix, "\n") + 1;
}

/** Identify Blade-like component tags we want to ignore entirely */
function isBladeLikeComponentTag(string $lowerTag): bool {
    return str_starts_with($lowerTag, 'x-')
        || str_starts_with($lowerTag, 'flux:')
        || str_starts_with($lowerTag, 'livewire:');
}

/**
 * Scan attributes string for Vue-ish directives/shortcuts.
 * - If Livewire/Alpine attributes are present on the tag, suppress Vue shorthand matches (@..., :...),
 *   but still allow explicit v-* directives (they are distinctive).
 */
function scanVueAttributes(string $attrs, string $lowerTag): array {
    $hits = [];

    // Alpine / Livewire presence on the tag?
    $hasLivewire = preg_match('/\swire:[\w.-]+\s*=/i', $attrs);
    // Alpine common patterns: x-data, x-show, x-bind:..., x-on:...
    $hasAlpine   = preg_match('/\sx-(?:data|show|bind:[\w.-]+|on:[\w.-]+)\b/i', $attrs);

    // If the tag uses Livewire/Alpine, we don't treat shorthand (@..., :...) as Vue.
    $suppressShorthand = $hasLivewire || $hasAlpine;

    // Vue v-* directives (keep even if Alpine/Livewire present)
    if (preg_match_all('/\s(v-(?:if|else-if|else|for|show|model|on:[\w.-]+|bind:[\w.-]+|html|text|cloak|once|slot))\s*=/i', $attrs, $m)) {
        foreach ($m[1] as $attr) {
            $hits[] = ['type' => 'v-directive', 'attr' => $attr];
        }
    }

    if (!$suppressShorthand) {
        // @event="..." shorthand — only consider when Alpine/Livewire are not present
        if (preg_match_all('/\s(@[A-Za-z][\w.-]*)\s*=/i', $attrs, $m2)) {
            foreach ($m2[1] as $attr) {
                $hits[] = ['type' => '@event', 'attr' => $attr];
            }
        }

        // :prop="..." shorthand — exclude XML namespaces like xmlns:, xlink:, xml:
        if (preg_match_all('/\s:(?!xmlns\b|xlink\b|xml\b)([A-Za-z_][\w.-]*)\s*=/i', $attrs, $m3)) {
            foreach ($m3[1] as $prop) {
                $hits[] = ['type' => ':bind', 'attr' => ':' . $prop];
            }
        }
    }

    // Blade+Vue mustache escape marker
    if (preg_match('/@{{/', $attrs)) {
        $hits[] = ['type' => 'mustache-escape', 'attr' => '@{{ ... }}'];
    }

    return $hits;
}

/** Scan a single Blade file and return hits */
function scanBladeFile(string $path, array $vueComponents, array $standardTags, bool $flagCustomTags): array {
    $content = file_get_contents($path);
    $results = [];
    $lowerCaseVueComponents = array_map('strtolower', $vueComponents);

    foreach (findTags($content) as [$tag, $attrs, $offset]) {
        $lowerTag = strtolower($tag);

        // Skip Blade-like component tags entirely (Blade, Flux, Livewire)
        if (isBladeLikeComponentTag($lowerTag)) {
            continue;
        }

        $line = lineFromOffset($content, $offset);

        // Known Vue component tag?
        $isKnownVueComponent = in_array($lowerTag, $lowerCaseVueComponents, true);

        // Heuristic custom tag (not standard HTML/SVG and not a known Vue component)
        $looksCustom = !isset($standardTags[$lowerTag]) && !$isKnownVueComponent;

        // Check attributes for Vue usage
        $attrHits = scanVueAttributes($attrs, $lowerTag);

        // Record hits
        if ($isKnownVueComponent) {
            $results[] = [
                'line'   => $line,
                'kind'   => 'vue-component-tag',
                'detail' => "<{$tag}>"
            ];
        }

        foreach ($attrHits as $hit) {
            $results[] = [
                'line'   => $line,
                'kind'   => $hit['type'],
                'detail' => $hit['attr']
            ];
        }

        // Optionally flag custom tags (exclude <template> and Blade-like tags)
        if ($flagCustomTags && $looksCustom && $lowerTag !== 'template' && !isBladeLikeComponentTag($lowerTag)) {
            $results[] = [
                'line'   => $line,
                'kind'   => 'custom-tag',
                'detail' => "<{$tag}>"
            ];
        }
    }

    return $results;
}

/** Walk views dir */
$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($bladeRoot, FilesystemIterator::SKIP_DOTS)
);

$report = [];

foreach ($rii as $file) {
    /** @var SplFileInfo $file */
    if ($file->isDir()) continue;
    $path = $file->getPathname();

    if (pathIsExcluded($path, $excludePaths)) continue;
    if (!preg_match('/\.blade\.php$/', $path)) continue;

    $hits = scanBladeFile($path, $vueComponents, $standardTags, $flagCustomTags);

    if (!empty($hits)) {
        usort($hits, fn($a, $b) => $a['line'] <=> $b['line']);
        $report[$path] = $hits;
    }
}

/** Output */
if (empty($report)) {
    echo "✅ No Vue-like usage found in Blade templates (after filters).\n";
    exit(0);
}

foreach ($report as $path => $hits) {
    echo "📄 {$path}\n";
    foreach ($hits as $h) {
        printf("  Line %-5d %-18s %s\n", $h['line'], '['.$h['kind'].']', $h['detail']);
    }
    echo "\n";
}

$totalFiles = count($report);
$totalHits  = array_sum(array_map('count', $report));
echo "— Scanned complete. {$totalHits} hits across {$totalFiles} files.\n";


