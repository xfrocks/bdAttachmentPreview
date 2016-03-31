<?php

class bdAttachmentPreview_XenForo_DataWriter_DiscussionMessage_Post extends XFCP_bdAttachmentPreview_XenForo_DataWriter_DiscussionMessage_Post
{
    protected function _messagePreSave()
    {
        parent::_messagePreSave();

        if ($this->isInsert() && $this->get('position') == 0) {
            $forum = $this->_getForumInfo();
            if (!empty($forum['bdattachmentpreview_options'])) {
                $options = unserialize($forum['bdattachmentpreview_options']);
                if (!empty($options['requires_thumbnail'])) {
                    $this->_bdAttachmentPreview_preSave_requiresThumbnail($forum);
                }
            }
        }
    }

    protected function _bdAttachmentPreview_preSave_requiresThumbnail(array $forum)
    {
        $attachmentHash = $this->getExtraData(self::DATA_ATTACHMENT_HASH);
        if (empty($attachmentHash)) {
            $this->error(new XenForo_Phrase('bdattachmentpreview_forum_x_requires_attachment',
                array('forum' => $forum['title'])), 'attach_count');
        }

        $thumbnailCount = $this->_db->fetchOne('
            SELECT COUNT(*)
            FROM `xf_attachment` AS attachment
            INNER JOIN `xf_attachment_data` as attachment_data
                ON (attachment_data.data_id = attachment.data_id)
            WHERE attachment.temp_hash = ?
                AND attachment_data.thumbnail_width > 0
                AND attachment_data.thumbnail_height > 0
        ', $attachmentHash);
        if ($thumbnailCount == 0) {
            $this->error(new XenForo_Phrase('bdattachmentpreview_forum_x_requires_thumbnail',
                array('forum' => $forum['title'])), 'attach_count');
        }
    }

}