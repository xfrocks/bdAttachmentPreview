<?php

class bdAttachmentPreview_XenForo_ControllerAdmin_Forum extends XFCP_bdAttachmentPreview_XenForo_ControllerAdmin_Forum
{
    public function actionEdit()
    {
        $response = parent::actionEdit();

        if ($response instanceof XenForo_ControllerResponse_View
            && !empty($response->params['forum']['bdattachmentpreview_options'])
        ) {
            $response->params['bdAttachmentPreview_options']
                = unserialize($response->params['forum']['bdattachmentpreview_options']);
        }

        return $response;
    }

    public function actionSave()
    {
        $GLOBALS['bdAttachmentPreview_XenForo_ControllerAdmin_Forum::actionSave'] = $this;

        return parent::actionSave();
    }

    public function bdAttachmentPreview_actionSave(XenForo_DataWriter_Forum $dw)
    {
        if (!$this->_input->inRequest('bdattachmentpreview_options_included')) {
            return;
        }

        $options = $this->_input->filterSingle('bdattachmentpreview_options', XenForo_Input::ARRAY_SIMPLE);

        $dw->set('bdattachmentpreview_options', $options);
    }
}