<?php

final class ConduitBoolOrPHIDListParameterTypeTestCase
  extends PhabricatorTestCase {

  public function testValidValues() {
    $cases = array(
      array(true, true),
      array(false, false),
      array(array(), array()),
      array(
        array('PHID-USER-1111', 'PHID-OPKG-2222'),
        array('PHID-USER-1111', 'PHID-OPKG-2222'),
      ),
    );

    foreach ($cases as $case) {
      list($input, $expect) = $case;
      $this->assertEqual(
        $expect,
        $this->readValue($input, true),
        pht('Value for input "%s".', json_encode($input)));
    }
  }

  public function testNonStrictBool() {
    $this->assertEqual(true, $this->readValue('true', false));
    $this->assertEqual(false, $this->readValue('0', false));
  }

  public function testInvalidValues() {
    $cases = array(
      'true',
      1,
      array('PHID-USER-1111', 2),
      array('a' => 'PHID-USER-1111'),
    );

    foreach ($cases as $input) {
      $caught = null;
      try {
        $this->readValue($input, true);
      } catch (Exception $ex) {
        $caught = $ex;
      } catch (Throwable $ex) {
        $caught = $ex;
      }

      $this->assertTrue(
        ($caught instanceof Exception),
        pht('Input "%s" should be rejected.', json_encode($input)));
    }
  }

  public function testMissingValue() {
    $type = id(new ConduitBoolOrPHIDListParameterType())
      ->setViewer(new PhabricatorUser());

    $this->assertEqual(null, $type->getValue(array(), 'accept'));
  }

  private function readValue($value, $strict) {
    $request = array(
      'accept' => $value,
    );

    return id(new ConduitBoolOrPHIDListParameterType())
      ->setViewer(new PhabricatorUser())
      ->getValue($request, 'accept', $strict);
  }

}
