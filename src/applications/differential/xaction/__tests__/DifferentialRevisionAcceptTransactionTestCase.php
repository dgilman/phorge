<?php

final class DifferentialRevisionAcceptTransactionTestCase
  extends PhabricatorTestCase {

  public function testConduitTakesReviewerList() {
    $viewer = new PhabricatorUser();

    // Use a closed revision, so the action is not available and the field
    // does not need to load reviewers.
    $revision = id(new DifferentialRevision())
      ->setModernRevisionStatus(DifferentialRevisionStatus::PUBLISHED);

    $field = id(new DifferentialRevisionAcceptTransaction())
      ->newEditField($revision, $viewer)
      ->setViewer($viewer);

    $this->assertTrue(
      ($field->getConduitParameterType()
        instanceof ConduitBoolOrPHIDListParameterType));

    $this->assertTrue(
      phutil_nonempty_string($field->getConduitDocumentation()));
  }

  public function testOtherActionsTakeBool() {
    $viewer = new PhabricatorUser();

    $revision = id(new DifferentialRevision())
      ->setModernRevisionStatus(DifferentialRevisionStatus::PUBLISHED);

    $field = id(new DifferentialRevisionRejectTransaction())
      ->newEditField($revision, $viewer)
      ->setViewer($viewer);

    $this->assertTrue(
      ($field->getConduitParameterType() instanceof ConduitBoolParameterType));
  }

}
