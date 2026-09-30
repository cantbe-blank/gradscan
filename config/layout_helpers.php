<?php
/**
 * GradScan layout helpers.
 *
 * A layout is a 16:9 canvas: an optional background image (designed by the
 * school in Canva/PowerPoint/etc.) with graduate fields placed on top.
 * All positions are percentages of the canvas, and font sizes use cqw
 * (1% of canvas width), so the same layout looks identical on any screen size.
 *
 * Stored in layout.layout_config (JSON). Old configs that only have
 * background_color / text_color / accent_color / header_text / show_photo
 * are still valid: missing pieces are filled with defaults.
 */

/** Whitelist of 10 Windows-safe fonts. */
function gs_allowed_fonts(): array
{
    return [
        'Arial',
        'Arial Black',
        'Calibri',
        'Cambria',
        'Georgia',
        'Impact',
        'Segoe UI',
        'Tahoma',
        'Times New Roman',
        'Verdana',
    ];
}

/** Validates that a font belongs to the whitelist, with fallback. */
function gs_valid_font(?string $font, string $fallback = 'Segoe UI'): string
{
    if ($font !== null && in_array($font, gs_allowed_fonts(), true)) {
        return $font;
    }
    return $fallback;
}

/** Default position/size/font for every placeable field. */
function gs_default_layout_fields(): array
{
    return [
        'photo'           => ['x' => 6,  'y' => 12, 'w' => 22],
        'header'          => ['x' => 35, 'y' => 8,  'w' => 60, 'size' => 3.2, 'align' => 'left', 'font' => 'Segoe UI'],
        'name'            => ['x' => 35, 'y' => 28, 'w' => 60, 'size' => 5.0, 'align' => 'left', 'font' => 'Segoe UI'],
        'student_id'      => ['x' => 35, 'y' => 48, 'w' => 60, 'size' => 2.2, 'align' => 'left', 'font' => 'Segoe UI'],
        'course'          => ['x' => 35, 'y' => 56, 'w' => 60, 'size' => 2.4, 'align' => 'left', 'font' => 'Segoe UI'],
        'honors'          => ['x' => 35, 'y' => 66, 'w' => 60, 'size' => 2.8, 'align' => 'left', 'font' => 'Segoe UI'],
        'graduation_year' => ['x' => 35, 'y' => 76, 'w' => 60, 'size' => 2.2, 'align' => 'left', 'font' => 'Segoe UI'],
    ];
}

function gs_valid_color($value, string $fallback): string
{
    return (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)) ? strtolower($value) : $fallback;
}

function gs_clamp($value, float $min, float $max, float $fallback): float
{
    if (!is_numeric($value)) {
        return $fallback;
    }
    return max($min, min($max, (float)$value));
}

/**
 * Turns any decoded layout_config (or untrusted posted data) into a complete,
 * clamped, safe config. Everything the renderer prints goes through here.
 */
function gs_normalize_layout_config($config): array
{
    $config = is_array($config) ? $config : [];

    $bg     = gs_valid_color($config['background_color'] ?? null, '#074422');
    $text   = gs_valid_color($config['text_color'] ?? null, '#ffffff');
    $accent = gs_valid_color($config['accent_color'] ?? null, '#ffdd21');

    $fields = [];
    foreach (gs_default_layout_fields() as $key => $d) {
        $in = (isset($config['fields'][$key]) && is_array($config['fields'][$key])) ? $config['fields'][$key] : [];

        if (array_key_exists('visible', $in)) {
            $visible = (bool)$in['visible'];
        } elseif ($key === 'photo') {
            $visible = (bool)($config['show_photo'] ?? true); // legacy flag
        } else {
            $visible = true;
        }

        $f = [
            'visible' => $visible,
            'x'       => gs_clamp($in['x'] ?? null, 0, 100, $d['x']),
            'y'       => gs_clamp($in['y'] ?? null, 0, 100, $d['y']),
            'w'       => gs_clamp($in['w'] ?? null, 5, 100, $d['w']),
        ];

        if ($key !== 'photo') {
            $align = $in['align'] ?? $d['align'];
            $f['size']  = gs_clamp($in['size'] ?? null, 1, 12, $d['size']);
            $f['color'] = gs_valid_color($in['color'] ?? null, $key === 'honors' ? $accent : $text);
            $f['align'] = in_array($align, ['left', 'center', 'right'], true) ? $align : $d['align'];
            $f['font']  = gs_valid_font($in['font'] ?? null, $d['font'] ?? 'Segoe UI');
        }

        $fields[$key] = $f;
    }

    $bgImage = $config['background_image'] ?? null;
    if (!is_string($bgImage) || !preg_match('#^layout_backgrounds/[A-Za-z0-9_\-]+\.(png|jpe?g|webp)$#i', $bgImage)) {
        $bgImage = null;
    }

    return [
        'background_color' => $bg,
        'text_color'       => $text,
        'accent_color'     => $accent,
        'header_text'      => mb_substr(trim((string)($config['header_text'] ?? '')), 0, 100),
        // Minimum seconds each graduate stays on the audience display
        'display_seconds'  => (int)round(gs_clamp($config['display_seconds'] ?? null, 1, 60, defined('GS_DEFAULT_DISPLAY_SECONDS') ? GS_DEFAULT_DISPLAY_SECONDS : 5)),
        'show_photo'       => $fields['photo']['visible'], // kept so older display code keeps working
        'background_image' => $bgImage,
        'fields'           => $fields,
    ];
}

