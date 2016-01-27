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
                $videoExt = bdAttachmentPreview_Option::get('videoExt', 'preg_split');
                if (in_array($extension, $videoExt, true)) {
                    return $this->generateVideoThumb($dw);
                }

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
        list($tempFile, $shouldUnlinkTempFile) = $this->_common_prepareTempFile($dw);
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
        if (!$this->_common_setDwThumbnail($dw, $previewTempFile, IMAGETYPE_PNG, false)) {
            return false;
        }
        $dw->set('bdattachmentpreview_data', array('options' => $options));

        return true;
    }

    public function generateVideoThumb(XenForo_DataWriter_AttachmentData $dw)
    {
        $options = bdAttachmentPreview_Option::get('videoThumb');
        if (empty($options['ffmpeg'])) {
            return false;
        }

        // step 1: prepare a valid temp file
        list($tempFile, $shouldUnlinkTempFile) = $this->_common_prepareTempFile($dw);
        if (!$tempFile) {
            return false;
        }

        // step 2: generate preview
        $previewTempFile = '';
        if (!empty($options['ffmpeg'])) {
            $previewTempFile = $this->_generateVideoThumb_ffmpeg($options['ffmpeg'], $tempFile, $options);
        }

        // step 3: clean up temp file (if needed)
        if ($shouldUnlinkTempFile) {
            unlink($tempFile);
        }

        // step 4: keep track of new preview (if generated)
        if (!$this->_common_setDwThumbnail($dw, $previewTempFile, IMAGETYPE_JPEG, true)) {
            return false;
        }

        return true;
    }

    protected function _common_prepareTempFile(XenForo_DataWriter_AttachmentData $dw)
    {
        $tempFile = $dw->getExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_FILE);
        $shouldUnlinkTempFile = false;

        if (!$tempFile) {
            $data = $dw->getExtraData(XenForo_DataWriter_AttachmentData::DATA_FILE_DATA);
            if ($data) {
                $tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'bdattachmentpreview_');
                file_put_contents($tempFile, $data);
                $shouldUnlinkTempFile = true;
            }
        }

        if (!$tempFile) {
            if (XenForo_Application::debugMode()) {
                XenForo_Helper_File::log(__CLASS__, sprintf('prepareTempFile failed (filename=%s, file_hash=%s)',
                    $dw->get('filename'), $dw->get('file_hash')));
            }
        }

        return array($tempFile, $shouldUnlinkTempFile);
    }

    protected function _common_setDwThumbnail(
        XenForo_DataWriter_AttachmentData $dw,
        $previewTempFile,
        $previewType,
        $resize
    ) {
        if (empty($previewTempFile)) {
            return false;
        }

        $previewImage = XenForo_Image_Abstract::createFromFile($previewTempFile, $previewType);
        if (!$previewImage) {
            if (XenForo_Application::debugMode()) {
                XenForo_Helper_File::log(__CLASS__, sprintf('setThumbnailWidthHeight: failed to create image '
                    . '($$previewTempFile=%s, $previewType=%d)',
                    $previewTempFile, $previewType));
            }

            return false;
        }

        if ($resize) {
            if ($previewImage->thumbnail(XenForo_Application::getOptions()->get('attachmentThumbnailDimensions')))
            {
                $previewImage->output($previewType, $previewTempFile);
            }
        }

        $dw->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_THUMB_FILE, $previewTempFile);
        $dw->set('thumbnail_width', $previewImage->getWidth());
        $dw->set('thumbnail_height', $previewImage->getHeight());

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

    protected function _generateVideoThumb_ffmpeg($binaryPath, $pdfPath, array $options)
    {
        $extraParams = array();
        if (isset($options['ffmpeg_params'])) {
            $extraParams[] = $options['ffmpeg_params'];
        }

        $thumbTempFile = tempnam(XenForo_Helper_File::getTempDir(), 'video_thumb_ffmpeg');
        $ffmpegCmd = sprintf('%1$s -i %2$s -vframes 1 -y %4$s -f image2 -- %3$s',
            $binaryPath,
            escapeshellarg($pdfPath),
            escapeshellarg($thumbTempFile),
            implode(' ', $extraParams));
        $ffmpegOutput = array();
        $ffmpegStatus = 0;
        exec($ffmpegCmd, $ffmpegOutput, $ffmpegStatus);

        if ($ffmpegStatus === 0) {
            if (XenForo_Application::debugMode()) {
                XenForo_Helper_File::log(__METHOD__, sprintf("%s -> %d\n%s", $ffmpegCmd,
                    $ffmpegStatus, implode("\n", $ffmpegOutput)));
            }

            return $thumbTempFile;
        } else {
            XenForo_Error::logError("%s -> %d\n%s", $ffmpegCmd,
                $ffmpegStatus, implode("\n", $ffmpegOutput));

            unlink($thumbTempFile);
            return '';
        }
    }
}