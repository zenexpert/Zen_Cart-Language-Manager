<?php

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    protected function executeInstall()
    {
        // register Admin Page (Tools Menu)
        // Check if exists first to avoid duplicates
        if (!zen_page_key_exists('toolsLocalizationManager')) {
            zen_register_admin_page(
                'toolsLocalizationManager',
                'BOX_LOCALIZATION_LANGUAGE_MANAGER',
                'FILENAME_LANGUAGE_MANAGER',
                '',
                'localization',
                'Y',
                100
            );
        }
    }

    protected function executeUninstall()
    {
        // Remove Admin Page
        zen_deregister_admin_pages(['toolsLocalizationManager']);

    }
}
