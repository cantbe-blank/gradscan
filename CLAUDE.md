# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

GradScan is a plain-PHP (no framework, no router) graduation-ceremony app served by XAMPP from `C:\xampp\htdocs\gradscan` (URL `http://localhost/gradscan/`). Each graduate gets a one-time QR code; an operator scans it on stage and the graduate's details appear on an audience display, styled by the school's saved layout. The branding is ASCOT (green/yellow palette).

## Commands

- **Serve**: start Apache + MySQL in XAMPP, then open `http://localhost/gradscan/login.php`. No build step for PHP.
- **Database**: import `gradscan.sql` (the full dump, which is current) into a MySQL database named `gradscan`. `schema.sql` is an older, bare schema. DB credentials are in `config/config.php`, which exposes the global mysqli `$conn`.
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

Every page does its own session/role check at the top: `session_start()`, then `$_SESSION['role']`, then redirect to `login.php`. There is no shared auth include, so add the check to each new page. Pages use relative `require_once '../config/config.php'` (or `__DIR__ . '/../config/...'`), so the paths depend on directory depth.

**UI migration in progress (branch `tailwind-rewrite`).** `login.php`, `school_admin/`, and `operator/` use Tailwind. `school_admin/` and `operator/` each have their own `includes/{header,sidebar,footer}.php`, and they share custom `gs-*` component classes defined in `frontend/src/input.css` under `@layer components`, with theme tokens like `ascot-dark` and `font-poppins`. `super_admin/` still uses AdminLTE 3.2 (Bootstrap and jQuery) from the vendored `AdminLTE-3.2.0/` directory. When converting a page, reuse the existing `gs-*` classes instead of inlining long utility lists.

**Graduate → QR pipeline** (`school_admin/add_graduate.php`): graduates are added one at a time or bulk-imported from a spreadsheet (PhpSpreadsheet) plus a ZIP of photos. Each graduate gets a `qr_code` row with a random `qr_token` (`bin2hex(random_bytes(16))`) and a PNG saved to `qrcodes/`. Photos go to `photos/`. Both directories hold runtime content and are gitignored.

**Scan flow:**
1. The operator uses an SM8070 USB QR scanner in keyboard-wedge (HID) mode: it types the token and then presses its suffix key. `operator/js/hid_scanner.js` (`gsListenForScanner`) collects fast keystrokes at the document level, accepts Enter or Tab as the end of a scan, or submits a complete 32-hex token after a short idle. There is no camera input. `libs/jsQR.js` and `operator/jsQR-master/` are left over from the old camera scanner.
2. `operator/scanner.php` POSTs JSON `{token}` to `operator/scan_process.php`.
3. `scan_process.php` looks up `qr_code` by token and rejects any status other than `active`. On success, it marks the QR `used` (one-time use), writes a `scan_log` row (`success`/`not_found`/`used`/...), and returns `{graduate, layout, display_html}`. `display_html` is the school's active layout rendered by `gs_render_layout_canvas()`.
4. `scanner.php` opens `audience_display.php` in a popup and sends it results over `BroadcastChannel('gradscan_display')`. The display injects `display_html` into a 16:9 stage. Scans typed into the display window while it has focus are forwarded back over `BroadcastChannel('gradscan_scanner_input')`. Both windows must be in the same browser.

**Layouts** (`config/layout_helpers.php`, `school_admin/layouts.php`): a layout is a JSON config stored in `layout.layout_config`. It describes a 16:9 canvas with an optional background image (in `layout_backgrounds/`) and graduate fields positioned in percentages, with font sizes in `cqw` units. Schools can import a layout from a `.pptx` file (`gs_parse_pptx_layout`; `templates/GradScan_Layout_Starter.pptx` is the starter). Always pass configs through `gs_normalize_layout_config`, which fills defaults and keeps legacy configs (`background_color`/`text_color`/`accent_color`/`header_text`/`show_photo`) valid. Fonts are whitelisted via `gs_allowed_fonts()`. `gs_render_layout_canvas()` renders the canvas server-side, both for the admin previews and for the audience display.

**Data model** (`gradscan.sql`): `school` → `user` (roles `super_admin`/`school_admin`/`operator`), `school` → `graduate` → `qr_code` → `scan_log`, and `school` → `layout` (one row has `is_active = 1`). All DB access uses procedural mysqli prepared statements. Follow that style.
