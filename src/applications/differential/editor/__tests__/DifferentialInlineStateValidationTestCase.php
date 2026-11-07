<?php

final class DifferentialInlineStateValidationTestCase
  extends PhabricatorTestCase {

  public function testRejectNonInlinePHIDs() {
    $xaction = $this->newConduitTransaction(
      array(
        'PHID-TASK-1111' => PhabricatorInlineComment::STATE_DONE,
      ));

    $errors = $this->validate(array($xaction));
    $this->assertEqual(1, count($errors));
  }

  public function testEmptyMapIsValid() {
    $xaction = $this->newConduitTransaction(array());

    $errors = $this->validate(array($xaction));
    $this->assertEqual(0, count($errors));
  }

  public function testWebTransactionsAreNotChecked() {
    // Inline state transactions from the web UI have no "inline.conduit"
    // metadata. They are built from draft states, and are not checked again.
    $old = array(
      'PHID-TASK-1111' => PhabricatorInlineComment::STATE_UNDONE,
    );

    $xaction = id(new DifferentialTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_INLINESTATE)
      ->setOldValue($old)
      ->setNewValue(
        array(
          'PHID-TASK-1111' => PhabricatorInlineComment::STATE_DONE,
        ));

    $errors = $this->validate(array($xaction));
    $this->assertEqual(0, count($errors));
    $this->assertEqual($old, $xaction->getOldValue());
  }

  private function newConduitTransaction(array $new_value) {
    return id(new DifferentialTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_INLINESTATE)
      ->setMetadataValue('inline.conduit', true)
      ->setNewValue($new_value);
  }

  private function validate(array $xactions) {
    $editor = id(new DifferentialTransactionEditor())
      ->setActor(new PhabricatorUser());

    $method = new ReflectionMethod($editor, 'validateTransaction');
    return $method->invoke(
      $editor,
      new DifferentialRevision(),
      PhabricatorTransactions::TYPE_INLINESTATE,
      $xactions);
  }

}
