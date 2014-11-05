<?php

/**
 * Craft Social Login by Dukt
 *
 * @package   Craft Social Login
 * @author    Benjamin David
 * @copyright Copyright (c) 2014, Dukt
 * @license   https://dukt.net/craft/social/docs/license
 * @link      https://dukt.net/craft/social/
 */

namespace Craft;

class Social_UserRecord extends BaseRecord
{
    /**
     * Get Table Name
     */
    public function getTableName()
    {
        return 'social_users';
    }

    /**
     * Define Attributes
     */
    public function defineAttributes()
    {
        return array(
            'provider' => array(AttributeType::String, 'required' => true),
            'socialUid' => array(AttributeType::String, 'required' => true),
            'tokenId' => array(AttributeType::Number, 'required' => false),
        );
    }

    /**
     * Define Relations
     */
    public function defineRelations()
    {
        return array(
            'user' => array(static::BELONGS_TO, 'UserRecord', 'onDelete' => static::CASCADE, 'required' => true),
        );
    }

    /**
     * Define Indexes
     *
     * @return array
     */
    public function defineIndexes()
    {
        return array(
            array('columns' => array('provider', 'socialUid'), 'unique' => true)
        );
    }
}
