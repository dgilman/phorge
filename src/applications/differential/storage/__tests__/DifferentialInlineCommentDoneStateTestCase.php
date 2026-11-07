<?php

final class DifferentialInlineCommentDoneStateTestCase
  extends PhabricatorTestCase {

  public function testCanViewerChangeDoneState() {
    $owner_phid = 'PHID-USER-owner';
    $reviewer_phid = 'PHID-USER-reviewer';
    $other_phid = 'PHID-USER-other';

    $revision = id(new DifferentialRevision())
      ->setAuthorPHID($owner_phid);

    // Each case is: viewer, inline author, is published, expected result.
    $cases = array(
      array($owner_phid, $reviewer_phid, true, true),
      array($owner_phid, $reviewer_phid, false, true),
      array($owner_phid, $owner_phid, true, true),
      array($reviewer_phid, $reviewer_phid, false, true),
      array($reviewer_phid, $reviewer_phid, true, false),
      array($other_phid, $reviewer_phid, true, false),
      array($other_phid, $reviewer_phid, false, false),
      array(null, $reviewer_phid, true, false),
    );

    foreach ($cases as $case) {
      list($viewer_phid, $author_phid, $is_published, $expect) = $case;

      $viewer = new PhabricatorUser();
      if ($viewer_phid) {
        $viewer->setPHID($viewer_phid);
      }

      $inline = id(new DifferentialInlineComment())
        ->setAuthorPHID($author_phid);
      if ($is_published) {
        $inline->getStorageObject()->setTransactionPHID('PHID-XACT-1111');
      }

      $this->assertEqual(
        $expect,
        $inline->canViewerChangeDoneState($viewer, $revision),
        pht(
          'Viewer "%s", inline author "%s", published "%s".',
          $viewer_phid,
          $author_phid,
          $is_published ? 'yes' : 'no'));
    }
  }

}
