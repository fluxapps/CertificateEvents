<?php
require_once('./Services/EventHandling/classes/class.ilEventHookPlugin.php');
require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Certificate/classes/Certificate/class.srCertificate.php');
require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Certificate/classes/Definition/class.srCertificateDefinition.php');
// Needed for 4.3 not needed for 4.4+
if (is_file('./Services/Tracking/classes/class.ilLPCollections.php')) {
	require_once('./Services/Tracking/classes/class.ilLPCollections.php');
}
require_once('./Services/Tracking/classes/class.ilLPObjSettings.php');
require_once("./Services/Tracking/classes/class.ilTrQuery.php");
require_once("./Services/Tracking/classes/class.ilLPStatusFactory.php");
require_once('./Modules/Course/classes/class.ilCourseParticipants.php');


/**
 * ilCertificateEventsPlugin
 *
 * @author  Stefan Wanzenried <sw@studer-raimann.ch>
 * @version $Id:
 */
class ilCertificateEventsPlugin extends ilEventHookPlugin {

    /**
     * Handle the event
     *
     * @param    string        component, e.g. "Services/User"
     * @param    event        event, e.g. "afterUpdate"
     * @param    array        array of event specific parameters
     */
    public function handleEvent($a_component, $a_event, $a_parameter) {
        global $ilUser;
        // Generate certificate if course is completed
        if ($a_component == 'Modules/Course' && $a_event == 'participantHasPassedCourse') {
            $obj_id = $a_parameter["obj_id"];
            $user_id = $a_parameter["usr_id"];
            if ($obj_id && $user_id) {
                $ref_ids = ilObject::_getAllReferences($obj_id);
                $ref_ids = array_values($ref_ids);
                if (count($ref_ids)) {
                    $ref_id = $ref_ids[0];
                    // Only generate certificate if user is participant of course!!
                    // Note: This is a workaround for an ILIAS feature/bug: A user can pass a course without being a member
                    if (!ilCourseParticipants::_isParticipant($ref_id, $user_id)) {
                        return;
                    }
                    /** @var srCertificateDefinition $definition */
                    $definition = srCertificateDefinition::where(array('ref_id' => $ref_id))->first();
                    if (!is_null($definition)) {
                        // Only create certificate if the generation setting of type is set to AUTO
                        if ($definition->getGeneration() == srCertificateTypeSetting::GENERATION_AUTO) {
                            $cert = new srCertificate();
                            $cert->setUserId($user_id);
                            $cert->setDefinition($definition);
                            $cert->create();
                            // Display info message for user if certificate is downloadable and the current user is equal to the certificate user
//                            /** @var $ilUser ilObjUser */
//                            if ($ilUser->getId() == $user_id && $definition->getDownloadable()) {
//                                ilUtil::sendInfo('A certificate was generated. Within a few minutes, you can download it on the "Personal Desktop"', true);
//                            }
                        }
                    }
                }
            }
        }

        // Need to copy copy certificate definition when copying course
        if ($a_component == 'Modules/Course' && $a_event == 'copy') {
            /** @var ilObjCourse $obj */
            /** @var ilObjCourse $obj_orig */
            $obj = $a_parameter['object'];
            $obj_orig = $a_parameter['cloned_from_object'];

            // Get certificate definition from old object and clone everything
            require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Certificate/classes/Definition/class.srCertificateDefinition.php');
            $definition = srCertificateDefinition::where(array("ref_id" => $obj_orig->getRefId()))->first();
            if (!is_null($definition)) {
                $definition->copy($obj->getRefId());
            }
        }

    }


    /**
     * Get Plugin Name. Must be same as in class name il<Name>Plugin
     * and must correspond to plugins subdirectory name.
     *
     * Must be overwritten in plugin class of plugin
     * (and should be made final)
     *
     * @return    string    Plugin Name
     */
    function getPluginName()
    {
        return "CertificateEvents";
    }
}