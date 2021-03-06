<?php

class bdAttachmentPreview_DevHelper_Config extends DevHelper_Config_Base
{
    protected $_dataClasses = array();
    protected $_dataPatches = array(
        'xf_attachment_data' => array(
            'bdattachmentpreview_data' => array('name' => 'bdattachmentpreview_data', 'type' => 'serialized'),
        ),
        'xf_forum' => array(
            'bdattachmentpreview_options' => array('name' => 'bdattachmentpreview_options', 'type' => 'serialized'),
        ),
    );
    protected $_exportPath = '/Users/sondh/XenForo/bdAttachmentPreview';
    protected $_exportIncludes = array();
    protected $_exportExcludes = array();
    protected $_exportAddOns = array();
    protected $_exportStyles = array();
    protected $_options = array();

    /**
     * Return false to trigger the upgrade!
     **/
    protected function _upgrade()
    {
        return true; // remove this line to trigger update

        /*
        $this->addDataClass(
            'name_here',
            array( // fields
                'field_here' => array(
                    'type' => 'type_here',
                    // 'length' => 'length_here',
                    // 'required' => true,
                    // 'allowedValues' => array('value_1', 'value_2'),
                    // 'default' => 0,
                    // 'autoIncrement' => true,
                ),
                // other fields go here
            ),
            array('primary_key_1', 'primary_key_2'), // or 'primary_key', both are okie
            array( // indeces
                array(
                    'fields' => array('field_1', 'field_2'),
                    'type' => 'NORMAL', // UNIQUE or FULLTEXT
                ),
            ),
        );
        */
    }
}