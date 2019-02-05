<?php namespace Impero\Ftp\Controller;

use Impero\Ftp\Entity\Ftps;
use Impero\Ftp\Form\Ftp as FtpForm;
use Impero\Ftp\Record\Ftp as FtpRecord;
use Impero\Ftp\Service\Ftp as FtpService;
use Pckg\Framework\Controller;
use Pckg\Maestro\Helper\Maestro;

class Ftp extends Controller
{

    use Maestro;

    /**
     * List all available ftps.
     *
     * @param Ftps $ftps
     *
     * @return mixed
     */
    public function getIndexAction(Ftps $ftps)
    {
        return $this->tabelize($ftps, ['username', 'path'], 'Ftps');
    }

    /**
     * Show add form.
     * Form is automatically filled with session data.
     *
     * @param FtpForm   $ftpForm
     * @param FtpRecord $ftpRecord
     *
     * @return mixed
     */
    public function getAddAction(FtpForm $ftpForm, FtpRecord $ftpRecord)
    {
        /**
         * We may have some default values.
         */
        $ftpForm->populateFromRecord($ftpRecord);

        /**
         * Then we simply display form.
         */
        return $this->formalize($ftpForm, $ftpRecord, 'Add ftp');
    }

    /**
     * Save form data.
     * Form is automatically filled with request data.
     *
     * @param FtpForm   $ftpForm
     * @param FtpRecord $ftpRecord
     *
     * @return mixed
     */
    public function postAddAction(FtpForm $ftpForm, FtpRecord $ftpRecord, FtpService $ftpService)
    {
        /**
         * Fill record with posted data.
         */
        $ftpForm->populateToRecord($ftpRecord);

        /**
         * User ID may be set if admin added it.
         */
        $ftpRecord->setUserIdByAuthIfNotSet();

        /**
         * Simply save record.
         */
        $ftpRecord->save();

        /**
         * Save record to vsftpd.
         */
        $ftpService->saveAccount([
                                     'comment'     => $ftpRecord->id,
                                     'user'        => $ftpRecord->getFullUsername(),
                                     'password'    => $this->request()->password,
                                     'status'      => 1,
                                     'uid'         => 2001,
                                     'gid'         => 2001,
                                     'dir'         => $ftpRecord->getFullPath(),
                                     'ulbandwidth' => 100,
                                     'dlbandwidth' => 100,
                                     'ipaccess'    => '*',
                                     'quotasize'   => 50,
                                     'quotafiles'  => 50,
                                 ]);

        /**
         * If ftp was added via ajax, we display some data and redirect url.
         * Otherwise we redirect user to edit form.
         */
        return $this->response()->respondWithSuccessRedirect($ftpRecord->getEditUrl());
    }

    /**
     * Show edit form.
     * Form is automatically filled with session data.
     *
     * @param FtpForm   $ftpForm
     * @param FtpRecord $ftpRecord
     *
     * @return mixed
     */
    public function getEditAction(FtpForm $ftpForm, FtpRecord $ftpRecord)
    {
        /**
         * Fill form with record data.
         */
        $ftpForm->populateFromRecord($ftpRecord);

        /**
         * Then we simply display form.
         */
        return $this->formalize($ftpForm, $ftpRecord, 'Edit ftp');
    }

    /**
     * Save form data.
     * Form was automatically filled with request data.
     *
     * @param FtpForm   $ftpForm
     * @param FtpRecord $ftpRecord
     *
     * @return $this
     */
    public function postEditAction(FtpForm $ftpForm, FtpRecord $ftpRecord, FtpService $ftpService)
    {
        /**
         * Fill record with posted data.
         */
        $ftpForm->populateToRecordAndSave($ftpRecord);

        /**
         * Save record to vsftpd.
         */
        $ftpService->saveAccount([
                                     'comment'     => $ftpRecord->id,
                                     'user'        => $ftpRecord->getFullUsername(),
                                     'password'    => $this->request()->password,
                                     'status'      => 1,
                                     'uid'         => 2001,
                                     'gid'         => 2001,
                                     'dir'         => $ftpRecord->getFullPath(),
                                     'ulbandwidth' => 100,
                                     'dlbandwidth' => 100,
                                     'ipaccess'    => '*',
                                     'quotasize'   => 50,
                                     'quotafiles'  => 50,
                                 ]);

        /**
         * If ftp was added via ajax, we display some data.
         * Otherwise we redirect user to current page.
         */
        return $this->response()->respondWithSuccessRedirect($ftpRecord->getEditUrl());
    }

    /**
     * Delete record.
     *
     * @param FtpRecord $ftpRecord
     *
     * @return $this
     */
    public function getDeleteAction(FtpRecord $ftpRecord)
    {
        /**
         * Delete record with one-liner.
         */
        $ftpRecord->delete();

        /**
         * Respond with useful data.
         */
        return $this->response()->respondWithSuccessRedirect();
    }

}
