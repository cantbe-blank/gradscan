# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

GradScan is a plain-PHP (no framework, no router) graduation-ceremony app served by XAMPP from `C:\xampp\htdocs\gradscan` (URL `http://localhost/gradscan/`). Each graduate gets a one-time QR code; an operator scans it on stage and the graduate's details appear on an audience display, styled by the school's saved layout. The branding is ASCOT (green/yellow palette).

## Commands

- **Serve**: start Apache + MySQL in XAMPP, then open `http://localhost/gradscan/login.php`. No build step for PHP.
- **Database**: import `gradscan.sql` into a MySQL/MariaDB database named `gradscan`, then apply any files in `migrations/` that the database doesn't have yet (in date order, e.g. `mysql -u root -p gradscan < migrations/2026-09-30_scan_log_school_year.sql`). `schema.sql` is an older, bare schema. DB credentials are in `config/config.php`, which exposes the global mysqli `$conn` and then requires `config/app.php`.
- **PHP deps**: `composer install` (chillerlan/php-qrcode, phpoffice/phpspreadsheet). `vendor/` is gitignored, but some of it is still tracked.
- **Tailwind (v4, CLI)**: after any class change, rebuild the committed `frontend/dist/output.css`:
  - `npx @tailwindcss/cli -i ./frontend/src/input.css -o ./frontend/dist/output.css --watch`
  - `input.css` uses `@source "../../"`, so it scans the whole project for classes.
- **Seed a user**: `create_admin.php` inserts a `school_admin` into the first school row.
- There are no tests, linter, or CI for the app. (The tests under `operator/jsQR-master/` and `AdminLTE-3.2.0/` belong to vendored libraries.)

## Architecture

**Roles and routing.** `login.php` authenticates against the `user` table (`password_verify`), stores `user_id`, `school_id`, `role`, and `full_name` in `$_SESSION`, and redirects by role into one of three directories:
- `super_admin/`: manages schools, school admins, and operators, deactivates accounts, and views reports. It is not scoped to a school.
- `school_admin/`: manages graduates, layouts, and scan history. Every query is scoped by `$_SESSION['school_id']`.
- `operator/`: runs the scanner and the audience display.

Every page checks the role at the top. New pages should use `gs_require_role(['school_admin'], '../login.php')` from `config/app.php` (older `super_admin/` and `operator/` pages still inline the same check). Pages use relative `require_once '../config/config.php'` (or `__DIR__ . '/../config/...'`), so the paths depend on directory depth.

**UI (Tailwind v4).** Every page uses Tailwind: `login.php`, `school_admin/`, `operator/`, and `super_admin/`. Each role directory has its own `includes/` (header, sidebar, footer; `super_admin/` also has `topbar.php`). They share custom `gs-*` component classes defined in `frontend/src/input.css` under `@layer components`, with theme tokens like `ascot-dark` and `font-poppins`. Reuse the `gs-*` classes instead of inlining long utility lists. `super_admin/` pages sit at two depths (`super_admin/` and `super_admin/{schools,admins,operators}/`), so each page sets `$root` (`'../'` or `'../../'`), plus `$pageTitle`, `$topbarTitle`, and `$activeNav`, before requiring the includes. No PHP page uses the vendored `AdminLTE-3.2.0/` directory any more.

**Graduate → QR pipeline** (`school_admin/add_graduate.php`, `config/qr_helpers.php`): graduates are added one at a time or bulk-imported from a spreadsheet (PhpSpreadsheet) plus a ZIP of photos; photos go to `photos/` (gitignored). `gs_issue_qr()` gives each graduate a `qr_code` row with a random `qr_token`; reissuing marks the old code `invalidated` and inserts a new row, so the newest row is the current code (`gs_current_qr()`). QR images are never stored: `school_admin/qr.php` renders SVG/PNG from the token on request, `qr_cards.php` is the printable sheet (QR fixed at 30 mm, which the desk scanner reads best), and the Graduates View panel builds a phone image in the browser with the QR at ~40% of the width. `qrcodes/` only holds PNGs from older versions.

**Scan flow:**
1. The operator uses an SM8070 USB QR scanner in keyboard-wedge (HID) mode: it types the token and then presses its suffix key. `operator/js/hid_scanner.js` (`gsListenForScanner`) collects fast keystrokes at the document level, accepts Enter or Tab as the end of a scan, or submits a complete 32-hex token after a short idle. There is no camera input. `libs/jsQR.js` and `operator/jsQR-master/` are left over from the old camera scanner.
2. `operator/scanner.php` POSTs JSON `{token}` to `operator/scan_process.php`.
3. `scan_process.php` looks up `qr_code` by token, expires codes past `expires_at`, and rejects any status other than `active`. It marks the code `used` with `UPDATE ... WHERE qr_status = 'active'` and checks the affected rows, so simultaneous scans of one code can't both succeed. Every attempt writes a `scan_log` row that copies `school_id` and `graduation_year` (so history survives graduate deletion). It returns `{graduate, layout, display_html, display_seconds, scanned_at}`.
4. `scanner.php` queues successful scans and opens `audience_display.php` in a popup, sending each graduate over `BroadcastChannel('gradscan_display')` no sooner than the previous one's `display_seconds` (per school, set in Layout Management, stored in `layout_config`). The operator presses Display Now for the first graduate; Show Next Now skips the wait. The display injects `display_html` into a 16:9 stage. Scans typed into the display window while it has focus are forwarded back over `BroadcastChannel('gradscan_scanner_input')`. Both windows must be in the same browser.

**Layouts** (`config/layout_helpers.php`, `school_admin/layouts.php`): a layout is a JSON config stored in `layout.layout_config`. It describes a 16:9 canvas with an optional background image (in `layout_backgrounds/`) and graduate fields positioned in percentages, with font sizes in `cqw` units. Schools can import a layout from a `.pptx` (`gs_parse_pptx_layout`; `templates/GradScan_Layout_Starter.pptx` is the starter). The first slide in presentation order is the layout: boxes are matched by `{{token}}` text or Selection Pane name, and group transforms, theme colors/fonts, master text styles and text insets are applied. The background picture comes from the slide's picture fill (or its layout/master's) or a picture covering the slide; there is no slide rendering (no LibreOffice). Import is a JSON preview: `layouts.php` stores the extracted picture as `bg_{layout_id}_*` and returns the fields, and nothing is saved until Save Layout posts `imported_background`. Each save calls `gs_cleanup_layout_images()`, which deletes that layout's other `bg_{layout_id}_*` files. Always pass configs through `gs_normalize_layout_config`, which fills defaults and keeps legacy configs (`background_color`/`text_color`/`accent_color`/`header_text`/`show_photo`) valid. Fonts are whitelisted via `gs_allowed_fonts()`. `gs_render_layout_canvas()` renders the canvas server-side, both for the admin previews and for the audience display.

**Time**: `config/app.php` sets `APP_TIMEZONE` (Asia/Manila) for PHP and sets the same UTC offset on each MySQL connection. Timestamps are always created by the database (`NOW()`, column defaults); format them with `gs_format_datetime()` / `gs_format_duration()`. Scan History computes each graduate's interval with `LAG()` over successful scans per graduation year and day (MariaDB 10.4+), treating gaps over 10 minutes as breaks.

**Data model** (`gradscan.sql`): `school` → `user` (roles `super_admin`/`school_admin`/`operator`), `school` → `graduate` → `qr_code` → `scan_log`, and `school` → `layout` (one row has `is_active = 1`). All DB access uses procedural mysqli prepared statements. Follow that style.
