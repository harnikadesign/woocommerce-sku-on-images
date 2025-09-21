# WooCommerce SKU On Images

Automatically overlays product SKU text onto WooCommerce product images upon upload/assignment. Includes admin settings, live preview, logging + pagination, Google Fonts, bulk regenerate with force/skip, and backup/restore tools.

## Features
- Overlay SKU on original product images (thumbnails untouched)
- Position: 4 corners; per-side outer margins
- Typography: font size, line height, Google Font auto-download
- Styling: text color, background color, opacity, per-side inner padding
- Text alignment: left, center, right (centered by metrics)
- Admin UI: settings page under WooCommerce, live preview (upload or Media Library)
- Logging: last run log with AJAX pagination, filters, chips, CSV/JSON export
- Bulk tools: regenerate (force/skip), backup originals, restore originals
- Media Library integration: backup column, filters, per-item + bulk + visible + filtered restore, and dry-run CSV export

## Requirements
- WordPress 5.8+
- WooCommerce
- PHP 7.4+
- GD and/or Imagick

## Installation
1. Copy this folder to `wp-content/plugins/woocommerce-sku-on-images`.
2. Activate “WooCommerce SKU On Images”.
3. Go to WooCommerce → SKU Image Overlay to configure.

## Notes
- Only the original image is modified. Generated sizes are not touched by this plugin.
- If you run a thumbnail regeneration plugin later, regenerated sizes will reflect whatever the current original image contains.
- Enable “Backup original before overlay” if you plan to regenerate sizes later from pristine originals.

## Development
- No build steps required. Plain PHP/CSS/JS.
- Directory layout:
  - `includes/` main classes
  - `assets/` admin JS + CSS + placeholder font
  - `templates/` admin page template

## License
See `readme.txt` (GPLv2 or later).
