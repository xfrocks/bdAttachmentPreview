<?php

XenForo_Model_Attachment::$dataColumns .= ', data.bdattachmentpreview_data';

class bdAttachmentPreview_Listener
{
    public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
    {

    }

    public static function load_class_XenForo_DataWriter_AttachmentData($class, array &$extend)
    {
        if ($class === 'XenForo_DataWriter_AttachmentData') {
            $extend[] = 'bdAttachmentPreview_XenForo_DataWriter_AttachmentData';
        }
    }

    public static function load_class_XenForo_ControllerPublic_Attachment($class, array &$extend)
    {
        if ($class === 'XenForo_ControllerPublic_Attachment') {
            $extend[] = 'bdAttachmentPreview_XenForo_ControllerPublic_Attachment';
        }
    }

    public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
    {
        $hashes += bdAttachmentPreview_FileSums::getHashes();
    }
}