/** Largest background image accepted, from an upload or from inside a .pptx. */
const GS_MAX_BACKGROUND_BYTES = 8 * 1024 * 1024;

/**
 * Returns the file extension for JPG/PNG/WEBP image bytes, or null for
 * anything else. The type comes from the image data, never a filename.
 */
function gs_image_ext_from_bytes(string $bytes): ?string
{
    $info = @getimagesizefromstring($bytes);
    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    return ($info && isset($allowed[$info[2]])) ? $allowed[$info[2]] : null;
}

/**
 * Validates and stores background image bytes for a layout.
 *
 * @return array{ok:bool, path?:string, error?:string}
 */
function gs_store_layout_background(string $bytes, int $layout_id): array
{
    if (strlen($bytes) > GS_MAX_BACKGROUND_BYTES) {
        return ['ok' => false, 'error' => 'The background image must be 8 MB or smaller.'];
    }

    $ext = gs_image_ext_from_bytes($bytes);
    if ($ext === null) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, or WEBP images are allowed for the background.'];
    }

    $dir = dirname(__DIR__) . '/layout_backgrounds/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // uniqid keeps two saves in the same second from colliding
    $filename = 'bg_' . $layout_id . '_' . str_replace('.', '', uniqid('', true)) . '.' . $ext;

    if (file_put_contents($dir . $filename, $bytes) === false) {
        return ['ok' => false, 'error' => 'Could not save the background image on the server.'];
    }

    return ['ok' => true, 'path' => 'layout_backgrounds/' . $filename];
}

/**
 * Validates and stores an uploaded background image.
 *
 * @return array{ok:bool, path?:string, error?:string}
 */
function gs_save_layout_background(array $file, int $layout_id): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'The background image failed to upload. Please try again.'];
    }

    if ($file['size'] > GS_MAX_BACKGROUND_BYTES) {
        return ['ok' => false, 'error' => 'The background image must be 8 MB or smaller.'];
    }

    return gs_store_layout_background((string)file_get_contents($file['tmp_name']), $layout_id);
}

/**
 * True when $relativePath is a stored background image that belongs to this
 * layout, e.g. one extracted by a PowerPoint import that hasn't been saved yet.
 */
function gs_is_own_layout_image(string $relativePath, int $layout_id): bool
{
    return preg_match('#^layout_backgrounds/bg_' . $layout_id . '_[A-Za-z0-9]+\.(png|jpe?g|webp)$#', $relativePath) === 1
        && is_file(dirname(__DIR__) . '/' . $relativePath);
}

/**
 * Deletes a background image, but only if it belongs to this layout
 * (so a school replacing its image never deletes a shared template's file).
 */
function gs_delete_layout_image(string $relativePath, int $layout_id): void
{
    $base = basename($relativePath);

    if (strpos($base, 'bg_' . $layout_id . '_') !== 0) {
        return;
    }

    $full = dirname(__DIR__) . '/layout_backgrounds/' . $base;
    if (is_file($full)) {
        unlink($full);
    }
}

/**
 * Deletes every stored image for this layout except $keepPath. Called after a
 * save, it clears out images from PowerPoint imports that were never saved.
 */
function gs_cleanup_layout_images(int $layout_id, ?string $keepPath): void
{
    $keep = $keepPath !== null ? basename($keepPath) : null;

    foreach (glob(dirname(__DIR__) . '/layout_backgrounds/bg_' . $layout_id . '_*') ?: [] as $file) {
        if (basename($file) !== $keep) {
            unlink($file);
        }
    }
}


/* ------------------------------------------------------------------------
 * PowerPoint (.pptx) import
 *
 * A .pptx is a zip of XML parts. Slide 1 (in presentation order) holds the
 * layout: text boxes containing {{name}}, {{course}}, {{student_id}},
 * {{honors}}, {{year}}, {{header}} and a box named "photo" (or containing
 * {{photo}}). A box can also be recognised by its name in PowerPoint's
 * Selection Pane, e.g. a box named "Name".
 *
 * The background picture is taken from the slide itself: its background
 * picture fill (Format Background > Picture fill, also inherited from the
 * slide layout/master), or else one picture covering the whole slide.
 * ---------------------------------------------------------------------- */

const GS_PPTX_NS = [
    'p' => 'http://schemas.openxmlformats.org/presentationml/2006/main',
    'a' => 'http://schemas.openxmlformats.org/drawingml/2006/main',
    'r' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
];

/** Loads one XML part of the .pptx, or null if it's missing or broken. */
function gs_pptx_xpath(ZipArchive $zip, ?string $part): ?DOMXPath
{
    if ($part === null) {
        return null;
    }

    $xml = $zip->getFromName($part);
    if ($xml === false) {
        return null;
    }

    $doc = new DOMDocument();
    if (!@$doc->loadXML($xml, LIBXML_NONET)) {
        return null;
    }

    $xpath = new DOMXPath($doc);
    foreach (GS_PPTX_NS as $prefix => $uri) {
        $xpath->registerNamespace($prefix, $uri);
    }
    return $xpath;
}

