<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Journal d'audit LogiOne (SEC-03) : qui a fait quoi et quand.
 * Aucune clé, aucun secret et aucune copie complète des réponses d'élèves n'y sont écrits.
 */
final class LogiPilot_Log
{
    public static function init(): void
    {
    }

    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'logipilot_log';
    }

    public static function add(string $action, string $object_type = '', int $object_id = 0, array $data = array()): void
    {
        global $wpdb;

        $table = self::table();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return;
        }

        $wpdb->insert(
            $table,
            array(
                'user_id' => get_current_user_id(),
                'action' => substr($action, 0, 80),
                'object_type' => substr($object_type, 0, 40),
                'object_id' => $object_id,
                'data' => $data ? wp_json_encode($data) : null,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s')
        );
    }
}
