<?php
/**
 * @package Admin
 * @copyright Copyright 2003-2025 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: language_manager.php Jan 02 2026 ZenExpert $
 * @copyright 2026 ZenExpert - https://zenexpert.com
 */

require('includes/application_top.php');

// determine target language
// check input, sanitize, and default to 'english'
$target_language = (isset($_REQUEST['language_target']) && preg_match('/^[a-z0-9_-]+$/i', $_REQUEST['language_target']))
    ? $_REQUEST['language_target']
    : 'english';

$db_template_set = $db->Execute(
                                "SET @temp_language =
                                (SELECT languages_id FROM " . TABLE_LANGUAGES . " lg
                                INNER JOIN " . TABLE_TEMPLATE_SELECT . " ts
                                ON ts.template_language = lg.languages_id
                                WHERE lg.directory = '" . $target_language . "')"
                                );
$db_template = $db->Execute(
                            "SELECT template_dir FROM " . TABLE_TEMPLATE_SELECT . "
                            WHERE (@temp_language IS NULL AND template_language = 0)
                                OR (template_language = CAST(@temp_language AS UNSIGNED))"
                            );
$active_template = $db_template->fields['template_dir'];

// dynamic paths
$base_lang_dir = DIR_FS_CATALOG_LANGUAGES . $target_language . '/';
$override_dir  = DIR_FS_CATALOG_LANGUAGES . $target_language . '/' . $active_template . '/';

$action = (isset($_GET['action']) ? $_GET['action'] : '');
$current_file = (isset($_GET['file']) ? $_GET['file'] : '');

// main language file selected and language changed - adjust file to match
if (strpos($current_file, '../lang.') === 0) {
    $expected_loader = '../lang.' . $target_language . '.php';
    if ($current_file !== $expected_loader) {
        $current_file = $expected_loader;
    }
}

// reset current file on language change
if (isset($_GET['language_target']) && !isset($_GET['file'])) {
    $current_file = '';
}

$editor_mode = (isset($_REQUEST['mode']) && $_REQUEST['mode'] === 'advanced') ? 'advanced' : 'basic';

