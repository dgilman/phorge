<?php

final class PhabricatorObjectRelationshipTestCase
  extends PhabricatorTestCase {

  public function testAssertCanRelateObjects() {
    $relationship = new DifferentialRevisionHasCommitRelationship();

    $revision = id(new DifferentialRevision())
      ->setPHID('PHID-DREV-1111');
    $commit = id(new PhabricatorRepositoryCommit())
      ->setPHID('PHID-CMIT-1111');
    $task = id(new ManiphestTask())
      ->setPHID('PHID-TASK-1111');

    $dst_objects = mpull(array($revision, $commit, $task), null, 'getPHID');

    // Each case is: PHIDs to add, expect an exception.
    $cases = array(
      array(array(), false),
      array(array('PHID-CMIT-1111'), false),
      array(array('PHID-CMIT-2222'), true),
      array(array('PHID-DREV-1111'), true),
      array(array('PHID-TASK-1111'), true),
      array(array('PHID-CMIT-1111', 'PHID-TASK-1111'), true),
    );

    foreach ($cases as $case) {
      list($add_phids, $expect_exception) = $case;

      $caught = null;
      try {
        $relationship->assertCanRelateObjects(
          $revision,
          $add_phids,
          $dst_objects);
      } catch (Exception $ex) {
        $caught = $ex;
      } catch (Throwable $ex) {
        $caught = $ex;
      }

      $this->assertEqual(
        $expect_exception,
        ($caught instanceof Exception),
        pht('Adding PHIDs: %s.', implode(', ', $add_phids)));
    }
  }

}
