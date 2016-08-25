<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\app\events;

/**
 * Elements delete event class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class ElementsDeleteEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var array The element IDs associated with this event.
     */
    public $elementIds;
}