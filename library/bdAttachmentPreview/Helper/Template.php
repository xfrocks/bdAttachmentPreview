<?php

class bdAttachmentPreview_Helper_Template
{
    public static function getSafeUrl($url) {
        return bdAttachmentPreview_ShippableHelper_Http::resolveRedirect(trim($url));
    }
}