/**
 * Reads a part's relationships as [rId => ['type' => short type, 'target' => zip path]].
 * The short type is the last segment of the relationship type, e.g. "image".
 */
function gs_pptx_rels(ZipArchive $zip, string $part): array
{
    $relsPart = dirname($part) . '/_rels/' . basename($part) . '.rels';
    $xml = $zip->getFromName($relsPart);
    if ($xml === false) {
        return [];
    }

    $doc = new DOMDocument();
    if (!@$doc->loadXML($xml, LIBXML_NONET)) {
        return [];
    }

    $rels = [];
    foreach ($doc->getElementsByTagName('Relationship') as $rel) {
        if ($rel->getAttribute('TargetMode') === 'External') {
            continue;
        }

        // Resolve "../media/image1.png" against the part's folder
        $segments = [];
        foreach (explode('/', dirname($part) . '/' . $rel->getAttribute('Target')) as $seg) {
            if ($seg === '..') {
                array_pop($segments);
            } elseif ($seg !== '' && $seg !== '.') {
                $segments[] = $seg;
            }
        }

        $type = $rel->getAttribute('Type');
        $rels[$rel->getAttribute('Id')] = [
            'type'   => substr($type, strrpos($type, '/') + 1),
            'target' => implode('/', $segments),
        ];
    }
    return $rels;
}

/** Target of the first relationship of $type, e.g. a slide's slideLayout. */
function gs_pptx_rel_target(array $rels, string $type): ?string
{
    foreach ($rels as $rel) {
        if ($rel['type'] === $type) {
            return $rel['target'];
        }
    }
    return null;
}

/**
 * Theme colors and fonts, with the master's color map applied, so that
 * scheme names used in slides (tx1, bg1, accent1, ...) resolve to hex.
 */
function gs_pptx_theme(?DOMXPath $theme, ?DOMXPath $master): array
{
    $colors = [];
    $fonts = ['major' => null, 'minor' => null];

    if ($theme) {
        foreach ($theme->query('//a:clrScheme/*') as $slot) {
            $srgb = $theme->query('./a:srgbClr/@val', $slot)->item(0);
            $sys  = $theme->query('./a:sysClr/@lastClr', $slot)->item(0);
            if ($srgb || $sys) {
                $colors[$slot->localName] = strtolower(($srgb ?: $sys)->nodeValue);
            }
        }
        $fonts['major'] = $theme->query('//a:majorFont/a:latin/@typeface')->item(0)?->nodeValue;
        $fonts['minor'] = $theme->query('//a:minorFont/a:latin/@typeface')->item(0)?->nodeValue;
    }

    // Default Office color map; the master's p:clrMap overrides it
    $map = ['bg1' => 'lt1', 'tx1' => 'dk1', 'bg2' => 'lt2', 'tx2' => 'dk2'];
    $clrMap = $master?->query('//p:clrMap')->item(0);
    if ($clrMap) {
        foreach ($clrMap->attributes as $attr) {
            $map[$attr->name] = $attr->value;
        }
    }
    foreach ($map as $alias => $slot) {
        if (isset($colors[$slot])) {
            $colors[$alias] = $colors[$slot];
        }
    }

    return ['colors' => $colors, 'fonts' => $fonts];
}

/** Applies PowerPoint's lumMod/lumOff tint (e.g. "White, darker 25%") to a hex color. */
function gs_pptx_apply_lum(string $hex, ?int $lumMod, ?int $lumOff): string
{
    if ($lumMod === null && $lumOff === null) {
        return $hex;
    }

    [$r, $g, $b] = array_map(fn($c) => hexdec($c) / 255, str_split($hex, 2));
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;
    $h = $s = 0.0;

    if ($max !== $min) {
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match (true) {
            $max === $r => fmod(($g - $b) / $d + 6, 6),
            $max === $g => ($b - $r) / $d + 2,
            default     => ($r - $g) / $d + 4,
        } / 6;
    }

    $l = max(0, min(1, $l * (($lumMod ?? 100000) / 100000) + (($lumOff ?? 0) / 100000)));

    $hue = function ($p, $q, $t) {
        $t = fmod($t + 1, 1);
        if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1 / 2) return $q;
        if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
        return $p;
    };

    if ($s == 0) {
        $rgb = [$l, $l, $l];
    } else {
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $rgb = [$hue($p, $q, $h + 1 / 3), $hue($p, $q, $h), $hue($p, $q, $h - 1 / 3)];
    }

    return implode('', array_map(fn($c) => sprintf('%02x', (int)round($c * 255)), $rgb));
}

