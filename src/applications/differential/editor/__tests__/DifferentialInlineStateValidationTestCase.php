<?php

final class DifferentialInlineStateValidationTestCase
  extends PhabricatorTestCase {

  public function testRejectNonInlinePHIDs() {
    $xaction = $this->newTransaction(
      array(
        'PHID-TASK-1111' => PhabricatorInlineComment::STATE_DONE,
      ));

    $errors = $this->validate(array($xaction));
    $this->assertEqual(1, count($errors));
  }

  public function testRejectInvalidStates() {
    $cases = array(
      'done',
      array('PHID-XCMT-1111' => PhabricatorInlineComment::STATE_DRAFT),
      array('PHID-XCMT-1111' => PhabricatorInlineComment::STATE_UNDRAFT),
      array('PHID-XCMT-1111' => true),
      array('PHID-XCMT-1111' => array()),
    );

    foreach ($cases as $new_value) {
      $xaction = $this->newTransaction($new_value);

      $errors = $this->validate(array($xaction));
      $this->assertEqual(
        1,
        count($errors),
        pht('Value "%s" should be rejected.', json_encode($new_value)));
    }
  }

  public function testEmptyMapIsValid() {
    $xaction = $this->newTransaction(array());

    $errors = $this->validate(array($xaction));
    $this->assertEqual(0, count($errors));
  }

  public function testTransactionsWithOldValuesAreChecked() {
    // The web UI builds inline state transactions with old values. These
    // transactions are checked too.
    $xaction = $this->newTransaction(
      array(
        'PHID-TASK-1111' => PhabricatorInlineComment::STATE_DONE,
      ))
      ->setOldValue(
        array(
          'PHID-TASK-1111' => PhabricatorInlineComment::STATE_DRAFT,
        ));

    $errors = $this->validate(array($xaction));
    $this->assertEqual(1, count($errors));
  }

  private function newTransaction($new_value) {
    return id(new DifferentialTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_INLINESTATE)
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
