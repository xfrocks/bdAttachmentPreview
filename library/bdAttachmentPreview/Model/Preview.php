<?php

class bdAttachmentPreview_Model_Preview extends XenForo_Model
{
    public function onInsertUploadedAttachmentData(XenForo_DataWriter_AttachmentData $dw)
    {
        $filename = $dw->get('filename');
        $extension = XenForo_Helper_File::getFileExtension($filename);

        return $this->generatePreview($extension, $dw);
    }

    public function generatePreview($extension, XenForo_DataWriter_AttachmentData $dw)
    {
        switch ($extension) {
            case 'pdf':
                return $this->generatePdfPreview($dw);
            default:
                return false;
        }
    }

    public function generatePdfPreview(XenForo_DataWriter_AttachmentData $dw)
    {
        $options = bdAttachmentPreview_Option::get('pdf');
        if (empty($options['ghostscript'])) {
            return false;
        }

        // step 1: prepare a valid temp file
        $tempFile = $dw->getExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_FILE);
        $shouldUnlinkTempFile = false;
        if (!$tempFile) {
            $data = $dw->getExtraData(XenForo_DataWriter_AttachmentData::DATA_FILE_DATA);
            if ($data) {
                $tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'pdf_preview');
                file_put_contents($tempFile, $data);
                $shouldUnlinkTempFile = true;
            }
        }
        if (!$tempFile) {
            return false;
        }

        // step 2: generate preview
        $options = $this->_generatePdfPreview_prepareOptions($tempFile, $options);
        $previewTempFile = '';
        if (!empty($options['ghostscript'])) {
            $previewTempFile = $this->_generatePdfPreview_ghostscript($options['ghostscript'], $tempFile, $options);
        }

        // step 3: clean up temp file (if needed)
        if ($shouldUnlinkTempFile) {
            unlink($tempFile);
        }

        // step 4: keep track of new preview (if generated)
        if (empty($previewTempFile)) {
            return false;
        }
        $previewImage = XenForo_Image_Abstract::createFromFile($previewTempFile, IMAGETYPE_PNG);
        if (!$previewImage) {
            return false;
        }

        $dw->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_THUMB_FILE, $previewTempFile);
        $dw->set('thumbnail_height', $previewImage->getHeight());
        $dw->set('thumbnail_width', $previewImage->getWidth());
        $dw->set('bdattachmentpreview_data', array('options' => $options));

        return true;
    }

    protected function _generatePdfPreview_prepareOptions($pdfPath, array $options)
    {
        $pdfInfo = array();
        if (!empty($options['pdfinfo'])) {
            $pdfInfo = $this->_generatePdfPreview_pdfinfo($options['pdfinfo'], $pdfPath);
        }

        if (isset($options['pages_max'])
            && $options['pages_max'] > 0
            && isset($pdfInfo['pages'])
            && $pdfInfo['pages'] > 0
        ) {
            $options['_prepared_pages_offset'] = 0;
            $options['_prepared_pages_limit'] = min($options['pages_max'], $pdfInfo['pages']);
        }

        return $options;
    }

    protected function _generatePdfPreview_ghostscript($binaryPath, $pdfPath, array $options)
    {
        $extraParams = array();
        if (isset($options['_prepared_pages_offset'])
            && isset($options['_prepared_pages_limit'])
        ) {
            $extraParams[] = sprintf('-dFirstPage=%d', 1 + $options['_prepared_pages_offset']);
            $extraParams[] = sprintf('-dLastPage=%d',
                $options['_prepared_pages_offset'] + $options['_prepared_pages_limit']);
        }

        $previewTempFile = tempnam(XenForo_Helper_File::getTempDir(), 'pdf_preview_ghostscript');
        $ghostscriptCmd = sprintf('%1$s -dSAFER -sDEVICE=png16m %4$s -o %3$s %2$s',
            $binaryPath,
            escapeshellarg($pdfPath),
            escapeshellarg($previewTempFile),
            implode(' ', $extraParams));
        $ghostscriptOutput = array();
        $ghostscriptStatus = 0;
        exec($ghostscriptCmd, $ghostscriptOutput, $ghostscriptStatus);

        if ($ghostscriptStatus === 0) {
            if (XenForo_Application::debugMode()) {
                XenForo_Helper_File::log(__METHOD__, sprintf("%s -> %d\n%s", $ghostscriptCmd,
                    $ghostscriptStatus, implode("\n", $ghostscriptOutput)));
            }

            return $previewTempFile;
        } else {
            XenForo_Error::logError("%s -> %d\n%s", $ghostscriptCmd,
                $ghostscriptStatus, implode("\n", $ghostscriptOutput));

            unlink($previewTempFile);
            return '';
        }
    }

    protected function _generatePdfPreview_pdfinfo($binaryPath, $pdfPath)
    {
        $info = array();

        $pdfinfoCmd = sprintf('%1$s %2$s', $binaryPath, escapeshellarg($pdfPath));
        $pdfinfoOutput = array();
        $pdfinfoStatus = 0;
        exec($pdfinfoCmd, $pdfinfoOutput, $pdfinfoStatus);

        if ($pdfinfoStatus === 0) {
            foreach ($pdfinfoOutput as $line) {
                if (preg_match('#^(?<key>[^:]+):\s+(?<value>.+?)$#', $line, $matches)) {
                    $info[strtolower($matches['key'])] = $matches['value'];
                }
            }
        }

        return $info;
    }
}