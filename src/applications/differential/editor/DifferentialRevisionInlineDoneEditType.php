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

    // Record the current states and the inline authors, like the inline state
    // transactions from the web UI. See PHI995. DifferentialTransactionEditor
    // validates the inlines.
    $old_value = array();
    $details = array();

    $field = $this->getEditField();
    $object = $field->getObject();
    if ($new_value && $object->getPHID()) {
      $inlines = id(new DifferentialDiffInlineCommentQuery())
        ->setViewer($field->getViewer())
        ->withObjectPHIDs(array($object->getPHID()))
        ->withPHIDs(array_keys($new_value))
        ->execute();
      $inlines = mpull($inlines, null, 'getPHID');

      // Use the same key order as the new value, so a transaction which does
      // not change any state has no effect.
      foreach ($new_value as $phid => $state) {
        $inline = idx($inlines, $phid);
        if ($inline) {
          $old_value[$phid] = $inline->getFixedState();
          $details[$phid] = array(
            'authorPHID' => $inline->getAuthorPHID(),
          );
        }
      }
    }

    $xaction = $this->newTransaction($template)
      ->setMetadataValue('inline.conduit', true)
      ->setMetadataValue('inline.details', $details)
      ->setOldValue($old_value)
      ->setNewValue($new_value);

    return array($xaction);
  }

}
