<?php

class bdAttachmentPreview_XenForo_ControllerPublic_Attachment extends XFCP_bdAttachmentPreview_XenForo_ControllerPublic_Attachment
{
    public function actionPreview()
    {
        $attachmentId = $this->_input->filterSingle('attachment_id', XenForo_Input::UINT);
        $attachment = $this->_getAttachmentOrError($attachmentId);

        $attachmentModel = $this->_getAttachmentModel();
        $attachment = $attachmentModel->prepareAttachment($attachment, true);
        if (empty($attachment['thumbnailUrl'])) {
            return $this->responseNoPermission();
        }

        $this->canonicalizeRequestUrl(
            XenForo_Link::buildPublicLink('attachments/preview', $attachment)
        );

        $viewParams = array(
            'attachment' => $attachment,
            'canViewAttachment' => $attachmentModel->canViewAttachment($attachment),
        );

        return $this->responseView(
            'bdAttachmentPreview_ViewPublic_Attachment_Preview',
            'bdattachmentpreview_attachment_preview',
            $viewParams
        );
    }
}