/** Resolves the first color (srgbClr / schemeClr / sysClr) under $node to "#rrggbb". */
function gs_pptx_color(DOMXPath $xp, ?DOMNode $node, array $theme): ?string
{
    if ($node === null) {
        return null;
    }

    $clr = $xp->query('./a:srgbClr | ./a:schemeClr | ./a:sysClr', $node)->item(0);
    if (!$clr instanceof DOMElement) {
        return null;
    }

    $hex = match ($clr->localName) {
        'srgbClr'   => strtolower($clr->getAttribute('val')),
        'sysClr'    => strtolower($clr->getAttribute('lastClr')),
        'schemeClr' => $theme['colors'][$clr->getAttribute('val')] ?? null,
    };

    if ($hex === null || !preg_match('/^[0-9a-f]{6}$/', $hex)) {
        return null;
    }

    $mod = $xp->query('./a:lumMod/@val', $clr)->item(0);
    $off = $xp->query('./a:lumOff/@val', $clr)->item(0);

    return '#' . gs_pptx_apply_lum($hex, $mod ? (int)$mod->nodeValue : null, $off ? (int)$off->nodeValue : null);
}

/**
 * Finds a background picture fill in a slide/layout/master part.
 *
 * @return array{fill:?string, color:?string} fill is the image's zip path
 */
function gs_pptx_part_background(?DOMXPath $xp, ZipArchive $zip, ?string $part, array $theme): array
{
    $out = ['fill' => null, 'color' => null];
    if ($xp === null) {
        return $out;
    }

    $embed = $xp->query('//p:cSld/p:bg/p:bgPr/a:blipFill/a:blip/@r:embed')->item(0);
    if ($embed) {
        $rels = gs_pptx_rels($zip, $part);
        $out['fill'] = $rels[$embed->nodeValue]['target'] ?? null;
    }

    $out['color'] = gs_pptx_color($xp, $xp->query('//p:cSld/p:bg/p:bgPr/a:solidFill')->item(0), $theme)
                 ?? gs_pptx_color($xp, $xp->query('//p:cSld/p:bg/p:bgRef')->item(0), $theme);

    return $out;
}

/**
 * Walks a shape tree, yielding every shape and picture with its rectangle
 * converted to slide coordinates (group transforms applied).
 *
 * @return list<array{node:DOMElement, x:float, y:float, cx:float, cy:float, hasXfrm:bool}>
 */
function gs_pptx_flatten_shapes(DOMXPath $xp, DOMElement $tree, array $groups = []): array
{
    $out = [];

    foreach ($tree->childNodes as $child) {
        if (!$child instanceof DOMElement) {
            continue;
        }

        if ($child->localName === 'grpSp') {
            $x = $xp->query('./p:grpSpPr/a:xfrm', $child)->item(0);
            if ($x) {
                $g = [
                    'ox'  => (float)$xp->evaluate('number(./a:off/@x)', $x),
                    'oy'  => (float)$xp->evaluate('number(./a:off/@y)', $x),
                    'cx'  => (float)$xp->evaluate('number(./a:ext/@cx)', $x),
                    'cy'  => (float)$xp->evaluate('number(./a:ext/@cy)', $x),
                    'chx' => (float)$xp->evaluate('number(./a:chOff/@x)', $x),
                    'chy' => (float)$xp->evaluate('number(./a:chOff/@y)', $x),
                    'chcx'=> (float)$xp->evaluate('number(./a:chExt/@cx)', $x),
                    'chcy'=> (float)$xp->evaluate('number(./a:chExt/@cy)', $x),
                ];
                $out = array_merge($out, gs_pptx_flatten_shapes($xp, $child, array_merge([$g], $groups)));
            }
            continue;
        }

        if ($child->localName !== 'sp' && $child->localName !== 'pic') {
            continue;
        }

        $xfrm = $xp->query('./p:spPr/a:xfrm', $child)->item(0);
        $rect = ['x' => 0.0, 'y' => 0.0, 'cx' => 0.0, 'cy' => 0.0];
        if ($xfrm) {
            $rect = [
                'x'  => (float)$xp->evaluate('number(./a:off/@x)', $xfrm),
                'y'  => (float)$xp->evaluate('number(./a:off/@y)', $xfrm),
                'cx' => (float)$xp->evaluate('number(./a:ext/@cx)', $xfrm),
                'cy' => (float)$xp->evaluate('number(./a:ext/@cy)', $xfrm),
            ];
            // Innermost group first: map child space into the group's space
            foreach ($groups as $g) {
                $sx = $g['chcx'] > 0 ? $g['cx'] / $g['chcx'] : 1;
                $sy = $g['chcy'] > 0 ? $g['cy'] / $g['chcy'] : 1;
                $rect = [
                    'x'  => $g['ox'] + ($rect['x'] - $g['chx']) * $sx,
                    'y'  => $g['oy'] + ($rect['y'] - $g['chy']) * $sy,
                    'cx' => $rect['cx'] * $sx,
                    'cy' => $rect['cy'] * $sy,
                ];
            }
        }

        $out[] = ['node' => $child, 'hasXfrm' => (bool)$xfrm] + $rect;
    }

    return $out;
}

/**
 * For a placeholder shape (p:ph) with no position of its own, finds the
 * matching placeholder in the layout or master and returns its rectangle.
 */
