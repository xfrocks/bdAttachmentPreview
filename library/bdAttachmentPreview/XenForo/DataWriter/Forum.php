<?php

class bdAttachmentPreview_XenForo_DataWriter_Forum extends XFCP_bdAttachmentPreview_XenForo_DataWriter_Forum
{
    protected function _getFields()
    {
        $fields = parent::_getFields();

        $fields['xf_forum']['bdattachmentpreview_options'] = array(
            'type' => XenForo_DataWriter::TYPE_SERIALIZED,
        );

        return $fields;
    }

    protected function _preSave()
    {
        if (isset($GLOBALS['bdAttachmentPreview_XenForo_ControllerAdmin_Forum::actionSave'])) {
            /** @var bdAttachmentPreview_XenForo_ControllerAdmin_Forum $controller */
            $controller = $GLOBALS['bdAttachmentPreview_XenForo_ControllerAdmin_Forum::actionSave'];
            $controller->bdAttachmentPreview_actionSave($this);
        }

        parent::_preSave();
    }

}