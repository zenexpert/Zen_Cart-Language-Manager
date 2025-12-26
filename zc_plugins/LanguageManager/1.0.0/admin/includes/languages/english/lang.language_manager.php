<?php

$define = [
    'HEADING_TITLE' => 'Language Manager',
    'NAVBAR_TITLE' => 'Language Manager',

    // Create Pack Section
    'BUTTON_CREATE_NEW_PACK' => 'Create New Language Pack',
    'TEXT_CLONE_LABEL' => 'Clone <strong>English</strong> to New Language:',
    'TEXT_PLACEHOLDER_LANG_CODE' => 'e.g. french',
    'TEXT_TITLE_LOWERCASE' => 'Lowercase letters only',
    'BUTTON_CREATE' => 'Create',

    // Selector Section
    'TEXT_TARGET_LANGUAGE' => 'Target Language:',
    'TEXT_FILE_TO_EDIT' => 'File to Edit:',
    'TEXT_CHOOSE_FILE' => '-- Choose File --',

    // Modes & Legends
    'TEXT_MODE_BASIC' => 'Basic Mode',
    'TEXT_MODE_ADVANCED' => 'Advanced / Developer',
    'TEXT_SEARCH_PLACEHOLDER' => 'Search keys or text...',

    'TEXT_LEGEND_BASIC_TITLE' => 'Basic Mode:',
    'TEXT_LEGEND_BASIC_DESC' => 'Enter text normally. Quotes and special characters are handled automatically.',
    'TEXT_LEGEND_BASIC_NOTE' => 'Note: Complex values (like Constants) are locked in this mode to prevent errors. Switch to Advanced to edit them.',

    'TEXT_LEGEND_ADVANCED_TITLE' => 'Advanced Mode:',
    'TEXT_LEGEND_ADVANCED_DESC' => 'You are editing raw PHP code.',
    'TEXT_LEGEND_ADVANCED_RULES' => '<span class="text-danger">Rules:</span> You <strong>must</strong> include quotes (e.g., <code>\'My Text\'</code>) or valid PHP syntax (e.g., <code>\'Hello \' . STORE_NAME</code>).',

    // Table
    'TABLE_HEADING_KEY' => 'Key',
    'TABLE_HEADING_ORIGINAL' => 'Original (Raw Code)',
    'TABLE_HEADING_OVERRIDE' => 'Your Override',
    'TEXT_RAW_PHP' => '(RAW PHP)',
    'TEXT_COMPLEX_LOCKED' => 'COMPLEX PHP (Switch to Advanced Mode to Edit)',
    'TEXT_CONTAINS_TOKEN' => 'Contains %s',

    'BUTTON_SAVE_CHANGES' => 'Save Changes',
];

return $define;
