<?php
// Needed for 4.3 not needed for 4.4+
if (is_file('./Services/Tracking/classes/class.ilLPCollections.php')) {
    require_once('./Services/Tracking/classes/class.ilLPCollections.php');
}
require_once('./Services/Tracking/classes/class.ilLPObjSettings.php');
require_once("./Services/Tracking/classes/class.ilTrQuery.php");
require_once("./Services/Tracking/classes/class.ilLPStatusFactory.php");
require_once('./Modules/Course/classes/class.ilCourseParticipants.php');

/**
 * Class srCertificateEventsCourseHandler
 *
 * @author Stefan Wanzenried <sw@studer-raimann.ch>
 */
class srCertificateEventsCourseHandler
{

    /**
     * @var ilObjCourse
     */
    protected $course;

    /**
     * @param ilObjCourse $course
     */
    public function __construct(ilObjCourse $course)
    {
        $this->course = $course;
    }


    /**
     * @param string $event
     * @param array $params
     */
    public function handle($event, array $params)
    {
        switch ($event) {
            case 'participantHasPassedCourse':
                $this->generateCertificate($params);
                break;
            case 'copy':
                $this->copyDefinition($params);
                break;
        }
    }


    /**
     * @param array $params
     */
    protected function copyDefinition(array $params)
    {
        /** @var ilObjCourse $obj_orig */
        $obj_orig = $params['cloned_from_object'];
        $definition = srCertificateDefinition::where(array("ref_id" => $obj_orig->getRefId()))->first();
        if (!is_null($definition)) {
            $definition->copy($this->course->getRefId());
        }

    }


    /**
     * @param array $params
     * @throws Exception
     */
    protected function generateCertificate(array $params)
    {
        if (!$this->checkPreconditions($params)) {
            return;
        }
        /** @var srCertificateDefinition $definition */
        $definition = srCertificateDefinition::where(array('ref_id' => $this->course->getRefId()))->first();
        if (is_null($definition)) {
            return;
        }
        // Only create certificate if the generation setting of type is set to AUTO
        if ($definition->getGeneration() == srCertificateTypeSetting::GENERATION_AUTO) {
        	// and if there are no active certificates
	        $cert = srCertificate::where(array(
		        'active' => 1,
		        'user_id' => (int) $params['usr_id'],
		        'definition_id' => $definition->getId(),
	        ))->first();

	        if (!$cert) {
		        $cert = new srCertificate();
		        $cert->setUserId((int) $params['usr_id']);
		        $cert->setDefinition($definition);
		        $cert->create();
	        }

        }
    }


    /**
     * @param array $params
     * @return bool
     */
    protected function checkPreconditions(array $params)
    {
        if (!isset($params['usr_id'])) {
            return false;
        }
        // Check that the user is actually a participant
        if (!ilCourseParticipants::_isParticipant($this->course->getRefId(), $params['usr_id'])) {
            return false;
        }
        // Check that course is not offline or outside of the activation period
        if (!$this->course->isActivated()) {
            return false;
        }
        if (!is_file('./Services/Tracking/classes/class.ilLPCollections.php')) {
            return true;
        }
        // If objects determine the learning progress, make sure at least one object is marked as relevant
        $lp_settings = new ilLPObjSettings($this->course->getId());
        if ($lp_settings->getMode() != LP_MODE_COLLECTION) {
            return true;
        }
        // Loop objects, as soon as we find one that determines the learning progress, we can return true
        $collections = new ilLPCollections($this->course->getId());
        $items = ilLPCollections::_getPossibleItems($this->course->getRefId(), $collections);
        foreach ($items as $item) {
            if ($collections->isAssigned($item)) {
                return true;
            }
        }

        return false;
    }

}