<?php

final class PhabricatorApplyEditFieldTestCase
  extends PhabricatorTestCase {

  public function testConduitParameterType() {
    $field = id(new PhabricatorApplyEditField())
      ->setViewer(new PhabricatorUser());

    $this->assertTrue(
      ($field->getConduitParameterType() instanceof ConduitBoolParameterType),
      pht('Without Conduit options, the field takes a boolean.'));

    $field->setConduitOptions(true);

    $this->assertTrue(
      ($field->getConduitParameterType()
        instanceof ConduitBoolOrPHIDListParameterType),
      pht('With Conduit options, the field takes a boolean or a list.'));
  }

}
