<?php
require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Certificate/classes/class.ilCertificatePlugin.php');
require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Certificate/classes/class.ilCertificateConfig.php');
require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Certificate/classes/Notification/class.srCertificateDiskSpaceWarningNotification.php');
require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Certificate/classes/Notification/class.srCertificateNoDiskSpaceNotification.php');
require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Certificate/classes/Notification/class.srCertificateEmailNotification.php');
require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Certificate/classes/Notification/class.srCertificateOthersNotification.php');
require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Certificate/classes/Notification/class.srCertificateUserNotification.php');
require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Certificate/classes/Notification/class.srCertificateCallBackNotification.php');
require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Certificate/classes/Notification/class.srCertificateNoWritePermissionNotification.php');
require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Certificate/classes/Notification/class.srCertificateFailedNotification.php');

/**
 * Class srCertificateEventsCertificateHandler
 *
 * @author Stefan Wanzenried <sw@studer-raimann.ch>
 */
class srCertificateEventsCertificateHandler
{

    /**
     * @var srCertificate
     */
    protected $certificate;

    /**
     * @var ilCertificatePlugin
     */
    protected $pl;

    /**
     * @var ilLog
     */
    protected $log;


    /**
     * @param srCertificate $certificate
     */
    public function __construct(srCertificate $certificate)
    {
        global $ilLog;

        $this->certificate = $certificate;
        $this->pl = ilCertificatePlugin::getInstance();
        $this->log = $ilLog;
    }


    public function handle($event, array $params)
    {
        switch ($event) {
            case 'changeStatus':
                $this->sendNotifications($params['old_status'], $params['new_status']);
                break;
        }
    }


    /**
     * @param int $status
     */
    protected function checkDiskSpace($status)
    {
        $free_space = disk_free_space($this->certificate->getCertificatePath());
        if ($status == srCertificate::STATUS_PROCESSED) {
            //Send mail to administrator if the free space is below the configured value
            $disk_space = (int) $this->pl->config('disk_space_warning');
            if ($disk_space > 0 && $free_space < ($disk_space * 1000000) && !$this->pl->config('disk_space_warning_sent')) {
                $notification = new srCertificateDiskSpaceWarningNotification($this->certificate);
                $notification->notify();
                ilCertificateConfig::set('disk_space_warning_sent', 1);
            } elseif ($this->pl->config('disk_space_warning_sent') && $free_space > ($disk_space * 1000000)) {
                ilCertificateConfig::set('disk_space_warning_sent', 0);
            }
        } elseif ($status == srCertificate::STATUS_FAILED) {
            // If there's less than 1MB space left, it's probably a space problem
            if ($free_space < 1000) {
                $notification = new srCertificateNoDiskSpaceNotification($this->certificate);
                $notification->notify();
                $this->log->write("srCertificate::generate() Failed to generate certificate with ID {$this->certificate->getId()}; Free disk space below 1MB.");
            }
        }
    }


    /**
     * @param int $old_status
     * @param int $new_status
     */
    protected function sendNotifications($old_status, $new_status)
    {
        switch ($new_status) {
            case srCertificate::STATUS_PROCESSED:
                // Notify users defined in certificate definition
                if ($receivers = $this->certificate->getDefinition()->getNotification()) {
                    $receivers = explode(',', $receivers);
                    $this->sendNotification($this->certificate, $receivers);
                }
                // Check for user notification
                if ($this->certificate->getDefinition()->getNotificationUser()) {
                    $notification = new srCertificateUserNotification($this->certificate);
                    $notification->notify();
                }
                // Check if admin should be notified about critical disk-space
                $this->checkDiskSpace(srCertificate::STATUS_PROCESSED);
                break;
            case srCertificate::STATUS_CALLED_BACK:
                if ($callback_email = $this->pl->config('callback_email')) {
                    $receivers = array($callback_email);
                    if (strpos($callback_email, ',')) {
                        $receivers = explode(',', $callback_email);
                    }
                    foreach ($receivers as $email) {
                        $notification = new srCertificateCallBackNotification($this->certificate, trim($email));
                        $notification->notify();
                    }
                }
                break;
            case srCertificate::STATUS_FAILED:
                if (!is_writable($this->certificate->getCertificatePath())) {
                    $notification = new srCertificateNoWritePermissionNotification($this->certificate);
                    $notification->notify();
                    $this->log->write("Failed to generate certificate with ID {$this->certificate->getId()}; Certificate data directory is not writable.");
                } else {
                    $notification = new srCertificateFailedNotification($this->certificate);
                    $notification->notify();
                    $this->log->write("Failed to generate certificate with ID {$this->certificate->getId()}");
                }
                $this->checkDiskSpace(srCertificate::STATUS_FAILED);
                break;
        }
    }

    /**
     * Send a notification
     *
     * @param srCertificate $certificate
     * @param array $receivers_email
     */
    protected function sendNotification(srCertificate $certificate, array $receivers_email)
    {
        foreach ($receivers_email as $email) {
            $notification = new srCertificateOthersNotification($certificate, $email);
            $notification->notify();
        }
    }
}