function gs_pptx_inherited_rect(DOMXPath $xp, DOMElement $shape, array $parents): ?array
{
    $ph = $xp->query('./p:nvSpPr/p:nvPr/p:ph', $shape)->item(0);
    if (!$ph instanceof DOMElement) {
        return null;
    }

    $idx = $ph->getAttribute('idx');
    $type = $ph->getAttribute('type') ?: 'body';

    foreach ($parents as $pxp) {
        if ($pxp === null) {
            continue;
        }
        $query = $idx !== ''
            ? "//p:sp[p:nvSpPr/p:nvPr/p:ph[@idx='$idx']]"
            : "//p:sp[p:nvSpPr/p:nvPr/p:ph[@type='$type']]";
        $match = $pxp->query($query)->item(0);
        $xfrm = $match ? $pxp->query('./p:spPr/a:xfrm', $match)->item(0) : null;
        if ($xfrm) {
            return [
                'x'  => (float)$pxp->evaluate('number(./a:off/@x)', $xfrm),
                'y'  => (float)$pxp->evaluate('number(./a:off/@y)', $xfrm),
                'cx' => (float)$pxp->evaluate('number(./a:ext/@cx)', $xfrm),
                'cy' => (float)$pxp->evaluate('number(./a:ext/@cy)', $xfrm),
            ];
        }
    }
    return null;
}

/**
 * Normalises a Selection Pane name like "{{Name}} Shape" or "Student ID box"
 * to a field key, or null if it isn't one of ours.
 */
function gs_pptx_field_from_name(string $name): ?string
{
    $n = strtolower(trim(preg_replace('/[{}]/', '', $name)));
    $n = preg_replace('/\s+(shape|box|placeholder|text\s*box|textbox)$/', '', $n);
    $n = preg_replace('/[\s\-]+/', '_', trim($n));

    $aliases = [
        'photo'           => ['photo', 'graduate_photo', 'picture'],
        'header'          => ['header', 'heading'],
        'name'            => ['name', 'full_name', 'graduate_name'],
        'student_id'      => ['student_id', 'id', 'student_number', 'id_number'],
        'course'          => ['course', 'course_major', 'course_&_major', 'program'],
        'honors'          => ['honors', 'honor', 'latin_honors'],
        'graduation_year' => ['year', 'graduation_year', 'grad_year', 'batch'],
    ];

    foreach ($aliases as $key => $names) {
        if (in_array($n, $names, true)) {
            return $key;
        }
    }
    return null;
}

/**
 * Parses the first slide of a PowerPoint (.pptx) file into layout settings.
 *
 * Returned fields hold positions (percent of the slide), text size in cqw,
 * color, alignment and font. Fields not found on the slide are listed in
 * "missing". "background" holds the background picture's bytes, if any.
 *
 * @return array{ok:bool, error?:string, fields?:array, background_color?:?string,
 *               header_text?:?string, background?:?string, found?:array,
 *               missing?:array, warnings?:list<string>}
 */
function gs_parse_pptx_layout(string $pptxFilePath): array
{
    if (!is_file($pptxFilePath)) {
        return ['ok' => false, 'error' => 'PowerPoint file not found.'];
    }

    $zip = new ZipArchive();
    if ($zip->open($pptxFilePath) !== true) {
        return ['ok' => false, 'error' => 'Could not read the PowerPoint file. Make sure it is a valid .pptx file.'];
    }

    try {
        return gs_parse_pptx_zip($zip);
    } finally {
        $zip->close();
    }
}

