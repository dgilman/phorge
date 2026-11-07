<?php

final class DifferentialRevisionInlineDoneEditType
  extends PhabricatorEditType {

  public function generateTransactions(
    PhabricatorApplicationTransaction $template,
    array $spec) {

    $value = idx($spec, 'value');
    if (!is_array($value)) {
      throw new Exception(
        pht(
          'Transaction value when marking inline comments as done must be '.
          'a map with inline comment PHIDs as keys and booleans as values.'));
    }

    $new_value = array();
    foreach ($value as $phid => $is_done) {
      if (!is_string($phid) || !is_bool($is_done)) {
        throw new Exception(
          pht(
            'Transaction value when marking inline comments as done must be '.
            'a map with inline comment PHIDs as keys and booleans as values.'));
      }

      if ($is_done) {
        $new_value[$phid] = PhabricatorInlineComment::STATE_DONE;
      } else {
        $new_value[$phid] = PhabricatorInlineComment::STATE_UNDONE;
      }
    }

    // The editor requires an old value. DifferentialTransactionEditor fills
    // in the old states and the inline authors, and validates the inlines.
    $xaction = $this->newTransaction($template)
      ->setIgnoreOnNoEffect(true)
      ->setOldValue(array())
      ->setNewValue($new_value);

    return array($xaction);
  }

}