// create new language pack
if ($action == 'create_language' && isset($_POST['new_language'])) {

    // sanitize
    $new_lang_name = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $_POST['new_language']));
    $source_lang = 'english';

    // catalog paths (frontend)
    $cat_source_loader = DIR_FS_CATALOG_LANGUAGES . 'lang.' . $source_lang . '.php';
    $cat_source_dir    = DIR_FS_CATALOG_LANGUAGES . $source_lang . '/';

    $cat_target_loader = DIR_FS_CATALOG_LANGUAGES . 'lang.' . $new_lang_name . '.php';
    $cat_target_dir    = DIR_FS_CATALOG_LANGUAGES . $new_lang_name . '/';

    // admin paths (backend)
    $adm_source_loader = DIR_FS_ADMIN . DIR_WS_LANGUAGES . 'lang.' . $source_lang . '.php';
    $adm_source_dir    = DIR_FS_ADMIN . DIR_WS_LANGUAGES . $source_lang . '/';

    $adm_target_loader = DIR_FS_ADMIN . DIR_WS_LANGUAGES .'lang.' . $new_lang_name . '.php';
    $adm_target_dir    = DIR_FS_ADMIN . DIR_WS_LANGUAGES . $new_lang_name . '/';

    // validation
    $errors = [];

    if (empty($new_lang_name)) {
        $errors[] = ERROR_LANGUAGE_NAME_REQUIRED;
    }
    // check if target already exists (catalog)
    elseif (file_exists($cat_target_loader) || is_dir($cat_target_dir)) {
        $errors[] = sprintf(ERROR_CATALOG_LANG_EXISTS, $new_lang_name);
    }
    // check if source exists (catalog)
    elseif (!file_exists($cat_source_loader) || !is_dir($cat_source_dir)) {
        $errors[] = sprintf(ERROR_SOURCE_CATALOG_NOT_FOUND, $source_lang);
    }
    // check if source exists (admin)
    elseif (!file_exists($adm_source_loader) || !is_dir($adm_source_dir)) {
        $errors[] = sprintf(ERROR_SOURCE_ADMIN_NOT_FOUND, $source_lang);
    }

    if (!empty($errors)) {
        foreach($errors as $err) $messageStack->add($err, 'error');
    } else {

        // catalog clone
        if (!copy($cat_source_loader, $cat_target_loader)) {
            $messageStack->add(ERROR_COPY_CATALOG_LOADER, 'error');
        } else {
            // recursive copy
            recursive_copy($cat_source_dir, $cat_target_dir);

            // rename (lang.english.php -> lang.new.php)
            $cat_internal_src = $cat_target_dir . 'lang.' . $source_lang . '.php';
            $cat_internal_dst = $cat_target_dir . 'lang.' . $new_lang_name . '.php';
            if (file_exists($cat_internal_src)) rename($cat_internal_src, $cat_internal_dst);

            $messageStack->add(SUCCESS_CATALOG_CREATED, 'success');
        }

        // admin clone
        if (!copy($adm_source_loader, $adm_target_loader)) {
            // if Admin copy fails, warn the user, but catalog might have already succeeded
            $messageStack->add(ERROR_COPY_ADMIN_LOADER, 'error');
        } else {
            // recursive copy
            recursive_copy($adm_source_dir, $adm_target_dir);

            // rename (lang.english.php -> lang.new.php)
            $adm_internal_src = $adm_target_dir . 'lang.' . $source_lang . '.php';
            $adm_internal_dst = $adm_target_dir . 'lang.' . $new_lang_name . '.php';
            if (file_exists($adm_internal_src)) rename($adm_internal_src, $adm_internal_dst);

            $messageStack->add(SUCCESS_ADMIN_CREATED, 'success');
        }

        $messageStack->add(sprintf(SUCCESS_LANG_PACK_READY, $new_lang_name), 'success');
    }
}

