<?php

class bdAttachmentPreview_XenForo_DataWriter_AttachmentData extends XFCP_bdAttachmentPreview_XenForo_DataWriter_AttachmentData
{
    protected function _getFields()
    {
        $fields = parent::_getFields();

        $fields['xf_attachment_data']['bdattachmentpreview_data'] = array(
            'type' => XenForo_DataWriter::TYPE_SERIALIZED,
        );

        return $fields;
    }

    protected function _preSave()
    {
        if ($this->isInsert()) {
            $thumbTempFile = $this->getExtraData(self::DATA_TEMP_THUMB_FILE);
            $thumbData = $this->getExtraData(self::DATA_THUMB_DATA);
            if (!$thumbTempFile && !$thumbData) {
                /** @var bdAttachmentPreview_Model_Preview $previewModel */
                $previewModel = $this->getModelFromCache('bdAttachmentPreview_Model_Preview');
                $previewModel->onInsertUploadedAttachmentData($this);
            }
        }

        parent::_preSave();
    }

}