function gs_parse_pptx_zip(ZipArchive $zip): array
{
    $warnings = [];

    // --- Slide size (EMU); default 16:9 ---
    $pres = gs_pptx_xpath($zip, 'ppt/presentation.xml');
    $slideW = (float)($pres?->evaluate('number(//p:sldSz/@cx)') ?: 12192000);
    $slideH = (float)($pres?->evaluate('number(//p:sldSz/@cy)') ?: 6858000);
    if (is_nan($slideW) || $slideW <= 0) $slideW = 12192000;
    if (is_nan($slideH) || $slideH <= 0) $slideH = 6858000;

    if (abs($slideW / $slideH - 16 / 9) > 0.02) {
        $warnings[] = 'The slide is not 16:9 (Design > Slide Size > Widescreen), so positions may not line up exactly.';
    }

    // --- First slide in presentation order (not necessarily slide1.xml) ---
    $slidePart = null;
    $firstId = $pres?->query('//p:sldIdLst/p:sldId/@r:id')->item(0);
    if ($firstId) {
        $slidePart = gs_pptx_rels($zip, 'ppt/presentation.xml')[$firstId->nodeValue]['target'] ?? null;
    }
    $slidePart ??= 'ppt/slides/slide1.xml';

    $slide = gs_pptx_xpath($zip, $slidePart);
    if ($slide === null) {
        return ['ok' => false, 'error' => 'Could not find the first slide in the PowerPoint file.'];
    }

    // --- Layout, master and theme, for inherited styles and backgrounds ---
    $layoutPart = gs_pptx_rel_target(gs_pptx_rels($zip, $slidePart), 'slideLayout');
    $masterPart = $layoutPart ? gs_pptx_rel_target(gs_pptx_rels($zip, $layoutPart), 'slideMaster') : null;
    $themePart  = $masterPart ? gs_pptx_rel_target(gs_pptx_rels($zip, $masterPart), 'theme') : null;

    $layout = gs_pptx_xpath($zip, $layoutPart);
    $master = gs_pptx_xpath($zip, $masterPart);
    $theme  = gs_pptx_theme(gs_pptx_xpath($zip, $themePart), $master);

    // Text style defaults, most specific last in each list
    $defaultStyles = [];
    foreach ([[$pres, '//p:defaultTextStyle/a:lvl1pPr'], [$master, '//p:txStyles/p:otherStyle/a:lvl1pPr']] as [$xp, $q]) {
        if ($xp && ($n = $xp->query($q)->item(0))) {
            $defaultStyles[] = [$xp, $n];
        }
    }
    $masterTitle = $master?->query('//p:txStyles/p:titleStyle/a:lvl1pPr')->item(0);
    $masterBody  = $master?->query('//p:txStyles/p:bodyStyle/a:lvl1pPr')->item(0);

    // --- Background: slide, then layout, then master ---
    $bgFill = null;
    $bgColor = null;
    foreach ([[$slide, $slidePart], [$layout, $layoutPart], [$master, $masterPart]] as [$xp, $part]) {
        $bg = gs_pptx_part_background($xp, $zip, $part, $theme);
        $bgFill ??= $bg['fill'];
        $bgColor ??= $bg['color'];
    }

    $allowedFonts = [];
    foreach (gs_allowed_fonts() as $font) {
        $allowedFonts[strtolower(str_replace([' ', '-', '_'], '', $font))] = $font;
    }

    $tokenPatterns = [
        'photo'           => '/\{\{\s*photo\s*\}\}/i',
        'name'            => '/\{\{\s*(name|full_name)\s*\}\}/i',
        'course'          => '/\{\{\s*(course|course_major|course & major)\s*\}\}/i',
        'student_id'      => '/\{\{\s*(student_id|student id|id)\s*\}\}/i',
        'honors'          => '/\{\{\s*honors\s*\}\}/i',
        'graduation_year' => '/\{\{\s*(year|graduation_year|grad_year)\s*\}\}/i',
        'header'          => '/\{\{\s*header\s*\}\}/i',
    ];

    $tree = $slide->query('//p:cSld/p:spTree')->item(0);
    $shapes = $tree instanceof DOMElement ? gs_pptx_flatten_shapes($slide, $tree) : [];

    $parsed = [];
    $headerText = null;
    $fullSlidePictures = [];

    foreach ($shapes as $s) {
        $node = $s['node'];

        $nvPr = $slide->query('./p:nvSpPr/p:cNvPr | ./p:nvPicPr/p:cNvPr', $node)->item(0);
        if ($nvPr instanceof DOMElement && $nvPr->getAttribute('hidden') === '1') {
            continue;
        }
        $shapeName = $nvPr instanceof DOMElement ? $nvPr->getAttribute('name') : '';

        if (!$s['hasXfrm']) {
            $inherited = gs_pptx_inherited_rect($slide, $node, [$layout, $master]);
            if ($inherited === null) {
                continue;
            }
            $s = array_merge($s, $inherited);
        }

        // Paragraph texts
        $lines = [];
        foreach ($slide->query('./p:txBody/a:p', $node) as $p) {
            $line = '';
            foreach ($slide->query('.//a:t', $p) as $t) {
                $line .= $t->nodeValue;
            }
            $lines[] = $line;
        }
        $fullText = implode("\n", $lines);

        // Which field is this? A {{token}} in the text wins over the shape name.
        $key = null;
        foreach ($tokenPatterns as $candidate => $pattern) {
            if (preg_match($pattern, $fullText)) {
                $key = $candidate;
                break;
            }
        }
        $key ??= gs_pptx_field_from_name($shapeName);

        if ($key === null) {
            // A picture covering (nearly) the whole slide can be the background
            if ($node->localName === 'pic' && $s['cx'] >= $slideW * 0.9 && $s['cy'] >= $slideH * 0.9) {
                $embed = $slide->query('./p:blipFill/a:blip/@r:embed', $node)->item(0);
                if ($embed) {
                    $fullSlidePictures[] = $embed->nodeValue;
                }
            }
            continue;
        }

        if (isset($parsed[$key])) {
            $warnings[] = 'More than one box is marked as "' . $key . '"; only the first one was used.';
            continue;
        }

        if ($key === 'photo') {
            $parsed['photo'] = [
                'visible' => true,
                'x' => round($s['x'] / $slideW * 100, 2),
                'y' => round($s['y'] / $slideH * 100, 2),
                'w' => round($s['cx'] / $slideW * 100, 2),
            ];
            if ($s['cy'] > 0 && abs(($s['cx'] / $s['cy']) - (2 / 3)) > 0.08) {
                $warnings[] = 'The photo box is not 2:3 (portrait). GradScan shows photos at 2:3 using the box\'s width.';
            }
            continue;
        }

        // --- Text style: run, then shape list style, then placeholder/master defaults ---
        $firstRun = $slide->query('./p:txBody/a:p/a:r/a:rPr', $node)->item(0);
        $firstPara = $slide->query('./p:txBody/a:p/a:pPr', $node)->item(0);
        $shapeLvl1 = $slide->query('./p:txBody/a:lstStyle/a:lvl1pPr', $node)->item(0);

        $ph = $slide->query('./p:nvSpPr/p:nvPr/p:ph', $node)->item(0);
        $phType = $ph instanceof DOMElement ? ($ph->getAttribute('type') ?: 'body') : null;

        // [xpath, node] pairs from most to least specific
        $styleChain = [];
        if ($firstRun)  $styleChain[] = [$slide, $firstRun];
        if ($firstPara) $styleChain[] = [$slide, $firstPara];
        if ($shapeLvl1) $styleChain[] = [$slide, $shapeLvl1];
        if ($phType !== null) {
            $phStyle = in_array($phType, ['title', 'ctrTitle'], true) ? $masterTitle : $masterBody;
            if ($phStyle) $styleChain[] = [$master, $phStyle];
        }
        foreach (array_reverse($defaultStyles) as $pair) {
            $styleChain[] = $pair;
        }

        $sizePt = null;
        $color = null;
        $typeface = null;
        $align = null;

        foreach ($styleChain as [$xp, $n]) {
            // lvl1pPr/pPr hold character defaults in a:defRPr
            $rpr = $n->localName === 'rPr' ? $n : $xp->query('./a:defRPr', $n)->item(0);

            if ($align === null && $n->localName !== 'rPr' && $n instanceof DOMElement && $n->hasAttribute('algn')) {
                $align = ['ctr' => 'center', 'r' => 'right', 'l' => 'left', 'just' => 'left', 'dist' => 'center'][$n->getAttribute('algn')] ?? null;
            }
            if (!$rpr instanceof DOMElement) {
                continue;
            }
            if ($sizePt === null && $rpr->hasAttribute('sz')) {
                $sizePt = (int)$rpr->getAttribute('sz') / 100;
            }
            $color ??= gs_pptx_color($xp, $xp->query('./a:solidFill', $rpr)->item(0), $theme);
            $typeface ??= $xp->query('./a:latin/@typeface', $rpr)->item(0)?->nodeValue;
        }
        $sizePt ??= 18.0; // PowerPoint's built-in default

        $color ??= isset($theme['colors']['tx1']) ? '#' . $theme['colors']['tx1'] : null;

        // Shrink-on-overflow text is drawn smaller than its set size
        $fontScale = $slide->evaluate('number(./p:txBody/a:bodyPr/a:normAutofit/@fontScale)', $node);
        if (!is_nan($fontScale) && $fontScale > 0) {
            $sizePt *= $fontScale / 100000;
        }

        // Theme font references: +mj-lt (headings) / +mn-lt (body)
        if ($typeface === '+mj-lt') $typeface = $theme['fonts']['major'];
        if ($typeface === '+mn-lt') $typeface = $theme['fonts']['minor'];
        $typeface ??= $theme['fonts']['minor'];

        $font = null;
        if ($typeface) {
            $font = $allowedFonts[strtolower(str_replace([' ', '-', '_'], '', $typeface))] ?? null;
            if ($font === null) {
                $warnings[] = 'The font "' . $typeface . '" (used for ' . $key . ') isn\'t one of the supported fonts, so the current font was kept.';
            }
        }

        // --- Position: the text sits inside the box's inner padding ---
        $bodyPr = $slide->query('./p:txBody/a:bodyPr', $node)->item(0);
        $inset = function (string $attr, int $default) use ($bodyPr) {
            return ($bodyPr instanceof DOMElement && $bodyPr->hasAttribute($attr)) ? (float)$bodyPr->getAttribute($attr) : $default;
        };
        $lIns = $inset('lIns', 91440);
        $rIns = $inset('rIns', 91440);
        $tIns = $inset('tIns', 45720);
        $bIns = $inset('bIns', 45720);

        $x = $s['x'] + $lIns;
        $w = max(0, $s['cx'] - $lIns - $rIns);
        $y = $s['y'] + $tIns;

        // Middle/bottom anchored text: estimate where the lines start
        $anchor = $bodyPr instanceof DOMElement ? $bodyPr->getAttribute('anchor') : '';
        if ($anchor === 'ctr' || $anchor === 'b') {
            $lineCount = max(1, count(array_filter($lines, fn($l) => trim($l) !== '')));
            $textH = $lineCount * $sizePt * 1.15 * 12700;
            $innerH = max(0, $s['cy'] - $tIns - $bIns);
            $y += max(0, $anchor === 'ctr' ? ($innerH - $textH) / 2 : $innerH - $textH);
        }

        $item = [
            'visible' => true,
            'x'       => round($x / $slideW * 100, 2),
            'y'       => round($y / $slideH * 100, 2),
            'w'       => round($w / $slideW * 100, 2),
            'size'    => round($sizePt * 12700 / $slideW * 100, 2),
        ];
        if ($color !== null) $item['color'] = $color;
        if ($align !== null) $item['align'] = $align;
        if ($font !== null)  $item['font']  = $font;

        $parsed[$key] = $item;

        if ($key === 'header') {
            $text = trim(preg_replace('/\s+/', ' ', preg_replace($tokenPatterns['header'], '', $fullText)));
            if ($text !== '') {
                $headerText = mb_substr($text, 0, 100);
            }
        }
    }

    // --- Background picture bytes ---
    $background = null;
    $bgSource = $bgFill;
    if ($bgSource === null && $fullSlidePictures) {
        $bgSource = gs_pptx_rels($zip, $slidePart)[$fullSlidePictures[0]]['target'] ?? null;
    }

    if ($bgSource === null) {
        $warnings[] = 'No background picture was found on the slide, so the current background image was kept. '
                    . 'Set one with Format Background > Picture or texture fill, or place a picture that covers the whole slide.';
    } else {
        $stat = $zip->statName($bgSource);
        if ($stat && $stat['size'] > GS_MAX_BACKGROUND_BYTES) {
            $warnings[] = 'The background picture is larger than 8 MB, so it was not imported. Compress it (Picture Format > Compress Pictures) and try again.';
        } else {
            $bytes = $zip->getFromName($bgSource);
            if ($bytes !== false && gs_image_ext_from_bytes($bytes) !== null) {
                $background = $bytes;
            } else {
                $warnings[] = 'The background picture is not a JPG, PNG, or WEBP image, so it was not imported. '
                            . 'Insert it as a PNG or JPG instead.';
            }
        }
    }

    $found = array_keys($parsed);

    return [
        'ok'               => true,
        'background_color' => $bgColor,
        'header_text'      => $headerText,
        'background'       => $background,
        'fields'           => $parsed,
        'found'            => $found,
        'missing'          => array_values(array_diff(array_keys(gs_default_layout_fields()), $found)),
        'warnings'         => array_values(array_unique($warnings)),
    ];
}

