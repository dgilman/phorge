<?php

final class DifferentialRevisionCommitsEditTestCase
  extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testAddAndRemoveCommits() {
    $author = $this->generateNewTestUser();
    $revision = $this->newRevision($author);

    $repository = $this->newRepository(
      $author,
      PhabricatorPolicies::POLICY_USER);
    $commit = $this->newCommit($repository);

    $this->editRevision(
      $author,
      $revision,
      'commits.add',
      array($commit->getPHID()));
    $this->assertEqual(
      array($commit->getPHID()),
      $this->loadCommitPHIDs($revision));

    // The edge is written in both directions.
    $this->assertEqual(
      array($revision->getPHID()),
      PhabricatorEdgeQuery::loadDestinationPHIDs(
        $commit->getPHID(),
        DiffusionCommitHasRevisionEdgeType::EDGECONST));

    $this->editRevision(
      $author,
      $revision,
      'commits.remove',
      array($commit->getPHID()));
    $this->assertEqual(
      array(),
      $this->loadCommitPHIDs($revision));
  }

  public function testRejectInvalidObjects() {
    $author = $this->generateNewTestUser();
    $revision = $this->newRevision($author);

    $hidden_repository = $this->newRepository(
      $author,
      PhabricatorPolicies::POLICY_NOONE);
    $hidden_commit = $this->newCommit($hidden_repository);

    $invalid_phids = array(
      // Not a commit.
      $author->getPHID(),
      // The revision itself.
      $revision->getPHID(),
      // A commit that the actor can not see.
      $hidden_commit->getPHID(),
      // An object that does not exist.
      'PHID-CMIT-doesnotexist',
    );

    foreach ($invalid_phids as $invalid_phid) {
      $caught = null;
      try {
        $this->editRevision(
          $author,
          $revision,
          'commits.add',
          array($invalid_phid));
      } catch (Exception $ex) {
        $caught = $ex;
      } catch (Throwable $ex) {
        $caught = $ex;
      }

      $this->assertTrue(
        $caught instanceof PhabricatorApplicationTransactionValidationException,
        pht('Adding "%s" should fail.', $invalid_phid));
    }

    $this->assertEqual(
      array(),
      $this->loadCommitPHIDs($revision));
  }

  public function testKeepExistingHiddenCommit() {
    $author = $this->generateNewTestUser();
    $revision = $this->newRevision($author);

    $hidden_repository = $this->newRepository(
      $author,
      PhabricatorPolicies::POLICY_NOONE);
    $hidden_commit = $this->newCommit($hidden_repository);

    $repository = $this->newRepository(
      $author,
      PhabricatorPolicies::POLICY_USER);
    $commit = $this->newCommit($repository);

    // Link the hidden commit as the omnipotent user, like the commit import
    // worker does.
    $xaction = id(new DifferentialTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue(
        'edge:type',
        DifferentialRevisionHasCommitEdgeType::EDGECONST)
      ->setNewValue(
        array(
          '+' => array_fuse(array($hidden_commit->getPHID())),
        ));

    id(new DifferentialTransactionEditor())
      ->setActor(PhabricatorUser::getOmnipotentUser())
      ->setActingAsPHID(id(new PhabricatorDiffusionApplication())->getPHID())
      ->setContentSource($this->newContentSource())
      ->setContinueOnMissingFields(true)
      ->applyTransactions($revision, array($xaction));

    // The author can not see the hidden commit, but can keep it when they set
    // the list of commits.
    $this->editRevision(
      $author,
      $revision,
      'commits.set',
      array($hidden_commit->getPHID(), $commit->getPHID()));

    $expect = array($hidden_commit->getPHID(), $commit->getPHID());
    sort($expect);

    $this->assertEqual($expect, $this->loadCommitPHIDs($revision));
  }

  private function newRevision(PhabricatorUser $author) {
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
        ),
      )))
      ->setUser($author)
      ->execute();

    return id(new DifferentialRevisionQuery())
      ->setViewer($author)
      ->withPHIDs(array($result['object']['phid']))
      ->executeOne();
  }

  private function newRepository(PhabricatorUser $author, $view_policy) {
    return PhabricatorRepository::initializeNewRepository($author)
      ->setVersionControlSystem(
        PhabricatorRepositoryType::REPOSITORY_TYPE_GIT)
      ->setName(pht('Test Repository %d', mt_rand()))
      ->setViewPolicy($view_policy)
      ->save();
  }

  private function newCommit(PhabricatorRepository $repository) {
    return id(new PhabricatorRepositoryCommit())
      ->setRepositoryID($repository->getID())
      ->setCommitIdentifier(sha1(mt_rand()))
      ->setEpoch(PhabricatorTime::getNow())
      ->setAuditStatus(DiffusionCommitAuditStatus::NONE)
      ->setSummary('Test')
      ->save();
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

  private function loadCommitPHIDs(DifferentialRevision $revision) {
    $phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $revision->getPHID(),
      DifferentialRevisionHasCommitEdgeType::EDGECONST);
    sort($phids);
    return $phids;
  }

}
