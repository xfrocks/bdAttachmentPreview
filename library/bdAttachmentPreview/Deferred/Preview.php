<?php

class bdAttachmentPreview_Deferred_Preview extends XenForo_Deferred_Abstract
{

    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        $data = array_merge(array(
            'batch' => 50,
            'position' => 0
        ), $data);

        /** @var $attachmentModel XenForo_Model_Attachment */
        $attachmentModel = XenForo_Model::create('XenForo_Model_Attachment');
        /** @var bdAttachmentPreview_Model_Preview $previewModel */
        $previewModel = $attachmentModel->getModelFromCache('bdAttachmentPreview_Model_Preview');

        $s = microtime(true);

        $dataIds = $attachmentModel->getAttachmentDataIdsInRange($data['position'], $data['batch']);
        if (sizeof($dataIds) == 0) {
            return false;
        }

        foreach ($dataIds AS $dataId) {
            $data['position'] = $dataId;

            /** @var XenForo_DataWriter_AttachmentData $dw */
            $dw = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData', XenForo_DataWriter::ERROR_SILENT);
            if ($dw->setExistingData($dataId)
                && strval($dw->get('thumbnail_width')) === '0'
                && strval($dw->get('thumbnail_height')) === '0'
                && $previewModel->onInsertUploadedAttachmentData($dw)
            ) {
                try {
                    $dw->save();
                } catch (Exception $e) {
                    XenForo_Error::logException($e, false, "Preview rebuild for #$dataId: ");
                }
            }

            if ($targetRunTime
                && microtime(true) - $s > $targetRunTime
            ) {
                break;
            }
        }

        $actionPhrase = new XenForo_Phrase('rebuilding');
        $typePhrase = new XenForo_Phrase('bdattachmentpreview_previews');
        $status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

        return $data;
    }

    public function canCancel()
    {
        return true;
    }
}