// save overrides
if ($action == 'save' && !empty($current_file)) {

    // decode JSON payload
    $is_json_source = false;

    if (isset($_POST['json_payload']) && !empty($_POST['json_payload'])) {
        $clean_payload = stripslashes($_POST['json_payload']);
        $clean_payload = str_replace(' ', '+', $clean_payload); // fix browser space encoding

        $json_raw = base64_decode($clean_payload);
        $decoded = json_decode($json_raw, true);

        if (is_array($decoded)) {
            $_POST['definitions'] = $decoded['definitions'] ?? [];
            $_POST['use_default'] = $decoded['use_default'] ?? [];
            $is_json_source = true; // skip extra processing later
        }
    }

    $save_mode = isset($_POST['save_mode']) ? $_POST['save_mode'] : 'basic';

    // we allow ".." ONLY if it refers to the main parent language file (e.g. ../english.php)
    $valid_parent_file = 'lang.' . $target_language . '.php';

    // check if the requested file matches "../lang.target.php"
    $is_valid_parent = ($current_file === '../' . $valid_parent_file);

    $is_traversal = (strpos($current_file, '..') !== false);

    if ($is_traversal && !$is_valid_parent) {
        $messageStack->add('Invalid filename. You are trying to edit ' . htmlspecialchars($current_file) . ' but the system only allows ' . htmlspecialchars('../'.$valid_parent_file), 'error');
    } elseif (!file_exists($base_lang_dir . $current_file)) {
        $messageStack->add('Error: Base file not found: ' . $base_lang_dir . $current_file, 'error');
    } else {
        // case: main language file (../lang.english.php)
        // override location: includes/languages/YOUR_TEMPLATE/lang.english.php
        if ($is_valid_parent) {
            $target_dir = DIR_FS_CATALOG_LANGUAGES . $active_template . '/';
            $target_file_path = $target_dir . basename($current_file);
        }
        // standard files (root, modules, extra defs) - insert the template name into the deepest directory
        else {

            $path_parts = pathinfo($current_file);
            $dir_part   = $path_parts['dirname']; // 'modules/shipping' or '.'
            $file_part  = $path_parts['basename'];

            if ($dir_part === '.') {
                $dir_part = '';
            } else {
                $dir_part .= '/';
            }

            $target_dir = $base_lang_dir . $dir_part . $active_template . '/';
            $target_file_path = $target_dir . $file_part;
        }

        // prepare content
        // get the original header (preserve $locales, @setlocale, etc)
        $header_content = get_file_header($base_lang_dir . $current_file);

        // if header is empty or just standard open tag, add a docblock for clarity
        if (trim($header_content) === '<?php') {
            $header_content .= "/**\n" .
                " * Override for " . basename($current_file) . "\n" .
                " * Generated by Admin Language Manager\n" .
                " * Mode: " . strtoupper($save_mode) . "\n" .
                " * Date: " . date('Y-m-d H:i:s') . "\n" .
                " */\n\n";
        }

        $file_content = $header_content . "return [\n";

        // process definitions
        $save_count = 0;
        $errors = [];

        if (isset($_POST['definitions']) && is_array($_POST['definitions'])) {
            foreach ($_POST['definitions'] as $key => $input) {

                // check "use default"
                // if checked, DO NOT write this key to the file.
                // either keeps the original or deletes an existing override
                if (isset($_POST['use_default']) && isset($_POST['use_default'][$key])) {
                    continue;
                }

                // process input
                $input = trim($input);

                // ONLY strip slashes if using standard POST
                // JSON data is already clean
                if (!$is_json_source) {
                    $input = stripslashes($input);
                }

                $input = htmlspecialchars_decode($input, ENT_QUOTES);

                if ($save_mode === 'basic') {
                    $final_line = var_export($input, true);
                    $file_content .= "  '$key' => " . $final_line . ",\n";
                    $save_count++;
                } else {
                    // allow empty strings in custom mode
                    if ($input === '') {
                        $file_content .= "  '$key' => '',\n";
                        $save_count++;
                        continue;
                    }

                    if (!is_valid_php_expression($input)) {
                        $errors[] = "<strong>$key</strong>". PHP_SYNTAX_ERROR;
                        continue;
                    }
                    $file_content .= "  '$key' => " . $input . ",\n";
                    $save_count++;
                }
            }
        }

        $file_content .= "];\n";

        // write to disk or show errors
        if (!empty($errors)) {
            foreach ($errors as $err) $messageStack->add($err, 'error');
            $messageStack->add(ERROR_SYNTAX_ERROR, 'warning');
        } else {
            // make sure directory exists
            if (!is_dir($target_dir)) {
                if (!mkdir($target_dir, 0755, true)) {
                    $messageStack->add(ERROR_CANT_CREATE_DIR . $target_dir, 'error');
                    $save_count = -1; // prevent saving
                }
            }

            if ($save_count >= 0) {
                // if we have overrides, save the file
                if ($save_count > 0) {
                    if (file_put_contents($target_file_path, $file_content)) {
                        $messageStack->add(sprintf(TEXT_SUCCESS_SAVED, $save_count) . str_replace(DIR_FS_CATALOG, '', $target_file_path), 'success');
                    } else {
                        $messageStack->add(ERROR_CHECK_PERMISSIONS . $target_dir, 'error');
                    }
                }
                // if all overrides were removed (empty), delete the override file to revert to core
                elseif (file_exists($target_file_path)) {
                    unlink($target_file_path);
                    $messageStack->add(TEXT_OVERRIDES_REMOVED, 'success');
                }
            }
        }
    }
}

// prepare file list for selection
$files_grouped = [
    'Main Language File' => [],
    'Page Definitions' => [],
    'Extra Definitions' => [],
    'Modules: Payment' => [],
    'Modules: Shipping' => [],
    'Modules: Order Total' => [],
    'Modules: Other' => []
];

