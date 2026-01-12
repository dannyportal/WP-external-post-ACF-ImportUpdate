<?php

namespace ActivatedInsights\HomeCareAgencyImporter\Services;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use ActivatedInsights\HomeCareAgencyImporter\Services\LogLevel;
use DateTime;

/**
 * Custom logging service for this plugin to standardize the format, ensure
 * that the calling function is known, and ensure a log level is indicated.
 * Also allows creating a log and admin notice at the same time for convenience.
 * 
 * @package ActivatedInsights\HomeCareAgencyImporter\Services
 */
class LogService {
    /**
     * Whether the script is in direct echo mode. TRUE indicates that the script
     * was called via a URL request and is outputting plain text log information.
     * FALSE indicates that the script was called via the WordPress admin UI and
     * should output HTML admin notices.
     * @var bool
     */
    protected static bool $isDirectEcho = false;

    /**
     * Set whether the script is in direct echo mode. TRUE indicates that the script
     * was called via a URL request and is outputting plain text log information.
     * FALSE indicates that the script was called via the WordPress admin UI and
     * should output HTML admin notices.
     * 
     * @param bool $isDirectEcho 
     * @return void 
     */
    public static function setIsDirectEcho(bool $isDirectEcho): void {
        self::$isDirectEcho = $isDirectEcho;

        // Set output headers for direct echo mode
        if ($isDirectEcho === true) {
            header('Content-type: text/plain; charset-utf-8');
            send_nosniff_header();
            header('Cache-Control: no-cache');
            header('Pragma: no-cache');
        }
    }

    /**
     * Get whether the script is in direct echo mode. TRUE indicates that the script
     * was called via a URL request and is outputting plain text log information.
     * FALSE indicates that the script was called via the WordPress admin UI and
     * should output HTML admin notices.
     * 
     * @return bool 
     */
    public static function getIsDirectEcho(): bool {
        return self::$isDirectEcho;
    }

    /**
     * Log a message in the standard PHP/WordPress log, while requiring the
     * inclusion of caller details and log level to ensure useful logs.
     * Also allows automatically creating a WordPress admin notice at the
     * same time.
     * 
     * @param string $callerMethod Set this to __METHOD__ so the calling function is indicated.
     * @param LogLevel $logLevel Logging level to indicate the severity of the message.
     * @param string $message Message to display in the log / admin notice.
     * @param bool $isAdminNotice Set to true to also create a WP Admin notice, or false to only log.
     * @return void 
     */
    public static function log(
        string $callerMethod, 
        LogLevel $logLevel, 
        string $message, 
        bool $isAdminNotice = false
    ): void {    
        $messageWithFullPrefix =  "[HCA Importer Plugin][{$logLevel->value}][$callerMethod] $message";  
        error_log($messageWithFullPrefix);
        
        // Flush the output buffers to ensure the log message is written immediately.
        // If this is not done, the log message may not be written until the script ends.
        ob_flush();
        flush();

        // When logging for a task requested via URL (instead of the admin UI),
        // echo the log message directly.
        if (self::$isDirectEcho) {
            $timestamp = (new DateTime())->format("Y-m-d H:i:s");
            echo "$timestamp $messageWithFullPrefix" . PHP_EOL;
        } 
        
        if ($isAdminNotice) {
            self::adminNotice($logLevel, "[HCA Importer Plugin][$callerMethod] $message");
        }
    }

    /**
     * Function to call after a task process request is complete. Kills the script
     * if the script is in direct echo mode. This is to prevent WordPress
     * themes/templates from being output since direct echo outputs plain
     * text log information instead.
     * @return void 
     */
    public static function dieIfDirectEcho(): void {
        if (self::$isDirectEcho) {
            die();
        }
    }

    /**
     * Create a WordPress admin notice with a specified log level and message.
     * 
     * @param LogLevel $logLevel Logging level to indicate the severity of the message. Affects the color of the notice.
     * @param string $message Message to display in the admin notice.
     * @return void 
     */
    public static function adminNotice(LogLevel $logLevel, string $message): void {
        add_action('admin_notices', function() use ($logLevel, $message) {
            ?>
                <div class="notice notice-<?=$logLevel->value?> is-dismissible">
                    <p><?=esc_html($message)?></p>
                </div>
            <?php
        });
    }
}