<?php

abstract class PhabricatorObjectRelationship extends Phobject {

  private $viewer;
  private $contentSource;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function getContentSource() {
    return $this->contentSource;
  }

  final public function getRelationshipConstant() {
    return $this->getPhobjectClassConstant('RELATIONSHIPKEY');
  }

  abstract public function isEnabledForObject($object);

  abstract public function getEdgeConstant();

  /**
   * @return string
   */
  abstract protected function getActionName();

  /**
   * @return string
   */
  abstract protected function getActionIcon();

  /**
   * @return bool
   */
  abstract public function canRelateObjects($src, $dst);

  /**
   * @return string
   */
  abstract public function getDialogTitleText();

  /**
   * @return string
   */
  abstract public function getDialogHeaderText();

  /**
   * @return string
   */
  abstract public function getDialogButtonText();

  /**
   * Whether to list the relationship action as a menu item in the
   * "Edit Related Objects" menu in the object's side column
   *
   * @return bool
   */
  public function shouldAppearInActionMenu() {
    return true;
  }

  protected function isActionEnabled($object) {
    $viewer = $this->getViewer();

    return PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $object,
      PhabricatorPolicyCapability::CAN_EDIT);
  }

  public function getRequiredRelationshipCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  final public function newSource() {
    $viewer = $this->getViewer();

    return $this->newRelationshipSource()
      ->setViewer($viewer);
  }

  abstract protected function newRelationshipSource();

  final public function getSourceURI($object) {
    $relationship_key = $this->getRelationshipConstant();
    $object_phid = $object->getPHID();

    return "/search/source/{$relationship_key}/{$object_phid}/";
  }

  final public function newAction($object) {
    $is_enabled = $this->isActionEnabled($object);
    $action_uri = $this->getActionURI($object);

    return id(new PhabricatorActionView())
      ->setName($this->getActionName())
      ->setHref($action_uri)
      ->setIcon($this->getActionIcon())
      ->setDisabled(!$is_enabled)
      ->setWorkflow(true);
  }

  final public static function getAllRelationships() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(self::class)
      ->setUniqueMethod('getRelationshipConstant')
      ->execute();
  }

  private function getActionURI($object) {
    $phid = $object->getPHID();
    $type = $this->getRelationshipConstant();
    return "/search/rel/{$type}/{$phid}/";
  }

  public function getMaximumSelectionSize() {
    return null;
  }

  public function canUndoRelationship() {
    return true;
  }

  /**
   * Check that objects can be added to this relationship.
   *
   * @param object $src Source object.
   * @param list<string> $add_phids PHIDs of the objects to add.
   * @param map<string, object> $dst_objects Destination objects which the
   *   viewer can load, keyed by PHID.
   * @return void
   * @throws Exception If an object can not be added.
   */
  final public function assertCanRelateObjects(
    $src,
    array $add_phids,
    array $dst_objects) {

    foreach ($add_phids as $add_phid) {
      $dst_object = idx($dst_objects, $add_phid);
      if (!$dst_object) {
        throw new Exception(
          pht(
            'You can not create a relationship to object "%s" because '.
            'the object does not exist or could not be loaded.',
            $add_phid));
      }

      if ($add_phid == $src->getPHID()) {
        throw new Exception(
          pht(
            'You can not create a relationship to object "%s" because '.
            'objects can not be related to themselves.',
            $add_phid));
      }

      if (!$this->canRelateObjects($src, $dst_object)) {
        throw new Exception(
          pht(
            'You can not create a relationship (of type "%s") to object '.
            '"%s" because it is not the right type of object for this '.
            'relationship.',
            $this->getRelationshipConstant(),
            $add_phid));
      }
    }
  }

  public function willUpdateRelationships($object, array $add, array $rem) {
    return array();
  }

  public function didUpdateRelationships($object, array $add, array $rem) {
    return;
  }

}
