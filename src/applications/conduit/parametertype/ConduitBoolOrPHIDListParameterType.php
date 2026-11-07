<?php

final class ConduitBoolOrPHIDListParameterType
  extends ConduitParameterType {

  protected function getParameterValue(array $request, $key, $strict) {
    $value = parent::getParameterValue($request, $key, $strict);

    if (is_array($value)) {
      $type = new ConduitPHIDListParameterType();
    } else {
      $type = new ConduitBoolParameterType();
    }

    return $type
      ->setViewer($this->getViewer())
      ->getValue($request, $key, $strict);
  }

  protected function getParameterTypeName() {
    return 'bool|list<phid>';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('A boolean.'),
      pht('List of PHIDs.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      'true',
      '["PHID-WXYZ-1111", "PHID-WXYZ-2222"]',
    );
  }

}
