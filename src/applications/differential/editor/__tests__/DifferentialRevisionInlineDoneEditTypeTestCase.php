<?php

final class DifferentialRevisionInlineDoneEditTypeTestCase
  extends PhabricatorTestCase {

  public function testGenerateTransactions() {
    $xactions = $this->generate(
      array(
        'PHID-XCMT-1111' => true,
        'PHID-XCMT-2222' => false,
      ));

    $this->assertEqual(1, count($xactions));

    $xaction = head($xactions);
    $this->assertEqual(
      PhabricatorTransactions::TYPE_INLINESTATE,
      $xaction->getTransactionType());
    $this->assertEqual(
      array(
        'PHID-XCMT-1111' => PhabricatorInlineComment::STATE_DONE,
        'PHID-XCMT-2222' => PhabricatorInlineComment::STATE_UNDONE,
      ),
      $xaction->getNewValue());
    $this->assertTrue($xaction->getMetadataValue('inline.conduit'));

    // This revision has no inlines, so there are no old states to record.
    $this->assertEqual(array(), $xaction->getOldValue());
  }

  public function testInvalidValues() {
    $cases = array(
      'PHID-XCMT-1111',
      array('PHID-XCMT-1111'),
      array('PHID-XCMT-1111' => 'true'),
      array('PHID-XCMT-1111' => 1),
    );

    foreach ($cases as $value) {
      $caught = null;
      try {
        $this->generate($value);
      } catch (Exception $ex) {
        $caught = $ex;
      } catch (Throwable $ex) {
        $caught = $ex;
      }

      $this->assertTrue(
        ($caught instanceof Exception),
        pht('Value "%s" should be rejected.', json_encode($value)));
    }
  }

  private function generate($value) {
    $field = id(new DifferentialRevisionInlineDoneEditField())
      ->setObject(new DifferentialRevision())
      ->setViewer(new PhabricatorUser());

    $type = id(new DifferentialRevisionInlineDoneEditType())
      ->setEditField($field)
      ->setTransactionType(PhabricatorTransactions::TYPE_INLINESTATE);

    return $type->generateTransactions(
      new DifferentialTransaction(),
      array(
        'value' => $value,
      ));
  }

}
