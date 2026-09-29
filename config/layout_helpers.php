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
        'show_photo'       => $fields['photo']['visible'], // kept so older display code keeps working
        'background_image' => $bgImage,
        'fields'           => $fields,
    ];
}

/**
 * Validates and stores an uploaded background image.
 * The file extension comes from the detected image type, never from the
 * uploaded filename.
 *
 * @return array{ok:bool, path?:string, error?:string}
 */
function gs_save_layout_background(array $file, int $layout_id): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'The background image failed to upload. Please try again.'];
    }

    if ($file['size'] > 8 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'The background image must be 8 MB or smaller.'];
    }

    $info = @getimagesize($file['tmp_name']);
    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    if (!$info || !isset($allowed[$info[2]])) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, or WEBP images are allowed for the background.'];
    }

    $dir = dirname(__DIR__) . '/layout_backgrounds/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = 'bg_' . $layout_id . '_' . time() . '.' . $allowed[$info[2]];

    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return ['ok' => false, 'error' => 'Could not save the background image on the server.'];
    }

    return ['ok' => true, 'path' => 'layout_backgrounds/' . $filename];
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
 * Parses slide 1 of a PowerPoint (.pptx) file to extract graduate field placements.
 * Looks for tokens: {{name}}, {{course}}, {{student_id}}, {{honors}}, {{year}}, {{header}},
 * and shapes named or placeholder for "photo".
 * Converts positions and sizes to percentages (cqw for font size).
 *
 * @return array{ok:bool, fields?:array, background_color?:?string, header_text?:?string, found?:array, missing?:array, error?:string}
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

    // 1. Read slide dimensions from presentation.xml (default to 16:9 EMU 12192000 x 6858000)
    $slideW = 12192000;
    $slideH = 6858000;
    $presXmlStr = $zip->getFromName('ppt/presentation.xml');
    if ($presXmlStr) {
        if (preg_match('/<p:sldSz\s+[^>]*cx="(\d+)"\s+[^>]*cy="(\d+)"/i', $presXmlStr, $m)) {
            $slideW = (int)$m[1];
            $slideH = (int)$m[2];
        } elseif (preg_match('/<p:sldSz\s+[^>]*cy="(\d+)"\s+[^>]*cx="(\d+)"/i', $presXmlStr, $m)) {
            $slideW = (int)$m[2];
            $slideH = (int)$m[1];
        }
    }

    // 2. Read Slide 1 XML
    $slide1XmlStr = $zip->getFromName('ppt/slides/slide1.xml');
    $zip->close();

    if (!$slide1XmlStr) {
        return ['ok' => false, 'error' => 'Could not find Slide 1 in the PowerPoint file.'];
    }

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$doc->loadXML($slide1XmlStr)) {
        return ['ok' => false, 'error' => 'Failed to parse Slide 1 XML structure.'];
    }

    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('p', 'http://schemas.openxmlformats.org/presentationml/2006/main');
    $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

    // Slide background color if defined
    $bgColor = null;
    $bgClrNodes = $xpath->query('//p:bg//a:srgbClr/@val');
    if ($bgClrNodes && $bgClrNodes->length > 0) {
        $bgColor = '#' . strtolower($bgClrNodes->item(0)->nodeValue);
    }

    // Slide width in points (1 pt = 12700 EMU)
    $slideWpt = $slideW > 0 ? ($slideW / 12700) : 960;

    // Tokens to map to layout fields
    $tokenMap = [
        'name'            => '/\{\{\s*name\s*\}\}/i',
        'course'          => '/\{\{\s*(course|course_major|course & major)\s*\}\}/i',
        'student_id'      => '/\{\{\s*(student_id|student id|id)\s*\}\}/i',
        'honors'          => '/\{\{\s*honors\s*\}\}/i',
        'graduation_year' => '/\{\{\s*(year|graduation_year|grad_year)\s*\}\}/i',
        'header'          => '/\{\{\s*header\s*\}\}/i',
    ];

    $allowedFonts = gs_allowed_fonts();
    $fontMap = [];
    foreach ($allowedFonts as $af) {
        $fontMap[strtolower(str_replace([' ', '-', '_'], '', $af))] = $af;
    }

    $parsedFields = [];
    $detectedHeaderText = null;

    // Query all shapes and pictures (including inside groups and hidden shapes)
    $shapes = $xpath->query('//p:sp | //p:pic');

    foreach ($shapes as $shape) {
        // Name from cNvPr
        $nameAttr = $xpath->query('.//p:nvSpPr/p:cNvPr/@name | .//p:nvPicPr/p:cNvPr/@name', $shape);
        $shapeName = ($nameAttr && $nameAttr->length > 0) ? trim($nameAttr->item(0)->nodeValue) : '';

        // Position & Size in EMU
        $offXNode = $xpath->query('.//a:xfrm/a:off/@x', $shape);
        $offYNode = $xpath->query('.//a:xfrm/a:off/@y', $shape);
        $extCxNode = $xpath->query('.//a:xfrm/a:ext/@cx', $shape);

        if (!$offXNode->length || !$offYNode->length || !$extCxNode->length) {
            continue;
        }

        $offX = (float)$offXNode->item(0)->nodeValue;
        $offY = (float)$offYNode->item(0)->nodeValue;
        $extCx = (float)$extCxNode->item(0)->nodeValue;

        $xPct = max(0, min(100, round(($offX / $slideW) * 100, 2)));
        $yPct = max(0, min(100, round(($offY / $slideH) * 100, 2)));
        $wPct = max(5, min(100, round(($extCx / $slideW) * 100, 2)));

        // Detect if photo
        $isPhoto = false;
        if (preg_match('/\bphoto\b/i', $shapeName) || preg_match('/\{\{\s*photo\s*\}\}/i', $shapeName)) {
            $isPhoto = true;
        }

        // Text content and styling across paragraphs
        $paragraphs = $xpath->query('.//p:txBody//a:p | .//a:p', $shape);
        $fullText = '';
        $firstSize = null;
        $firstColor = null;
        $firstAlign = null;
        $firstFont = null;

        if ($paragraphs->length > 0) {
            foreach ($paragraphs as $p) {
                if ($firstAlign === null) {
                    $algnNode = $xpath->query('./a:pPr/@algn', $p);
                    if ($algnNode->length > 0) {
                        $algn = $algnNode->item(0)->nodeValue;
                        if ($algn === 'ctr') $firstAlign = 'center';
                        elseif ($algn === 'r') $firstAlign = 'right';
                        elseif ($algn === 'l') $firstAlign = 'left';
                    }
                }

                $runs = $xpath->query('.//a:r', $p);
                foreach ($runs as $r) {
                    $tNode = $xpath->query('./a:t', $r);
                    if ($tNode->length > 0) {
                        $fullText .= $tNode->item(0)->nodeValue;
                    }

                    if ($firstSize === null) {
                        $szNode = $xpath->query('./a:rPr/@sz', $r);
                        if ($szNode->length > 0) {
                            $szPt = ((float)$szNode->item(0)->nodeValue) / 100;
                            $cqw = round(($szPt / $slideWpt) * 100, 1);
                            $firstSize = max(1, min(12, $cqw));
                        }
                    }

                    if ($firstColor === null) {
                        $clrNode = $xpath->query('.//a:solidFill/a:srgbClr/@val', $r);
                        if ($clrNode->length > 0) {
                            $cVal = $clrNode->item(0)->nodeValue;
                            if (preg_match('/^[0-9a-fA-F]{6}$/', $cVal)) {
                                $firstColor = '#' . strtolower($cVal);
                            }
                        }
                    }

                    if ($firstFont === null) {
                        $tfNode = $xpath->query('.//a:latin/@typeface', $r);
                        if ($tfNode->length > 0) {
                            $rawTf = trim($tfNode->item(0)->nodeValue);
                            $cleanTf = strtolower(str_replace([' ', '-', '_'], '', $rawTf));
                            if (isset($fontMap[$cleanTf])) {
                                $firstFont = $fontMap[$cleanTf];
                            }
                        }
                    }
                }
            }
        }

        if (preg_match('/\{\{\s*photo\s*\}\}/i', $fullText)) {
            $isPhoto = true;
        }

        if ($isPhoto) {
            $parsedFields['photo'] = [
                'visible' => true,
                'x'       => $xPct,
                'y'       => $yPct,
                'w'       => $wPct,
            ];
            continue;
        }

        // Match against known tokens
        foreach ($tokenMap as $key => $pattern) {
            if (isset($parsedFields[$key])) {
                continue;
            }
            if (preg_match($pattern, $fullText) || preg_match($pattern, $shapeName)) {
                $item = [
                    'visible' => true,
                    'x'       => $xPct,
                    'y'       => $yPct,
                    'w'       => $wPct,
                ];
                if ($firstSize !== null)  $item['size']  = $firstSize;
                if ($firstColor !== null) $item['color'] = $firstColor;
                if ($firstAlign !== null) $item['align'] = $firstAlign;
                if ($firstFont !== null)  $item['font']  = $firstFont;

                $parsedFields[$key] = $item;

                if ($key === 'header') {
                    $cleanedHeader = trim(preg_replace('/\{\{\s*header\s*\}\}/i', '', $fullText));
                    if ($cleanedHeader !== '') {
                        $detectedHeaderText = mb_substr($cleanedHeader, 0, 100);
                    }
                }
                break;
            }
        }
    }

    $allExpected = array_keys(gs_default_layout_fields());
    $found = array_keys($parsedFields);
    $missing = array_values(array_diff($allExpected, $found));

    return [
        'ok'               => true,
        'background_color' => $bgColor,
        'header_text'      => $detectedHeaderText,
        'fields'           => $parsedFields,
        'found'            => $found,
        'missing'          => $missing,
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
