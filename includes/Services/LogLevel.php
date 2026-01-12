<?php

namespace ActivatedInsights\HomeCareAgencyImporter\Services;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

/**
 * Enum used to indicate logging level when using the LogService.
 * Also used for WordPress admin notice levels when making admin
 * notices. See https://developer.wordpress.org/reference/hooks/admin_notices/
 */
enum LogLevel: string {
    /**
     * Failure has occurred that interrupts expected behavior. 
     * Will display the message with a white background and a red left border.
     */
    case ERROR = 'error';

    /**
     * Potential concern has occured but expected behavior may be able to continue.
     * Will display the message with a white background and a yellow/orange left border.
     */
    case WARNING = 'warning';

    /**
     * Expected/desired behavior has occurred.
     * Will display the message with a white background and a green left border.
     */
    case SUCCESS = 'success';

    /**
     * A purely information message.
     * Will display the message with a white background a blue left border.
     */
    case INFO = 'info';
}