// scan main directory (includes/languages/)
if (is_dir(dirname($base_lang_dir))) {
    $parent_files = scan_lang_dir(dirname($base_lang_dir) . '/');
    foreach ($parent_files as $f) {
        if ($f === 'lang.' . $target_language . '.php') {
            $files_grouped['Main Language File'][] = '../' . $f;
        }
    }
}

// scan selected language (includes/languages/{target}/)
if (is_dir($base_lang_dir)) {
    $root_files = scan_lang_dir($base_lang_dir);
    foreach ($root_files as $f) {
        if ($f === 'lang.' . $target_language . '.php') {
            $files_grouped['Main Language File'][] = $f;
        }
        elseif (strpos($f, 'lang.') === 0) {
            $files_grouped['Page Definitions'][] = $f;
        }
    }

    // scan extra_definitions
    if (is_dir($base_lang_dir . 'extra_definitions/')) {
        $files_grouped['Extra Definitions'] = scan_lang_dir($base_lang_dir . 'extra_definitions/', 'extra_definitions/');
    }

    // scan modules
    $modules_base = $base_lang_dir . 'modules/';
    if (is_dir($modules_base)) {
        $module_types = ['payment', 'shipping', 'order_total'];
        foreach ($module_types as $type) {
            $path = $modules_base . $type . '/';
            $key = 'Modules: ' . ucwords(str_replace('_', ' ', $type));
            if (is_dir($path)) {
                $files_grouped[$key] = scan_lang_dir($path, 'modules/' . $type . '/');
            }
        }
    }
}
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
</head>
<body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
<div class="container-fluid">
    <h1 class="page-header"><?php echo HEADING_TITLE; ?> <small>(<?php echo $active_template; ?>)</small></h1>

    <div class="row">

        <div class="col-xs-12 col-md-6 col-md-push-6 text-right">
            <button class="btn btn-info" type="button" data-toggle="collapse" data-target="#createLangCollapse" aria-expanded="false" aria-controls="createLangCollapse" style="margin-bottom: 15px;">
                <i class="fa fa-plus-circle"></i> <?php echo BUTTON_CREATE_NEW_PACK; ?>
            </button>

            <div id="createLangCollapse" class="collapse text-left">
                <div class="well well-sm"> <?php echo zen_draw_form('createLang', FILENAME_LANGUAGE_MANAGER, 'action=create_language', 'post'); ?>

                    <label style="margin-bottom: 5px; display:block;"><?php echo TEXT_CLONE_LABEL; ?></label>

                    <div style="display: flex; align-items: center;">
                        <div class="input-group" style="width: 100%;">
                            <input type="text" name="new_language" class="form-control" placeholder="<?php echo TEXT_PLACEHOLDER_LANG_CODE; ?>" pattern="[a-z0-9]+" title="<?php echo TEXT_TITLE_LOWERCASE; ?>" required>
                            <span class="input-group-btn">
                                <button type="submit" class="btn btn-primary"><?php echo BUTTON_CREATE; ?></button>
                            </span>
                        </div>
                    </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xs-12 col-md-6 col-md-pull-6">
            <div class="card mb-3">
                <div class="card-body bg-light">
                    <form action="<?php echo zen_href_link(FILENAME_LANGUAGE_MANAGER); ?>" method="get">
                        <input type="hidden" name="cmd" value="<?php echo FILENAME_LANGUAGE_MANAGER; ?>" />

                        <div class="form-group">
                            <label><?php echo TEXT_TARGET_LANGUAGE; ?></label>
                            <select name="language_target" class="form-control" onchange="this.form.submit()">
                                <?php
                                $installed_languages_directories = $db->Execute('SELECT DISTINCT directory FROM ' . DB_PREFIX . TABLE_LANGUAGES . ';');
                                foreach($installed_languages_directories as $inst_lang_dir) {
                                    $lname = $inst_lang_dir['directory'];
                                    $selected = ($target_language == $lname) ? 'selected' : '';
                                    echo "<option value='$lname' $selected>" . ucfirst($lname) . "</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group mb-0">
                            <label><?php echo TEXT_FILE_TO_EDIT; ?></label>
                            <select name="file" onchange="this.form.submit()" class="form-control">
                                <option value=""><?php echo TEXT_CHOOSE_FILE; ?></option>
                                <?php foreach ($files_grouped as $group_label => $group_files) {
                                    if (empty($group_files)) continue;
                                    ?>
                                    <optgroup label="<?php echo htmlspecialchars($group_label); ?>">
                                        <?php foreach ($group_files as $f) { ?>
                                            <option value="<?php echo $f; ?>" <?php echo ($current_file == $f ? 'selected' : ''); ?>>
                                                <?php echo $f; ?>
                                            </option>
                                        <?php } ?>
                                    </optgroup>
                                <?php } ?>
                            </select>
                        </div>

                        <?php if ($editor_mode === 'advanced') { ?>
                            <input type="hidden" name="mode" value="advanced">
                        <?php } ?>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <?php if (!empty($current_file) && file_exists($base_lang_dir . $current_file)) {
        // load raw data
        $raw_base_defs = get_raw_language_defs($base_lang_dir . $current_file);

        $override_filepath = '';

        if (strpos($current_file, '../') === 0) {
            $override_filepath = DIR_FS_CATALOG_LANGUAGES . $active_template . '/' . basename($current_file);
        } else {
            // standard/module files
            $path_parts = pathinfo($current_file);
            $dir_part   = $path_parts['dirname'];
            $file_part  = $path_parts['basename'];

            if ($dir_part === '.') $dir_part = '';
            else $dir_part .= '/';

            $override_filepath = $base_lang_dir . $dir_part . $active_template . '/' . $file_part;
        }

        $raw_override_defs = [];
        if (file_exists($override_filepath)) {
            $raw_override_defs = get_raw_language_defs($override_filepath);
        }
        ?>

        <div class="row">
            <div class="col-md-6">
                <div class="mode-switch">
                    <a href="<?php echo zen_href_link(FILENAME_LANGUAGE_MANAGER, 'file=' . $current_file); ?>" class="mode-btn <?php echo $editor_mode == 'basic' ? 'active' : ''; ?>">
                        <i class="fa fa-pencil"></i> <?php echo TEXT_MODE_BASIC; ?>
                    </a>
                    <a href="<?php echo zen_href_link(FILENAME_LANGUAGE_MANAGER, 'file=' . $current_file . '&mode=advanced'); ?>" class="mode-btn <?php echo $editor_mode == 'advanced' ? 'active' : ''; ?>">
                        <i class="fa fa-code"></i> <?php echo TEXT_MODE_ADVANCED; ?>
                    </a>
                </div>
            </div>
            <div class="col-md-6 text-right">
                <input type="text" id="searchInput" onkeyup="filterTable()" class="form-control" placeholder="<?php echo TEXT_SEARCH_PLACEHOLDER; ?>" style="max-width:300px; display:inline-block;">
            </div>
        </div>

        <div class="legend-box">
            <?php if ($editor_mode === 'basic') { ?>
                <strong><?php echo TEXT_LEGEND_BASIC_TITLE; ?></strong> <?php echo TEXT_LEGEND_BASIC_DESC; ?><br>
                <em><?php echo TEXT_LEGEND_BASIC_NOTE; ?></em>
            <?php } else { ?>
                <strong><?php echo TEXT_LEGEND_ADVANCED_TITLE; ?></strong> <?php echo TEXT_LEGEND_ADVANCED_DESC; ?> <br>
                <?php echo TEXT_LEGEND_ADVANCED_RULES; ?>
            <?php } ?>
        </div>

        <?php echo zen_draw_form('languageUpdate', FILENAME_LANGUAGE_MANAGER, 'action=save&file=' . $current_file, 'post'); ?>
        <input type="hidden" name="save_mode" value="<?php echo $editor_mode; ?>">
        <input type="hidden" name="mode" value="<?php echo $editor_mode; ?>">
        <input type="hidden" name="language_target" value="<?php echo $target_language; ?>">
        <input type="hidden" name="json_payload" id="json_payload" value="">

        <table class="table table-bordered table-hover lang-table" id="langTable">
            <thead class="thead-dark">
            <tr>
                <th class="col-key"><?php echo TABLE_HEADING_KEY; ?></th>
                <th class="col-orig"><?php echo TABLE_HEADING_ORIGINAL; ?></th>
                <th class="col-edit"><?php echo TABLE_HEADING_OVERRIDE; ?> <?php echo ($editor_mode == 'advanced' ? TEXT_RAW_PHP : ''); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($raw_base_defs as $key => $raw_base_val) {
                $has_override = isset($raw_override_defs[$key]);
                $raw_current_val = $has_override ? $raw_override_defs[$key] : '';

                // display logic
                $is_locked = false;
                $display_val = '';
                $placeholder = '';

                if ($editor_mode === 'basic') {
                    // check if current value is too complex for basic editor
                    $check_val = $has_override ? $raw_current_val : $raw_base_val;

                    if (is_complex_php($check_val)) {
                        $is_locked = true;
                        $display_val = TEXT_COMPLEX_LOCKED;
                    } else {
                        // strip outer quotes for display
                        $display_val = trim($raw_current_val, "'\"");
                        $placeholder = trim($raw_base_val, "'\"");
                    }
                } else {
                    // ADVANCED: show everything raw
                    $display_val = $raw_current_val;
                    $placeholder = $raw_base_val;
                }
                ?>
                <tr>
                    <td class="col-key"><?php echo $key; ?></td>
                    <td class="col-orig">
                        <code><?php echo htmlspecialchars($raw_base_val); ?></code>
                        <?php if (strpos($raw_base_val, '%s') !== false) echo '<div class="text-danger has-variable">' . sprintf(TEXT_CONTAINS_TOKEN, '%s') . '</div>'; ?>
                    </td>
                    <td class="col-edit">
                        <?php
                        // determine state
                        $is_default_checked = !$has_override;
                        ?>

                        <div id="wrapper_<?php echo $key; ?>" class="textarea-wrapper <?php echo $is_default_checked ? 'is-default' : ''; ?>">

                            <?php if ($is_locked) { ?>
                                <input type="text" class="form-control" disabled value="<?php echo $display_val; ?>" style="background:#eee; color:#888; font-style:italic;">

                            <?php } else { ?>
                                <textarea
                                    name="definitions[<?php echo $key; ?>]"
                                    id="input_<?php echo $key; ?>"
                                    data-key="<?php echo $key; ?>"
                                    class="form-control definition-input <?php echo $has_override ? 'has-override' : ''; ?>"
                                    placeholder="<?php echo htmlspecialchars($placeholder); ?>"
                                    style="<?php echo ($editor_mode == 'advanced' ? 'font-family:monospace; color:#d63384;' : ''); ?>"
                                ><?php echo htmlspecialchars($display_val); ?></textarea>
                            <?php } ?>

                            <label class="use-default-label" title="<?php echo TEXT_USE_DEFAULT_LABEL; ?>">
                                <input
                                    type="checkbox"
                                    name="use_default[<?php echo $key; ?>]"
                                    id="default_cb_<?php echo $key; ?>"
                                    data-key="<?php echo $key; ?>"
                                    class="default-checkbox"
                                    value="1"
                                    <?php echo $is_default_checked ? 'checked' : ''; ?>
                                >
                                <?php echo TEXT_USE_DEFAULT; ?>
                            </label>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>

        <button type="submit" class="btn btn-success btn-lg sticky-save"><i class="fa fa-save"></i> <?php echo BUTTON_SAVE_CHANGES; ?></button>
        </form>
    <?php } ?>

</div>
<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
</body>
</html>
