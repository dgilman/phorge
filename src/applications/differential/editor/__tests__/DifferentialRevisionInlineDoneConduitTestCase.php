<?php

final class DifferentialRevisionInlineDoneConduitTestCase
  extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testAuthorMarksInlinesDone() {
    $author = $this->generateNewTestUser();
    $reviewer = $this->generateNewTestUser();

    list($revision, $changeset) = $this->newRevision($author);
    $inline = $this->newPublishedInline($revision, $changeset, $reviewer);

    $this->markDone($author, $revision, array($inline->getPHID() => true));
    $this->assertEqual(
      PhabricatorInlineComment::STATE_DONE,
      $this->loadComment($inline->getPHID())->getFixedState());

    // The transaction records the inline author, like the web UI does.
    $xaction = $this->loadLastInlineStateTransaction($revision);
    $this->assertEqual(
      array(
        $inline->getPHID() => PhabricatorInlineComment::STATE_DONE,
      ),
      $xaction->getNewValue());
    $this->assertEqual(
      array(
        $inline->getPHID() => array(
          'authorPHID' => $reviewer->getPHID(),
        ),
      ),
      $xaction->getMetadataValue('inline.details'));

    $this->markDone($author, $revision, array($inline->getPHID() => false));
    $this->assertEqual(
      PhabricatorInlineComment::STATE_UNDONE,
      $this->loadComment($inline->getPHID())->getFixedState());
  }

  public function testRejectInvalidInlines() {
    $author = $this->generateNewTestUser();
    $reviewer = $this->generateNewTestUser();

    list($revision, $changeset) = $this->newRevision($author);
    $published = $this->newPublishedInline($revision, $changeset, $reviewer);

    $draft = $this->newInline($revision, $changeset, $reviewer)
      ->setContent('Draft.')
      ->save();

    list($other_revision, $other_changeset) = $this->newRevision($author);
    $other = $this->newPublishedInline(
      $other_revision,
      $other_changeset,
      $reviewer);

    // Each case is: actor, inline PHID.
    $cases = array(
      // Only the revision author can change published inlines.
      array($reviewer, $published->getPHID()),
      // Drafts can not be changed with the API.
      array($author, $draft->getPHID()),
      // The inline must be on this revision.
      array($author, $other->getPHID()),
      // The PHID must be an inline comment.
      array($author, $revision->getPHID()),
      // The inline comment must exist.
      array($author, 'PHID-XCMT-doesnotexist'),
    );

    foreach ($cases as $case) {
      list($actor, $phid) = $case;

      $caught = null;
      try {
        $this->markDone($actor, $revision, array($phid => true));
      } catch (Exception $ex) {
        $caught = $ex;
      } catch (Throwable $ex) {
        $caught = $ex;
      }

      $this->assertTrue(
        $caught instanceof PhabricatorApplicationTransactionValidationException,
        pht('Marking "%s" as done should fail.', $phid));
    }

    $this->assertTrue(
      $this->loadComment($published->getPHID())->getFixedState() !==
        PhabricatorInlineComment::STATE_DONE);
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

    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($author)
      ->withPHIDs(array($result['object']['phid']))
      ->executeOne();

    return array($revision, head($diff->getChangesets()));
  }

  private function newInline(
    DifferentialRevision $revision,
    DifferentialChangeset $changeset,
    PhabricatorUser $actor) {

    return id(new DifferentialInlineComment())
      ->setRevision($revision)
      ->setChangesetID($changeset->getID())
      ->setAuthorPHID($actor->getPHID())
      ->setIsNewFile(1)
      ->setLineNumber(1)
      ->setLineLength(0)
      ->setIsEditing(false);
  }

  private function newPublishedInline(
    DifferentialRevision $revision,
    DifferentialChangeset $changeset,
    PhabricatorUser $actor) {

    $inline = $this->newInline($revision, $changeset, $actor)
      ->setContent('Please fix this.')
      ->save();

    $editor = id(new DifferentialTransactionEditor())
      ->setActor($actor)
      ->setContentSource($this->newContentSource())
      ->setIsSilent(true);

    $xactions = $editor->newAutomaticInlineTransactions(
      $revision,
      DifferentialTransaction::TYPE_INLINE,
      new DifferentialDiffInlineCommentQuery());
    $editor->applyTransactions($revision, $xactions);

    return $inline;
  }

  private function markDone(
    PhabricatorUser $actor,
    DifferentialRevision $revision,
    array $value) {

    id(new ConduitCall(
      'differential.revision.edit',
      array(
        'objectIdentifier' => $revision->getPHID(),
        'transactions' => array(
          array('type' => 'inlines.done', 'value' => $value),
        ),
      )))
      ->setUser($actor)
      ->execute();
  }

  private function loadComment($phid) {
    return id(new DifferentialTransactionComment())->loadOneWhere(
      'phid = %s',
      $phid);
  }

  private function loadLastInlineStateTransaction(
    DifferentialRevision $revision) {

    $xactions = id(new DifferentialTransaction())->loadAllWhere(
      'objectPHID = %s AND transactionType = %s ORDER BY id DESC',
      $revision->getPHID(),
      PhabricatorTransactions::TYPE_INLINESTATE);

    return head($xactions);
  }

}
