<?php namespace Gnp\Orders\Controller;

use Gnp\Orders\Entity\Orders;
use Gnp\Orders\Entity\Orders as OrdersEntity;
use Gnp\Orders\Record\Order;
use Pckg\Collection;
use Pckg\Dynamic\Entity\Tables;
use Pckg\Dynamic\Service\Dynamic;
use Pckg\Framework\Controller;
use Pckg\Maestro\Helper\Maestro;

class Vouchers extends Controller
{

    use Maestro;

    protected $dynamicService;

    public function __construct(Dynamic $dynamicService) {
        $this->dynamicService = $dynamicService;
    }

    public function getIndexAction(OrdersEntity $orders) {
        /**
         * Set table.
         */
        $table = (new Tables())->where('framework_entity', get_class($orders))->oneOrFail();
        $this->dynamicService->setTable($table);

        /**
         * Apply entity extension.
         */
        $this->dynamicService->applyOnEntity($orders);

        /**
         * When limited and grouped, we need to order results by grouped field.
         */
        $orders->orderBy('offer_id DESC');

        /**
         * Get all records
         */
        $orders->forVouchers();

        $this->dynamicService->getFilterService()->filterByGet($orders);

        $all = $orders->all();

        $groups = [];
        $groupedBy = $all->groupBy(
            function(Order $order) use (&$groups) {
                $groups[0][$order->offer_id] = $order->offer->title;

                return $order->offer_id;
            }
        );

        $tabelize = $this->tabelize($orders, ['id'], 'Vouchers')
                         ->setRecords($groupedBy)
                         ->setPerPage(50)
                         ->setPage(1)
                         ->setTotal($all->total())
                         ->setGroups($groups)
                         ->setEntityActions(
                             [
                                 'generateVoucher',
                                 'sendVoucher',
                                 'filter',
                                 'view',
                             ]
                         )
                         ->setRecordActions(
                             [
                                 'generateVoucher',
                                 'sendVoucher',
                                 'previewVoucher',
                                 'downloadVoucher',
                                 'applyVoucher',
                                 'reValidVoucher',
                             ]
                         )
                         ->setFields(
                             [
                                 'id',
                                 'num',
                                 'offer'     => function($order) {
                                     return $order->offer ? $order->offer->title : ' -- no offer -- ';
                                 },
                                 'payee'     => function($order) {
                                     $user = $order->user;

                                     if (!$user) {
                                         return ' -- no user -- ';
                                     }

                                     return $user->surname . ' ' . $user->name . "<br />" .
                                            $user->email . '<br />' .
                                            $user->phone;
                                 },
                                 'packets'   => function(Order $order) {
                                     return $order->getPacketsSummary();
                                 },
                                 'additions' => function(Order $order) {
                                     return $order->getAdditionsSummary();
                                 },
                                 'app'       => function(Order $order) {
                                     return $order->appartment;
                                 },
                                 'checkin',
                                 'people',
                                 'voucherId' => function(Order $order) {
                                     return $order->getVoucherId();
                                 },
                                 'voucher_url',
                                 'voucher_sent_at',
                                 'taken_at',
                                 'take_comment',
                             ]
                         )->setViews(['Gnp\Orders:vouchers']);

        if ($this->request()->isAjax()) {
            return [
                'records' => $tabelize->transformRecords(),
            ];
        }

        return $tabelize;
    }

    public function getCheckinAction(OrdersEntity $orders) {
        /**
         * Set table.
         */
        $table = (new Tables())->where('framework_entity', get_class($orders))->oneOrFail();
        $this->dynamicService->setTable($table);

        /**
         * Apply entity extension.
         */
        $this->dynamicService->applyOnEntity($orders);

        /**
         * When limited and grouped, we need to order results by grouped field.
         */
        $orders->orderBy('offer_id DESC');
        $orders->groupBy('id');

        /**
         * Get all records
         */
        $orders->forVouchers();

        $this->dynamicService->getFilterService()->filterByGet($orders);

        $all = $orders->all();

        $groups = [];

        $tabelize = $this->tabelize($orders, ['id'], 'Vouchers')
                         ->setRecords($all)
                         ->setPerPage(50)
                         ->setPage(1)
                         ->setTotal($all->total())
                         ->setGroups($groups)
                         ->setEntityActions(
                             []
                         )
                         ->setRecordActions(
                             [
                                 'applyVoucher',
                                 'reValidVoucher',
                             ]
                         )
                         ->setFields(
                             [
                                 'id',
                                 'num',
                                 'offer'      => function($order) {
                                     return $order->offer ? $order->offer->title : ' -- no offer -- ';
                                 },
                                 'payee'      => function($order) {
                                     $user = $order->user;

                                     if (!$user) {
                                         return ' -- no user -- ';
                                     }

                                     return $user->surname . ' ' . $user->name . "<br />" .
                                            $user->email . '<br />' .
                                            $user->phone;
                                 },
                                 'packets'    => function(Order $order) {
                                     return $order->getPacketsSummary();
                                 },
                                 'additions'  => function(Order $order) {
                                     return $order->getAdditionsSummary();
                                 },
                                 'attributes' => function(Order $order) {
                                     return $order->checkin . '<br />' . $order->people . '<br />' . $order->appartment;
                                 },
                                 'voucherId'  => function(Order $order) {
                                     return $order->getVoucherId();
                                 },
                                 'taken_at',
                                 'take_comment',
                             ]
                         )->setViews(['Gnp\Orders:vouchers']);

        if ($this->request()->isAjax()) {
            return [
                'records' => $tabelize->transformRecords(),
            ];
        }

        return $tabelize;
    }

    public function getPreviewAction(Order $order) {
        return view(
            'voucher/voucher',
            [
                'order' => $order,
            ]
        );
    }

    public function postGenerateAction($orders) {
        $orders = (new Orders())->where('id', explode(',', $orders))->all();
        $orders->each(
            function(Order $order) {
                $order->queueGenerateVoucher();
            }
        );

        return $this->response()->respondWithAjaxSuccessAndRedirectBack();
    }

    public function postSendAction($orders) {
        $orders = (new Orders())->where('id', explode(',', $orders))->all();
        $orders->each(
            function(Order $order) {
                $order->queueSendVoucher();
            }
        );

        return $this->response()->respondWithAjaxSuccessAndRedirectBack();
    }

    public function getDownloadAction(Order $order) {
        header("Content-type:application/pdf");
        header("Content-Disposition:attachment;filename='Voucher #" . $order->id . ".pdf'");
        readfile($order->getAbsoluteVoucherUrl());
        die();
    }

    public function postApplyAction(Order $order) {
        $order->takeVoucher();

        return $this->response()->respondWithAjaxSuccessAndRedirectBack();
    }

}