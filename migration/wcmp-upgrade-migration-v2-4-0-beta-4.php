<?php

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('wcmp_installation_migration_v2_4_0_beta_4')) :

    /**
     * Migrates pre v2.4.0-beta-4 settings
     */
    class wcmp_installation_migration_v2_4_0_beta_4
    {
        public function __construct()
        {
            // remove log file (now uses WC logger)
            $upload_dir  = wp_upload_dir();
            $upload_base = trailingslashit($upload_dir['basedir']);
            $log_file    = $upload_base . 'myparcelbe_log.txt';
            if (@file_exists($log_file)) {
                @unlink($log_file);
            }
        }
    }

endif;

new wcmp_installation_migration_v2_4_0_beta_4();
