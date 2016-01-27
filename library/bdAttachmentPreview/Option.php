<?php

class bdAttachmentPreview_Option
{
    public static function get($key, $subKey = null)
    {
        $options = XenForo_Application::getOptions();

        return $options->get('bdAttachmentPreview_' . $key, $subKey);
    }

    public static function renderPdf(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
    {
        $instructions = array();
        $aptGet = exec('which apt-get');
        $yum = exec('which yum');

        $ghostscript = exec('which ghostscript');
        if (empty($ghostscript)) {
            if ($aptGet) {
                $instructions['ghostscript'] = 'sudo apt-get install ghostscript';
            } elseif ($yum) {
                $instructions['ghostscript'] = 'sudo yum install ghostscript';
            }
        }

        $pdfinfo = exec('which pdfinfo');
        if (empty($pdfinfo)) {
            if ($aptGet) {
                $instructions['pdfinfo'] = 'sudo apt-get install poppler-utils';
            } elseif ($yum) {
                $instructions['pdfinfo'] = 'sudo yum install poppler-utils';
            }
        }

        $editLink = $view->createTemplateObject('option_list_option_editlink', array(
            'preparedOption' => $preparedOption,
            'canEditOptionDefinition' => $canEdit
        ));

        return $view->createTemplateObject('bdattachmentpreview_option_template_pdf', array(
            'fieldPrefix' => $fieldPrefix,
            'listedFieldName' => $fieldPrefix . '_listed[]',
            'preparedOption' => $preparedOption,
            'formatParams' => $preparedOption['formatParams'],
            'editLink' => $editLink,

            'ghostscript' => $ghostscript,
            'pdfinfo' => $pdfinfo,
            'instructions' => $instructions,
        ));
    }
}