<?php

final class DifferentialRevisionAcceptConduitTestCase
  extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testAcceptAsSpecificReviewers() {
    $author = $this->generateNewTestUser();
    $reviewer = $this->generateNewTestUser();
    $project = $this->newProject($author, array($reviewer));

    // With "true", the reviewer accepts as all reviewers they have authority
    // for: themselves and the project.
    $revision = $this->newRevision(
      $author,
      array($reviewer->getPHID(), $project->getPHID()));
    $this->editRevision($reviewer, $revision, 'accept', true);

    $this->assertReviewerStatuses(
      $revision,
      array(
        $reviewer->getPHID() => DifferentialReviewerStatus::STATUS_ACCEPTED,
        $project->getPHID() => DifferentialReviewerStatus::STATUS_ACCEPTED,
      ));

    // With a list, the reviewer accepts only as the reviewers in the list.
    $revision = $this->newRevision(
      $author,
      array($reviewer->getPHID(), $project->getPHID()));
    $this->editRevision(
      $reviewer,
      $revision,
      'accept',
      array($reviewer->getPHID()));

    $this->assertReviewerStatuses(
      $revision,
      array(
        $reviewer->getPHID() => DifferentialReviewerStatus::STATUS_ACCEPTED,
        $project->getPHID() => DifferentialReviewerStatus::STATUS_ADDED,
      ));
  }

  public function testAcceptAsInvalidReviewer() {
    $author = $this->generateNewTestUser();
    $reviewer = $this->generateNewTestUser();
    $other = $this->generateNewTestUser();
    $project = $this->newProject($author, array($other));

    $revision = $this->newRevision(
      $author,
      array($reviewer->getPHID(), $project->getPHID()));

    // The reviewer is not a member of the project, so they can not accept on
    // its behalf.
    $caught = null;
    try {
      $this->editRevision(
        $reviewer,
        $revision,
        'accept',
        array($project->getPHID()));
    } catch (Exception $ex) {
      $caught = $ex;
    } catch (Throwable $ex) {
      $caught = $ex;
    }

    $this->assertTrue(
      $caught instanceof PhabricatorApplicationTransactionValidationException);
  }

  private function newRevision(PhabricatorUser $author, array $reviewer_phids) {
    $raw_diff = <<<EODIFF
diff --git a/src b/src
index 123457..bb216b1 100644
--- a/src
+++ b/src
@@ -1,3 +1,3 @@
 Line a
-Line b
+Line 2
 Line c
EODIFF;

    $parser = new ArcanistDiffParser();
    $diff = DifferentialDiff::newFromRawChanges(
      $author,
      $parser->parseDiff($raw_diff));
    $diff
      ->setAuthorPHID($author->getPHID())
      ->setCreationMethod('test')
      ->setLintStatus(DifferentialLintStatus::LINT_SKIP)
      ->setUnitStatus(DifferentialUnitStatus::UNIT_SKIP)
      ->setDescription('test')
      ->save();

    $result = id(new ConduitCall(
      'differential.revision.edit',
      array(
        'transactions' => array(
          array('type' => 'update', 'value' => $diff->getPHID()),
          array('type' => 'title', 'value' => 'Test'),
          array('type' => 'testPlan', 'value' => 'Test'),
          array('type' => 'reviewers.set', 'value' => $reviewer_phids),
          array('type' => 'draft', 'value' => false),
        ),
      )))
      ->setUser($author)
      ->execute();

    return id(new DifferentialRevisionQuery())
      ->setViewer($author)
      ->withPHIDs(array($result['object']['phid']))
      ->executeOne();
  }

  private function editRevision(
    PhabricatorUser $actor,
    DifferentialRevision $revision,
    $type,
    $value) {

    id(new ConduitCall(
      'differential.revision.edit',
      array(
        'objectIdentifier' => $revision->getPHID(),
        'transactions' => array(
          array('type' => $type, 'value' => $value),
        ),
      )))
      ->setUser($actor)
      ->execute();
  }

  private function assertReviewerStatuses(
    DifferentialRevision $revision,
    array $expect) {

    $reviewers = id(new DifferentialReviewer())->loadAllWhere(
      'revisionPHID = %s',
      $revision->getPHID());
    $actual = mpull($reviewers, 'getReviewerStatus', 'getReviewerPHID');

    ksort($expect);
    ksort($actual);

    $this->assertEqual($expect, $actual);
  }

  private function newProject(PhabricatorUser $author, array $members) {
    $result = id(new ConduitCall(
      'project.edit',
      array(
        'transactions' => array(
          array('type' => 'name', 'value' => 'Test Project '.mt_rand()),
          array('type' => 'members.set', 'value' => mpull($members, 'getPHID')),
        ),
      )))
      ->setUser($author)
      ->execute();

    return id(new PhabricatorProjectQuery())
      ->setViewer($author)
      ->withPHIDs(array($result['object']['phid']))
      ->executeOne();
  }

}