/**
 * Renders a layout as HTML for one graduate. Use this on the scan display
 * screen and for previews. $root is the path from the current page back to
 * the project root (e.g. '../' from school_admin/).
 *
 * $data keys: first_name, middle_name, last_name, suffix, student_id,
 *             course, major, honors, graduation_year, photo
 */
function gs_render_layout_canvas($config, array $data, string $root = '../'): string
{
    $c = gs_normalize_layout_config($config);

    $honorLabels = [
        'cum laude'       => 'Cum Laude',
        'magna cum laude' => 'Magna Cum Laude',
        'summa cum laude' => 'Summa Cum Laude',
    ];

    $fullName = trim(implode(' ', array_filter([
        $data['first_name'] ?? '',
        $data['middle_name'] ?? '',
        $data['last_name'] ?? '',
        $data['suffix'] ?? '',
    ])));

    $course = trim(($data['course'] ?? '') . (!empty($data['major']) ? ' — ' . $data['major'] : ''));
    $honors = strtolower((string)($data['honors'] ?? 'none'));

    $texts = [
        'header'          => $c['header_text'],
        'name'            => $fullName,
        'student_id'      => (string)($data['student_id'] ?? ''),
        'course'          => $course,
        'honors'          => $honorLabels[$honors] ?? '',
        'graduation_year' => (string)($data['graduation_year'] ?? ''),
    ];

    $weights = ['header' => 600, 'name' => 700, 'student_id' => 500, 'course' => 500, 'honors' => 600, 'graduation_year' => 500];

    $bgStyle = 'background-color:' . $c['background_color'] . ';';
    if ($c['background_image']) {
        $bgStyle .= "background-image:url('" . htmlspecialchars($root . $c['background_image'], ENT_QUOTES) . "');"
                  . 'background-size:cover;background-position:center;';
    }

    $html = '<div style="position:relative;width:100%;aspect-ratio:16/9;overflow:hidden;container-type:inline-size;' . $bgStyle . '">';

    foreach ($c['fields'] as $key => $f) {
        if (!$f['visible']) {
            continue;
        }

        $pos = sprintf('position:absolute;left:%.2F%%;top:%.2F%%;width:%.2F%%;', $f['x'], $f['y'], $f['w']);

        if ($key === 'photo') {
            $html .= '<div style="' . $pos . '">'
                   . '<div style="aspect-ratio:2/3;width:100%;overflow:hidden;border-radius:0.6cqw;background:#cbd5e1;">';
            if (!empty($data['photo'])) {
                $html .= '<img src="' . htmlspecialchars($root . $data['photo'], ENT_QUOTES) . '" alt="" style="display:block;width:100%;height:100%;object-fit:cover;">';
            }
            $html .= '</div></div>';
            continue;
        }

        if (($texts[$key] ?? '') === '') {
            continue;
        }

        $fontFamily = htmlspecialchars($f['font'] ?? 'Segoe UI', ENT_QUOTES);
        $style = $pos . sprintf(
            'font-family:\'%s\', sans-serif;font-size:%.2Fcqw;color:%s;text-align:%s;line-height:1.15;font-weight:%d;',
            $fontFamily,
            $f['size'],
            $f['color'],
            $f['align'],
            $weights[$key] ?? 500
        );

        $html .= '<div style="' . $style . '">' . htmlspecialchars($texts[$key]) . '</div>';
    }

    return $html . '</div>';
}
