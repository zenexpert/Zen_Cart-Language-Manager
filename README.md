# Zen Cart Language Manager (Admin Plugin)

A powerful, user-friendly Admin plugin for **Zen Cart 2.1.0+** that allows store owners and developers to edit language definitions directly from the backend. 

It is designed specifically for the modern **Array-Based Language System** introduced in recent Zen Cart versions. It utilizes the Template Override system, ensuring core language files are never modified directly.


## Features

* **Visual Editor:** Edit text definitions in a clean 3-column layout (Key, Original, Override).
* **Safe Overrides:** Automatically saves changes to `includes/languages/english/YOUR_TEMPLATE/`.
* **Dual Editing Modes:**
    * **Basic Mode:** Safe text editing. Locks complex PHP (constants/concatenation) to prevent syntax errors.
    * **Advanced Mode:** Full raw PHP editing for developers who need to use constants (e.g., `'Text ' . STORE_NAME`).
* **Create Language Packs:** One-click cloning of the English language pack to a new language (e.g., French).
    * **Full Cloning:** Copies both **Catalog** and **Admin** language files.
    * **Auto-Renaming:** Automatically renames internal definition files (e.g., `lang.english.php` → `lang.french.php`).
* **Deep Scanning:** Scans Main language files, Page definitions, and Module definitions (Payment, Shipping, Order Total).


## Requirements

* **Zen Cart:** v2.1.0 or newer (Compatible with v2.0.0+ using array-based language files).
* **PHP:** 7.4, 8.0, 8.1, 8.2+.


## Installation

### 1. Upload Files
1. Unzip the package.
2. Upload the contents of zc_plugins files to your server, maintaining the directory structure.

### 2. Activate Plugin
1. Log into your Zen Cart Admin.
2. Navigate to **Modules > Plugin Manager**.
3. Find **Language Manager** in the list.
4. Click the **Install** button.
   * *This will automatically register the page in the Localization menu and set necessary permissions.*

### 3. Permissions Check
Ensure your Catalog language directory is writable so the plugin can create folders and write override files.
* **Path:** `/includes/languages/`
* **Permissions:** `755` (standard) or `777` (if required by your host).


## Usage Guide

### Editing Language Files
1. Navigate to **Localization > Language Manager**.
2. Select the **Target Language** (e.g., English).
3. Select the **File to Edit** from the dropdown (grouped by Main, Page, Modules, etc.).
4. Enter your changes in the **"Your Override"** column.
5. Click **Save Changes**.
   * *Note:* The plugin will automatically create the necessary directory structure in your active template folder.

### Creating a New Language
1. Click the **"Create New Language Pack"** button at the top right to reveal the form.
2. Enter the name of the new language (e.g., `french`, `german`).
   * *Input must be lowercase alphanumeric only.*
3. Click **Create**.
4. **What happens:**
   * Clones `includes/languages/english` to `includes/languages/french` (Catalog).
   * Clones `YOUR_ADMIN/includes/languages/english` to `YOUR_ADMIN/includes/languages/french` (Admin).
   * Renames internal definition files (e.g., `lang.english.php` → `lang.french.php`).
5. You can now go to **Localization > Languages** in Zen Cart and add the new language record.

### Basic vs. Advanced Mode
* **Basic Mode (Default):** Designed for standard text. If a definition contains PHP logic (like `'Welcome ' . STORE_NAME`), the input is **LOCKED** to prevent accidental syntax errors.
* **Advanced Mode:** Allows editing of all fields as Raw PHP. You must ensure you include valid PHP syntax (quotes, dots, semicolons are handled by the array structure).


## Important Notes

* **Security:** This tool uses `var_export` in Basic Mode to ensure valid PHP syntax. In Advanced Mode, it performs a syntax lint check (`php -l` equivalent) before saving to prevent "White Screen of Death" errors.
* **Legacy Files:** This plugin is designed for **Array-Based** language files (`return [];`). It ignores older `define('CONSTANT', 'Val');` files often found in outdated plugins.


## MOST Important Note

* **BACKUP:** This tool CAN cause errors, including Fatal PHP Errors. Make sure you have recent backups of your site's files before making any changes. USE THIS TOOL AT YOUR OWN RISK!

## Contributing
Found a bug? Feel free to submit an issue or pull request.


## License
GNU Public License V2.0
