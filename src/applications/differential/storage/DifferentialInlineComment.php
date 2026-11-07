<?php

final class DifferentialInlineComment
  extends PhabricatorInlineComment {

  protected function newStorageObject() {
    return new DifferentialTransactionComment();
  }

  public function getControllerURI() {
    return urisprintf(
      '/differential/comment/inline/edit/%s/',
      $this->getRevisionID());
  }

  public function getTransactionCommentForSave() {
    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorOldWorldContentSource::SOURCECONST);

    $this->getStorageObject()
      ->setViewPolicy('public')
      ->setEditPolicy($this->getAuthorPHID())
      ->setContentSource($content_source)
      ->attachIsHidden(false)
      ->setCommentVersion(1);

    return $this->getStorageObject();
  }

  public function supportsHiding() {
    if ($this->getSyntheticAuthor()) {
      return false;
    }
    return true;
  }

  public function isHidden() {
    if (!$this->supportsHiding()) {
      return false;
    }
    return $this->getStorageObject()->getIsHidden();
  }

  public static function newFromModernComment(
    DifferentialTransactionComment $comment) {

    $obj = new self();
    $obj->setStorageObject($comment);

    return $obj;
  }

  /**
   * Test if a viewer may mark this inline comment as "Done" or "Not Done".
   *
   * @param PhabricatorUser $viewer
   * @param DifferentialRevision $revision The revision of this comment.
   * @return bool
   */
  public function canViewerChangeDoneState(
    PhabricatorUser $viewer,
    DifferentialRevision $revision) {

    $viewer_phid = $viewer->getPHID();
    if (!$viewer_phid) {
      return false;
    }

    // You own the revision, so you can mark the comment as "Done".
    if ($viewer_phid == $revision->getAuthorPHID()) {
      return true;
    }

    // You made this comment and it's still a draft, so you can mark it as
    // "Done".
    if ($viewer_phid == $this->getAuthorPHID() && $this->isDraft()) {
      return true;
    }

    return false;
  }

  public function setChangesetID($id) {
    $this->getStorageObject()->setChangesetID($id);
    return $this;
  }

  public function getChangesetID() {
    return $this->getStorageObject()->getChangesetID();
  }

  public function setRevision(DifferentialRevision $revision) {
    $this->getStorageObject()->setRevisionPHID($revision->getPHID());
    return $this;
  }

  public function getRevisionPHID() {
    return $this->getStorageObject()->getRevisionPHID();
  }

  // Although these are purely transitional, they're also *extra* dumb.

  public function setRevisionID($revision_id) {
    $revision = id(new DifferentialRevision())->load($revision_id);
    return $this->setRevision($revision);
  }

  public function getRevisionID() {
    $phid = $this->getStorageObject()->getRevisionPHID();
    if (!$phid) {
      return null;
    }

    $revision = id(new DifferentialRevision())->loadOneWhere(
      'phid = %s',
      $phid);
    if (!$revision) {
      return null;
    }
    return $revision->getID();
  }

}
