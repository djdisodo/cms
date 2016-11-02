<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\app\models;

use craft\app\base\Model;

/**
 * Folders parameters.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class FolderCriteria extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var integer ID
     */
    public $id;

    /**
     * @var integer Parent ID
     */
    public $parentId = false;

    /**
     * @var integer Source ID
     */
    public $volumeId;

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var string Path
     */
    public $path;

    /**
     * @var string Order
     */
    public $order = 'name asc';

    /**
     * @var integer Offset
     */
    public $offset;

    /**
     * @var integer Limit
     */
    public $limit;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id', 'parentId', 'sourceId', 'offset', 'limit'], 'number', 'integerOnly' => true],
        ];
    }
}